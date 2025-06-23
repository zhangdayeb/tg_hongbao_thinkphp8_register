<?php
declare (strict_types=1);

namespace app\middleware;


use app\common\model\AdminPower;
use app\common\model\AdminRole;
use app\common\model\RolePower;
use app\common\traites\ApiResponseTrait;

class Role
{
    use ApiResponseTrait;

    public function handle($request, \Closure $next)
    {
        $admin = session('admin_user');
        //权限验证
        //查询当前用户角色是否为禁用
        $role = AdminRole::where('id', $admin['role'])->find();
        if (empty($role)) return $this->failed('该角色没分配权限');
        $role = $role->toArray();
        if ($role['status'] != 1) return $this->failed('该角色被禁用');

        //超级管理员 ---> 唯一超级管理员 id == 2 的 wodage
        if ($admin['id'] == config('ToConfig.admin_vip.admin_vip_id')) return $next($request);

        //获取可访问控制器
        $power = RolePower::where('role_id', $admin['role'])->find();
        if (empty($power)) return $this->failed('没有可访问的控制器');
        $power= $power->toArray();
        //查询控制器
        $func = AdminPower::whereIn('id', $power['auth_ids'])->select()->toArray();

        $path = array_column($func, 'path');
       $path_info = $_SERVER['PATH_INFO'];
       //线下 PATH_INFO  线上 REQUEST_URI;
        if (config('ToConfig.app_system.app_system')) $path_info=$_SERVER['REQUEST_URI'];

        // if (!in_array(substr($path_info,6), $path)) return $this->failed($path_info.'--'.'权限不够');
        return $next($request);

    }
}
