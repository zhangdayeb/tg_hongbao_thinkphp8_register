<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\RemoteLoginService;
use app\service\AutoLoginService;
use app\common\model\User;
use app\common\model\RemoteRegisterLog;
use think\facade\Log;
use think\Response;

/**
 * 登录控制器
 * 处理用户自动登录流程
 */
class LoginController extends BaseController
{
    private RemoteLoginService $remoteLoginService;
    private AutoLoginService $autoLoginService;

    public function __construct()
    {
        parent::__construct();
        $this->remoteLoginService = new RemoteLoginService();
        $this->autoLoginService = new AutoLoginService();
    }

    /**
     * 主入口方法 - 处理用户访问 /login?user_id=1
     * @return Response
     */
    public function index(): Response
    {
        try {
            // 获取用户ID参数
            $userId = $this->request->param('user_id/d', 0);
            
            if (empty($userId)) {
                return $this->error('缺少用户ID参数');
            }

            // 执行自动登录
            $result = $this->autoLogin($userId);
            
            if ($result['success']) {
                // 登录成功，重定向到免登录地址
                return redirect($result['auto_login_url']);
            } else {
                // 登录失败，返回错误信息
                return $this->error($result['message']);
            }

        } catch (\Exception $e) {
            Log::error('登录控制器异常: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 执行自动登录流程
     * @param int $userId 用户ID
     * @return array
     */
    private function autoLogin(int $userId): array
    {
        try {
            // 1. 验证用户是否存在且已远程注册
            $remoteAccount = $this->getRemoteAccountInfo($userId);
            if (!$remoteAccount) {
                return [
                    'success' => false,
                    'message' => '用户不存在或未完成远程注册'
                ];
            }

            // 2. 准备登录信息（从远程注册记录获取）
            $account = $remoteAccount['remote_account'];
            $password = $remoteAccount['remote_password']; // 明文密码
            
            if (empty($account) || empty($password)) {
                return [
                    'success' => false,
                    'message' => '远程账号或密码为空'
                ];
            }

            // 3. 执行远程登录
            $loginResult = $this->remoteLoginService->login($account, $password);
            
            if (!$loginResult['success']) {
                return [
                    'success' => false,
                    'message' => '远程登录失败: ' . $loginResult['message']
                ];
            }

            // 4. 生成免登录地址
            $autoLoginUrl = $this->autoLoginService->generateUrl($loginResult['token']);
            
            // 5. 更新用户最后活动时间
            $this->updateUserLastActivity($userId, $loginResult['token']);

            Log::info('用户自动登录成功', [
                'user_id' => $userId,
                'remote_account' => $account,
                'token' => substr($loginResult['token'], 0, 10) . '...'
            ]);

            return [
                'success' => true,
                'message' => '登录成功',
                'token' => $loginResult['token'],
                'auto_login_url' => $autoLoginUrl
            ];

        } catch (\Exception $e) {
            Log::error('自动登录异常', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'success' => false,
                'message' => '登录过程中发生异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取远程账号信息
     * @param int $userId 用户ID
     * @return array|null
     */
    private function getRemoteAccountInfo(int $userId): ?array
    {
        try {
            // 1. 首先检查用户是否存在且状态正常
            $user = User::where('id', $userId)
                ->where('status', 1) // 状态正常
                ->field('id,user_name,status')
                ->find();

            if (!$user) {
                Log::warning('用户不存在或状态异常', ['user_id' => $userId]);
                return null;
            }

            // 2. 从远程注册日志获取远程账号密码
            $remoteAccount = RemoteRegisterLog::getRemoteAccountByUserId($userId);
            
            if (!$remoteAccount) {
                Log::warning('用户未找到远程注册记录', [
                    'user_id' => $userId,
                    'user_name' => $user['user_name']
                ]);
                return null;
            }

            Log::info('获取远程账号信息成功', [
                'user_id' => $userId,
                'local_user_name' => $user['user_name'],
                'remote_account' => $remoteAccount['remote_account']
            ]);

            return $remoteAccount;

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
     * 更新用户最后活动时间并记录token
     * @param int $userId 用户ID
     * @param string $token 登录token
     */
    private function updateUserLastActivity(int $userId, string $token): void
    {
        try {
            User::where('id', $userId)->update([
                'last_activity_at' => date('Y-m-d H:i:s'),
                'remarks' => 'last_token:' . $token . '|updated:' . date('Y-m-d H:i:s')
            ]);

            Log::info('用户活动时间更新成功', [
                'user_id' => $userId,
                'update_time' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            Log::error('更新用户活动时间失败', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 检查用户注册状态
     * @param int $userId 用户ID
     * @return array
     */
    public function checkRegistrationStatus(int $userId): array
    {
        try {
            // 检查用户是否存在
            $user = User::where('id', $userId)->field('id,user_name,status')->find();
            if (!$user) {
                return [
                    'success' => false,
                    'message' => '用户不存在'
                ];
            }

            // 检查远程注册状态
            $isRegistered = RemoteRegisterLog::isUserRegistered($userId);
            $remoteAccount = null;
            
            if ($isRegistered) {
                $remoteAccountInfo = RemoteRegisterLog::getRemoteAccountByUserId($userId);
                $remoteAccount = $remoteAccountInfo['remote_account'] ?? null;
            }

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'local_user_name' => $user['user_name'],
                    'user_status' => $user['status'],
                    'is_remote_registered' => $isRegistered,
                    'remote_account' => $remoteAccount,
                    'can_auto_login' => $isRegistered && $user['status'] == 1
                ]
            ];

        } catch (\Exception $e) {
            Log::error('检查用户注册状态异常', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '检查状态时发生异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取用户登录历史
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @return array
     */
    public function getLoginHistory(int $userId, int $limit = 10): array
    {
        try {
            // 这里可以从日志表或其他地方获取登录历史
            // 暂时从用户表的remarks字段解析
            $user = User::where('id', $userId)
                ->field('last_activity_at,remarks')
                ->find();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => '用户不存在'
                ];
            }

            // 解析最后登录信息
            $lastLoginInfo = $this->parseLastLoginInfo($user['remarks']);

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'last_activity_at' => $user['last_activity_at'],
                    'last_login_info' => $lastLoginInfo,
                    'history' => [] // 可以扩展为完整的登录历史
                ]
            ];

        } catch (\Exception $e) {
            Log::error('获取用户登录历史异常', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '获取登录历史时发生异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 解析最后登录信息
     * @param string $remarks 备注内容
     * @return array
     */
    private function parseLastLoginInfo(string $remarks): array
    {
        if (empty($remarks)) {
            return [];
        }

        $info = [];
        if (preg_match('/last_token:([^|]+)/', $remarks, $matches)) {
            $info['last_token'] = substr($matches[1], 0, 10) . '...';
        }
        if (preg_match('/updated:([^|]+)/', $remarks, $matches)) {
            $info['updated_at'] = $matches[1];
        }

        return $info;
    }

    /**
     * 返回错误响应
     * @param string $message 错误信息
     * @return Response
     */
    private function error(string $message): Response
    {
        return response()->json([
            'code' => 400,
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], 400);
    }
}