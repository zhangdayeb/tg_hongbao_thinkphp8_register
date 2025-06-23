<?php

namespace app\admin\controller\log;

use app\admin\controller\Base;
use think\facade\Db;
use think\facade\Log;
use think\Exception;

class TongZhi extends Base
{
    /**
     * 公司收款账户管理控制器
     */
    
    public function initialize()
    {
        // 临时跳过所有验证，仅用于测试通知功能
        // 正式环境需要恢复验证逻辑
        return;
        
        parent::initialize();
    }

    /**
     * 获取最新的充值/提现记录 (用于轮询通知)
     * @return \think\Response
     */
    public function getLatestRecords()
    {
        try {
            // POST请求接收参数
            $lastCheckTime = $this->request->param('lastCheckTime', '');
            $limit = $this->request->param('limit', 10);
            $types = $this->request->param('types', 'recharge,withdraw');
            
            // token和admin_type会自动传递过来
            $token = $this->request->param('token', '');
            $adminType = $this->request->param('admin_type', '');
            
            Log::info('通知接口接收参数', [
                'lastCheckTime' => $lastCheckTime,
                'limit' => $limit,
                'token' => $token ? 'exists' : 'empty',
                'admin_type' => $adminType
            ]);
            
            // 转换前端传来的毫秒时间戳为PHP时间戳
            $lastCheckTimestamp = $lastCheckTime ? intval($lastCheckTime / 1000) : (time() - 300); // 默认检查最近5分钟
            
            // 查询最新的充值记录（从资金流水表）
            $rechargeRecords = Db::table('ntp_common_pay_money_log')
                ->alias('log')
                ->leftJoin('ntp_common_user user', 'log.uid = user.id')
                ->where('log.type', 1) // 收入
                ->where('log.status', 101) // 充值
                ->where('log.create_time', '>', date('Y-m-d H:i:s', $lastCheckTimestamp))
                ->field('log.id, log.create_time, log.money, log.uid, user.user_name')
                ->order('log.create_time desc')
                ->limit($limit)
                ->select();
            
            // 查询最新的提现记录（从提现表）
            $withdrawRecords = Db::table('ntp_common_pay_withdraw')
                ->alias('withdraw')  // 改为更明确的别名
                ->leftJoin('ntp_common_user user', 'withdraw.user_id = user.id')  // 使用明确的别名
                ->where('withdraw.create_time', '>', date('Y-m-d H:i:s', $lastCheckTimestamp))
                ->field('withdraw.id, withdraw.create_time, withdraw.money, withdraw.user_id as uid, user.user_name')
                ->order('withdraw.create_time desc')
                ->limit($limit)
                ->select();
            
            // 组合数据并转换格式
            $allRecords = [];
            
            // 处理充值记录
            foreach ($rechargeRecords as $record) {
                $allRecords[] = [
                    'id' => 'recharge_' . $record['id'],
                    'type' => 'recharge',
                    'amount' => floatval($record['money']),
                    'userName' => $record['user_name'] ?: '用户' . $record['uid'],
                    'userId' => $record['uid'],
                    'createTime' => $record['create_time'],
                    'timestamp' => strtotime($record['create_time']) * 1000,
                    'status' => 'completed',
                    'remark' => '用户充值'
                ];
            }
            
            // 处理提现记录
            foreach ($withdrawRecords as $record) {
                $allRecords[] = [
                    'id' => 'withdraw_' . $record['id'],
                    'type' => 'withdraw',
                    'amount' => floatval($record['money']),
                    'userName' => $record['user_name'] ?: '用户' . $record['uid'],
                    'userId' => $record['uid'],
                    'createTime' => $record['create_time'],
                    'timestamp' => strtotime($record['create_time']) * 1000,
                    'status' => 'completed',
                    'remark' => '用户提现'
                ];
            }
            
            // 按时间倒序排列
            usort($allRecords, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });
            
            // 限制返回数量
            $allRecords = array_slice($allRecords, 0, $limit);
            
            Log::info('查询结果', [
                'recharge_count' => count($rechargeRecords),
                'withdraw_count' => count($withdrawRecords),
                'total_records' => count($allRecords),
                'last_check_timestamp' => $lastCheckTimestamp
            ]);
            
            return json([
                'code' => 1,
                'msg' => 'success',
                'data' => [
                    'records' => $allRecords,
                    'hasMore' => false,
                    'total' => count($allRecords),
                    'lastCheckTime' => time() * 1000 // 前端需要毫秒时间戳
                ]
            ]);

        } catch (Exception $e) {
            Log::error('获取最新记录失败: ' . $e->getMessage());
            
            return json([
                'code' => 0,
                'msg' => '获取记录失败: ' . $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 获取最新充值记录
     * @return \think\Response
     */
    public function getLatestRecharges()
    {
        try {
            $lastCheckTime = $this->request->param('lastCheckTime', '');
            $limit = $this->request->param('limit', 5);
            
            // 模拟充值数据
            $mockRecords = $this->generateMockRecords($lastCheckTime, $limit, 'recharge');
            
            return json([
                'code' => 200,
                'message' => 'success',
                'data' => [
                    'records' => $mockRecords,
                    'total' => count($mockRecords)
                ]
            ]);

        } catch (Exception $e) {
            return json([
                'code' => 500,
                'message' => '获取充值记录失败',
                'data' => null
            ]);
        }
    }

    /**
     * 获取最新提现记录
     * @return \think\Response
     */
    public function getLatestWithdraws()
    {
        try {
            $lastCheckTime = $this->request->param('lastCheckTime', '');
            $limit = $this->request->param('limit', 5);
            
            // 模拟提现数据
            $mockRecords = $this->generateMockRecords($lastCheckTime, $limit, 'withdraw');
            
            return json([
                'code' => 200,
                'message' => 'success',
                'data' => [
                    'records' => $mockRecords,
                    'total' => count($mockRecords)
                ]
            ]);

        } catch (Exception $e) {
            return json([
                'code' => 500,
                'message' => '获取提现记录失败',
                'data' => null
            ]);
        }
    }

    /**
     * 标记通知已读
     * @return \think\Response
     */
    public function markNotificationsRead()
    {
        try {
            $recordIds = $this->request->param('recordIds', []);
            
            // 这里后续可以更新数据库标记已读状态
            // 目前只是模拟返回成功
            
            return json([
                'code' => 200,
                'message' => '标记成功',
                'data' => [
                    'markedCount' => count($recordIds)
                ]
            ]);

        } catch (Exception $e) {
            return json([
                'code' => 500,
                'message' => '标记失败',
                'data' => null
            ]);
        }
    }

    /**
     * 生成模拟数据
     * @param string $lastCheckTime 最后检查时间
     * @param int $limit 数量限制
     * @param string $type 记录类型 recharge|withdraw|all
     * @return array
     */
    private function generateMockRecords($lastCheckTime = '', $limit = 10, $type = 'all')
    {
        $records = [];
        
        // 模拟用户名列表
        $userNames = ['张三', '李四', '王五', '赵六', '钱七', '孙八', '周九', '吴十'];
        
        // 模拟金额范围
        $amounts = [100, 200, 500, 1000, 2000, 5000, 10000];
        
        // 随机决定是否有新记录 (30% 概率有新记录)
        $hasNewRecord = rand(1, 100) <= 30;
        
        if ($hasNewRecord) {
            // 随机生成 1-3 条新记录
            $recordCount = rand(1, min(3, $limit));
            
            for ($i = 0; $i < $recordCount; $i++) {
                // 随机选择类型
                if ($type === 'all') {
                    $recordType = rand(0, 1) ? 'recharge' : 'withdraw';
                } else {
                    $recordType = $type;
                }
                
                $records[] = [
                    'id' => 'record_' . time() . '_' . $i . '_' . rand(1000, 9999),
                    'type' => $recordType,
                    'amount' => $amounts[array_rand($amounts)],
                    'userName' => $userNames[array_rand($userNames)],
                    'userId' => 'user_' . rand(1000, 9999),
                    'createTime' => date('Y-m-d H:i:s'),
                    'timestamp' => time() * 1000,
                    'status' => 'completed',
                    'remark' => $recordType === 'recharge' ? '在线充值' : '银行卡提现'
                ];
            }
        }
        
        return $records;
    }

    /**
     * 测试接口 - 用于前端调试
     * @return \think\Response
     */
    public function test()
    {
        // 生成一条测试记录
        $testRecord = [
            'id' => 'test_record_' . time(),
            'type' => 'recharge',
            'amount' => 1000,
            'userName' => '测试用户',
            'userId' => 'test_user_001',
            'createTime' => date('Y-m-d H:i:s'),
            'timestamp' => time() * 1000,
            'status' => 'completed',
            'remark' => '测试充值'
        ];

        return json([
            'code' => 200,
            'message' => '测试接口调用成功',
            'data' => [
                'records' => [$testRecord],
                'total' => 1,
                'serverTime' => date('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * 手动触发通知 - 用于测试
     * @return \think\Response
     */
    public function triggerNotification()
    {
        $type = $this->request->param('type', 'recharge'); // recharge 或 withdraw
        
        $mockRecord = [
            'id' => 'manual_' . time() . '_' . rand(1000, 9999),
            'type' => $type,
            'amount' => rand(100, 5000),
            'userName' => '手动测试用户',
            'userId' => 'manual_test_' . rand(100, 999),
            'createTime' => date('Y-m-d H:i:s'),
            'timestamp' => time() * 1000,
            'status' => 'completed',
            'remark' => $type === 'recharge' ? '手动测试充值' : '手动测试提现'
        ];

        return json([
            'code' => 200,
            'message' => '手动触发通知成功',
            'data' => [
                'records' => [$mockRecord],
                'total' => 1
            ]
        ]);
    }
}