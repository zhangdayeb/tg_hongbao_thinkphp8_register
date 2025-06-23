<?php
declare (strict_types=1);

namespace app\middleware;


use app\common\model\AdminModel;
use app\common\model\AdminToken;
use app\common\model\User;
use app\common\traites\ApiResponseTrait;

class Auth
{
    use ApiResponseTrait;

    public function handle($request, \Closure $next)
    {
        //校验token
        $token = $request->post('token');
        //$token = $request->header('x-csrf-token');
        if (empty($token)) return $this->failed('token不存在');

        //$type 1后台用户 2代理商后台
        $map['type'] = $request->post('admin_type/d',1);
        $map['token']=$token;

        //查询token $type 1后台用户
        $res = (new AdminToken())->where($map)->find();
        if (empty($res)) return $this->failed('无效token');

        //校验是否过期的token
        $expiration_time = time() - strtotime($res['create_time']);
        // if ($expiration_time >= env('token.token_time', 10)) return $this->failed('token过期');
        // config('ToConfig.admin_agent.admin_agent') 代理商 类型
        switch ($map['type']) {
            case 1:
                $this->admin_user($token, $res);
                break;
            case config('ToConfig.admin_agent.admin_agent'):
                $this->agent_user($token, $res);
                break;
            default:
                return $this->failed('登陆类型不存在');
        }

        return $next($request);

    }

    //后台管理员
    public function admin_user($token, $res)
    {
        //校验成功处理逻辑
        //查询用户数据并存入session
        $res = (new AdminModel())->find($res['admin_uid']);
        if (empty($res)) return $this->failed('无效token');
        //session 写入日志
        //if (empty(session())) (new \app\common\service\LoginLog())->login();
        $res['token'] = $token;
        session('admin_user', $res);
        // 添加中间件执行代码
    }

    //代理商管理员
    public function agent_user($token, $res)
    {
        //校验成功处理逻辑
        //查询用户数据并存入session
        $res = (new User())->find($res['admin_uid']);
        if (empty($res)) return $this->failed('无效token');
        if ($res->status != 1) return $this->failed('该服务商被禁用');
        if ($res->type != 1)  return $this->failed('该用户不是代理');
        //session 写入日志
        //if (empty(session())) (new \app\common\service\LoginLog())->login();
        $res['token'] = $token;
        $res['role'] = config('ToConfig.admin_agent.admin_agent_id');
        $res['agent'] = 1;//服务商标示
        session('admin_user', $res);
        // 添加中间件执行代码
    }

}
