<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\RemoteLoginService;
use app\service\InviteCodeService;
use app\service\AutoLoginService;
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
    private AutoLoginService $autoLoginService;

    protected function initialize()
    {
        parent::initialize();
        $this->remoteLoginService = new RemoteLoginService();
        $this->inviteCodeService = new InviteCodeService();
        $this->autoLoginService = new AutoLoginService();
    }

    // public function __construct()
    // {
    //     parent::__construct();
        
    // }

    /**
     * 主入口方法 - 处理用户访问 /login?user_id=1
     * 自动登录并重定向到免登录地址
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

            $autoLoginUrl = $this->autoLoginService->generateUrl($loginResult['token']);
            
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

            // 步骤1: 执行自动登录获取token
            $steps[] = [
                'step' => 1,
                'name' => '执行自动登录获取token',
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ];

            $loginResult = $this->autoLogin($userId);
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
     * @return array
     */
    private function autoLogin(int $userId): array
    {
        try {
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
                    'message' => '用户未完成远程注册'
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
                    'message' => '远程登录失败: ' . $loginResult['message']
                ];
            }

            // 4. 生成免登录地址
            $autoLoginUrl = $this->autoLoginService->generateUrl($loginResult['token']);
            
            // 5. 更新用户最后活动时间
            User::where('id', $userId)->update([
                'last_activity_at' => date('Y-m-d H:i:s'),
                'remarks' => 'last_token:' . $loginResult['token'] . '|updated:' . date('Y-m-d H:i:s')
            ]);

            Log::info('用户自动登录成功', [
                'user_id' => $userId,
                'user_name' => $user['user_name'],
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