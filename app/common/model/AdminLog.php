<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class AdminLog extends Model
{
    use TraitModel;
    public $name = 'common_admin_log';
}