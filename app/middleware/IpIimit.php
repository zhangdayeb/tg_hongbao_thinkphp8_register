<?php
declare (strict_types=1);

namespace app\middleware;


use app\common\model\IpConfig;
use app\common\traites\ApiResponseTrait;

class IpIimit
{
    use ApiResponseTrait;

    //验证IP是否可登陆
    public function handle($request, \Closure $next)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (empty($ip))  return $this->failed('ip不存在');
        //查询IP是否存在
        $res = (new IpConfig())->where(['ip'=>$ip,'status'=>1])->find();
        if (empty($res))  return $this->failed('ip限制登录');
        return $next($request);

    }
}
