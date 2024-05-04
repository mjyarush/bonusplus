<?php


class shopBonusplusPluginFrontendUserController extends waJsonController

{

    public function execute()
    {
        
        $plugin = wa()->getPlugin('bonusplus');
        $bonusplus = $plugin->getSettings();

        $post  = json_decode(file_get_contents("php://input"), true);

        $user = new shopBonusplusUsers;

        if(!empty($post)){
            if($post['transactionType'] != 'Debit_Manual' && $post['transactionType'] != 'Credit_Manual'){
                $user = $user->updateUser($post['phoneNumber'],$post['amount']);
                
            }
        }else{
            $msg = 'Ошибка БонусПЛЮС webhook';
            waLog::dump($msg, 'bonusplus/webhook.log');
            return false;
        }

        if($user){
            $msg = 'Бонусный баланс пользователя:'.$post['phoneNumber'].'изменен на'.$post['amount'].'баллов';
        }else{
            $msg = 'Пользователь:'.$post['phoneNumber'].'не найден в базе';
        }

        waLog::dump($msg, 'bonusplus/webhook.log');
    }
}