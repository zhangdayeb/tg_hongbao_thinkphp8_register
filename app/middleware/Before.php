<?php
declare (strict_types=1);

namespace app\middleware;


use app\common\model\AdminLog;
use app\common\traites\ApiResponseTrait;

class Before
{
    use ApiResponseTrait;

    /**
     * config('ToConfig.action_log')
     * 某方法执行成功之后写入操作日志
     * @param $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        $response = $next($request);
        //写操作日志
        $path_info = $request->pathinfo();
        $action = $request->action();
        $config = config('ToConfig.action_log');

        if (empty($action) && !isset($action) || !in_array($action, $config)) return $next($request);

        //操作日志插入
        $save['system'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $save['browser'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $save['ip'] = $_SERVER['REMOTE_ADDR'];
        $save['admin_uid'] = session('admin_user.id');
        $save['create_time'] = date('Y-m-d H:i:s');
        $save['action'] = $path_info;
        (new AdminLog())->save($save);
        return $response;
    }
}
