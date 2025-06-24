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
    // 邀请码页面URL
    private const INVITE_URL = 'https://www.cg888.vip/profile/share';
    
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

            // 1. 使用token访问邀请页面
            $html = $this->fetchInvitePage($token);
            if (!$html) {
                return [
                    'success' => false,
                    'message' => '无法访问邀请页面'
                ];
            }

            // 2. 从HTML中解析邀请码
            $inviteCode = $this->parseInviteCodeFromHtml($html);
            if (!$inviteCode) {
                return [
                    'success' => false,
                    'message' => '无法从页面中解析邀请码'
                ];
            }

            Log::info('邀请码获取成功', [
                'invite_code' => $inviteCode
            ]);

            return [
                'success' => true,
                'invite_code' => $inviteCode
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
     * 更新用户邀请码到数据库
     * @param int $userId 用户ID
     * @param string $inviteCode 邀请码
     * @return array
     */
    public function updateInviteCode(int $userId, string $inviteCode): array
    {
        try {
            // 检查用户是否存在
            $user = User::where('id', $userId)->find();
            if (!$user) {
                return [
                    'success' => false,
                    'message' => '用户不存在'
                ];
            }

            // 更新邀请码
            $result = User::where('id', $userId)->update([
                'game_invitation_code' => $inviteCode,
                'last_activity_at' => date('Y-m-d H:i:s')
            ]);

            if ($result) {
                Log::info('邀请码更新成功', [
                    'user_id' => $userId,
                    'invite_code' => $inviteCode
                ]);

                return [
                    'success' => true,
                    'message' => '邀请码更新成功'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => '邀请码更新失败'
                ];
            }

        } catch (\Exception $e) {
            Log::error('更新邀请码异常', [
                'user_id' => $userId,
                'invite_code' => $inviteCode,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return [
                'success' => false,
                'message' => '更新邀请码过程中发生异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取用户的邀请码（从数据库）
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserInviteCode(int $userId): array
    {
        try {
            $user = User::where('id', $userId)
                ->field('id,user_name,game_invitation_code,last_activity_at')
                ->find();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => '用户不存在'
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'user_id' => $user['id'],
                    'user_name' => $user['user_name'],
                    'invite_code' => $user['game_invitation_code'],
                    'last_activity_at' => $user['last_activity_at']
                ]
            ];

        } catch (\Exception $e) {
            Log::error('获取用户邀请码异常', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => '获取用户邀请码过程中发生异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 批量获取邀请码
     * @param array $userTokenPairs 用户ID和token对应关系 [['user_id' => 1, 'token' => 'xxx'], ...]
     * @return array
     */
    public function batchGetInviteCodes(array $userTokenPairs): array
    {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($userTokenPairs as $pair) {
            $userId = $pair['user_id'] ?? 0;
            $token = $pair['token'] ?? '';

            if (empty($userId) || empty($token)) {
                $results[] = [
                    'user_id' => $userId,
                    'success' => false,
                    'message' => '用户ID或token为空'
                ];
                $failCount++;
                continue;
            }

            // 获取邀请码
            $getResult = $this->getInviteCode($token);
            if ($getResult['success']) {
                // 更新到数据库
                $updateResult = $this->updateInviteCode($userId, $getResult['invite_code']);
                $results[] = [
                    'user_id' => $userId,
                    'success' => $updateResult['success'],
                    'invite_code' => $getResult['invite_code'],
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
                    'message' => $getResult['message']
                ];
                $failCount++;
            }

            // 避免请求过于频繁，添加延迟
            usleep(500000); // 0.5秒延迟
        }

        return [
            'success' => $successCount > 0,
            'total' => count($userTokenPairs),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'results' => $results
        ];
    }

    /**
     * 使用token访问邀请页面
     * @param string $token 登录token
     * @return string|null
     */
    private function fetchInvitePage(string $token): ?string
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, self::INVITE_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            // 设置请求头，包含token
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36',
                'Authorization: Bearer ' . $token
            ]);

            // 设置Cookie（如果token需要通过Cookie传递）
            curl_setopt($ch, CURLOPT_COOKIE, 'auth_token=' . $token);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Log::error('获取邀请页面CURL错误', [
                    'url' => self::INVITE_URL,
                    'error' => $error
                ]);
                return null;
            }

            if ($httpCode !== 200) {
                Log::error('获取邀请页面HTTP错误', [
                    'url' => self::INVITE_URL,
                    'http_code' => $httpCode
                ]);
                return null;
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('获取邀请页面异常', [
                'url' => self::INVITE_URL,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 从HTML中解析邀请码
     * @param string $html HTML内容
     * @return string|null
     */
    public function parseInviteCodeFromHtml(string $html): ?string
    {
        try {
            // 方法1: 通过正则表达式匹配邀请码
            // 假设邀请码格式为数字或字母数字组合
            $patterns = [
                '/invite[_-]?code["\']?\s*[:=]\s*["\']?([A-Za-z0-9]{6,20})["\']?/i',
                '/邀请码["\']?\s*[:：=]\s*["\']?([A-Za-z0-9]{6,20})["\']?/i',
                '/invitationCode["\']?\s*[:=]\s*["\']?([A-Za-z0-9]{6,20})["\']?/i',
                '/referral[_-]?code["\']?\s*[:=]\s*["\']?([A-Za-z0-9]{6,20})["\']?/i',
                '/data-invite[_-]?code["\']?\s*=\s*["\']?([A-Za-z0-9]{6,20})["\']?/i'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $inviteCode = trim($matches[1]);
                    if (!empty($inviteCode)) {
                        Log::info('通过正则表达式找到邀请码', [
                            'pattern' => $pattern,
                            'invite_code' => $inviteCode
                        ]);
                        return $inviteCode;
                    }
                }
            }

            // 方法2: 通过DOM解析查找特定元素
            if (class_exists('DOMDocument')) {
                $inviteCode = $this->parseInviteCodeByDOM($html);
                if ($inviteCode) {
                    return $inviteCode;
                }
            }

            // 方法3: 查找包含邀请码关键词的文本
            $inviteCode = $this->parseInviteCodeByKeywords($html);
            if ($inviteCode) {
                return $inviteCode;
            }

            Log::warning('无法从HTML中解析邀请码', [
                'html_length' => strlen($html),
                'html_preview' => substr($html, 0, 500)
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('解析邀请码异常', [
                'error' => $e->getMessage(),
                'html_length' => strlen($html)
            ]);
            return null;
        }
    }

    /**
     * 通过DOM解析查找邀请码
     * @param string $html HTML内容
     * @return string|null
     */
    private function parseInviteCodeByDOM(string $html): ?string
    {
        try {
            $dom = new \DOMDocument();
            @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $xpath = new \DOMXPath($dom);

            // 查找可能包含邀请码的元素
            $queries = [
                "//input[@name='invite_code']/@value",
                "//input[@name='invitationCode']/@value",
                "//input[@name='referral_code']/@value",
                "//*[@class='invite-code']/text()",
                "//*[@id='invite-code']/text()",
                "//*[@data-invite-code]/@data-invite-code",
                "//span[contains(@class, 'invite')]/text()",
                "//div[contains(@class, 'invite')]/text()"
            ];

            foreach ($queries as $query) {
                $nodes = $xpath->query($query);
                if ($nodes && $nodes->length > 0) {
                    $value = trim($nodes->item(0)->nodeValue);
                    if (preg_match('/^[A-Za-z0-9]{6,20}$/', $value)) {
                        Log::info('通过DOM解析找到邀请码', [
                            'query' => $query,
                            'invite_code' => $value
                        ]);
                        return $value;
                    }
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('DOM解析邀请码异常', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 通过关键词查找邀请码
     * @param string $html HTML内容
     * @return string|null
     */
    private function parseInviteCodeByKeywords(string $html): ?string
    {
        try {
            // 清理HTML标签，获取纯文本
            $text = strip_tags($html);
            
            // 在邀请码关键词附近查找代码
            $keywords = ['邀请码', '推荐码', '邀请链接', 'invite', 'referral', 'invitation'];
            
            foreach ($keywords as $keyword) {
                // 查找关键词位置
                $pos = mb_stripos($text, $keyword);
                if ($pos !== false) {
                    // 提取关键词周围的文本
                    $start = max(0, $pos - 50);
                    $length = 200;
                    $context = mb_substr($text, $start, $length);
                    
                    // 在上下文中查找邀请码格式
                    if (preg_match('/[A-Za-z0-9]{6,20}/', $context, $matches)) {
                        $inviteCode = $matches[0];
                        Log::info('通过关键词找到邀请码', [
                            'keyword' => $keyword,
                            'context' => $context,
                            'invite_code' => $inviteCode
                        ]);
                        return $inviteCode;
                    }
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('关键词解析邀请码异常', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}