<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\RemoteLoginService;
use app\service\InviteCodeService;
use app\service\AutoLoginService;
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
            $account = $this->request->param('account', '');
            $password = $this->request->param('password', '');

            if (empty($account) || empty($password)) {
                return $this->error('账号和密码不能为空');
            }

            // 执行登录
            $result = $this->remoteLoginService->login($account, $password);

            if ($result['success']) {
                Log::info('API远程登录成功', [
                    'account' => $account,
                    'token' => substr($result['token'], 0, 10) . '...'
                ]);

                return $this->success([
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

            if (empty($userId) || empty($token)) {
                return $this->error('用户ID和token不能为空');
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
                        'invite_code' => $result['invite_code'],
                        'updated' => true
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
                'token' => $token,
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
     * 获取登录状态API
     * GET /api/login-status
     * @return Response
     */
    public function loginStatus(): Response
    {
        try {
            $userId = $this->request->param('user_id/d', 0);

            if (empty($userId)) {
                return $this->error('用户ID不能为空');
            }

            // 获取用户信息（包含最后活动时间和token信息）
            $user = \app\common\model\User::where('id', $userId)
                ->field('id,user_name,last_activity_at,remarks')
                ->find();

            if (!$user) {
                return $this->error('用户不存在');
            }

            // 解析token信息
            $tokenInfo = $this->parseTokenFromRemarks($user['remarks']);

            return $this->success([
                'user_id' => $user['id'],
                'user_name' => $user['user_name'],
                'last_activity_at' => $user['last_activity_at'],
                'has_token' => !empty($tokenInfo['token']),
                'token_updated_at' => $tokenInfo['updated_at'] ?? null
            ], '获取登录状态成功');

        } catch (\Exception $e) {
            Log::error('API获取登录状态异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('获取登录状态过程中发生异常');
        }
    }

    /**
     * 从备注字段解析token信息
     * @param string $remarks 备注内容
     * @return array
     */
    private function parseTokenFromRemarks(string $remarks): array
    {
        if (empty($remarks)) {
            return [];
        }

        $result = [];
        if (preg_match('/last_token:([^|]+)/', $remarks, $matches)) {
            $result['token'] = $matches[1];
        }
        if (preg_match('/updated:([^|]+)/', $remarks, $matches)) {
            $result['updated_at'] = $matches[1];
        }

        return $result;
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