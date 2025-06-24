<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class RemoteRegister extends Command
{
    protected function configure()
    {
        $this->setName('remote:register')
            ->setDescription('远程注册用户到游戏平台');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始执行远程注册任务...');
        
        try {
            // 获取最近2分钟内新增的用户
            $newUsers = $this->getNewUsers();
            
            if (empty($newUsers)) {
                $output->writeln('没有新用户需要注册');
                return;
            }
            
            $output->writeln('找到 ' . count($newUsers) . ' 个新用户需要注册');
            
            foreach ($newUsers as $user) {
                $this->processUserRegistration($user, $output);
            }
            
            $output->writeln('远程注册任务执行完成');
            
        } catch (\Exception $e) {
            $output->writeln('执行出错: ' . $e->getMessage());
            Log::error('远程注册出错: ' . $e->getMessage());
        }
    }

    /**
     * 获取邀请人的推荐码（用于注册时填写）
     * @param int $userId 被邀请人用户ID
     * @return string 邀请人的game_invitation_code，没有则返回空字符串
     */
    private function getInviterReferralCode($userId)
    {
        try {
            // 从ntp_user_invitations表查找邀请关系
            $invitation = Db::name('user_invitations')
                ->where('invitee_id', $userId)
                ->field('inviter_id')
                ->find();
            
            if (!$invitation || !$invitation['inviter_id']) {
                return '';
            }
            
            // 获取邀请人的game_invitation_code
            $inviterCode = Db::name('common_user')
                ->where('id', $invitation['inviter_id'])
                ->value('game_invitation_code');
            
            return $inviterCode ?: '';
            
        } catch (\Exception $e) {
            Log::error('获取邀请人推荐码异常', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return '';
        }
    }

    /**
     * 获取最近2分钟内新增的用户
     */
    private function getNewUsers()
    {
        $twoMinutesAgo = date('Y-m-d H:i:s', time() - 120);
        
        // 查询最近2分钟内新增且未注册过的用户
        return Db::name('common_user')
            ->alias('u')
            ->leftJoin('remote_register_log r', 'u.id = r.local_user_id')
            ->where('u.create_time', '>=', $twoMinutesAgo)
            ->where('r.id', 'null')
            ->field('u.id, u.user_name, u.tg_first_name, u.tg_last_name, u.create_time')
            ->select()
            ->toArray();
    }

    /**
     * 处理单个用户注册
     */
    private function processUserRegistration($user, Output $output)
    {
        $output->writeln("正在注册用户: {$user['user_name']}");
        
        // 生成远程账号和密码
        $remoteAccount = $this->generateRemoteAccount($user);
        $remotePassword = '123456'; // 默认密码
        
        // 获取邀请人的邀请码（用于注册时的推荐码）
        $referralCode = $this->getInviterReferralCode($user['id']);
        
        try {
            // 执行远程注册
            $registerResult = $this->doRemoteRegister($remoteAccount, $remotePassword, $referralCode);
            
            // 记录注册结果
            $this->logRegistrationResult($user['id'], $remoteAccount, $remotePassword, $registerResult);
            
            if ($registerResult['success']) {
                $output->writeln("用户 {$user['user_name']} 注册成功" . 
                    ($referralCode ? "，使用推荐码: {$referralCode}" : ""));
                
                // 注册成功后，获取邀请码
                $this->processInviteCodeAfterRegistration($user, $remoteAccount, $remotePassword, $output);
            } else {
                $output->writeln("用户 {$user['user_name']} 注册失败: " . $registerResult['message']);
            }
            
        } catch (\Exception $e) {
            $output->writeln("用户 {$user['user_name']} 注册异常: " . $e->getMessage());
            
            // 记录异常
            $this->logRegistrationResult($user['id'], $remoteAccount, $remotePassword, [
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 注册成功后处理邀请码获取
     */
    private function processInviteCodeAfterRegistration($user, $remoteAccount, $remotePassword, Output $output)
    {
        try {
            $output->writeln("开始获取用户 {$user['user_name']} 的邀请码...");
            
            // 等待1秒，确保注册完全成功
            sleep(1);
            
            // 使用项目现有的服务类
            $remoteLoginService = new \app\service\RemoteLoginService();
            $inviteCodeService = new \app\service\InviteCodeService();
            
            // 1. 先登录获取token
            $loginResult = $remoteLoginService->login($remoteAccount, $remotePassword);
            
            if (!$loginResult['success']) {
                $output->writeln("用户 {$user['user_name']} 登录失败: " . $loginResult['message']);
                return;
            }
            
            $output->writeln("用户 {$user['user_name']} 登录成功，获取到token");
            
            // 2. 使用token获取邀请码
            $inviteResult = $inviteCodeService->getInviteCode($loginResult['token']);
            
            if ($inviteResult['success']) {
                // 3. 更新邀请码到common_user表
                $updateResult = $inviteCodeService->updateInviteCode($user['id'], $inviteResult['invite_code']);
                
                if ($updateResult['success']) {
                    $output->writeln("用户 {$user['user_name']} 邀请码获取并保存成功: " . $inviteResult['invite_code']);
                    
                    // 记录日志
                    Log::info('注册后邀请码获取成功', [
                        'user_id' => $user['id'],
                        'user_name' => $user['user_name'],
                        'remote_account' => $remoteAccount,
                        'invite_code' => $inviteResult['invite_code']
                    ]);
                } else {
                    $output->writeln("用户 {$user['user_name']} 邀请码获取成功但保存失败: " . $updateResult['message']);
                }
            } else {
                $output->writeln("用户 {$user['user_name']} 邀请码获取失败: " . $inviteResult['message']);
            }
            
        } catch (\Exception $e) {
            $output->writeln("用户 {$user['user_name']} 邀请码处理异常: " . $e->getMessage());
            
            Log::error('注册后邀请码获取异常', [
                'user_id' => $user['id'],
                'user_name' => $user['user_name'],
                'remote_account' => $remoteAccount,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 生成远程账号名
     */
    private function generateRemoteAccount($user)
    {
        // 基于用户名生成，如果用户名已存在则加上时间戳
        $baseAccount = $user['user_name'];
        
        // 如果用户名过长，截取前10位
        if (strlen($baseAccount) > 10) {
            // $baseAccount = substr($baseAccount, 0, 10);
        }
        
        return $baseAccount;
    }

    /**
     * 执行远程注册
     */
    private function doRemoteRegister($account, $password, $referralCode = '')
    {
        $baseUrl = 'https://www.cg888.vip/api/core/member/frontend';
        
        try {
            // 1. 获取配置信息
            $configResponse = $this->makeApiRequest($baseUrl . '/member-config/get', 'POST');
            if (!$configResponse || $configResponse['code'] != 200) {
                throw new \Exception('获取配置信息失败');
            }

            // 2. 获取验证密钥
            $verifyKeyResponse = $this->makeApiRequest($baseUrl . '/login/verify-key/get', 'POST');
            if (!$verifyKeyResponse || $verifyKeyResponse['code'] != 200) {
                throw new \Exception('获取验证密钥失败');
            }

            // 3. 计算验证码
            $verifyKey = $this->calculateVerifyKey($verifyKeyResponse['data']);

            // 4. 注册验证
            $verifyResponse = $this->makeApiRequest($baseUrl . '/register/verify', 'POST', [
                'verifyKey' => $verifyKey
            ]);
            
            if (!$verifyResponse || $verifyResponse['code'] != 200) {
                throw new \Exception('注册验证失败');
            }

            // 5. 执行注册
            $registerResponse = $this->makeApiRequest($baseUrl . '/register', 'POST', [
                'account' => $account,
                'password' => $password,
                'referralCode' => $referralCode, // 使用邀请人的邀请码
                'registerDomain' => 'https://www.cg888.vip/'
            ]);

            if ($registerResponse && $registerResponse['code'] == 200) {
                return [
                    'success' => true,
                    'message' => '注册成功',
                    'data' => $registerResponse['data']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $registerResponse['message'] ?? '注册失败'
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * 计算验证码
     * 公式: (10 ^ digit * random) 无条件舍去小数位 - array[0] + array[1] - array[2] + array[3]
     */
    private function calculateVerifyKey($data)
    {
        $digit = $data['digit'];
        $random = $data['random'];
        $array = $data['array'];

        // 计算公式
        $result = floor(pow(10,$digit) * $random) - $array[0] + $array[1] - $array[2] + $array[3];

        return $result;
    }

    /**
     * 发送API请求
     */
    private function makeApiRequest($url, $method = 'POST', $data = [])
    {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // 设置请求头
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("CURL错误: " . $error);
        }

        if ($httpCode !== 200) {
            throw new \Exception("HTTP错误: " . $httpCode);
        }

        return json_decode($response, true);
    }

    /**
     * 记录注册结果
     */
    private function logRegistrationResult($localUserId, $remoteAccount, $remotePassword, $result)
    {
        $data = [
            'local_user_id' => $localUserId,
            'remote_account' => $remoteAccount,
            'remote_password' => $remotePassword,
            'register_status' => $result['success'] ? 1 : 2,
            'register_time' => date('Y-m-d H:i:s'),
            'error_message' => $result['success'] ? null : $result['message'],
            'retry_count' => 0
        ];

        Db::name('remote_register_log')->insert($data);
    }
}