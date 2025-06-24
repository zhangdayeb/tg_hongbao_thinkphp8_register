<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Log;

/**
 * 自动登录服务
 * 负责生成免登录地址和验证token
 */
class AutoLoginService
{
    // 免登录基础URL
    private const AUTO_LOGIN_BASE_URL = 'https://www.cg888.vip/';
    
    // 默认来源参数
    private const DEFAULT_SOURCE = 'tg';
    
    // token有效期（秒）
    private const TOKEN_EXPIRE_TIME = 3600; // 1小时

    /**
     * 生成免登录地址
     * @param string $token 登录token
     * @param string $source 来源标识
     * @param array $extraParams 额外参数
     * @return string
     */
    public function generateUrl(string $token, string $source = self::DEFAULT_SOURCE, array $extraParams = []): string
    {
        try {
            // 基础参数
            $params = [
                'fr' => $source,
                't' => $token
            ];

            // 合并额外参数
            if (!empty($extraParams)) {
                $params = array_merge($params, $extraParams);
            }

            // 构建查询字符串
            $queryString = http_build_query($params);
            
            // 生成完整URL
            $url = self::AUTO_LOGIN_BASE_URL . '?' . $queryString;

            Log::info('生成免登录地址', [
                'token' => substr($token, 0, 10) . '...',
                'source' => $source,
                'url' => $url,
                'extra_params' => $extraParams
            ]);

            return $url;

        } catch (\Exception $e) {
            Log::error('生成免登录地址异常', [
                'token' => substr($token, 0, 10) . '...',
                'source' => $source,
                'extra_params' => $extraParams,
                'error' => $e->getMessage()
            ]);

            // 异常情况下返回基础URL
            return self::AUTO_LOGIN_BASE_URL;
        }
    }

    /**
     * 验证token有效性
     * @param string $token 登录token
     * @return bool
     */
    public function validateToken(string $token): bool
    {
        try {
            // 检查token格式
            if (empty($token) || strlen($token) < 10) {
                Log::warning('Token格式无效', ['token' => $token]);
                return false;
            }

            // 这里可以添加更复杂的验证逻辑
            // 比如：检查token是否在有效期内、是否被撤销等
            
            // 简单的格式验证（根据实际token格式调整）
            if (!preg_match('/^[A-Za-z0-9+\/=]{20,}$/', $token)) {
                Log::warning('Token格式不符合要求', ['token' => substr($token, 0, 10) . '...']);
                return false;
            }

            Log::info('Token验证通过', ['token' => substr($token, 0, 10) . '...']);
            return true;

        } catch (\Exception $e) {
            Log::error('验证token异常', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 生成带时间戳的免登录地址
     * @param string $token 登录token
     * @param string $source 来源标识
     * @return string
     */
    public function generateUrlWithTimestamp(string $token, string $source = self::DEFAULT_SOURCE): string
    {
        $extraParams = [
            'ts' => time(),
            'ver' => '1.0'
        ];

        return $this->generateUrl($token, $source, $extraParams);
    }

    /**
     * 生成带过期时间的免登录地址
     * @param string $token 登录token
     * @param int $expireTime 过期时间戳
     * @param string $source 来源标识
     * @return string
     */
    public function generateUrlWithExpire(string $token, int $expireTime, string $source = self::DEFAULT_SOURCE): string
    {
        $extraParams = [
            'exp' => $expireTime,
            'ts' => time()
        ];

        return $this->generateUrl($token, $source, $extraParams);
    }

    /**
     * 解析免登录URL中的参数
     * @param string $url 免登录URL
     * @return array
     */
    public function parseUrl(string $url): array
    {
        try {
            $parsedUrl = parse_url($url);
            $params = [];

            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $params);
            }

            return [
                'success' => true,
                'token' => $params['t'] ?? '',
                'source' => $params['fr'] ?? '',
                'timestamp' => $params['ts'] ?? 0,
                'expire_time' => $params['exp'] ?? 0,
                'all_params' => $params
            ];

        } catch (\Exception $e) {
            Log::error('解析免登录URL异常', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 检查URL是否过期
     * @param string $url 免登录URL
     * @return bool
     */
    public function isUrlExpired(string $url): bool
    {
        try {
            $parseResult = $this->parseUrl($url);
            
            if (!$parseResult['success']) {
                return true;
            }

            $expireTime = $parseResult['expire_time'];
            if (empty($expireTime)) {
                // 如果没有设置过期时间，使用默认有效期
                $timestamp = $parseResult['timestamp'];
                if (empty($timestamp)) {
                    return true;
                }
                $expireTime = $timestamp + self::TOKEN_EXPIRE_TIME;
            }

            $isExpired = time() > $expireTime;

            if ($isExpired) {
                Log::info('免登录URL已过期', [
                    'url' => $url,
                    'expire_time' => date('Y-m-d H:i:s', $expireTime),
                    'current_time' => date('Y-m-d H:i:s')
                ]);
            }

            return $isExpired;

        } catch (\Exception $e) {
            Log::error('检查URL过期异常', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return true;
        }
    }

    /**
     * 生成短链接（模拟功能）
     * @param string $longUrl 长链接
     * @return string
     */
    public function generateShortUrl(string $longUrl): string
    {
        try {
            // 这里可以集成第三方短链接服务
            // 比如：bit.ly、tinyurl等
            
            // 模拟生成短链接
            $shortCode = $this->generateShortCode();
            $shortUrl = 'https://short.domain/' . $shortCode;

            Log::info('生成短链接', [
                'long_url' => $longUrl,
                'short_url' => $shortUrl,
                'short_code' => $shortCode
            ]);

            // 实际项目中应该保存长短链接的映射关系
            // 这里仅作演示
            return $shortUrl;

        } catch (\Exception $e) {
            Log::error('生成短链接异常', [
                'long_url' => $longUrl,
                'error' => $e->getMessage()
            ]);

            // 异常情况下返回原链接
            return $longUrl;
        }
    }

    /**
     * 生成短码
     * @param int $length 短码长度
     * @return string
     */
    private function generateShortCode(int $length = 6): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $shortCode = '';
        
        for ($i = 0; $i < $length; $i++) {
            $shortCode .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        
        return $shortCode;
    }

    /**
     * 批量生成免登录地址
     * @param array $tokenList token列表
     * @param string $source 来源标识
     * @return array
     */
    public function batchGenerateUrls(array $tokenList, string $source = self::DEFAULT_SOURCE): array
    {
        $results = [];

        foreach ($tokenList as $index => $token) {
            if (empty($token)) {
                $results[$index] = [
                    'success' => false,
                    'token' => $token,
                    'error' => 'Token为空'
                ];
                continue;
            }

            try {
                $url = $this->generateUrl($token, $source);
                $results[$index] = [
                    'success' => true,
                    'token' => substr($token, 0, 10) . '...',
                    'url' => $url
                ];
            } catch (\Exception $e) {
                $results[$index] = [
                    'success' => false,
                    'token' => substr($token, 0, 10) . '...',
                    'error' => $e->getMessage()
                ];
            }
        }

        Log::info('批量生成免登录地址完成', [
            'total' => count($tokenList),
            'success' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success']))
        ]);

        return $results;
    }

    /**
     * 获取默认配置
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'base_url' => self::AUTO_LOGIN_BASE_URL,
            'default_source' => self::DEFAULT_SOURCE,
            'token_expire_time' => self::TOKEN_EXPIRE_TIME,
            'supported_sources' => ['tg', 'web', 'app', 'api'],
            'url_pattern' => self::AUTO_LOGIN_BASE_URL . '?fr={source}&t={token}'
        ];
    }
}