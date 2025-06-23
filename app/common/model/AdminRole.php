<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class AdminRole extends Model
{
    use TraitModel;

    public $name = 'common_admin_role';

    public static function page_list($limit, $page)
    {
        return self::alias('a')
            ->join('common_admin_role_menu b', 'b.role_id = a.id', 'left')
            ->join('common_admin_role_power c', 'c.role_id = a.id', 'left')
            ->field('a.*,b.role_id mid,b.auth_ids bids,c.role_id cid,c.auth_ids cids')
            ->order('id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page], false)->each(function ($item, $key) {
                $item->action = [];
                //转为int类型
                if (isset($item->cids)) {
                    $arr = explode(',', $item->cids);
                    foreach ($arr as $key => &$value) {
                        $value = intval($value);
                    }
                    $item->action = $arr;
                }
                $item->menus = [];
                if (isset($item->bids)) {
                    $arr = explode(',', $item->bids);
                    foreach ($arr as $key => &$value) {
                        $value = intval($value);
                    }
                    $item->menus = $arr;
                }
            });
    }
}