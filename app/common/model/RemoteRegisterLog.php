<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;
use think\facade\Log;

/**
 * 远程注册日志模型
 * 对应数据表: ntp_remote_register_log
 */
class RemoteRegisterLog extends Model
{
    // 设置表名
    protected $name = 'remote_register_log';
    
    // 设置主键
    protected $pk = 'id';
    
    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'local_user_id'   => 'int',
        'remote_account'  => 'string',
        'remote_password' => 'string',
        'register_status' => 'int',
        'register_time'   => 'datetime',
        'error_message'   => 'string',
        'retry_count'     => 'int'
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = false;

    // 状态常量
    const STATUS_PENDING = 0;  // 待注册
    const STATUS_SUCCESS = 1;  // 注册成功
    const STATUS_FAILED = 2;   // 注册失败

    /**
     * 根据本地用户ID获取远程账号信息
     * @param int $userId 本地用户ID
     * @return array|null
     */
    public static function getRemoteAccountByUserId(int $userId): ?array
    {
        try {
            $remoteLog = self::where('local_user_id', $userId)
                ->where('register_status', self::STATUS_SUCCESS)
                ->field('id,local_user_id,remote_account,remote_password,register_time')
                ->order('id', 'desc') // 获取最新的成功记录
                ->find();

            if (!$remoteLog) {
                Log::info('用户未找到远程注册记录', ['user_id' => $userId]);
                return null;
            }

            Log::info('获取远程账号信息成功', [
                'user_id' => $userId,
                'remote_account' => $remoteLog['remote_account']
            ]);

            return $remoteLog->toArray();

        } catch (\Exception $e) {
            Log::error('获取远程账号信息异常', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }

    /**
     * 检查用户是否已远程注册成功
     * @param int $userId 本地用户ID
     * @return bool
     */
    public static function isUserRegistered(int $userId): bool
    {
        try {
            $count = self::where('local_user_id', $userId)
                ->where('register_status', self::STATUS_SUCCESS)
                ->count();

            return $count > 0;

        } catch (\Exception $e) {
            Log::error('检查用户注册状态异常', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取注册失败的用户列表
     * @param int $limit 限制数量
     * @return array
     */
    public static function getFailedUsers(int $limit = 100): array
    {
        try {
            return self::where('register_status', self::STATUS_FAILED)
                ->field('id,local_user_id,remote_account,error_message,register_time,retry_count')
                ->order('register_time', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();

        } catch (\Exception $e) {
            Log::error('获取注册失败用户列表异常', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取待重试的用户列表
     * @param int $maxRetryCount 最大重试次数
     * @param int $limit 限制数量
     * @return array
     */
    public static function getRetryUsers(int $maxRetryCount = 3, int $limit = 50): array
    {
        try {
            return self::where('register_status', self::STATUS_FAILED)
                ->where('retry_count', '<', $maxRetryCount)
                ->field('id,local_user_id,remote_account,remote_password,error_message,retry_count')
                ->order('register_time', 'asc')
                ->limit($limit)
                ->select()
                ->toArray();

        } catch (\Exception $e) {
            Log::error('获取待重试用户列表异常', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 更新重试次数
     * @param int $id 记录ID
     * @return bool
     */
    public static function incrementRetryCount(int $id): bool
    {
        try {
            $result = self::where('id', $id)->inc('retry_count')->update();
            return $result > 0;

        } catch (\Exception $e) {
            Log::error('更新重试次数异常', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取用户注册统计信息
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public static function getRegistrationStats(string $startDate = '', string $endDate = ''): array
    {
        try {
            $query = self::field('register_status, count(*) as count');

            if (!empty($startDate)) {
                $query->where('register_time', '>=', $startDate);
            }
            if (!empty($endDate)) {
                $query->where('register_time', '<=', $endDate);
            }

            $stats = $query->group('register_status')->select()->toArray();

            // 格式化统计结果
            $result = [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'pending' => 0
            ];

            foreach ($stats as $stat) {
                $result['total'] += $stat['count'];
                switch ($stat['register_status']) {
                    case self::STATUS_SUCCESS:
                        $result['success'] = $stat['count'];
                        break;
                    case self::STATUS_FAILED:
                        $result['failed'] = $stat['count'];
                        break;
                    case self::STATUS_PENDING:
                        $result['pending'] = $stat['count'];
                        break;
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('获取注册统计信息异常', [
                'error' => $e->getMessage()
            ]);
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'pending' => 0
            ];
        }
    }

    /**
     * 根据远程账号查找记录
     * @param string $remoteAccount 远程账号
     * @return array|null
     */
    public static function findByRemoteAccount(string $remoteAccount): ?array
    {
        try {
            $record = self::where('remote_account', $remoteAccount)
                ->where('register_status', self::STATUS_SUCCESS)
                ->field('id,local_user_id,remote_account,register_time')
                ->find();

            return $record ? $record->toArray() : null;

        } catch (\Exception $e) {
            Log::error('根据远程账号查找记录异常', [
                'remote_account' => $remoteAccount,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 批量获取用户的远程账号信息
     * @param array $userIds 用户ID数组
     * @return array
     */
    public static function batchGetRemoteAccounts(array $userIds): array
    {
        try {
            if (empty($userIds)) {
                return [];
            }

            return self::where('local_user_id', 'in', $userIds)
                ->where('register_status', self::STATUS_SUCCESS)
                ->field('local_user_id,remote_account,remote_password,register_time')
                ->order('local_user_id,id desc') // 每个用户取最新记录
                ->select()
                ->toArray();

        } catch (\Exception $e) {
            Log::error('批量获取远程账号信息异常', [
                'user_ids' => $userIds,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 清理过期的失败记录
     * @param int $days 保留天数
     * @return int 删除的记录数
     */
    public static function cleanExpiredFailedRecords(int $days = 30): int
    {
        try {
            $expireDate = date('Y-m-d H:i:s', time() - $days * 24 * 3600);
            
            $count = self::where('register_status', self::STATUS_FAILED)
                ->where('register_time', '<', $expireDate)
                ->count();

            if ($count > 0) {
                self::where('register_status', self::STATUS_FAILED)
                    ->where('register_time', '<', $expireDate)
                    ->delete();

                Log::info('清理过期失败记录', [
                    'deleted_count' => $count,
                    'expire_date' => $expireDate
                ]);
            }

            return $count;

        } catch (\Exception $e) {
            Log::error('清理过期失败记录异常', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 获取状态描述
     * @param int $status 状态值
     * @return string
     */
    public static function getStatusText(int $status): string
    {
        $statusMap = [
            self::STATUS_PENDING => '待注册',
            self::STATUS_SUCCESS => '注册成功',
            self::STATUS_FAILED => '注册失败'
        ];

        return $statusMap[$status] ?? '未知状态';
    }
}