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
class TGRedPacket extends Base
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
     * 红包列表
     */
    public function index()
    {
        // 当前页
        $page = $this->request->post('page', 1);
        // 每页显示数量
        $limit = $this->request->post('limit', 10);
        // 查询搜索条件
        $post = array_filter($this->request->post());
        
        $map = [];
        
        // 红包标题模糊搜索
        if (!empty($post['title'])) {
            $map[] = ['title', 'like', '%' . $post['title'] . '%'];
        }
        
        // 红包ID精确查找
        if (!empty($post['packet_id'])) {
            $map[] = ['packet_id', '=', $post['packet_id']];
        }
        
        // 发送者TG_ID搜索
        if (!empty($post['sender_tg_id'])) {
            $map[] = ['sender_tg_id', '=', $post['sender_tg_id']];
        }
        
        // 群组ID筛选
        if (!empty($post['chat_id'])) {
            $map[] = ['chat_id', '=', $post['chat_id']];
        }
        
        // 红包状态筛选
        if (isset($post['status']) && $post['status'] !== '') {
            $map[] = ['status', '=', $post['status']];
        }
        
        // 红包类型筛选
        if (isset($post['packet_type']) && $post['packet_type'] !== '') {
            $map[] = ['packet_type', '=', $post['packet_type']];
        }
        
        // 是否系统红包
        if (isset($post['is_system']) && $post['is_system'] !== '') {
            $map[] = ['is_system', '=', $post['is_system']];
        }
        
        // 金额范围筛选
        if (!empty($post['min_amount'])) {
            $map[] = ['total_amount', '>=', $post['min_amount']];
        }
        if (!empty($post['max_amount'])) {
            $map[] = ['total_amount', '<=', $post['max_amount']];
        }
        
        // 时间范围筛选
        if (!empty($post['start_date']) && !empty($post['end_date'])) {
            $map[] = ['created_at', 'between', [$post['start_date'] . ' 00:00:00', $post['end_date'] . ' 23:59:59']];
        }
        
        // 查询数据
        $list = $this->model
            ->where($map)
            ->order('id desc')
            ->paginate(['list_rows' => $limit, 'page' => $page]);
        
        // 格式化数据
        $list->each(function ($item) {
            $item->packet_type_text = $this->getPacketTypeText($item->packet_type);
            $item->status_text = $this->getStatusText($item->status);
            $item->chat_type_text = $this->getChatTypeText($item->chat_type);
            $item->is_system_text = $item->is_system ? '系统红包' : '用户红包';
            $item->progress = $this->calculateProgress($item);
            $item->created_at_format = date('Y-m-d H:i:s', strtotime($item->created_at));
            
            // 获取发送者信息
            $sender = User::where('id', $item->sender_id)->field('user_name,tg_username')->find();
            $item->sender_name = $sender ? ($sender->tg_username ?: $sender->user_name) : '未知用户';
            
            // 获取群组信息
            $group = TgCrowdList::where('crowd_id', $item->chat_id)->field('title')->find();
            $item->group_name = $group ? $group->title : '未知群组';
        });

        return $this->success($list);
    }

    /**
     * 红包详情
     */
    public function detail()
    {
        $id = $this->request->post('id');
        if (empty($id)) {
            return $this->failed('ID参数不能为空');
        }

        $redPacket = $this->model->where('id', $id)->find();
        if (empty($redPacket)) {
            return $this->failed('红包不存在');
        }

        // 格式化数据
        $redPacket->packet_type_text = $this->getPacketTypeText($redPacket->packet_type);
        $redPacket->status_text = $this->getStatusText($redPacket->status);
        $redPacket->chat_type_text = $this->getChatTypeText($redPacket->chat_type);
        $redPacket->is_system_text = $redPacket->is_system ? '系统红包' : '用户红包';
        $redPacket->progress = $this->calculateProgress($redPacket);
        
        // 获取发送者信息
        $sender = User::where('id', $redPacket->sender_id)->find();
        $redPacket->sender_info = $sender;
        
        // 获取群组信息
        $group = TgCrowdList::where('crowd_id', $redPacket->chat_id)->find();
        $redPacket->group_info = $group;
        
        // 获取领取记录
        $records = $this->recordModel
            ->where('packet_id', $redPacket->packet_id)
            ->order('grab_order asc')
            ->select();
        
        $redPacket->records = $records;
        $redPacket->record_count = count($records);
        
        // 统计信息
        $redPacket->stats = [
            'grabbed_amount' => $redPacket->total_amount - $redPacket->remain_amount,
            'grabbed_count' => $redPacket->total_count - $redPacket->remain_count,
            'completion_rate' => $redPacket->total_count > 0 ? round(($redPacket->total_count - $redPacket->remain_count) / $redPacket->total_count * 100, 2) : 0,
            'avg_amount' => $records->count() > 0 ? round($records->sum('amount') / $records->count(), 2) : 0,
        ];

        return $this->success($redPacket);
    }

    
    /**
     * 获取红包类型文本
     */
    private function getPacketTypeText($type)
    {
        $typeMap = [
            1 => '拼手气红包',
            2 => '平均红包',
            3 => '定制红包'
        ];
        
        return $typeMap[$type] ?? '未知类型';
    }

    /**
     * 获取状态文本
     */
    private function getStatusText($status)
    {
        $statusMap = [
            1 => '进行中',
            2 => '已抢完',
            3 => '已过期',
            4 => '已撤回',
            5 => '已取消'
        ];
        
        return $statusMap[$status] ?? '未知状态';
    }

    /**
     * 获取聊天类型文本
     */
    private function getChatTypeText($type)
    {
        $typeMap = [
            'group' => '群组',
            'supergroup' => '超级群组',
            'private' => '私聊'
        ];
        
        return $typeMap[$type] ?? '未知类型';
    }

    /**
     * 计算红包进度
     */
    private function calculateProgress($redPacket)
    {
        if ($redPacket->total_count == 0) {
            return 0;
        }
        
        $grabbedCount = $redPacket->total_count - $redPacket->remain_count;
        return round($grabbedCount / $redPacket->total_count * 100, 2);
    }
}