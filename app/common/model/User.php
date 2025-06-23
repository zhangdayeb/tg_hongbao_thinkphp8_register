<?php

namespace app\common\model;

use app\common\traites\TraitModel;
use think\Model;

class User extends Model
{
    use TraitModel;

    public $name = 'common_user';

    /**
     * 关联发布的动态
     */
    public function moments()
    {
        return $this->hasMany(Moment::class, 'user_id', 'id');
    }


    public static function page_list($where, $limit, $page, $date)
    {
        $map = self::whereMapUser();
        //时间查询存在
        if (empty($date)) {
            $res = self::alias('b')->where($where)->where($map)
                ->join('common_admin a', 'b.market_uid = a.id', 'left')
                ->field('b.*,a.user_name admin')
                ->order('id desc');
        } else {
            $res = self::alias('b')
                ->where($where)
                ->where($map)
                ->whereBetweenTime('b.create_time', $date['start'], $date['end'])
                ->join('common_admin a', 'b.market_uid = a.id', 'left')
                ->field('b.*,a.user_name admin')
                ->order('id desc');
        }
        return $res->paginate(['list_rows' => $limit, 'page' => $page], false)
            ->each(function ($item, $key) {
                unset($item->withdraw_pwd);
                unset($item->pwd);
                // 下面处理是为了 显示代理的 手机号
                $item->agent_id_1_phone = self::where('id', $item->agent_id_1)->value('phone');
                $item->agent_id_1_true_name = getTrueName($item->agent_id_1);
                $item->agent_zhitui_num = getZhiTuiNum($item->id);
                $item->is_real_name_text = getRealNameText($item->is_real_name);
                $item->bank_id = getBankID($item->id);
                $item->bank_name = getBankName($item->id);
                $item->bank_true_name = getBankTrueName($item->id);
                $item->bank_card_number = getBankCardNumber($item->id);
                $item->bank_address = getBankAdress($item->id);
                $item->real_name_id = getRealNameId($item->id);
                $item->true_name = getTrueName($item->id);
                $item->card_id = getTrueNameID($item->id);
                $item->id_card_number = getIDCardNumber($item->id);
                $item->last_login_ip = getLoginIP($item->id);
                $item->ip_address = getIPAdress($item->id);
                // 增加各种 辅助查询
            });
    }

    //代理商个人信息
    public static function page_one($limit, $page)
    {
        // $map = self::whereMap();
        // if (empty($map)) return false;
        $map = [];
        if (session('admin_user.agent')) {
            $map['a.id'] = session('admin_user.id');
        } else {
            $map['a.type'] = 1;
        }

        return self::alias('a')
            ->where($map)
            ->join('common_admin b', 'a.market_uid = b.id', 'left')
            ->field('a.*,b.user_name admin')
            ->paginate(['list_rows' => $limit, 'page' => $page], false)
            ->each(function ($item, $key) {
                $item->tg_url_txt = $_SERVER['REQUEST_SCHEME'] . '://' . randomkey(5) . '.' . config('ToConfig.app_tg.tg_url') . '?code=' . $item->invitation_code;
            });
    }

    //直接删除
    public function del($id)
    {
        $find = $this->find($id);
        if (empty($find))
            return false;
        return $find->delete();
    }

    private static function buildWhere($uid, $level)
    {
        if ('n' === strtolower($level)) {
            return self::where(function ($query) use ($uid) {
                $query->where('agent_id_1', '=', $uid)
                    ->whereOr('agent_id_2', '=', $uid)
                    ->whereOr('agent_id_3', '=', $uid);
            });
        } elseif ('w' === strtolower($level)) {
            $field = 'agent_up_ids';
            $op = 'REGEXP';
            $uid = "^$uid#|#$uid#|#$uid$";
            return self::where($field, $op, $uid);
        } else {
            $field = 'agent_id_' . $level;
            $op = '=';
            return self::where($field, $op, $uid);
        }
    }

    public static function getTotalByField($uid, $level, $total, $map_date = [])
    {
        if (!empty($map_date)) {
            $uids = [];
            $users = self::buildWhere($uid, $level)->select();
            foreach ($users as $k => $v) {
                $uids[] = $v->id;
            }

            $uids_str = implode(',', $uids);
            $status_str = [
                'money_all_recharge' => '101',
                'money_all_withdraw' => '201',
                'money_all_buy' => '203',
            ];
            return (new MoneyLog())
                ->where('status', 'in', $status_str[$total])
                ->where('uid', 'in', $uids_str)
                ->whereTime('create_time', 'between', $map_date)
                ->sum('money');
        } else {
            return self::buildWhere($uid, $level)->sum($total);
        }

    }

    public static function getCountByField($uid, $level, $where = [], $map_date = [])
    {
        if (!empty($map_date)) {
            $uids = [];
            $users = self::buildWhere($uid, $level)->select();
            foreach ($users as $k => $v) {
                $uids[] = $v->id;
            }
            $uids_str = implode(',', $uids);
            return self::where('id', 'in', $uids_str)
                ->whereTime('create_time', 'between', $map_date)
                ->count();
        } else {
            return self::buildWhere($uid, $level)->when($where, $where)->count();
        }

    }

    public static function getRegisterByToday($uid)
    {
        $count1 = self::where('agent_id_1', $uid)->whereDay('create_time')->count();
        $count2 = self::where('agent_id_2', $uid)->whereDay('create_time')->count();
        $count3 = self::where('agent_id_3', $uid)->whereDay('create_time')->count();

        return $count1 + $count2 + $count3;
    }


    public static function decreaseTimes($userid, $step = 1)
    {
        return self::decreaseBy('choujiang_times', $userid, $step);
    }

    public static function increaseTimes($userid, $step = 1)
    {
        return self::increaseBy('choujiang_times', $userid, $step);
    }

    public static function increasePoints($userid, $step = 1)
    {
        return self::increaseBy('given_money_cishan', $userid, $step);
    }

    public static function getPoints($uid)
    {
        return self::where('id', $uid)->value('given_money_cishan', 0);
    }

    public static function decreaseBy($field, $userid, $step = 1)
    {
        return self::where('id', $userid)->dec($field, $step)->update();
    }

    public static function increaseBy($field, $userid, $step = 1)
    {
        return self::where('id', $userid)->inc($field, $step)->update();
    }



}