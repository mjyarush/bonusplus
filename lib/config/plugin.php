<?php

return array(
    'name' => 'БонусПЛЮС',
    'description' => 'Плагин позволяет синхронизировать бонусы с сервисом bonusplus.pro ',
    'img' => 'img/sync.png',
    'version' => '2023.10.03',
    'vendor' => '667213',
    'custom_settings' => true,
    'frontend' => true,
    'handlers' =>
        [
            'order_action.complete' => 'updateBonus',
            'order_action.create' => 'decreaseBonus',
            'order_action.*' => 'checkStatus',
            'signup' => 'regUser',
        ],
);
