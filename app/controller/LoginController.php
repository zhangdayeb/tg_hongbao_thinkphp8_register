<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\RemoteLoginService;
use app\service\AutoLoginService;
use app\common\model\User;
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
            // 1. 验证用户是否存在
            $user = $this->getUserInfo($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => '用户不存在或已被禁用'
                ];
            }

            // 2. 准备登录信息
            $account = $user['user_name'];
            $password = $this->decodePassword($user['pwd']);
            
            if (empty($account) || empty($password)) {
                return [
                    'success' => false,
                    'message' => '用户账号或密码为空'
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
                'account' => $account,
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
     * 获取用户信息
     * @param int $userId 用户ID
     * @return array|null
     */
    private function getUserInfo(int $userId): ?array
    {
        $user = User::where('id', $userId)
            ->where('status', 1) // 状态正常
            ->field('id,user_name,pwd,last_activity_at,remarks')
            ->find();

        return $user ? $user->toArray() : null;
    }

    /**
     * 解码密码（base64解码）
     * @param string $encodedPassword 编码后的密码
     * @return string
     */
    private function decodePassword(string $encodedPassword): string
    {
        try {
            $decoded = base64_decode($encodedPassword);
            return $decoded !== false ? $decoded : '';
        } catch (\Exception $e) {
            Log::error('密码解码失败', [
                'encoded_password' => $encodedPassword,
                'error' => $e->getMessage()
            ]);
            return '';
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
        } catch (\Exception $e) {
            Log::error('更新用户活动时间失败', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
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