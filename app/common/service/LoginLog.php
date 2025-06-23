<?php
namespace app\common\service;


class LoginLog
{
    //登陆日志 ---> 各种日志 不要乱动就行了
    public function login($type=1)
    {
        $log['unique']=session('admin_user.id') ?  :session('home_user.id');
        $log['login_type']=$type;
        $log['login_time']=date('Y-m-d H:i:s');
        $log['login_ip']=$_SERVER['REMOTE_ADDR'];
        $log['login_address']=getIpAddressByIp($log['login_ip']);
        $log['login_equipment']= isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if(strlen($log['login_equipment']) >200) $log['login_equipment']=mb_substr($log['login_equipment'],0,200,'utf-8');
        (new \app\common\model\LoginLog())->save($log);
    }
}