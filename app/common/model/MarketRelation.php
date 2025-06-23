<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class MarketRelation extends Model
{
    use TraitModel;
    public $name = 'common_market_relation';

    public static function page_list($map,$limit, $page)
    {
        return self::alias('a')
            ->where($map)
            ->join('common_admin b', 'a.aid = b.id','left')
            ->join('common_admin c', 'a.pid = c.id','left')
            ->field('a.*,b.user_name,c.user_name p_name')
            ->order('id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page], false);
    }
}