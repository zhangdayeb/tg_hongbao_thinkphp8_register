<?php
declare(strict_types=1);

namespace app\service;

use app\common\model\User;
use think\facade\Log;

/**
 * 邀请码服务
 * 负责获取和管理用户邀请码
 */
class InviteCodeService
{
    // 直接调用后端API获取邀请码
    private string $INVITE_CODE_API = env('WEB_URL', '').'api/core/member/frontend/agent-code/list';
    
    // 请求超时时间
    private const TIMEOUT = 30;

    /**
     * 获取用户邀请码
     * @param string $token 登录token
     * @return array
     */
    public function getInviteCode(string $token): array
    {
        try {
            Log::info('开始获取邀请码', [
                'token' => substr($token, 0, 10) . '...'
            ]);

            // 直接调用后端API获取邀请码
            $result = $this->fetchInviteCodeFromApi($token);
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => $result['message']
                ];
            }

            Log::info('邀请码获取成功', [
                'invite_code' => $result['invite_code'],
                'token' => substr($token, 0, 10) . '...'
            ]);

            return [
                'success' => true,
                'invite_code' => $result['invite_code']
            ];

        } catch (\Exception $e) {
            Log::error('获取邀请码异常', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'success' => false,
                'message' => '获取邀请码过程中发生异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 直接调用API获取邀请码
     * @param string $token 登录token
     * @return array
     */
    private function fetchInviteCodeFromApi(string $token): array
    {
        try {
            // 构造请求头
            $headers = [
                'Accept: application/json, text/plain, */*',
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'DeviceId: 1y8cw7k2sgkt4m0vom3zm3sqzzmummpr',
                'Locale: zh_cn',
                'LoginDeviceType: MOBILE',
                'Origin: '.env('WEB_URL', ''),
                'Referer: '.env('WEB_URL', '').'profile/share',
                'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1'
            ];

            // 初始化cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->INVITE_CODE_API,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '{}',
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_ENCODING => ''
            ]);

            // 执行请求
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // 检查请求错误
            if ($curlError) {
                return [
                    'success' => false,
                    'message' => 'API请求失败: ' . $curlError
                ];
            }

            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'message' => "API返回错误状态码: {$httpCode}"
                ];
            }

            // 解析响应
            return $this->parseApiResponse($response);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API调用异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 解析API响应
     * @param string $response
     * @return array
     */
    private function parseApiResponse(string $response): array
    {
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'API响应格式错误'
            ];
        }

        // 检查API返回状态
        if (!isset($data['code']) || $data['code'] !== 200) {
            return [
                'success' => false,
                'message' => 'API返回错误: ' . ($data['message'] ?? '未知错误')
            ];
        }

        // 检查数据
        if (!isset($data['data']) || !is_array($data['data']) || empty($data['data'])) {
            return [
                'success' => false,
                'message' => '邀请码数据为空'
            ];
        }

        // 获取第一个启用的邀请码
        foreach ($data['data'] as $item) {
            if (isset($item['code']) && isset($item['enable']) && $item['enable'] === true) {
                return [
                    'success' => true,
                    'invite_code' => $item['code']
                ];
            }
        }

        return [
            'success' => false,
            'message' => '未找到可用的邀请码'
        ];
    }

    /**
     * 更新用户邀请码到数据库
     * @param int $userId 用户ID
     * @param string $inviteCode 邀请码
     * @return array
     */
    public function updateInviteCode(int $userId, string $inviteCode): array
    {
        try {
            // 严格验证用户ID
            if (empty($userId) || $userId <= 0) {
                Log::error('用户ID参数无效', [
                    'user_id' => $userId,
                    'user_id_type' => gettype($userId)
                ]);
                return [
                    'success' => false,
                    'message' => '用户ID参数无效'
                ];
            }

            // 检查用户是否存在
            $user = User::where('id', $userId)->find();
            if (!$user) {
                Log::warning('用户不存在', ['user_id' => $userId]);
                return [
                    'success' => false,
                    'message' => '用户不存在'
                ];
            }

            Log::info('准备更新邀请码', [
                'user_id' => $userId,
                'user_name' => $user['user_name'],
                'old_invite_code' => $user['game_invitation_code'],
                'new_invite_code' => $inviteCode
            ]);

            // 使用更安全的更新方式，先构建查询再执行
            $updateData = [
                'game_invitation_code' => $inviteCode,
                'last_activity_at' => date('Y-m-d H:i:s')
            ];

            // 记录SQL执行前的状态
            Log::info('执行更新SQL前状态', [
                'user_id' => $userId,
                'update_data' => $updateData,
                'where_condition' => "id = {$userId}"
            ]);

            // 执行更新操作
            $result = User::where('id', '=', $userId)->update($updateData);

            Log::info('SQL执行结果', [
                'user_id' => $userId,
                'update_result' => $result,
                'affected_rows' => $result
            ]);

            if ($result) {
                // 验证更新是否成功，重新查询确认
                $updatedUser = User::where('id', $userId)->find();
                
                Log::info('邀请码更新成功', [
                    'user_id' => $userId,
                    'user_name' => $user['user_name'],
                    'old_invite_code' => $user['game_invitation_code'],
                    'new_invite_code' => $inviteCode,
                    'verified_invite_code' => $updatedUser['game_invitation_code'],
                    'update_confirmed' => $updatedUser['game_invitation_code'] === $inviteCode
                ]);

                return [
                    'success' => true,
                    'message' => '邀请码更新成功',
                    'affected_rows' => $result
                ];
            } else {
                Log::warning('更新返回0行', [
                    'user_id' => $userId,
                    'invite_code' => $inviteCode,
                    'possible_reason' => '数据未变化或WHERE条件未匹配'
                ]);

                return [
                    'success' => false,
                    'message' => '邀请码更新失败：未影响任何行'
                ];
            }

        } catch (\Exception $e) {
            Log::error('更新邀请码异常', [
                'user_id' => $userId,
                'invite_code' => $inviteCode,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => '更新邀请码过程中发生异常: ' . $e->getMessage()
            ];
        }
    }
}