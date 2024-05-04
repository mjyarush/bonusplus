<?php

class shopBonusplusUsers
{
 protected $table_customer = 'shop_customer';
 protected $table_contact = 'wa_contact_data';
 protected $table_order = 'shop_order';

    public function getUsersBalance(){
    //Получаем массив бонусов пользователей(contact_id=>affiliate_bonus)

        $model = new waModel();

        return $model->query("SELECT contact_id, affiliate_bonus FROM `{$this->table_customer}`")->fetchAll('contact_id', true);
    }

    public function getUsersPhone(){

//      Получаем массив телефонов пользователей(contact_id=>phone)

        $model = new waModel();

        return $model->query("SELECT contact_id,value FROM `{$this->table_contact}` WHERE field='phone'")->fetchAll('contact_id', true);
    }

    public function insertBonus($users, $usersPhone, $api)
    {

        if(!empty($users)){
            $comment = 'Сервис Бонус плюс';
            $bonus = new shopAffiliateTransactionModel();
            $i = 0;



            foreach ($api as $key=>$value){

                //ищем номер телефона как в бонус плюс (7...)
                $contact_id = array_search($key,$usersPhone);

                if(is_bool($contact_id)){
                    //проверяем номер телефона с 8 в начале
                   $key = mb_substr($key, 1);
                   $key = "8".$key;
                   $contact_id = array_search($key,$usersPhone);
                }

                if(!is_bool($contact_id)) {

                    $curBonus = $users["$contact_id"];
                    $value = intval($value['bonus']);


                    if ($curBonus > $value) {
                        $amount = -1 * ($curBonus - $value);
                        $bonus->applyBonus($contact_id, $amount, $order_id = null, $comment, $type = null);
                        $i++;
                    } elseif ($curBonus < $value) {
                        $amount = $value - $curBonus;
                        $bonus->applyBonus($contact_id, $amount, $order_id = null, $comment, $type = null);
                        $i++;
                    }else{
                        continue;
                    }
                }
            }

            return $i;
        }else{
            return 'В магазине нет пользователей';
        }
    }

    public function getUserData($orderID)
    {

        $model = new waModel();

        $order = $model->query("SELECT contact_id,total FROM `{$this->table_order}` WHERE id=?", $orderID)->fetchAssoc();
        $data['total'] = $order['total'];
        $data['contact_id'] = $order['contact_id'];
        $data['phone'] = self::getUserPhone($order['contact_id']);
        $data['amount'] = self::getUserBalance($order['contact_id'], $orderID);

        return $data;
    }

    public function updateUser($phone, $amount, $comment=null)
    {

        $bonus = new shopAffiliateTransactionModel();
        $userId = $this->getUserID($phone);
        if(empty($comment)){
            if($amount>0) {
                $comment = 'Начисление с БонусПЛЮС';
            }else{
                $comment = 'Списание с БонусПЛЮС';
            }
        }

        if(!is_bool($userId)){
            $bonus->applyBonus($userId, $amount, $order_id = null, $comment, $type = null);
            return true;
        }else{
            $phone = mb_substr($phone, 1);
            $phone = "8".$phone;
            $userId = $this->getUserID($phone);

            if(!is_bool($userId)){
                $bonus->applyBonus($userId, $amount, $order_id = null, $comment, $type = null);
                return true;
            }else{
                return false;
            }
        }

    }

    public function updateLoyal($user,$api,$loyalCat,$card){
         if(!empty($user)&&!empty($api)){
             $count = 0;
             foreach ($api as $phone => $data){
                 $userID= $this->getUserID($phone);
                 if(is_bool($userID)){
                     $phone = mb_substr($phone, 1);
                     $phone = "8".$phone;
                     $userID= $this->getUserID($phone);
                 }
                 if(!empty($userID)){
                     $curCatShop = $this->curCardID($card,$userID);
                     if($loyalCat["cat_$curCatShop"] != $data['loyal']){
                         //del from cur cat
                         $this->deleteFromCat($curCatShop,$userID);

                         //search new catID
                         $newCatID = array_search($data['loyal'], $loyalCat);
                         $newCatID = mb_substr($newCatID, 4);
                         //add to new cat
                         $this->moveToCat($newCatID, $userID);
                         $count++;
                     }
                 }
             }
             return $count;
         }else{
             return 0;
         }
    }
    public function getUserID($phone){

        $model = new waModel();

        $user = $model->query("SELECT contact_id FROM `{$this->table_contact}` WHERE field='phone' and value = ? ", $phone)->fetchAssoc();

        if(!empty($user)) {
            return $user['contact_id'];
        }else{
            return false;
        }
    }
    public function getUserPhone($contact_id){

        $model = new waModel();
        $phone = $model->query("SELECT value FROM `{$this->table_contact}` WHERE field='phone' and contact_id = ? ", $contact_id)->fetchAssoc();

        return $phone['value'];
    }
    /*
     * Get bonus balance by Order_id
     * */
    protected function getUserBalance($contact_id, $order_id){

        $model = new shopAffiliateTransactionModel();
        $balance = $model-> getLast($contact_id, $order_id);

        return $balance['amount'];

    }
    /*
     * Get bonus balance by contact_id
     * */
    public function getUserBonusBalance($contact_id)
    {
        $model = new waModel();
        $balance = $model->query("SELECT affiliate_bonus FROM `{$this->table_customer}` WHERE contact_id=?", $contact_id)->fetchAssoc();
        $bonus = intval(strval($balance['affiliate_bonus']));
        return $bonus;
    }

    public function addToCat($phone, $apiData, $usersCat){

        $userId = $this->getUserID($phone);
        if(is_bool($userId)){
            $phone = "8".mb_substr($phone,1);
            $userId = $this->getUserID($phone);
        }

        $discId = $apiData['discountCardTypeId'];

        //search where user id in settings
        foreach ($usersCat as $key => $val){
            if(preg_match("/cat_*/", $key)){
                if (preg_match("/$discId\b/",$val)) {
                    $userCat = str_replace('cat_', '',$key);
                }
            }
        }

        //add user to shop category like in settings
        if(!empty($userCat) && !is_bool($userCat)){

            $m = new shopBonusplusWaContactCategoriesModel();
            $data = array(
                'category_id' => $userCat,
                'contact_id' => $userId,
            );
            $m->insert($data, 1);

            //create customer from contact, to apply bonus
            $m = new shopCustomerModel();
            $m->createFromContact($userId);

            $amount = $usersCat["hello_$userCat"];

            if(!empty($amount)&&$amount!=0){
              $data['regBonus']=$this->updateUser($phone,$amount, 'Приветственный бонус для пользователей БонусПЛЮС');
              if($data['regBonus']){
                  $data['amount'] = $amount;
              }
            }
            $data['phone'] = $phone;
            return $data;
        }


    }

    public function deleteFromCat($cat_id,$user_id)
    {
        $m = new shopBonusplusWaContactCategoriesModel();

        $m->deleteByField(array(
            'category_id'=> $cat_id,
            'contact_id' => $user_id,
        ));
    }
    public function moveToCat($cardId, $userID)
    {
        $m = new shopBonusplusWaContactCategoriesModel();
        $data = array(
            'category_id' => $cardId,
            'contact_id' => $userID,
        );
        $m->insert($data, 1);
    }

    public function getCardName($contact_id,$cards){
        $m = new shopBonusplusWaContactCategoriesModel();
        $cat = $m->getUserCategory($contact_id);

        foreach ($cards as $key=>$value){
            unset($cards[$key]);
            $key = mb_substr($key, 5);
            $cards[$key] = $value;
        }

        if($cat['count'] == 1){
            $i = $cat[0]['category_id'];
            return $cards[$i];
        }elseif($cat['count'] > 1){
            foreach ($cat as $row){
                $cardName = "card_".$row['category_id'];
                if(array_key_exists($cardName,$cards)){
                    return $cards[$cardName];
                }
            }
        }else{
            return 0;
        }
    }

    /*
     * Get user total spent
     * */
    public function getUserTotal($contact_id)
    {
        $model = new waModel();
        $res = $model->query("SELECT total_spent FROM `{$this->table_customer}` WHERE contact_id=?", $contact_id)->fetchAssoc();
        $total= intval(strval($res['total_spent']));
        return $total;
    }
    /*
     * Get actual card ID by total spent
     * */
    public function actualCardID($userTotal, $rule)
    {

        asort($rule);

            foreach($rule as $key=>$val){
                if(preg_match("/^(max)/",$key)){
                    if($val!="max"){
                        if ($val > $userTotal) {
                            $actualCat = mb_substr($key, 4);
                            break;
                        }
                    }else{
                        $actualCat = mb_substr($key, 4);
                    }
                }

            }

            waLog::dump("Актуальная категория лояльности: $actualCat",'bonusplus/my_debug.log');
            return $actualCat;

    }
    /*
     * Get cur card ID in shop
     * */
    public function curCardID($userCard,$userId){

        $m = new shopBonusplusWaContactCategoriesModel();
        $userCat = $m->getUserCategory($userId);

        foreach ($userCat as $key => $val){
            $search = "card_".$val['category_id'];
            If(array_key_exists($search,$userCard)){
                $curCard = $val['category_id'];
                break;
            }
        }

        if(!empty($curCard)){
            return $curCard;
        }else{
            return false;
        }
    }
}