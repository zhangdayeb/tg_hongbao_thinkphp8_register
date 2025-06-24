<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\RemoteLoginService;
use app\service\InviteCodeService;
use app\service\AutoLoginService;
use app\common\model\RemoteRegisterLog;
use think\facade\Log;
use think\Response;

/**
 * API控制器
 * 提供内部API调用接口
 */
class ApiController extends BaseController
{
    private RemoteLoginService $remoteLoginService;
    private InviteCodeService $inviteCodeService;
    private AutoLoginService $autoLoginService;

    public function __construct()
    {
        parent::__construct();
        $this->remoteLoginService = new RemoteLoginService();
        $this->inviteCodeService = new InviteCodeService();
        $this->autoLoginService = new AutoLoginService();
    }

    /**
     * 远程登录API
     * POST /api/remote-login
     * @return Response
     */
    public function remoteLogin(): Response
    {
        try {
            // 验证请求方法
            if (!$this->request->isPost()) {
                return $this->error('请求方法错误', 405);
            }

            // 获取参数
            $userId = $this->request->param('user_id/d', 0);
            $account = $this->request->param('account', '');
            $password = $this->request->param('password', '');

            // 如果提供了用户ID，从远程注册记录获取账号密码
            if (!empty($userId)) {
                $remoteAccount = RemoteRegisterLog::getRemoteAccountByUserId($userId);
                if ($remoteAccount) {
                    $account = $remoteAccount['remote_account'];
                    $password = $remoteAccount['remote_password'];
                }
            }

            if (empty($account) || empty($password)) {
                return $this->error('账号和密码不能为空');
            }

            // 执行登录
            $result = $this->remoteLoginService->login($account, $password);

            if ($result['success']) {
                Log::info('API远程登录成功', [
                    'user_id' => $userId,
                    'account' => $account,
                    'token' => substr($result['token'], 0, 10) . '...'
                ]);

                return $this->success([
                    'user_id' => $userId,
                    'account' => $account,
                    'token' => $result['token'],
                    'auto_login_url' => $this->autoLoginService->generateUrl($result['token'])
                ], '登录成功');
            } else {
                return $this->error($result['message']);
            }

        } catch (\Exception $e) {
            Log::error('API远程登录异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('登录过程中发生异常');
        }
    }

    /**
     * 获取邀请码API
     * POST /api/get-invite-code
     * @return Response
     */
    public function getInviteCode(): Response
    {
        try {
            // 验证请求方法
            if (!$this->request->isPost()) {
                return $this->error('请求方法错误', 405);
            }

            // 获取参数
            $userId = $this->request->param('user_id/d', 0);
            $token = $this->request->param('token', '');

            if (empty($userId)) {
                return $this->error('用户ID不能为空');
            }

            // 如果没有提供token，尝试先登录获取token
            if (empty($token)) {
                $loginResult = $this->autoLoginForUser($userId);
                if (!$loginResult['success']) {
                    return $this->error('自动登录失败: ' . $loginResult['message']);
                }
                $token = $loginResult['token'];
            }

            // 获取邀请码
            $result = $this->inviteCodeService->getInviteCode($token);

            if ($result['success']) {
                // 更新到数据库
                $updateResult = $this->inviteCodeService->updateInviteCode($userId, $result['invite_code']);
                
                if ($updateResult['success']) {
                    Log::info('获取邀请码成功', [
                        'user_id' => $userId,
                        'invite_code' => $result['invite_code']
                    ]);

                    return $this->success([
                        'user_id' => $userId,
                        'invite_code' => $result['invite_code'],
                        'updated' => true,
                        'token_used' => substr($token, 0, 10) . '...'
                    ], '邀请码获取成功');
                } else {
                    return $this->error('邀请码获取成功但保存失败: ' . $updateResult['message']);
                }
            } else {
                return $this->error($result['message']);
            }

        } catch (\Exception $e) {
            Log::error('API获取邀请码异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('获取邀请码过程中发生异常');
        }
    }

    /**
     * 验证token有效性API
     * POST /api/validate-token
     * @return Response
     */
    public function validateToken(): Response
    {
        try {
            // 验证请求方法
            if (!$this->request->isPost()) {
                return $this->error('请求方法错误', 405);
            }

            // 获取参数
            $token = $this->request->param('token', '');

            if (empty($token)) {
                return $this->error('token不能为空');
            }

            // 验证token
            $isValid = $this->autoLoginService->validateToken($token);

            return $this->success([
                'token' => substr($token, 0, 10) . '...',
                'is_valid' => $isValid,
                'auto_login_url' => $isValid ? $this->autoLoginService->generateUrl($token) : null
            ], $isValid ? 'token有效' : 'token无效');

        } catch (\Exception $e) {
            Log::error('API验证token异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('验证token过程中发生异常');
        }
    }

    /**
     * 获取用户注册状态API
     * GET /api/registration-status
     * @return Response
     */
    public function getRegistrationStatus(): Response
    {
        try {
            $userId = $this->request->param('user_id/d', 0);

            if (empty($userId)) {
                return $this->error('用户ID不能为空');
            }

            // 检查远程注册状态
            $isRegistered = RemoteRegisterLog::isUserRegistered($userId);
            $remoteAccount = null;
            $registerTime = null;

            if ($isRegistered) {
                $remoteAccountInfo = RemoteRegisterLog::getRemoteAccountByUserId($userId);
                if ($remoteAccountInfo) {
                    $remoteAccount = $remoteAccountInfo['remote_account'];
                    $registerTime = $remoteAccountInfo['register_time'];
                }
            }

            return $this->success([
                'user_id' => $userId,
                'is_registered' => $isRegistered,
                'remote_account' => $remoteAccount,
                'register_time' => $registerTime,
                'can_login' => $isRegistered
            ], '获取注册状态成功');

        } catch (\Exception $e) {
            Log::error('API获取注册状态异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('获取注册状态过程中发生异常');
        }
    }

    /**
     * 批量获取邀请码API
     * POST /api/batch-get-invite-codes
     * @return Response
     */
    public function batchGetInviteCodes(): Response
    {
        try {
            // 验证请求方法
            if (!$this->request->isPost()) {
                return $this->error('请求方法错误', 405);
            }

            // 获取参数
            $userIds = $this->request->param('user_ids', []);
            
            if (empty($userIds) || !is_array($userIds)) {
                return $this->error('用户ID列表不能为空');
            }

            $results = [];
            $successCount = 0;
            $failCount = 0;

            foreach ($userIds as $userId) {
                if (empty($userId)) {
                    continue;
                }

                try {
                    // 获取用户远程账号信息并登录
                    $loginResult = $this->autoLoginForUser((int)$userId);
                    if (!$loginResult['success']) {
                        $results[] = [
                            'user_id' => $userId,
                            'success' => false,
                            'message' => '登录失败: ' . $loginResult['message']
                        ];
                        $failCount++;
                        continue;
                    }

                    // 获取邀请码
                    $inviteResult = $this->inviteCodeService->getInviteCode($loginResult['token']);
                    if ($inviteResult['success']) {
                        // 更新到数据库
                        $updateResult = $this->inviteCodeService->updateInviteCode((int)$userId, $inviteResult['invite_code']);
                        
                        $results[] = [
                            'user_id' => $userId,
                            'success' => $updateResult['success'],
                            'invite_code' => $inviteResult['invite_code'],
                            'message' => $updateResult['message']
                        ];

                        if ($updateResult['success']) {
                            $successCount++;
                        } else {
                            $failCount++;
                        }
                    } else {
                        $results[] = [
                            'user_id' => $userId,
                            'success' => false,
                            'message' => '获取邀请码失败: ' . $inviteResult['message']
                        ];
                        $failCount++;
                    }

                    // 避免请求过于频繁
                    usleep(500000); // 0.5秒延迟

                } catch (\Exception $e) {
                    $results[] = [
                        'user_id' => $userId,
                        'success' => false,
                        'message' => '处理异常: ' . $e->getMessage()
                    ];
                    $failCount++;
                }
            }

            return $this->success([
                'total' => count($userIds),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'results' => $results
            ], '批量获取邀请码完成');

        } catch (\Exception $e) {
            Log::error('API批量获取邀请码异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('批量获取邀请码过程中发生异常');
        }
    }

    /**
     * 为用户自动登录获取token
     * @param int $userId 用户ID
     * @return array
     */
    private function autoLoginForUser(int $userId): array
    {
        try {
            // 获取远程账号信息
            $remoteAccount = RemoteRegisterLog::getRemoteAccountByUserId($userId);
            if (!$remoteAccount) {
                return [
                    'success' => false,
                    'message' => '用户未完成远程注册'
                ];
            }

            // 执行登录
            $loginResult = $this->remoteLoginService->login(
                $remoteAccount['remote_account'],
                $remoteAccount['remote_password']
            );

            if ($loginResult['success']) {
                return [
                    'success' => true,
                    'token' => $loginResult['token']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $loginResult['message']
                ];
            }

        } catch (\Exception $e) {
            Log::error('自动登录获取token异常', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '自动登录异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 返回成功响应
     * @param array $data 数据
     * @param string $message 消息
     * @return Response
     */
    private function success(array $data = [], string $message = '操作成功'): Response
    {
        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 返回错误响应
     * @param string $message 错误信息
     * @param int $code 错误代码
     * @return Response
     */
    private function error(string $message, int $code = 400): Response
    {
        return response()->json([
            'code' => $code,
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], $code);
    }
}