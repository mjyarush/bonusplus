<?php

class shopBonusplusCli extends waCliController{

    public function execute()
    {

        $plugin = wa('shop')->getPlugin('bonusplus');
        $status = $plugin->getSettings('syncStatus');
        $token = $plugin->getSettings('token');
        $reg = $plugin->getSettings('regCron');
        $cards = $plugin->getSettings('userCard');
        $loyalCat = $plugin->getSettings('userCategory');
        $loyal = $plugin->getSettings('loyalSync');

        if($status == 1 ){

            $obj = new shopBonusplusUsers;
            $usersPhone = $obj -> getUsersPhone();
            $users = $obj -> getUsersBalance();

            $api = new shopBonusplusApi();
            $api = $api ->getBonusPlusUsers($token);

            $count = $obj->insertBonus($users, $usersPhone, $api);

            $msg = "БонусПЛЮС: Обновлено $count записи";

            //check loyal
            if($loyal == 1){
               $count = $obj -> updateLoyal($users, $api,$loyalCat,$cards);
               $loyalMsg = "БонусПЛЮС: Лояльность изменена для $count пользователей";
               waLog::log($loyalMsg, 'bonusplus/cli.log');
            }
            //reg all customer in BonusPlus
            if($reg == 1){
               $count = $this->regUser($api,$usersPhone,$cards,$token);
               $regMsg = "БонусПЛЮС: Зарегистрировано $count пользователей";
               waLog::log($regMsg, 'bonusplus/cli.log');
            }

        }else{
            $msg = 'БонусПЛЮС: Синхронизация выключена в настройках плагина';
        }

        waLog::log($msg, 'bonusplus/cli.log');
    }
    public function regUser($bpUsers,$shopUsers,$cards,$token){

        $user = new shopBonusplusUsers;
        $api = new shopBonusplusApi();
        $count=0;

        //Search unReg user
        foreach ($shopUsers as $contact_id => $phone){

            $phone = mb_substr($phone, 1);
            $phone = "7".$phone;

            if(!array_key_exists($phone, $bpUsers) && is_numeric($phone)){
                $contact = new waContact($contact_id);
                $data['name'] = $contact->getName();
                $data['phone'] = $phone;
                $data['regBonus'] = $user->getUserBonusBalance($contact_id);
                $data['card'] = $user->getCardName($contact_id,$cards);

                //reg on BP
                if($api->createCustomer($data,$token)){
                    $count++;
                };
            }
        }
        return $count;
    }

}