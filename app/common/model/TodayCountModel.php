<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class TodayCountModel extends Model
{
    use TraitModel;
    public $name = 'common_today_count';
}