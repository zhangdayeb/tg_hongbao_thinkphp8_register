<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class PayRecharge extends Model
{
    public $name = 'common_pay_recharge';
    use TraitModel;
    public function getSuccessTimeAttr($value)
    {
        return empty($value) ? '未确认充值' : $value;
    }

    //确认充值
    public function setStatus($post)
    {
        $id = $post['id'];
        if ($id < 1) return false;

        return $this->update(
            ['id' => $id,
                'admin_uid' => session('admin_user.id'),
                'success_time' => date('Y-m-d H:i:s')
            ]);
    }

    public static function page_list($where,$limit, $page)
    {
        $map=self::whereMap();
        return self::alias('a')
            ->where($where)
            ->where($map)
            ->join('common_user b', 'a.user_id = b.id', 'left')
            ->join('common_admin c', 'a.admin_uid = c.id', 'left')
            ->field('a.*,b.user_name,c.user_name as admin_name')
            ->order('id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page], false);
    }

    public static function getUserLastRecharge($uid)
    {
        return self::where('uid',$uid)->order('id desc')->limit(1)->find();
    }
}