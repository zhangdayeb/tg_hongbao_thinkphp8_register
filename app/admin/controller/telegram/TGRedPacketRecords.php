<?php

namespace app\admin\controller\telegram;

use app\admin\controller\Base;
use app\common\model\RedPacket;
use app\common\model\RedPacketRecord;
use app\common\model\TgCrowdList;
use app\common\model\User;
use app\common\traites\PublicCrudTrait;
use think\facade\Db;
use think\exception\ValidateException;

/**
 * Telegram红包管理控制器
 */
class TGRedPacketRecords extends Base
{
    protected $model;
    protected $recordModel;
    use PublicCrudTrait;

    /**
     * 初始化
     */
    public function initialize()
    {
        $this->model = new RedPacket();
        $this->recordModel = new RedPacketRecord();
        parent::initialize();
    }

    /**
     * 红包记录列表 - 完善版本
     */
    public function records()
    {
        // 当前页
        $page = $this->request->post('page', 1);
        // 每页显示数量
        $limit = $this->request->post('limit', 10);
        // 查询搜索条件
        $post = array_filter($this->request->post());
        
        $map = [];
        
        // 红包ID筛选
        if (!empty($post['packet_id'])) {
            $map[] = ['r.packet_id', '=', $post['packet_id']];
        }
        
        // 用户TG_ID搜索
        if (!empty($post['user_tg_id'])) {
            $map[] = ['r.user_tg_id', '=', $post['user_tg_id']];
        }
        
        // 用户名搜索
        if (!empty($post['username'])) {
            $map[] = ['r.username', 'like', '%' . $post['username'] . '%'];
        }
        
        // 手气最佳筛选
        if (isset($post['is_best']) && $post['is_best'] !== '') {
            $map[] = ['r.is_best', '=', $post['is_best']];
        }
        
        // 金额范围
        if (!empty($post['min_amount'])) {
            $map[] = ['r.amount', '>=', $post['min_amount']];
        }
        if (!empty($post['max_amount'])) {
            $map[] = ['r.amount', '<=', $post['max_amount']];
        }
        
        // 时间范围
        if (!empty($post['start_date']) && !empty($post['end_date'])) {
            $map[] = ['r.created_at', 'between', [$post['start_date'] . ' 00:00:00', $post['end_date'] . ' 23:59:59']];
        }
        
        // 查询数据 - 使用关联查询获取完整信息
        $list = $this->recordModel
            ->alias('r')
            ->leftJoin('ntp_tg_red_packets rp', 'r.packet_id = rp.packet_id')
            ->leftJoin('ntp_common_user u', 'r.user_id = u.id')
            ->leftJoin('ntp_common_user sender', 'rp.sender_id = sender.id')
            ->leftJoin('ntp_tg_crowd_list tg', 'rp.chat_id = tg.crowd_id')
            ->field([
                'r.id, r.packet_id, r.user_id, r.user_tg_id, r.username, r.amount, r.is_best, r.grab_order, r.created_at',
                'rp.title as red_packet_title',
                'rp.total_amount as red_packet_total',
                'rp.total_count as red_packet_count',
                'rp.packet_type',
                'rp.status as red_packet_status',
                'rp.sender_id',
                'u.user_name',
                'u.tg_username as user_tg_username',
                'sender.user_name as sender_name',
                'sender.tg_username as sender_tg_username',
                'tg.title as group_name'
            ])
            ->where($map)
            ->order('r.id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]);
        
        // 格式化数据
        $list->each(function ($item) {
            // 格式化手气最佳文本
            $item->is_best_text = $item->is_best ? '手气最佳' : '普通';
            
            // 格式化时间
            $item->created_at_format = date('Y-m-d H:i:s', strtotime($item->created_at));
            
            // 用户显示名称 - 优先使用username字段，其次用户表的TG用户名，最后用TG_ID
            if (!empty($item->username)) {
                $item->user_display_name = $item->username;
            } elseif (!empty($item->user_tg_username)) {
                $item->user_display_name = '@' . $item->user_tg_username;
            } elseif (!empty($item->user_name)) {
                $item->user_display_name = $item->user_name;
            } else {
                $item->user_display_name = 'User' . substr($item->user_tg_id, -6);
            }
            
            // 发送者显示名称
            if (!empty($item->sender_tg_username)) {
                $item->sender_display_name = '@' . $item->sender_tg_username;
            } elseif (!empty($item->sender_name)) {
                $item->sender_display_name = $item->sender_name;
            } else {
                $item->sender_display_name = '未知用户';
            }
            
            // 群组名称处理 - 如果为空显示未知群组
            $item->group_display_name = !empty($item->group_name) ? $item->group_name : '未知群组';
            
            // 红包类型文本
            $item->packet_type_text = $this->getPacketTypeText($item->packet_type);
            
            // 红包状态文本
            $item->red_packet_status_text = $this->getRedPacketStatusText($item->red_packet_status);
            
            // 格式化金额显示
            $item->amount_formatted = number_format($item->amount, 2);
            $item->red_packet_total_formatted = number_format($item->red_packet_total, 2);
            
            // 抢取顺序文本
            $item->grab_order_text = $this->getGrabOrderText($item->grab_order);
            
            // 运气等级
            $item->luck_level = $this->getLuckLevel($item->is_best, $item->grab_order);
            
            // 计算抢取时间差（相对于红包创建时间）
            if (!empty($item->created_at)) {
                $item->grab_time_diff = $this->calculateTimeDiff($item->created_at);
            }
        });

        return $this->success($list);
    }

    /**
     * 获取红包类型文本
     */
    private function getPacketTypeText($type)
    {
        $types = [
            1 => '拼手气',
            2 => '平均分配'
        ];
        return $types[$type] ?? '未知';
    }

    /**
     * 获取红包状态文本
     */
    private function getRedPacketStatusText($status)
    {
        $statuses = [
            1 => '进行中',
            2 => '已抢完',
            3 => '已过期',
            4 => '已撤回',
            5 => '已取消'
        ];
        return $statuses[$status] ?? '未知';
    }

    /**
     * 获取抢取顺序文本
     */
    private function getGrabOrderText($order)
    {
        if ($order == 1) {
            return '第1个（首抢）';
        } else {
            return "第{$order}个";
        }
    }

    /**
     * 获取运气等级
     */
    private function getLuckLevel($isBest, $order)
    {
        if ($isBest) {
            return '手气最佳';
        }
        
        if ($order == 1) {
            return '首抢';
        } elseif ($order <= 3) {
            return '手气不错';
        } elseif ($order <= 5) {
            return '运气一般';
        } else {
            return '慢了一步';
        }
    }

    /**
     * 计算时间差
     */
    private function calculateTimeDiff($datetime)
    {
        $timestamp = strtotime($datetime);
        $now = time();
        $diff = $now - $timestamp;
        
        if ($diff < 60) {
            return $diff . '秒前';
        } elseif ($diff < 3600) {
            return round($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return round($diff / 3600) . '小时前';
        } elseif ($diff < 2592000) {
            return round($diff / 86400) . '天前';
        } else {
            return date('Y-m-d H:i', $timestamp);
        }
    }
}