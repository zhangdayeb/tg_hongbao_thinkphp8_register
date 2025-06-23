<?php


namespace app\common\model;

use app\common\model\AdminModel;
use think\Model;

class LoginLog extends Model
{

    public $name = 'common_login_log';

    public function user()
    {
        return $this->hasOne(AdminModel::class, 'id', 'unique');
    }

    public function getLoginTypeAttr($value)
    {
        $type = [1 => '后台管理员', 2 => '用户', 3 => '代理'];
        return isset($type[$value]) ? $type[$value] : $value;
    }
}