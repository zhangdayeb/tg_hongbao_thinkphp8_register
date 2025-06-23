<?php

namespace app\admin\controller\telegram;

use app\admin\controller\Base;
use app\common\model\TgCrowdList;
use app\common\traites\PublicCrudTrait;
use think\facade\Db;
use think\exception\ValidateException;

/**
 * Telegram群组管理控制器
 */
class TGQunZu extends Base
{
    protected $model;
    use PublicCrudTrait;

    /**
     * 初始化
     */
    public function initialize()
    {
        $this->model = new TgCrowdList();
        parent::initialize();
    }

    /**
     * 群组列表
     */
    public function index()
    {
        // 当前页
        $page = $this->request->post('page', 1);
        // 每页显示数量
        $limit = $this->request->post('limit', 10);
        // 查询搜索条件
        $post = array_filter($this->request->post());
        
        $map = [['del', '=', 0]]; // 未删除的记录
        
        // 群名称模糊搜索
        if (!empty($post['title'])) {
            $map[] = ['title', 'like', '%' . $post['title'] . '%'];
        }
        
        // 群ID精确查找
        if (!empty($post['crowd_id'])) {
            $map[] = ['crowd_id', '=', $post['crowd_id']];
        }
        
        // 机器人状态筛选
        if (!empty($post['bot_status'])) {
            $map[] = ['bot_status', '=', $post['bot_status']];
        }
        
        // 活跃状态筛选
        if (isset($post['is_active']) && $post['is_active'] !== '') {
            $map[] = ['is_active', '=', $post['is_active']];
        }
        
        // 广播开关筛选
        if (isset($post['broadcast_enabled']) && $post['broadcast_enabled'] !== '') {
            $map[] = ['broadcast_enabled', '=', $post['broadcast_enabled']];
        }
        
        // 创建时间范围
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
            $item->status_text = $item->is_active ? '活跃' : '不活跃';
            $item->broadcast_text = $item->broadcast_enabled ? '已启用' : '已禁用';
            $item->bot_status_text = $this->getBotStatusText($item->bot_status);
            $item->created_at_format = date('Y-m-d H:i:s', strtotime($item->created_at));
            $item->updated_at_format = date('Y-m-d H:i:s', strtotime($item->updated_at));
        });

        return $this->success($list);
    }

    /**
     * 群组详情
     */
    public function detail()
    {
        $id = $this->request->post('id');
        if (empty($id)) {
            return $this->failed('ID参数不能为空');
        }

        $group = $this->model->where('id', $id)->where('del', 0)->find();
        if (empty($group)) {
            return $this->failed('群组不存在');
        }

        // 格式化数据
        $group->status_text = $group->is_active ? '活跃' : '不活跃';
        $group->broadcast_text = $group->broadcast_enabled ? '已启用' : '已禁用';
        $group->bot_status_text = $this->getBotStatusText($group->bot_status);
        
        // 获取群组相关统计
        $stats = $this->getGroupStats($group->crowd_id);
        $group->stats = $stats;

        return $this->success($group);
    }

    /**
     * 添加群组
     */
    public function add()
    {
        $post = $this->request->post();
        
        // 验证必要字段
        if (empty($post['title'])) {
            return $this->failed('群名称不能为空');
        }
        if (empty($post['crowd_id'])) {
            return $this->failed('群ID不能为空');
        }
        if (empty($post['first_name'])) {
            return $this->failed('机器人名称不能为空');
        }

        // 检查群ID是否已存在
        $exists = $this->model->where('crowd_id', $post['crowd_id'])->where('del', 0)->find();
        if ($exists) {
            return $this->failed('该群组已存在');
        }

        // 准备数据
        $data = [
            'title' => $post['title'],
            'crowd_id' => $post['crowd_id'],
            'first_name' => $post['first_name'],
            'botname' => $post['botname'] ?? '',
            'user_id' => $post['user_id'] ?? '',
            'username' => $post['username'] ?? '',
            'member_count' => intval($post['member_count'] ?? 0),
            'is_active' => intval($post['is_active'] ?? 1),
            'broadcast_enabled' => intval($post['broadcast_enabled'] ?? 1),
            'bot_status' => $post['bot_status'] ?? 'member',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'del' => 0
        ];

        $result = $this->model->save($data);
        if ($result) {
            return $this->success([], '群组添加成功');
        }
        
        return $this->failed('群组添加失败');
    }

    /**
     * 编辑群组
     */
    public function edit()
    {
        $post = $this->request->post();
        $id = $post['id'] ?? 0;
        
        if (empty($id)) {
            return $this->failed('ID参数不能为空');
        }

        $group = $this->model->where('id', $id)->where('del', 0)->find();
        if (empty($group)) {
            return $this->failed('群组不存在');
        }

        // 准备更新数据
        $updateData = [
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // 可更新的字段
        $allowFields = ['title', 'first_name', 'botname', 'username', 'member_count', 'is_active', 'broadcast_enabled', 'bot_status'];
        foreach ($allowFields as $field) {
            if (isset($post[$field])) {
                $updateData[$field] = $post[$field];
            }
        }

        $result = $this->model->where('id', $id)->update($updateData);
        if ($result !== false) {
            return $this->success([], '群组更新成功');
        }

        return $this->failed('群组更新失败');
    }

    /**
     * 删除群组
     */
    public function delete()
    {
        $id = $this->request->post('id');
        if (empty($id)) {
            return $this->failed('ID参数不能为空');
        }

        $group = $this->model->where('id', $id)->where('del', 0)->find();
        if (empty($group)) {
            return $this->failed('群组不存在');
        }

        // 软删除
        $result = $this->model->where('id', $id)->update([
            'del' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($result) {
            return $this->success([], '群组删除成功');
        }

        return $this->failed('群组删除失败');
    }

    /**
     * 批量删除群组
     */
    public function batchDelete()
    {
        $ids = $this->request->post('ids');
        if (empty($ids) || !is_array($ids)) {
            return $this->failed('请选择要删除的群组');
        }

        $result = $this->model->whereIn('id', $ids)->update([
            'del' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($result) {
            return $this->success([], '批量删除成功');
        }

        return $this->failed('批量删除失败');
    }

    /**
     * 修改群组状态
     */
    public function changeStatus()
    {
        $id = $this->request->post('id');
        $status = $this->request->post('status');
        
        if (empty($id)) {
            return $this->failed('ID参数不能为空');
        }
        
        if (!in_array($status, [0, 1])) {
            return $this->failed('状态参数错误');
        }

        $result = $this->model->where('id', $id)->where('del', 0)->update([
            'is_active' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($result) {
            $statusText = $status ? '启用' : '禁用';
            return $this->success([], "群组{$statusText}成功");
        }

        return $this->failed('状态修改失败');
    }

    /**
     * 修改广播状态
     */
    public function changeBroadcast()
    {
        $id = $this->request->post('id');
        $broadcast = $this->request->post('broadcast');
        
        if (empty($id)) {
            return $this->failed('ID参数不能为空');
        }
        
        if (!in_array($broadcast, [0, 1])) {
            return $this->failed('广播参数错误');
        }

        $result = $this->model->where('id', $id)->where('del', 0)->update([
            'broadcast_enabled' => $broadcast,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($result) {
            $broadcastText = $broadcast ? '启用' : '禁用';
            return $this->success([], "广播{$broadcastText}成功");
        }

        return $this->failed('广播状态修改失败');
    }

    /**
     * 群组统计
     */
    public function statistics()
    {
        $stats = [
            // 基础统计
            'total_groups' => $this->model->where('del', 0)->count(),
            'active_groups' => $this->model->where(['del' => 0, 'is_active' => 1])->count(),
            'inactive_groups' => $this->model->where(['del' => 0, 'is_active' => 0])->count(),
            'broadcast_enabled' => $this->model->where(['del' => 0, 'broadcast_enabled' => 1])->count(),
            
            // 机器人状态统计
            'bot_admin' => $this->model->where(['del' => 0, 'bot_status' => 'administrator'])->count(),
            'bot_member' => $this->model->where(['del' => 0, 'bot_status' => 'member'])->count(),
            'bot_left' => $this->model->where(['del' => 0, 'bot_status' => 'left'])->count(),
            
            // 成员数量统计
            'total_members' => $this->model->where('del', 0)->sum('member_count'),
            'avg_members' => $this->model->where('del', 0)->avg('member_count'),
            
            // 今日新增群组
            'today_groups' => $this->model->where('del', 0)->whereTime('created_at', 'today')->count(),
            
            // 本周新增群组
            'week_groups' => $this->model->where('del', 0)->whereTime('created_at', 'week')->count(),
        ];

        // 成员数量分布
        $memberDistribution = [
            'small' => $this->model->where(['del' => 0])->where('member_count', '<', 50)->count(),
            'medium' => $this->model->where(['del' => 0])->where('member_count', 'between', [50, 200])->count(),
            'large' => $this->model->where(['del' => 0])->where('member_count', '>', 200)->count(),
        ];

        $stats['member_distribution'] = $memberDistribution;

        return $this->success($stats);
    }

    /**
     * 群组活跃度排行
     */
    public function activityRanking()
    {
        $limit = $this->request->post('limit', 10);
        
        // 这里可以根据实际业务逻辑计算活跃度
        // 暂时按成员数量排序
        $ranking = $this->model
            ->where('del', 0)
            ->where('is_active', 1)
            ->field('id,title,crowd_id,member_count,created_at')
            ->order('member_count desc')
            ->limit($limit)
            ->select();

        return $this->success($ranking);
    }

    /**
     * 导出群组列表
     */
    public function export()
    {
        $post = array_filter($this->request->post());
        $map = [['del', '=', 0]];
        
        // 应用搜索条件
        if (!empty($post['title'])) {
            $map[] = ['title', 'like', '%' . $post['title'] . '%'];
        }
        if (!empty($post['crowd_id'])) {
            $map[] = ['crowd_id', '=', $post['crowd_id']];
        }
        if (isset($post['is_active']) && $post['is_active'] !== '') {
            $map[] = ['is_active', '=', $post['is_active']];
        }

        $list = $this->model->where($map)->select();
        
        // 格式化导出数据
        $exportData = [];
        foreach ($list as $item) {
            $exportData[] = [
                'ID' => $item->id,
                '群名称' => $item->title,
                '群ID' => $item->crowd_id,
                '机器人名称' => $item->first_name,
                '机器人用户名' => $item->botname,
                '成员数量' => $item->member_count,
                '活跃状态' => $item->is_active ? '活跃' : '不活跃',
                '广播状态' => $item->broadcast_enabled ? '已启用' : '已禁用',
                '机器人状态' => $this->getBotStatusText($item->bot_status),
                '创建时间' => $item->created_at,
                '更新时间' => $item->updated_at,
            ];
        }

        return $this->success($exportData);
    }

    /**
     * 获取机器人状态文本
     */
    private function getBotStatusText($status)
    {
        $statusMap = [
            'administrator' => '管理员',
            'member' => '普通成员',
            'left' => '已离开',
            'kicked' => '已被踢出',
            'restricted' => '受限制'
        ];
        
        return $statusMap[$status] ?? '未知';
    }

    /**
     * 获取群组统计信息
     */
    private function getGroupStats($crowdId)
    {
        // 这里可以扩展更多统计信息
        $stats = [
            'red_packet_count' => Db::name('tg_red_packets')->where('chat_id', $crowdId)->count(),
            'red_packet_amount' => Db::name('tg_red_packets')->where('chat_id', $crowdId)->sum('total_amount'),
            'message_count' => Db::name('tg_messages')->where('chat_id', $crowdId)->count(),
        ];
        
        return $stats;
    }
}