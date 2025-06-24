<?php
declare(strict_types=1);

use think\facade\Route;

/**
 * 应用路由配置
 * 自动登录相关路由规则
 */

// 自动登录主入口 - GET /login?user_id=1
Route::rule('login', 'LoginController@index', 'GET');
Route::rule('testlogin', 'LoginController@testLogin', 'GET'); 
Route::rule('getcode', 'LoginController@getCode', 'GET');