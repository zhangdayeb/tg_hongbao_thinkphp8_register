<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class SysConfig extends Model
{
    use TraitModel;
    public $name = 'common_sys_config';
}