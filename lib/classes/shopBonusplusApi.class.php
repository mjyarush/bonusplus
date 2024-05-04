<?php

class shopBonusplusApi
{
    protected function sendRequest($url, $token, $params = null, $method = 'GET'){

        if (!empty ($url)){
            $handler = curl_init ($url);

            $options = [
                CURLOPT_RETURNTRANSFER          => true,
                CURLOPT_FOLLOWLOCATION          => true,
                CURLOPT_CUSTOMREQUEST           => $method,
                CURLOPT_CONNECTTIMEOUT          => 0,
                CURLOPT_SSL_VERIFYHOST          => false,
                CURLOPT_SSL_VERIFYPEER          => false,
                CURLOPT_TIMEOUT                 => 0,
                CURLOPT_ENCODING                => true,
                CURLOPT_HTTPHEADER              => ["Authorization: ApiKey $token",'Content-Type: application/json']
            ];

            if ($method == 'POST' || $method == 'PATCH'){
                $options[CURLOPT_POST] = true;
//                    $options[CURLOPT_VERBOSE] = 1;
                $options[CURLOPT_POSTFIELDS] = json_encode($params);
            }

            curl_setopt_array($handler, $options);
           
            $result = curl_exec ($handler);
            curl_close ($handler);

            return json_decode($result, true);
        }
        return false;
    }

    public function getBonusPlusUsers($token){


        $codToken = base64_encode($token);

        $apiUsers = array();

        $usersQuant = self::sendRequest('https://bonusplus.pro/api/customer/stat',$codToken);


        $params['bonusAmount'] = [ 'from' => 1];

        if($usersQuant['allCount'] > 1000){

            $params['rowCount'] = '1000';
            $q = ceil($usersQuant['allCount'] / 1000);

            for ($i = 1; $i <= $q; $i++){

                if($i<2){
                    $params['startRow'] = "$i";
                }else{
                    $row = (($i-1)*1000)+1;
                    $params['startRow'] = "$row";
                };

                $request = self::sendRequest("https://bonusplus.pro/api/customer/list",$codToken, $params, 'POST');

                foreach ($request as $arr){
                    $apiUsers[$arr['phone']] = [
                        'bonus' => $arr['availableBonuses'],
                        'loyal' => $arr['discountCardTypeId']
                    ];
                }
            }
        }else{

            $params['rowCount'] = $usersQuant['allCount'];

            $request = self::sendRequest("https://bonusplus.pro/api/customer/list",$codToken, $params, 'POST');

            foreach ($request as $user =>$params){
                $apiUsers[$params['phone']] = [
                    'bonus' => $params['availableBonuses'],
                    'loyal' => $params['discountCardTypeId']
                ];
            }
        }

//        Получаем массив телефонов => бонусов и лояльность с сервиса
        return $apiUsers;
    }

    public function updateUserBalance($token, $userData){
        $codToken = base64_encode($token);

        if(!empty($userData)){


            $phone = $userData['phone'];
            // приводим телефон к виду 79...
            $phone = mb_substr($phone, 1);
            $phone = "7".$phone;

            $params['amount'] = intval($userData['amount']);

            $request = self::sendRequest("https://bonusplus.pro/api/customer/$phone/balance",$codToken, $params, 'PATCH');

            return $request;
        }

    }

    public function subscribeWebhook($domain,$token,$authToken){
        $params =[
            'eventId'=> 'ChangeCustomerBonusBalanceEvent',
            'url' => "https://$domain/bonusplus/user/",
            'httpMethod' => 'POST',
            'contentType' => 'application/json',
            'authorization' => "Bearer $authToken",
        ];

        $msg = $this->sendRequest('https://bonusplus.pro/api/webhook/subscription/',$token,$params,"POST");

        if(!empty($msg['id'])){
            $status = 'Подписка на событие:'.$msg['eventId'].' активна';
            waLog::dump($status, 'bonusplus/webhook.log');
            return $msg['id'];
        }else{
            $msg = 'Что-то пошло не так, ответ БонусПЛЮС:'.$msg['msg'];
            waLog::dump($msg, 'bonusplus/webhook.log');
            return false;
        }


    }

    public function cancelSubscribe($eventId, $token){
        $status = $this->sendRequest("https://bonusplus.pro/api/webhook/subscription/$eventId",$token,null,"DELETE");
        if($status == null){
            $status = 'Подписка на событие не активна';
        }
        waLog::dump($status, 'bonusplus/webhook.log');
    }

    public function createCustomer($data,$token, $userCat = null){

        if(!empty($data['phone']) && !empty($token)){
            $token = base64_encode($token);
            $params['phone'] = $data['phone'];
            if(!empty($data['regBonus'])){
                $params['regBonus'] = $data['regBonus'];
            }
            if(!empty($data['email'])){
                $params['email'] = $data['email'];
            }
            if(!empty($data['name'])){
                $fio = explode(' ',$data['name']);
                $i = count($fio);
                if($i==1){
                    $params['fn'] = $fio[0];
                }elseif($i==2){
                    $params['fn'] = $fio[0];
                    $params['ln'] = $fio[1];
                }else{
                    $params['fn'] = $fio[0];
                    $params['ln'] = $fio[1];
                    $params['mn'] = $fio[2];
                }
            }
            if(!empty($data['card'])){
                $params['card'] = $data['card'];
            }

            $msg = $this->sendRequest('https://bonusplus.pro/api/customer', $token, $params,"POST");
            $user = new shopBonusplusUsers();

            if(!empty($msg['id'])){
                $status = 'Пользователь '.$msg['phone'].' создан в БонусПЛЮС';
                waLog::dump($status, 'bonusplus/signup.log');

                if(!is_null($userCat)) {
                    $data = $user->addToCat($msg['phone'], $msg, $userCat);
                }
                if(!empty($data['amount'])){
                    $res = $this->updateUserBalance(base64_decode($token),$data);
                    if(!empty($res['amount'])){
                        $status = 'Для пользователя: '.$res['phoneNumber'].' начислено приветственных бонусов  '.$res['amount'];
                        waLog::dump($status, 'bonusplus/signup.log');
                    }
                }

                return true;
            }else{
                if($msg['code'] == 'CUSTOMER_ALREADY_EXISTS'){
                    $data['phone'] = str_replace(['+','(',')','-',' '], '', $data['phone']);
                    $apiData = $this->getUserData($token, $data['phone']);
                    $data = $user->addToCat($data['phone'],$apiData, $userCat);
                    if(!empty($data['amount'])){

                        $res = $this->updateUserBalance(base64_decode($token),$data);
                            if(!empty($res['amount'])){
                                $status = 'Для пользователя: '.$res['phoneNumber'].' начислено приветственных бонусов - '.$res['amount'];
                                waLog::dump($status, 'bonusplus/signup.log');
                            }
                    }
                    return true;
                }else{
                    $status = 'Что-то пошло не так, ответ БонусПЛЮС:'.$msg['msg'];
                    waLog::dump($status, 'bonusplus/signup.log');
                    return false;
                }
            }
        }
    }

    public function getUserData($token, $phone){
        if(!empty($phone) && !empty($token)){
            return $this->sendRequest("https://bonusplus.pro/api/customer?phone=$phone", $token);
        }
    }

    public function changeCardStatus($token,$phone,$newCard){
        $token = base64_encode($token);
        $phone = "7".mb_substr($phone,1);

        if(!empty($newCard) && !empty($phone)){
            $params['newCard'] = $newCard;
            return $this->sendRequest("https://bonusplus.pro/api/customer/$phone", $token, $params,'PATCH');
        }else{
            waLog::log('Недостаточно данных для смены статуса','bonusplus/change-loyal.log');
        }

    }
}