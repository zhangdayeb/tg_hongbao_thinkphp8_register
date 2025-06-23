<?php

namespace app\admin\controller\telegram;

use app\admin\controller\Base;
use app\common\model\TgCrowdList;
use app\common\traites\PublicCrudTrait;
use think\facade\Db;
use think\exception\ValidateException;

/**
 * Telegram广告管理控制器
 */
class TGAD extends Base
{
    protected $model;
    use PublicCrudTrait;

    /**
     * 初始化
     */
    public function initialize()
    {
        // 使用广告表模型
        $this->model = Db::name('tg_advertisements');
        parent::initialize();
    }

    /**
     * 获取广告列表
     * POST /telegram/advertisements
     */
    public function getAdvertisementList()
    {
        $post = $this->request->post();
        
        // 分页参数
        $page = $post['page'] ?? 1;
        $limit = $post['limit'] ?? 20;
        
        // 构建查询条件
        $map = [];
        
        // 标题搜索
        if (!empty($post['title'])) {
            $map[] = ['title', 'like', '%' . $post['title'] . '%'];
        }
        
        // 发送模式筛选
        if (isset($post['send_mode']) && $post['send_mode'] !== '') {
            $sendModeMap = [
                'immediate' => 1,
                'scheduled' => 2, 
                'recurring' => 3
            ];
            if (isset($sendModeMap[$post['send_mode']])) {
                $map[] = ['send_mode', '=', $sendModeMap[$post['send_mode']]];
            }
        }
        
        // 状态筛选
        if (isset($post['status']) && $post['status'] !== '') {
            $statusMap = [
                'draft' => 0,
                'active' => 1,
                'completed' => 2,
                'cancelled' => 3
            ];
            if (isset($statusMap[$post['status']])) {
                $map[] = ['status', '=', $statusMap[$post['status']]];
            }
        }
        
        // 日期范围筛选
        if (!empty($post['start_date']) && !empty($post['end_date'])) {
            $map[] = ['created_at', 'between', [$post['start_date'] . ' 00:00:00', $post['end_date'] . ' 23:59:59']];
        }
        
        // 查询数据
        $total = $this->model->where($map)->count();
        $list = $this->model
            ->where($map)
            ->order('id desc')
            ->page($page, $limit)
            ->select();
        
        // 格式化数据
        $formattedList = [];
        foreach ($list as $item) {
            $formattedList[] = [
                'id' => $item['id'],
                'title' => $item['title'],
                'content' => $item['content'],
                'image_url' => $item['image_url'],
                'send_mode_text' => $this->getSendModeText($item['send_mode']),
                'send_mode' => $item['send_mode'],
                'send_time' => $item['send_time'],
                'daily_times' => $item['daily_times'],
                'interval_minutes' => $item['interval_minutes'],
                'status_text' => $this->getStatusText($item['status']),
                'status' => $item['status'],
                'total_sent_count' => $item['total_sent_count'],
                'success_count' => $item['success_count'],
                'failed_count' => $item['failed_count'],
                'success_rate' => $item['total_sent_count'] > 0 ? 
                    round($item['success_count'] / $item['total_sent_count'] * 100, 2) : 0,
                'start_date' => $item['start_date'],
                'end_date' => $item['end_date'],
                'last_sent_time' => $item['last_sent_time'],
                'next_send_time' => $item['next_send_time'],
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at']
            ];
        }
        
        return $this->success([
            'list' => $formattedList,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]);
    }

    /**
     * 获取广告详情
     * POST /telegram/advertisement/detail
     */
    public function getAdvertisementDetail()
    {
        $id = $this->request->post('id');
        
        if (empty($id)) {
            return $this->failed('广告ID不能为空');
        }
        
        $advertisement = $this->model->where('id', $id)->find();
        
        if (!$advertisement) {
            return $this->failed('广告不存在');
        }
        
        // 获取发送统计
        $sendLogs = Db::name('tg_message_logs')
            ->where('source_id', $id)
            ->where('source_type', 'advertisement')
            ->field('send_status,count(*) as count')
            ->group('send_status')
            ->select();
        
        $sendStats = [
            'pending' => 0,
            'success' => 0,
            'failed' => 0
        ];
        
        foreach ($sendLogs as $log) {
            switch ($log['send_status']) {
                case 0:
                    $sendStats['pending'] = $log['count'];
                    break;
                case 1:
                    $sendStats['success'] = $log['count'];
                    break;
                case 2:
                    $sendStats['failed'] = $log['count'];
                    break;
            }
        }
        
        $result = [
            'id' => $advertisement['id'],
            'title' => $advertisement['title'],
            'content' => $advertisement['content'],
            'image_url' => $advertisement['image_url'],
            'send_mode_text' => $this->getSendModeText($advertisement['send_mode']),
            'send_mode' => $advertisement['send_mode'],
            'send_time' => $advertisement['send_time'],
            'daily_times' => $advertisement['daily_times'],
            'interval_minutes' => $advertisement['interval_minutes'],
            'status_text' => $this->getStatusText($advertisement['status']),
            'status' => $advertisement['status'],
            'total_sent_count' => $advertisement['total_sent_count'],
            'success_count' => $advertisement['success_count'],
            'failed_count' => $advertisement['failed_count'],
            'start_date' => $advertisement['start_date'],
            'end_date' => $advertisement['end_date'],
            'last_sent_time' => $advertisement['last_sent_time'],
            'next_send_time' => $advertisement['next_send_time'],
            'is_sent' => $advertisement['is_sent'],
            'send_stats' => $sendStats,
            'created_at' => $advertisement['created_at'],
            'updated_at' => $advertisement['updated_at']
        ];
        
        return $this->success($result);
    }

/**
     * 创建广告
     * POST /telegram/advertisement/create
     */
    public function createAdvertisement()
    {
        $post = $this->request->post();
        
        // 验证必填字段
        if (empty($post['title'])) {
            return $this->failed('广告标题不能为空');
        }
        
        if (empty($post['content'])) {
            return $this->failed('广告内容不能为空');
        }
        
        if (!isset($post['send_mode']) || !in_array($post['send_mode'], [1, 2, 3])) {
            return $this->failed('发送模式参数错误，必须为1、2或3');
        }
        
        $sendMode = (int)$post['send_mode'];
        
        // 根据发送模式验证对应参数
        switch ($sendMode) {
            case 1: // 一次性定时发送
                if (empty($post['send_time'])) {
                    return $this->failed('一次性定时发送必须设置发送时间');
                }
                
                // 验证时间格式和有效性
                $sendTime = strtotime($post['send_time']);
                if ($sendTime === false || $sendTime <= time()) {
                    return $this->failed('发送时间格式错误或不能小于当前时间');
                }
                break;
                
            case 2: // 每日定时发送
                if (empty($post['daily_times'])) {
                    return $this->failed('每日定时发送必须设置每日发送时间点');
                }
                
                // 验证每日时间格式
                $dailyTimes = is_array($post['daily_times']) ? $post['daily_times'] : explode(',', $post['daily_times']);
                foreach ($dailyTimes as $time) {
                    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', trim($time))) {
                        return $this->failed('每日发送时间格式错误，请使用HH:MM格式');
                    }
                }
                break;
                
            case 3: // 循环间隔发送
                if (empty($post['interval_minutes']) || !is_numeric($post['interval_minutes']) || $post['interval_minutes'] <= 0) {
                    return $this->failed('循环间隔发送必须设置有效的间隔分钟数');
                }
                break;
        }
        
        // 设置默认日期范围
        $startDate = !empty($post['start_date']) ? $post['start_date'] : date('Y-m-d'); // 默认为当前日期
        $endDate = !empty($post['end_date']) ? $post['end_date'] : date('Y-m-d', strtotime('+1 year')); // 默认为一年后
        
        if (strtotime($endDate) < strtotime($startDate)) {
            return $this->failed('结束日期不能早于开始日期');
        }
        
        // 构建数据
        $data = [
            'title' => trim($post['title']),
            'content' => trim($post['content']),
            'image_url' => trim($post['image_url'] ?? ''),
            'send_mode' => $sendMode,
            'send_time' => null,
            'daily_times' => null,
            'interval_minutes' => null,
            'status' => 1, // 默认启用
            'is_sent' => 0, // 默认未发送
            'last_sent_time' => null,
            'next_send_time' => null, // 由定时调度函数计算
            'total_sent_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'created_by' => $this->adminId ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // 根据发送模式设置对应字段
        switch ($sendMode) {
            case 1: // 一次性定时
                $data['send_time'] = date('Y-m-d H:i:s', strtotime($post['send_time']));
                break;
                
            case 2: // 每日定时
                $dailyTimes = is_array($post['daily_times']) ? $post['daily_times'] : explode(',', $post['daily_times']);
                $data['daily_times'] = implode(',', array_map('trim', $dailyTimes));
                break;
                
            case 3: // 循环间隔
                $data['interval_minutes'] = (int)$post['interval_minutes'];
                break;
        }
        
        // 开启事务
        Db::startTrans();
        try {
            // 插入广告
            $adId = $this->model->insertGetId($data);
            
            if (!$adId) {
                throw new \Exception('广告数据插入失败');
            }
            
            Db::commit();
            
            return $this->success([
                'id' => $adId
            ]);
            
        } catch (\Exception $e) {
            Db::rollback();
            return $this->failed('广告创建失败：' . $e->getMessage());
        }
    }

    /**
     * 更新广告
     * POST /telegram/advertisement/update
     */
    public function updateAdvertisement()
    {
        $post = $this->request->post();
        
        if (empty($post['id'])) {
            return $this->failed('广告ID不能为空');
        }
        
        $advertisement = $this->model->where('id', $post['id'])->find();
        
        if (!$advertisement) {
            return $this->failed('广告不存在');
        }
        
        // 检查是否可编辑（已完成的广告不允许编辑）
        if ($advertisement['status'] == 2) {
            return $this->failed('已完成的广告不能编辑');
        }
        
        // 如果广告已经开始发送，某些字段不允许修改
        $isStarted = $advertisement['total_sent_count'] > 0;
        
        // 构建更新数据
        $updateData = [
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // 基础字段更新
        if (isset($post['title']) && !empty($post['title'])) {
            $updateData['title'] = trim($post['title']);
        }
        
        if (isset($post['content']) && !empty($post['content'])) {
            $updateData['content'] = trim($post['content']);
        }
        
        if (isset($post['image_url'])) {
            $updateData['image_url'] = trim($post['image_url']);
        }
        
        // 设置默认日期处理
        if (isset($post['start_date']) && !empty($post['start_date']) && !$isStarted) {
            $updateData['start_date'] = $post['start_date'];
        } else if ((!isset($post['start_date']) || empty($post['start_date'])) && !$isStarted && empty($advertisement['start_date'])) {
            // 如果前端没有发送start_date或为空且数据库中也没有，设置默认值
            $updateData['start_date'] = date('Y-m-d');
        }
        
        if (isset($post['end_date']) && !empty($post['end_date'])) {
            $updateData['end_date'] = $post['end_date'];
        } else if (isset($post['end_date']) && empty($post['end_date'])) {
            // 如果明确传了空值，则设置为null
            $updateData['end_date'] = null;
        } else if (!isset($post['end_date']) && empty($advertisement['end_date'])) {
            // 如果前端没有发送end_date且数据库中也没有，设置默认值
            $updateData['end_date'] = date('Y-m-d', strtotime('+1 year'));
        }
        
        if (isset($post['status']) && in_array($post['status'], [0, 1, 2])) {
            $updateData['status'] = (int)$post['status'];
        }
        
        // 发送模式相关字段更新（只有未开始发送的广告才能修改）
        if (!$isStarted && isset($post['send_mode']) && in_array($post['send_mode'], [1, 2, 3])) {
            $sendMode = (int)$post['send_mode'];
            
            // 验证发送模式参数
            switch ($sendMode) {
                case 1: // 一次性定时
                    if (empty($post['send_time'])) {
                        return $this->failed('一次性定时发送必须设置发送时间');
                    }
                    
                    $sendTime = strtotime($post['send_time']);
                    if ($sendTime === false || $sendTime <= time()) {
                        return $this->failed('发送时间格式错误或不能小于当前时间');
                    }
                    
                    $updateData['send_mode'] = 1;
                    $updateData['send_time'] = date('Y-m-d H:i:s', $sendTime);
                    $updateData['daily_times'] = null;
                    $updateData['interval_minutes'] = null;
                    $updateData['is_sent'] = 0; // 重置发送状态
                    break;
                    
                case 2: // 每日定时
                    if (empty($post['daily_times'])) {
                        return $this->failed('每日定时发送必须设置每日发送时间点');
                    }
                    
                    $dailyTimes = is_array($post['daily_times']) ? $post['daily_times'] : explode(',', $post['daily_times']);
                    foreach ($dailyTimes as $time) {
                        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', trim($time))) {
                            return $this->failed('每日发送时间格式错误，请使用HH:MM格式');
                        }
                    }
                    
                    $updateData['send_mode'] = 2;
                    $updateData['send_time'] = null;
                    $updateData['daily_times'] = implode(',', array_map('trim', $dailyTimes));
                    $updateData['interval_minutes'] = null;
                    break;
                    
                case 3: // 循环间隔
                    if (empty($post['interval_minutes']) || !is_numeric($post['interval_minutes']) || $post['interval_minutes'] <= 0) {
                        return $this->failed('循环间隔发送必须设置有效的间隔分钟数');
                    }
                    
                    $updateData['send_mode'] = 3;
                    $updateData['send_time'] = null;
                    $updateData['daily_times'] = null;
                    $updateData['interval_minutes'] = (int)$post['interval_minutes'];
                    break;
            }
        } else if (!$isStarted) {
            // 如果没有修改发送模式，但修改了对应参数
            $currentMode = $advertisement['send_mode'];
            
            switch ($currentMode) {
                case 1: // 一次性定时
                    if (isset($post['send_time'])) {
                        $sendTime = strtotime($post['send_time']);
                        if ($sendTime === false || $sendTime <= time()) {
                            return $this->failed('发送时间格式错误或不能小于当前时间');
                        }
                        $updateData['send_time'] = date('Y-m-d H:i:s', $sendTime);
                        $updateData['is_sent'] = 0; // 重置发送状态
                    }
                    break;
                    
                case 2: // 每日定时
                    if (isset($post['daily_times'])) {
                        $dailyTimes = is_array($post['daily_times']) ? $post['daily_times'] : explode(',', $post['daily_times']);
                        foreach ($dailyTimes as $time) {
                            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', trim($time))) {
                                return $this->failed('每日发送时间格式错误，请使用HH:MM格式');
                            }
                        }
                        $updateData['daily_times'] = implode(',', array_map('trim', $dailyTimes));
                    }
                    break;
                    
                case 3: // 循环间隔
                    if (isset($post['interval_minutes'])) {
                        if (!is_numeric($post['interval_minutes']) || $post['interval_minutes'] <= 0) {
                            return $this->failed('循环间隔分钟数必须为大于0的数字');
                        }
                        $updateData['interval_minutes'] = (int)$post['interval_minutes'];
                    }
                    break;
            }
        }
        
        // 验证日期范围
        $startDate = $updateData['start_date'] ?? $advertisement['start_date'] ?? date('Y-m-d');
        $endDate = $updateData['end_date'] ?? $advertisement['end_date'];
        
        if ($endDate && strtotime($endDate) < strtotime($startDate)) {
            return $this->failed('结束日期不能早于开始日期');
        }
        
        // 开启事务
        Db::startTrans();
        try {
            $result = $this->model->where('id', $post['id'])->update($updateData);
            
            if ($result === false) {
                throw new \Exception('广告数据更新失败');
            }
            
            Db::commit();
            
            return $this->success([
                'updated_fields' => array_keys($updateData)
            ]);
            
        } catch (\Exception $e) {
            Db::rollback();
            return $this->failed('广告更新失败：' . $e->getMessage());
        }
    }

    
    public function deleteAdvertisement()
    {
        $id = $this->request->post('id');
        
        if (empty($id)) {
            return $this->failed('广告ID不能为空');
        }
        
        // 开启事务
        Db::startTrans();
        try {
            // 直接尝试删除，返回受影响的行数
            $result = $this->model->where('id', $id)->delete();
            
            Db::commit();
            
            // 无论是否删除成功都返回成功（幂等性）
            return $this->success([], 1, '广告删除成功');
            
        } catch (\Exception $e) {
            Db::rollback();
            return $this->failed('广告删除失败：' . $e->getMessage());
        }
    }


    /**
     * 获取发送模式文本
     */
    private function getSendModeText($mode)
    {
        $modes = [
            1 => '定时发送',
            2 => '每日定时', 
            3 => '循环发送'
        ];
        
        return $modes[$mode] ?? 'unknown';
    }

    /**
     * 获取状态文本
     */
    private function getStatusText($status)
    {
        $statuses = [
            0 => '草稿',
            1 => '激活',
            2 => '完成',
            3 => '取消'
        ];
        
        return $statuses[$status] ?? 'unknown';
    }
}