<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class PayWithdraw extends Model
{

    public $name = 'common_pay_withdraw';
    use TraitModel;

    public $status = [
        0 => '申请中', 1 => '已付款', 2 => '拒绝付款',
    ];

    public function setStatus($post)
    {
        $id = $post['id'];
        if ($id < 1) return false;
        if ($post['status'] == 1) return $this->update(['id' => $id, 'admin_uid' => session('admin_user.id'), 'status' => 1, 'success_time' => date('Y-m-d H:i:s'), 'msg' => $post['msg']]);
        if ($post['status'] == 2) return $this->update(['id' => $id, 'admin_uid' => session('admin_user.id'), 'status' => 2, 'msg' => $post['msg']]);
        return false;
    }

    public static function page_list($where, $limit, $page)
    {
        $map = self::whereMap();
        return self::alias('a')
            ->where($where)
            ->where($map)
            ->join('common_user b', 'a.user_id = b.id', 'left')
            ->join('common_admin c', 'a.admin_uid = c.id', 'left')
            ->field('a.*,b.user_name,c.user_name admin_name')
            ->order('id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page], false)->each(function ($item, $key) {
                $status = '';
                switch ($item['status']) {
                    case 0:
                        $status = '申请中';
                        break;
                    case 1:
                        $status = '提现成功';
                        break;
                    case 2:
                        $status = '拒绝原因:'.$item->msg;
                        break;
                }
                $item->text = $status;
                // 图片执行
                $item->img_url = config('ToConfig.app_update.image_url'). '/' . $item->img_url;
            });
    }
}