<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\RemoteLoginService;
use app\service\InviteCodeService;
use app\common\model\User;
use app\common\model\RemoteRegisterLog;
use think\facade\Log;
use think\Response;

/**
 * 登录控制器
 * 处理用户自动登录流程和调试功能
 */
class LoginController extends BaseController
{
    private RemoteLoginService $remoteLoginService;
    private InviteCodeService $inviteCodeService;

    // 免登录基础URL配置
    private string $AUTO_LOGIN_BASE_URL = env('WEB_URL', '');
    private const DEFAULT_SOURCE = 'tg';

    protected function initialize()
    {
        parent::initialize();
        $this->remoteLoginService = new RemoteLoginService();
        $this->inviteCodeService = new InviteCodeService();
    }

/**
     * 主入口方法 - 处理用户访问 /login?user_id=1
     * 自动登录并重定向到免登录地址
     * @return Response
     */
    public function index(): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid('main_login_');
        
        try {
            // 获取用户ID参数
            $userId = $this->request->param('user_id/d', 0);
            
            if (empty($userId)) {
                Log::warning('主登录入口缺少用户ID', ['request_id' => $requestId]);
                return $this->error('缺少用户ID参数');
            }

            Log::info('开始主登录流程', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ]);

            // 执行自动登录
            $result = $this->autoLogin($userId, $requestId);
            
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($result['success']) {
                Log::info('主登录流程成功，准备JavaScript重定向', [
                    'request_id' => $requestId,
                    'user_id' => $userId,
                    'auto_login_url' => $result['auto_login_url'],
                    'total_time' => $totalTime . 'ms'
                ]);
                
                // 不用 redirect()，改用 JavaScript 重定向
                $autoLoginUrl = $result['auto_login_url'];
                
                $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>登录跳转中...</title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
</head>
<body>
    <div style='text-align:center; padding:50px; font-family: Arial;'>
        <h3>🔄 登录成功，正在跳转...</h3>
        <p>如果没有自动跳转，请<a href='{$autoLoginUrl}'>点击这里</a></p>
    </div>
    <script>
        window.location.href = '{$autoLoginUrl}';
    </script>
</body>
</html>";
                
                return response($html)->header([
                    'Content-Type' => 'text/html; charset=utf-8'
                ]);
            } else {
                Log::error('主登录流程失败', [
                    'request_id' => $requestId,
                    'user_id' => $userId,
                    'error' => $result['message'],
                    'total_time' => $totalTime . 'ms'
                ]);
                
                // 登录失败，返回错误信息
                return $this->error($result['message']);
            }

        } catch (\Exception $e) {
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error('主登录控制器异常', [
                'request_id' => $requestId,
                'user_id' => $userId ?? 0,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_time' => $totalTime . 'ms',
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 测试登录流程 - 处理 /testlogin?user_id=1
     * 返回详细的登录步骤信息和token，用于调试
     * @return Response
     */
    public function testLogin(): Response
    {
        try {
            $userId = $this->request->param('user_id/d', 0);
            
            if (empty($userId)) {
                return $this->error('缺少用户ID参数');
            }

            $steps = [];
            $startTime = microtime(true);

            // 步骤1: 检查用户状态
            $steps[] = [
                'step' => 1,
                'name' => '检查用户状态',
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ];

            $user = User::where('id', $userId)
                ->where('status', 1)
                ->field('id,user_name,status')
                ->find();

            if (!$user) {
                $steps[0]['result'] = 'FAILED';
                $steps[0]['message'] = '用户不存在或状态异常';
                $steps[0]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);
                
                return $this->success([
                    'user_id' => $userId,
                    'final_result' => 'FAILED',
                    'total_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                    'steps' => $steps
                ], '测试登录流程完成');
            }

            $steps[0]['result'] = 'SUCCESS';
            $steps[0]['data'] = ['user_name' => $user['user_name'], 'status' => $user['status']];
            $steps[0]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);

            // 步骤2: 获取远程账号信息
            $steps[] = [
                'step' => 2,
                'name' => '获取远程账号信息',
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ];

            $remoteAccount = RemoteRegisterLog::getRemoteAccountByUserId($userId);
            if (!$remoteAccount) {
                $steps[1]['result'] = 'FAILED';
                $steps[1]['message'] = '用户未完成远程注册';
                $steps[1]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);
                
                return $this->success([
                    'user_id' => $userId,
                    'final_result' => 'FAILED',
                    'total_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                    'steps' => $steps
                ], '测试登录流程完成');
            }

            $steps[1]['result'] = 'SUCCESS';
            $steps[1]['data'] = [
                'remote_account' => $remoteAccount['remote_account'],
                'has_password' => !empty($remoteAccount['remote_password'])
            ];
            $steps[1]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);

            // 步骤3: 执行远程登录
            $steps[] = [
                'step' => 3,
                'name' => '执行远程登录',
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ];

            $loginResult = $this->remoteLoginService->login(
                $remoteAccount['remote_account'],
                $remoteAccount['remote_password']
            );

            if (!$loginResult['success']) {
                $steps[2]['result'] = 'FAILED';
                $steps[2]['message'] = $loginResult['message'];
                $steps[2]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);
                
                return $this->success([
                    'user_id' => $userId,
                    'final_result' => 'FAILED',
                    'total_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                    'steps' => $steps
                ], '测试登录流程完成');
            }

            $steps[2]['result'] = 'SUCCESS';
            $steps[2]['data'] = [
                'token' => substr($loginResult['token'], 0, 10) . '...',
                'token_length' => strlen($loginResult['token'])
            ];
            $steps[2]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);

            // 步骤4: 生成免登录地址
            $steps[] = [
                'step' => 4,
                'name' => '生成免登录地址',
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ];

            $autoLoginUrl = $this->generateAutoLoginUrl($loginResult['token']);
            
            $steps[3]['result'] = 'SUCCESS';
            $steps[3]['data'] = ['auto_login_url' => $autoLoginUrl];
            $steps[3]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);

            // 返回完整测试结果
            return $this->success([
                'user_id' => $userId,
                'user_name' => $user['user_name'],
                'remote_account' => $remoteAccount['remote_account'],
                'final_result' => 'SUCCESS',
                'token' => $loginResult['token'],
                'auto_login_url' => $autoLoginUrl,
                'total_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'steps' => $steps
            ], '测试登录流程完成');

        } catch (\Exception $e) {
            Log::error('测试登录流程异常', [
                'user_id' => $userId ?? 0,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('测试登录流程异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试获取邀请码流程 - 处理 /getcode?user_id=1
     * 包含自动登录+获取邀请码+更新数据库，返回详细步骤信息
     * @return Response
     */
    public function getCode(): Response
    {
        try {
            $userId = $this->request->param('user_id/d', 0);
            
            if (empty($userId)) {
                return $this->error('缺少用户ID参数');
            }

            $steps = [];
            $startTime = microtime(true);
            $requestId = uniqid('getcode_');

            // 步骤1: 执行自动登录获取token
            $steps[] = [
                'step' => 1,
                'name' => '执行自动登录获取token',
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ];

            $loginResult = $this->autoLogin($userId, $requestId);
            if (!$loginResult['success']) {
                $steps[0]['result'] = 'FAILED';
                $steps[0]['message'] = $loginResult['message'];
                $steps[0]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);
                
                return $this->success([
                    'user_id' => $userId,
                    'final_result' => 'FAILED',
                    'total_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                    'steps' => $steps
                ], '测试获取邀请码流程完成');
            }

            $steps[0]['result'] = 'SUCCESS';
            $steps[0]['data'] = [
                'token' => substr($loginResult['token'], 0, 10) . '...',
                'auto_login_url' => $loginResult['auto_login_url']
            ];
            $steps[0]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);

            // 步骤2: 获取邀请码
            $steps[] = [
                'step' => 2,
                'name' => '获取邀请码',
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ];

            $inviteResult = $this->inviteCodeService->getInviteCode($loginResult['token']);
            if (!$inviteResult['success']) {
                $steps[1]['result'] = 'FAILED';
                $steps[1]['message'] = $inviteResult['message'];
                $steps[1]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);
                
                return $this->success([
                    'user_id' => $userId,
                    'final_result' => 'FAILED',
                    'total_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                    'steps' => $steps
                ], '测试获取邀请码流程完成');
            }

            $steps[1]['result'] = 'SUCCESS';
            $steps[1]['data'] = ['invite_code' => $inviteResult['invite_code']];
            $steps[1]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);

            // 步骤3: 更新邀请码到数据库
            $steps[] = [
                'step' => 3,
                'name' => '更新邀请码到数据库',
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ];

            $updateResult = $this->inviteCodeService->updateInviteCode($userId, $inviteResult['invite_code']);
            
            $steps[2]['result'] = $updateResult['success'] ? 'SUCCESS' : 'FAILED';
            $steps[2]['message'] = $updateResult['message'];
            $steps[2]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);

            // 步骤4: 获取更新后的用户信息
            $steps[] = [
                'step' => 4,
                'name' => '获取更新后的用户信息',
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ];

            $user = User::where('id', $userId)
                ->field('id,user_name,game_invitation_code,last_activity_at')
                ->find();

            $steps[3]['result'] = 'SUCCESS';
            $steps[3]['data'] = [
                'user_name' => $user['user_name'],
                'current_invite_code' => $user['game_invitation_code'],
                'last_activity_at' => $user['last_activity_at']
            ];
            $steps[3]['end_time'] = date('H:i:s.') . substr(microtime(), 2, 3);

            // 返回完整测试结果
            return $this->success([
                'user_id' => $userId,
                'user_name' => $user['user_name'],
                'final_result' => $updateResult['success'] ? 'SUCCESS' : 'PARTIAL_SUCCESS',
                'invite_code' => $inviteResult['invite_code'],
                'database_updated' => $updateResult['success'],
                'current_database_code' => $user['game_invitation_code'],
                'token_used' => substr($loginResult['token'], 0, 10) . '...',
                'auto_login_url' => $loginResult['auto_login_url'],
                'total_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'steps' => $steps
            ], '测试获取邀请码流程完成');

        } catch (\Exception $e) {
            Log::error('测试获取邀请码流程异常', [
                'user_id' => $userId ?? 0,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return $this->error('测试获取邀请码流程异常: ' . $e->getMessage());
        }
    }

    /**
     * 执行自动登录流程（私有方法）
     * @param int $userId 用户ID
     * @param string $requestId 请求ID
     * @return array
     */
    private function autoLogin(int $userId, string $requestId = ''): array
    {
        if (empty($requestId)) {
            $requestId = uniqid('auto_login_');
        }
        
        try {
            Log::info('开始自动登录流程', [
                'request_id' => $requestId,
                'user_id' => $userId
            ]);

            // 1. 检查用户是否存在且状态正常
            $user = User::where('id', $userId)
                ->where('status', 1)
                ->field('id,user_name,status')
                ->find();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => '用户不存在或状态异常'
                ];
            }

            // 2. 从远程注册日志获取远程账号密码
            $remoteAccount = RemoteRegisterLog::getRemoteAccountByUserId($userId);
            if (!$remoteAccount) {
                return [
                    'success' => false,
                    'message' => '用户未完成远程注册(首次登录自动注册)，请重新登录！'
                ];
            }

            $account = $remoteAccount['remote_account'];
            $password = $remoteAccount['remote_password'];
            
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
                    'message' => $loginResult['message'] // 已经是友好的错误信息
                ];
            }

            // 4. 生成免登录地址
            $autoLoginUrl = $this->generateAutoLoginUrl($loginResult['token']);
            
            // 5. 更新用户最后活动时间
            User::where('id', $userId)->update([
                'last_activity_at' => date('Y-m-d H:i:s'),
                'remarks' => 'last_token:' . $loginResult['token'] . '|updated:' . date('Y-m-d H:i:s')
            ]);

            Log::info('用户自动登录成功', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'user_name' => $user['user_name'],
                'remote_account' => $account,
                'token' => substr($loginResult['token'], 0, 10) . '...',
                'auto_login_url' => $autoLoginUrl
            ]);

            return [
                'success' => true,
                'message' => '登录成功',
                'token' => $loginResult['token'],
                'auto_login_url' => $autoLoginUrl
            ];

        } catch (\Exception $e) {
            Log::error('自动登录异常', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'success' => false,
                'message' => '登录过程中发生异常，请稍后重试'
            ];
        }
    }

    /**
     * 生成免登录地址
     * @param string $token 登录token
     * @param string $source 来源标识
     * @return string
     */
    private function generateAutoLoginUrl(string $token, string $source = self::DEFAULT_SOURCE): string
    {
        try {
            // 构建免登录URL
            $params = [
                'fr' => $source,
                't' => $token
            ];

            $queryString = http_build_query($params);
            $url = $this->AUTO_LOGIN_BASE_URL . '?' . $queryString;

            Log::info('生成免登录地址', [
                'token' => substr($token, 0, 10) . '...',
                'source' => $source,
                'url' => $url
            ]);

            return $url;

        } catch (\Exception $e) {
            Log::error('生成免登录地址异常', [
                'token' => substr($token, 0, 10) . '...',
                'source' => $source,
                'error' => $e->getMessage()
            ]);

            // 异常情况下返回基础URL
            return $this->AUTO_LOGIN_BASE_URL;
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
        return json([
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
     * @return Response
     */
    private function error(string $message): Response
    {
        return json([
            'code' => 400,
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], 400);
    }
}