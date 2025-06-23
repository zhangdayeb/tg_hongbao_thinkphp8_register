<?php


namespace app\common\model;


use app\common\traites\TraitModel;
use think\Model;

class AdminModel extends Model
{
    use TraitModel;
    public $name = 'common_admin';


    public static function queryMap(array $map,int $type =1 )
    {
        if ($type == 1){
            return self::where($map)->find();
        }
        return self::where($map)->select();
    }
    
    public static function page_list($map,$limit, $page)
    {
        return self::alias('a')
            ->where($map)
            ->where('a.id','>',0)
            ->join('common_admin_role b', 'a.role = b.id', 'left')
            ->join('common_market_level c', 'c.id = a.market_level', 'left')
            ->join('ntp_common_market_relation d', 'a.id = d.aid', 'left')
            ->field('a.*,b.name,c.mvalue,d.pid')
            ->order('id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page], false) ->each(function ($item, $key) {
                $item->tg_url_google='';
                !empty($item->google_code) && $item->tg_url_google = captchaUrl($item->google_code,$item->user_name);
            });
    }


    public static function agent_manage_page_list($map,$limit, $page)
    {
        $fields = 'a.id,a.user_name,a.pwd,a.pid,a.money,a.income_yesterday,a.income_today,a.role,a.channel_id,
        a.status,b.name as group_name,a.withdrawal_rate,a.profit_rate,a.kou_start,
        a.kou_rate,d.user_name as parent_name,a.withdraw_pwd,a.invitation_code,a.duan_url';
        return self::alias('a')
            ->where($map)
            ->field($fields)
            ->join('common_admin_role b', 'a.role = b.id', 'left')
//            ->join('common_pay_channel c', 'a.channel_id = c.id', 'left')
            ->join('common_admin d', 'a.pid = d.id', 'left')
            ->order('a.id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page], false)->each(function ($item, $key) {
                $channelName = Channel::where('id', 'in', $item->channel_id)->column('pay_channel');
                $item->channel_name = $channelName ? join(',', $channelName) : '';
                $item->pwd = pwdDecryption($item->pwd);
                $item->tg_url_google='';
                !empty($item->google_code) && $item->tg_url_google = captchaUrl($item->google_code,$item->user_name);
            });
    }



}