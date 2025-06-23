<?php
// 全局中间件定义文件
return [
    // 全局请求缓存
    //\app\middleware\Before::class,
    think\middleware\AllowCrossDomain::class,
    //\app\middleware\IpIimit::class,
    // \app\middleware\Cors::class,
    think\middleware\SessionInit::class,
];
