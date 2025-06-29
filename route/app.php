<?php
use think\facade\Route;

/**
 * 应用路由配置
 * 自动登录相关路由规则
 */
Route::rule('/', '/LoginController/index', 'GET');
Route::rule('login', '/LoginController/index', 'GET');
Route::rule('testlogin', '/LoginController/testLogin', 'GET'); 
Route::rule('getcode', '/LoginController/getCode', 'GET');
