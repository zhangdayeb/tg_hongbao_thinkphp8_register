<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class MenuModel extends Model
{
    use TraitModel;
    public $menu=[
        1=>'控制面板',
        2=>'权限管理',
        3=>'用户管理',
        4=>'财务管理',
        5=>'市场管理',
        6=>'代理商管理',
        7=>'公告管理',
        8=>'内容管理',
        9=>'日志管理',
        10=>'系统配置',
    ];
    public $name = 'common_admin_menu';
}