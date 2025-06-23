<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class MarketLevel extends Model
{
    use TraitModel;
    public $name = 'common_market_level';
}