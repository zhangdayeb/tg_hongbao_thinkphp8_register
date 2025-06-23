<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class MoneyLog extends Model
{
    public $name = 'common_pay_money_log';
    use TraitModel;

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'uid');
    }

    public function getTypeAttr($value)
    {
        $type = [1 => '收入', 2 => '支出', 3 => '后台修改金额', 4 => '提现退款'];
        return isset($type[$value]) ? $type[$value] : $value;
    }

    public function getStatusAttr($value)
    {
        $type = [101 => '充值', 201 => '提现', 301 => '积分操作', 401 => '套餐分销奖励', 403 => '充值分销奖励',];
        return isset($type[$value]) ? $type[$value] : $value;
    }

    public static function getIncomeType($typeInt)
    {
        $incomeType = [1 => '打赏收入', 2 => '返佣收入'];
        return $incomeType[$typeInt];
    }

    public static function getOrderType($typeInt)
    {
        // 订单类型：1=包天，2=包周，3=包月，4=包季度，5=包年，6=终身会员，7=1 小时会员价
        $orderType = [1 => '包天', 2 => '包周', 3 => '包月', 4 => '包季度', 5 => '包年', 6 => '终身会员', 7 => '1小时会员价'];
        return $orderType[$typeInt]??'单品';
    }

    public static function getAgentName($agentUid)
    {
        return AdminModel::where('id', $agentUid)->value('user_name');
    }


    public static function page_list($where, $limit, $page, $order)
    {
        $map = self::whereMap();
        return self::alias('a')
            ->where($where)
            ->where($map)
            ->join('common_user b', 'a.uid = b.id','left')
            ->join('common_admin c', 'a.market_uid = c.id','left')
            ->field('a.*,b.user_name,c.user_name admin_name')
            ->order($order)
            ->paginate(['list_rows' => $limit, 'page' => $page], false);
    }

    public static function agent_page_list($where, $fields, $limit, $page, $order)
    {
        $map = self::whereMap();
        return self::alias('a')
            ->where($where)
            ->where($map)
            // ->join('common_admin c', 'a.agent_uid = c.id','left')
            ->join('video b', 'a.video = b.id','left')
            ->field($fields)
            ->order($order)
            ->paginate(['list_rows' => $limit, 'page' => $page], false)->each(function ($item) {
                $item->income_type_str = '';
                $item->order_type_str = '';
                $item->agent_name = '';
                if ($item->income_type) {
                    $item->income_type_str= self::getIncomeType($item->income_type);
                }
                if ($item->order_type) {
                    $item->order_type_str = self::getOrderType($item->order_type);
                }
                if ($item->agent_uid) {
                    $item->agent_name = self::getAgentName($item->agent_uid);
                }
            });
    }
}