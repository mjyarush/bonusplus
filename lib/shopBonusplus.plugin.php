<?php

class shopBonusplusPlugin extends shopPlugin
{
    public function updateBonus($data){

        $status = $this->getSettings('syncStatus');
        $token = $this->getSettings('token');
        $rule = $this->getSettings('userLoyal');
        $cards = $this->getSettings('userCard');


        if($status == 1 ){

            $user = new shopBonusplusUsers;
            $userData = $user->getUserData($data['order_id']);

            $api = new shopBonusplusApi();
            $resApi = $api->updateUserBalance($token, $userData);

            if(!empty($resApi['msg'])){
                $msg = "Что-то пошло не так, ответ от сервиса БонусПЛЮС: ".$resApi['msg'];
            }else{
                $msg = "БонусПЛЮС: Для пользователя: ".$resApi['phoneNumber']." начислено бонусов ".$resApi['amount'];
            }

            $userTotal = $user->getUserTotal($userData['contact_id']);
            $userTotal = $userTotal + intval($userData['total']);
            $curLoyal = $user->curCardID($cards,$userData['contact_id']);
            $actualLoyal = $user->actualCardID($userTotal,$rule);

            if($curLoyal != $actualLoyal && !is_bool($curLoyal)){

                $newCard = $cards["card_$actualLoyal"];
                //delete from cat
                $user->deleteFromCat($curLoyal,$userData['contact_id']);
                //add to cat
                $user->moveToCat($actualLoyal, $userData['contact_id']);

                $changeLoyal = $api->changeCardStatus($token,$userData['phone'],$newCard);
                $phone = $changeLoyal['phone'];
                $level = $changeLoyal['discountCardName'];
                waLog::dump(" Пользователь $phone перешел на уровень $level",'bonusplus/loyal_change.log');
            }


        }else{
            $msg = 'БонусПЛЮС: Синхронизация выключена в настройках плагина';
        }

        waLog::dump($msg, 'bonusplus/order-complete.log');
    }

    public function decreaseBonus($data)
    {
        $status = $this->getSettings('syncStatus');

        if ($status == 1) {

            $m = new shopOrderParamsModel();
            $applyBonus = $m->getOne($data['order_id'], 'affiliate_bonus');

            if (!is_null($applyBonus)){

                $m = new shopBonusplusUsers();
                $userData['phone'] = $m->getUserPhone($data['contact_id']);
                $userData['amount'] = -1 *($applyBonus);

                $token = $this->getSettings('token');
                $api = new shopBonusplusApi();

                $api = $api->updateUserBalance($token, $userData);

                if (!empty($api['msg'])) {
                    $msg = "Что-то пошло не так, ответ от сервиса БонусПЛЮС: " . $api['msg'];
                } else {
                    $msg = "БонусПЛЮС: Для пользователя: " . $api['phoneNumber'] . " списано бонусов " . $api['amount'];
                }

                waLog::dump($msg, 'bonusplus/order-create.log');
            }

        }else{
            $msg = 'БонусПЛЮС: Синхронизация выключена в настройках плагина';
            waLog::dump($msg, 'bonusplus/order-create.log');
        }
    }
    public function checkStatus($data){

        $status = $this->getSettings('syncStatus');

        if ($status == 1) {

            if ($data['action_id'] == 'refund' && $data['after_state_id'] == 'refunded') {

                //get userData
                $m = new shopBonusplusUsers();
                $userData = $m->getUserData($data['order_id']);
                $contact_id = $m->getUserID($userData['phone']);

                $m = new shopAffiliateTransactionModel();
                $cancelBonus = $m->getLast($contact_id, $data['order_id']);

                $m = new shopOrderParamsModel();
                $applyBonus = $m->getOne($data['order_id'], 'affiliate_bonus');

                $userData['amount'] = $applyBonus - $cancelBonus['amount'];

                $token = $this->getSettings('token');
                $api = new shopBonusplusApi();
                $api = $api->updateUserBalance($token, $userData);

                if (!empty($api['msg'])) {
                    $msg = "Что-то пошло не так, ответ от сервиса БонусПЛЮС: " . $api['msg'];
                } else {
                    $msg = "БонусПЛЮС: Для пользователя: " . $api['phoneNumber'] . " возвращено бонусов " . $api['amount'];
                }

                waLog::dump($msg, 'bonusplus/order-cancel.log');

            }
        }else{
            $msg = 'БонусПЛЮС: Синхронизация выключена в настройках плагина';
            waLog::dump($msg, 'bonusplus/order-cancel.log');
        }
    }

    public function regUser($contact){

        if($this->getSettings('syncStatus') && $this->getSettings('regUsers') &&($contact instanceof waContact) && $contact->getId()) {

            if(!empty($contact->get('phone','value'))){
                $api = new shopBonusplusApi();
                $token = $this->getSettings('token');

                $phone = $contact->get('phone','value');
                $data['phone'] = $phone[0] ;

                if(!empty($contact->get('email'))){
                    $email = $contact->get('email','value');
                    $data['email'] = $email[0];
                }

                if(!empty($contact->getName())){
                    $data['name'] = $contact->getName();
                }
                // create shop customer to sync bonus
                if(!empty($contact->getId())){
                    $m = new shopCustomerModel();
                    $m->createFromContact($contact->getId());
                }
                

                $api->createCustomer($data,$token, $this->getSettings('userCategory'));
            }

        }
    }

    public function saveSettings($settings = array()) {

        if(empty($settings['token'])){
            $settings['syncStatus'] = 0;
            $settings['tokenMSG'] = 'Укажите токен БонусПЛЮС';
        }else{
            $domain =  wa()->getRouting()->getDomain(null, false, true);
            $api = new shopBonusplusApi();

            // Get contact category
            $categoryId= $this->getKeys($settings,'cat_');
            $helloBonus= $this->getKeys($settings,'hello_');
            $cards = $this->getKeys($settings, 'card_');
            $min_l = $this->getKeys($settings, 'min_');
            $max_l = $this->getKeys($settings, 'max_');

            $settings['userCategory'] = json_encode(array_merge($this->clearEmpty($categoryId),$this->clearEmpty($helloBonus)));
            $settings['userLoyal'] = json_encode(array_merge($this->clearEmpty($min_l),$this->clearEmpty($max_l)));
            $settings['userCard'] = json_encode($this->clearEmpty($cards), JSON_UNESCAPED_UNICODE);

            //Delete contact fields from arr settings
            $settings = $this->clearSet($settings,$categoryId);
            $settings = $this->clearSet($settings, $helloBonus);
            $settings = $this->clearSet($settings, $cards);
            $settings = $this->clearSet($settings, $min_l);
            $settings = $this->clearSet($settings, $max_l);


            $token = base64_encode($settings['token']);

            if($settings['hookStatus'] == 1){

                if(empty($this->getSettings('authToken'))){
                    $m = new waApiTokensModel();
                    $authToken = $m->getToken('Bonusplus', 1, 'webhook');
                    $settings['authToken'] = $authToken;
                }else{
                    $authToken = $this->getSettings('authToken');
                }
                if(empty($this->getSettings('subBool')) || $this->getSettings('subBool') == 0){
                    $api = $api->subscribeWebhook($domain,$token,$authToken);
                    if(is_int($api)){
                        $settings['subBool'] = 1;
                        $settings['subId'] = $api;
                    }else{
                        $settings['subBool'] = 0;
                        $settings['hookStatus'] = 0;
                    }
                }
            }else{
                if($this->getSettings('subBool') == 1 && !empty($this->getSettings('subId'))){
                    $api = $api->cancelSubscribe($this->getSettings('subId'),$token);
                    $settings['subId'] = '';
                    $settings['subBool'] = 0;
                }
            }
        }

        parent::saveSettings($settings);
    }
    /*
     * return array with suf key
     * */

    private function getKeys($settings,$suf){
        $var = array_filter($settings, function($key) use ($suf) {return strpos($key, "$suf") === 0;},ARRAY_FILTER_USE_KEY);
        return  $var;
    }
    private function clearSet($settings, $del){
        foreach ($del as $key => $val){
            unset($settings["$key"]);
        }
        return $settings;
    }

    private function clearEmpty($arr){
        $res = $arr;
        foreach ($arr as $key => $val){
            if(empty($val)){
                unset($res["$key"]);
            }
        }
        return $res;
    }
}