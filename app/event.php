<?php
// 事件定义文件
return [
    'bind'      => [
    ],

    'listen'    => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [],
        'LogLevel' => [],
        'LogWrite' => [],
        'RepeatPurchase'=>[\app\listener\RepeatPurchase::class,],//监听重复购买操作
    ],

    'subscribe' => [
    ],
];
