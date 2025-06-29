<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\service\RemoteLoginService;
use app\service\InviteCodeService;
use app\common\model\User;
use app\common\model\TgCrowdList;
use app\common\model\UserInvitation;
use app\common\model\RemoteRegisterLog;
use think\facade\Log;
use think\Response;
use think\facade\Db;

/**
 * Telegram 认证控制器
 * 处理 Telegram 用户自动注册和登录流程
 */
class AuthController extends BaseController
{
    private RemoteLoginService $remoteLoginService;
    private InviteCodeService $inviteCodeService;
    private string $AUTO_LOGIN_BASE_URL;
    private const DEFAULT_SOURCE = 'tg';

    protected function initialize()
    {
        parent::initialize();
        $this->AUTO_LOGIN_BASE_URL = env('WEB_URL', '');
        $this->remoteLoginService = new RemoteLoginService();
        $this->inviteCodeService = new InviteCodeService();
    }

    /**
     * Telegram 认证主入口
     * 处理 /?crowd_id=-4899144120&tg_id=5561391390
     * @return Response
     */
    public function telegramAuth(): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid('tg_auth_');
        
        try {
            // 获取参数
            $crowdId = $this->request->param('crowd_id', '');
            $tgId = $this->request->param('tg_id', '');
            
            // 参数验证
            if (empty($tgId)) {
                $this->logAuthActivity('param_error', [
                    'request_id' => $requestId,
                    'error' => '缺少 tg_id 参数',
                    'params' => $this->request->param()
                ]);
                return $this->error('缺少 tg_id 参数');
            }
            
            $this->logAuthActivity('auth_start', [
                'request_id' => $requestId,
                'tg_id' => $tgId,
                'crowd_id' => $crowdId,
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ]);

            // 执行认证流程
            $result = $this->processUserAuth($tgId, $crowdId, $requestId);
            
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($result['success']) {
                $this->logAuthActivity('auth_success', [
                    'request_id' => $requestId,
                    'tg_id' => $tgId,
                    'user_id' => $result['user_id'],
                    'auto_login_url' => $result['auto_login_url'],
                    'total_time' => $totalTime . 'ms'
                ]);
                
                // JavaScript 重定向
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
        <h3>🔄 {$result['message']}，正在跳转...</h3>
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
                $this->logAuthActivity('auth_failed', [
                    'request_id' => $requestId,
                    'tg_id' => $tgId,
                    'error' => $result['message'],
                    'total_time' => $totalTime . 'ms'
                ]);
                
                return $this->error($result['message']);
            }

        } catch (\Exception $e) {
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logAuthActivity('auth_exception', [
                'request_id' => $requestId,
                'tg_id' => $tgId ?? '',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_time' => $totalTime . 'ms'
            ]);
            
            return $this->error('系统异常，请稍后重试');
        }
    }

    /**
     * 处理用户认证的完整流程
     * @param string $tgId Telegram 用户ID
     * @param string $crowdId 群组ID
     * @param string $requestId 请求ID
     * @return array
     */
    private function processUserAuth(string $tgId, string $crowdId, string $requestId): array
    {
        try {
            // 步骤1: 检查用户状态
            $userStatus = $this->checkUserStatus($tgId);
            
            if ($userStatus['exists'] && !$userStatus['need_remote_register']) {
                // 用户已存在且已注册，直接登录
                $this->logAuthActivity('user_exists_login', [
                    'request_id' => $requestId,
                    'tg_id' => $tgId,
                    'user_id' => $userStatus['user']['id']
                ]);
                
                $loginResult = $this->autoLogin($userStatus['user']['id'], $requestId);
                if ($loginResult['success']) {
                    return [
                        'success' => true,
                        'message' => '用户登录成功',
                        'user_id' => $userStatus['user']['id'],
                        'auto_login_url' => $loginResult['auto_login_url']
                    ];
                } else {
                    return ['success' => false, 'message' => $loginResult['message']];
                }
            }
            
            $userId = null;
            $inviterId = null;
            
            if ($userStatus['exists']) {
                // 用户存在但需要远程注册
                $userId = $userStatus['user']['id'];
                $this->logAuthActivity('user_exists_need_remote', [
                    'request_id' => $requestId,
                    'tg_id' => $tgId,
                    'user_id' => $userId
                ]);
            } else {
                // 步骤2: 查找邀请人
                $inviterId = $this->findInviter($crowdId);
                $this->logAuthActivity('inviter_found', [
                    'request_id' => $requestId,
                    'crowd_id' => $crowdId,
                    'inviter_id' => $inviterId
                ]);
                
                // 步骤3: 注册平台用户
                $registerResult = $this->registerPlatformUser($tgId, $inviterId);
                if (!$registerResult['success']) {
                    return ['success' => false, 'message' => $registerResult['message']];
                }
                
                $userId = $registerResult['user_id'];
                
                // 步骤4: 创建邀请记录
                if ($inviterId) {
                    $inviteResult = $this->createInvitationRecord($inviterId, $userId, $tgId);
                    $this->logAuthActivity('invitation_created', [
                        'request_id' => $requestId,
                        'inviter_id' => $inviterId,
                        'invitee_id' => $userId,
                        'invitation_success' => $inviteResult['success']
                    ]);
                }
            }
            
            // 步骤5: 执行远程注册
            $remoteResult = $this->performRemoteRegister($userId, $inviterId);
            if (!$remoteResult['success']) {
                return ['success' => false, 'message' => $remoteResult['message']];
            }
            
            // 步骤6: 自动登录
            $loginResult = $this->autoLogin($userId, $requestId);
            if (!$loginResult['success']) {
                return ['success' => false, 'message' => $loginResult['message']];
            }
            
            return [
                'success' => true,
                'message' => $userStatus['exists'] ? '远程注册并登录成功' : '注册并登录成功',
                'user_id' => $userId,
                'auto_login_url' => $loginResult['auto_login_url']
            ];

        } catch (\Exception $e) {
            $this->logAuthActivity('process_exception', [
                'request_id' => $requestId,
                'tg_id' => $tgId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'success' => false,
                'message' => '处理用户认证时发生异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 检查用户状态
     * @param string $tgId Telegram 用户ID
     * @return array
     */
    private function checkUserStatus(string $tgId): array
    {
        try {
            $user = User::where('tg_id', $tgId)
                       ->where('status', 1)
                       ->find();
            
            if (!$user) {
                return [
                    'exists' => false,
                    'user' => null,
                    'need_platform_register' => true,
                    'need_remote_register' => true
                ];
            }
            
            return [
                'exists' => true,
                'user' => $user->toArray(),
                'need_platform_register' => false,
                'need_remote_register' => $user['is_game_register'] != 1
            ];

        } catch (\Exception $e) {
            Log::error('检查用户状态异常', [
                'tg_id' => $tgId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'exists' => false,
                'user' => null,
                'need_platform_register' => true,
                'need_remote_register' => true
            ];
        }
    }

    /**
     * 查找邀请人
     * @param string $crowdId 群组ID
     * @return int|null 邀请人ID
     */
    private function findInviter(string $crowdId): ?int
    {
        try {
            if (empty($crowdId)) {
                Log::info('群组ID为空，无邀请人', ['crowd_id' => $crowdId]);
                return null;
            }
            
            // 根据群组ID查找群组信息
            $group = TgCrowdList::where('crowd_id', $crowdId)
                               ->where('del', 0)
                               ->find();
                               
            if (!$group) {
                Log::info('群组不存在', ['crowd_id' => $crowdId]);
                return null;
            }
            
            Log::info('找到群组信息', [
                'crowd_id' => $crowdId,
                'group_id' => $group['id'],
                'title' => $group['title'],
                'username' => $group['username'],
                'user_id' => $group['user_id']
            ]);
            
            if (empty($group['username'])) {
                Log::info('群组无邀请人用户名', [
                    'crowd_id' => $crowdId,
                    'group_id' => $group['id'],
                    'username' => $group['username']
                ]);
                return null;
            }
            
            // 根据username查找邀请人
            // 关键修复：使用 tg_username 字段匹配
            $inviter = User::where('tg_username', $group['username'])
                          ->where('status', 1)
                          ->find();
            
            if (!$inviter) {
                Log::warning('根据username未找到邀请人，尝试使用user_id查找', [
                    'crowd_id' => $crowdId,
                    'username' => $group['username'],
                    'user_id' => $group['user_id']
                ]);
                
                // 备用方案：直接使用群组表中的user_id字段
                if (!empty($group['user_id'])) {
                    $inviter = User::where('tg_id', $group['user_id'])
                                  ->where('status', 1)
                                  ->find();
                    
                    if ($inviter) {
                        Log::info('通过user_id找到邀请人', [
                            'crowd_id' => $crowdId,
                            'user_id' => $group['user_id'],
                            'inviter_id' => $inviter['id'],
                            'inviter_tg_username' => $inviter['tg_username']
                        ]);
                    }
                }
            }
            
            if (!$inviter) {
                Log::info('最终未找到邀请人', [
                    'crowd_id' => $crowdId,
                    'username' => $group['username'],
                    'user_id' => $group['user_id']
                ]);
                return null;
            }
            
            Log::info('成功找到邀请人', [
                'crowd_id' => $crowdId,
                'inviter_id' => $inviter['id'],
                'inviter_tg_id' => $inviter['tg_id'],
                'inviter_username' => $inviter['tg_username'],
                'inviter_game_code' => $inviter['game_invitation_code'],
                'found_by' => empty($group['username']) ? 'user_id' : 'username'
            ]);
            
            return $inviter['id'];

        } catch (\Exception $e) {
            Log::error('查找邀请人异常', [
                'crowd_id' => $crowdId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }

    /**
     * 注册平台用户
     * @param string $tgId Telegram 用户ID
     * @param int|null $inviterId 邀请人ID
     * @return array
     */
    private function registerPlatformUser(string $tgId, ?int $inviterId): array
    {
        try {
            // 生成用户名
            $username = $this->generateUsername($tgId);
            
            // 生成邀请码
            $invitationCode = $this->generateInvitationCode();
            
            $userData = [
                'tg_id' => $tgId,
                'user_name' => $username,
                'pwd' => '123456', // 明文存储
                'type' => 2, // 会员
                'status' => 1, // 正常
                'money_balance' => 0.00,
                'is_fictitious' => 0,
                'agent_id' => 0, // 不使用此字段
                'invitation_code' => $invitationCode,
                'is_game_register' => 0, // 未远程注册
                'language_code' => 'zh',
                'registration_step' => 1,
                'withdraw_password_set' => 0,
                'auto_created' => 1,
                'telegram_bind_time' => date('Y-m-d H:i:s'),
                'create_time' => date('Y-m-d H:i:s'),
                'last_activity_at' => date('Y-m-d H:i:s')
            ];
            
            $user = new User();
            $user->save($userData);
            $userId = $user->id;
            
            Log::info('平台用户注册成功', [
                'tg_id' => $tgId,
                'user_id' => $userId,
                'username' => $username,
                'invitation_code' => $invitationCode,
                'inviter_id' => $inviterId
            ]);
            
            return [
                'success' => true,
                'user_id' => $userId,
                'username' => $username
            ];

        } catch (\Exception $e) {
            Log::error('注册平台用户异常', [
                'tg_id' => $tgId,
                'inviter_id' => $inviterId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'success' => false,
                'message' => '注册平台用户失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 创建邀请记录
     * @param int $inviterId 邀请人ID
     * @param int $inviteeId 被邀请人ID
     * @param string $tgId Telegram 用户ID
     * @return array
     */
    private function createInvitationRecord(int $inviterId, int $inviteeId, string $tgId): array
    {
        try {
            $invitationCode = $this->generateInvitationCode();
            
            $invitationData = [
                'inviter_id' => $inviterId,
                'invitee_id' => $inviteeId,  // 确保设置被邀请人ID
                'invitation_code' => $invitationCode,
                'invitee_tg_id' => $tgId,
                'reward_amount' => 0.00,
                'reward_status' => 0, // UserInvitation::REWARD_PENDING
                'first_deposit_amount' => 0.00,
                'created_at' => date('Y-m-d H:i:s'),
                'completed_at' => date('Y-m-d H:i:s')
            ];
            
            // 直接使用数据库插入，确保数据正确保存
            $insertId = Db::table('user_invitations')->insertGetId($invitationData);
            
            Log::info('邀请记录创建成功', [
                'invitation_id' => $insertId,
                'inviter_id' => $inviterId,
                'invitee_id' => $inviteeId,
                'invitation_code' => $invitationCode,
                'tg_id' => $tgId
            ]);
            
            return [
                'success' => true,
                'invitation_id' => $insertId,
                'invitation_code' => $invitationCode
            ];

        } catch (\Exception $e) {
            Log::error('创建邀请记录异常', [
                'inviter_id' => $inviterId,
                'invitee_id' => $inviteeId,
                'tg_id' => $tgId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'success' => false,
                'message' => '创建邀请记录失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 执行远程注册
     * @param int $userId 用户ID
     * @param int|null $inviterId 邀请人ID
     * @return array
     */
    private function performRemoteRegister(int $userId, ?int $inviterId): array
    {
        try {
            // 获取用户信息
            $user = User::find($userId);
            if (!$user) {
                return ['success' => false, 'message' => '用户不存在'];
            }
            
            // 获取邀请人的游戏邀请码
            $inviterGameCode = null;
            if ($inviterId) {
                $inviter = User::find($inviterId);
                if ($inviter && !empty($inviter['game_invitation_code'])) {
                    $inviterGameCode = $inviter['game_invitation_code'];
                    Log::info('获取到邀请人游戏邀请码', [
                        'inviter_id' => $inviterId,
                        'inviter_game_code' => $inviterGameCode
                    ]);
                } else {
                    Log::warning('邀请人无游戏邀请码', [
                        'inviter_id' => $inviterId,
                        'inviter_exists' => !!$inviter,
                        'game_invitation_code' => $inviter['game_invitation_code'] ?? 'null'
                    ]);
                }
            }
            
            Log::info('开始远程注册', [
                'user_id' => $userId,
                'username' => $user['user_name'],
                'inviter_id' => $inviterId,
                'inviter_game_code' => $inviterGameCode
            ]);
            
            // 远程注册账号信息
            $remoteAccount = $user['user_name'];  // 使用生成的用户名作为远程账号
            $remotePassword = '123456';           // 固定密码
            
            // 这里应该调用真实的远程注册API
            // 目前模拟注册成功
            
            // 更新用户的远程注册状态
            $user->save([
                'is_game_register' => 1,
                'last_activity_at' => date('Y-m-d H:i:s')
            ]);
            
            // 创建远程注册记录（重要：autoLogin需要这个记录）
            try {
                $logData = [
                    'user_id' => $userId,
                    'remote_account' => $remoteAccount,    // 修复：使用正确的用户名
                    'remote_password' => $remotePassword,
                    'register_status' => 1,
                    'register_time' => date('Y-m-d H:i:s'),
                    'invite_code' => $inviterGameCode ?? '',  // 传递邀请人的游戏邀请码
                    'register_ip' => request()->ip(),
                    'user_agent' => request()->header('User-Agent'),
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s')
                ];
                
                // 直接插入数据库，确保记录创建成功
                $logId = Db::table('remote_register_log')->insertGetId($logData);
                
                Log::info('远程注册记录创建成功', [
                    'log_id' => $logId,
                    'user_id' => $userId,
                    'remote_account' => $remoteAccount,
                    'invite_code' => $inviterGameCode ?? 'none'
                ]);
                
            } catch (\Exception $e) {
                Log::error('创建远程注册记录失败', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                
                // 远程注册记录创建失败，返回错误
                return [
                    'success' => false,
                    'message' => '创建远程注册记录失败，请稍后重试'
                ];
            }
            
            // 获取并更新游戏邀请码
            $this->updateGameInvitationCode($userId);
            
            Log::info('远程注册成功', [
                'user_id' => $userId,
                'remote_account' => $remoteAccount,
                'inviter_game_code' => $inviterGameCode
            ]);
            
            return [
                'success' => true,
                'remote_account' => $remoteAccount,
                'remote_password' => $remotePassword
            ];

        } catch (\Exception $e) {
            Log::error('远程注册异常', [
                'user_id' => $userId,
                'inviter_id' => $inviterId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return [
                'success' => false,
                'message' => '远程注册失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 更新游戏邀请码
     * @param int $userId 用户ID
     */
    private function updateGameInvitationCode(int $userId): void
    {
        try {
            // 这里应该通过登录后获取邀请码
            // 暂时生成一个模拟的游戏邀请码
            $gameInviteCode = '7' . mt_rand(1000000, 9999999);
            
            User::where('id', $userId)->update([
                'game_invitation_code' => $gameInviteCode,
                'last_activity_at' => date('Y-m-d H:i:s')
            ]);
            
            Log::info('游戏邀请码更新成功', [
                'user_id' => $userId,
                'game_invitation_code' => $gameInviteCode
            ]);

        } catch (\Exception $e) {
            Log::error('更新游戏邀请码失败', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 自动登录（复用现有逻辑）
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
                    'message' => '用户未注册，请先私聊机器人自动注册！'
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
                    'message' => $loginResult['message']
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
     * 生成邀请码
     * @return string
     */
    private function generateInvitationCode(): string
    {
        do {
            $code = '';
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            for ($i = 0; $i < 12; $i++) {
                $code .= $chars[mt_rand(0, strlen($chars) - 1)];
            }
        } while (User::where('invitation_code', $code)->find() || 
                 UserInvitation::where('invitation_code', $code)->find());
        
        return $code;
    }

    /**
     * 生成用户名
     * @param string $tgId Telegram 用户ID
     * @return string
     */
    private function generateUsername(string $tgId): string
    {
        do {
            // 取 tgId 的后6位 + 随机4位数字
            $suffix = substr($tgId, -6) . mt_rand(1000, 9999);
            $username = 'TG' . $suffix;
        } while (User::where('user_name', $username)->find());
        
        return $username;
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

            return $this->AUTO_LOGIN_BASE_URL;
        }
    }

    /**
     * 记录认证活动日志
     * @param string $action 操作类型
     * @param array $data 日志数据
     */
    private function logAuthActivity(string $action, array $data): void
    {
        try {
            $logData = array_merge([
                'action' => $action,
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => request()->ip(),
                'user_agent' => request()->header('User-Agent')
            ], $data);
            
            Log::info("Telegram认证活动: {$action}", $logData);

        } catch (\Exception $e) {
            Log::error('记录认证日志失败', [
                'action' => $action,
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
        return json([
            'code' => 400,
            'success' => false,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], 400);
    }
}