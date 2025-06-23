<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'admin:today_count' => \app\command\TodayCount::class,
        'admin:shou_yi' => \app\command\ShouYi::class,
    ],
];
