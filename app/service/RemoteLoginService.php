<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Log;

/**
 * 远程登录服务
 * 负责处理完整的远程登录流程
 */
class RemoteLoginService
{
    // API基础URL
    private string $BASE_URL = env('WEB_URL', '').'api/core/member/frontend';
    
    // 请求超时时间
    private const TIMEOUT = 30;
    
    // 重试配置
    private const MAX_RETRIES = 5;
    private const RETRY_DELAY = 10; // 秒

    /**
     * 执行完整登录流程
     * @param string $account 账号
     * @param string $password 密码
     * @return array
     */
    public function login(string $account, string $password): array
    {
        $startTime = microtime(true);
        $requestId = uniqid('login_');
        
        try {
            Log::info('开始远程登录流程', [
                'request_id' => $requestId,
                'account' => $account,
                'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
            ]);

            // 步骤1: 获取登录验证数据（支持重试）
            Log::info('步骤1: 开始获取验证数据', ['request_id' => $requestId]);
            $verifyKeyResult = $this->executeWithRetry(
                fn() => $this->getVerifyKey($requestId),
                '获取验证数据',
                $requestId
            );
            
            if (!$verifyKeyResult['success']) {
                return [
                    'success' => false,
                    'message' => $this->getFriendlyErrorMessage($verifyKeyResult['message'])
                ];
            }
            Log::info('步骤1完成: 验证数据获取成功', ['request_id' => $requestId]);

            // 步骤2: 计算验证Key（本地计算，无需重试）
            Log::info('步骤2: 开始计算验证Key', ['request_id' => $requestId]);
            $calculatedKey = $this->calculateKey($verifyKeyResult['data'], $requestId);
            Log::info('步骤2完成: 验证Key计算完成', [
                'request_id' => $requestId,
                'calculated_key' => $calculatedKey
            ]);
            
            // 步骤3: 登录验证（支持重试）
            Log::info('步骤3: 开始登录验证', ['request_id' => $requestId]);
            $loginVerifyResult = $this->executeWithRetry(
                fn() => $this->loginVerify($calculatedKey, $requestId),
                '登录验证',
                $requestId
            );
            
            if (!$loginVerifyResult['success']) {
                return [
                    'success' => false,
                    'message' => $this->getFriendlyErrorMessage($loginVerifyResult['message'])
                ];
            }
            Log::info('步骤3完成: 登录验证成功', ['request_id' => $requestId]);

            // 步骤4: 执行最终登录（支持重试）
            Log::info('步骤4: 开始最终登录', ['request_id' => $requestId]);
            $finalLoginResult = $this->executeWithRetry(
                fn() => $this->executeLogin($account, $password, $requestId),
                '最终登录',
                $requestId
            );
            
            if (!$finalLoginResult['success']) {
                return [
                    'success' => false,
                    'message' => $this->getFriendlyErrorMessage($finalLoginResult['message'])
                ];
            }
            Log::info('步骤4完成: 最终登录成功', ['request_id' => $requestId]);

            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info('远程登录流程完成', [
                'request_id' => $requestId,
                'account' => $account,
                'token' => substr($finalLoginResult['token'], 0, 10) . '...',
                'total_time' => $totalTime . 'ms'
            ]);

            return [
                'success' => true,
                'message' => '登录成功',
                'token' => $finalLoginResult['token']
            ];

        } catch (\Exception $e) {
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error('远程登录流程异常', [
                'request_id' => $requestId,
                'account' => $account,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_time' => $totalTime . 'ms'
            ]);

            return [
                'success' => false,
                'message' => $this->getFriendlyErrorMessage($e->getMessage())
            ];
        }
    }

    /**
     * 带重试机制的执行方法
     * @param callable $operation 要执行的操作
     * @param string $operationName 操作名称
     * @param string $requestId 请求ID
     * @return array
     */
    private function executeWithRetry(callable $operation, string $operationName, string $requestId): array
    {
        $maxRetries = self::MAX_RETRIES;
        $retryDelay = self::RETRY_DELAY;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $attemptStartTime = microtime(true);
            
            Log::info("第{$attempt}次尝试{$operationName}", [
                'request_id' => $requestId,
                'attempt' => $attempt,
                'max_retries' => $maxRetries
            ]);
            
            try {
                $result = $operation();
                $attemptTime = round((microtime(true) - $attemptStartTime) * 1000, 2);
                
                if ($result['success']) {
                    Log::info("{$operationName}成功", [
                        'request_id' => $requestId,
                        'attempt' => $attempt,
                        'attempt_time' => $attemptTime . 'ms'
                    ]);
                    return $result;
                }
                
                // 检查是否应该重试
                if (!$this->isRetryableError($result['message'])) {
                    Log::warning("{$operationName}失败，错误不可重试", [
                        'request_id' => $requestId,
                        'attempt' => $attempt,
                        'error' => $result['message'],
                        'attempt_time' => $attemptTime . 'ms'
                    ]);
                    return $result;
                }
                
                // 如果不是最后一次尝试，等待后重试
                if ($attempt < $maxRetries) {
                    Log::warning("第{$attempt}次{$operationName}失败，{$retryDelay}秒后重试", [
                        'request_id' => $requestId,
                        'attempt' => $attempt,
                        'error' => $result['message'],
                        'attempt_time' => $attemptTime . 'ms',
                        'next_retry_in' => $retryDelay . '秒'
                    ]);
                    sleep($retryDelay);
                } else {
                    Log::error("第{$attempt}次{$operationName}失败，已达最大重试次数", [
                        'request_id' => $requestId,
                        'attempt' => $attempt,
                        'error' => $result['message'],
                        'attempt_time' => $attemptTime . 'ms'
                    ]);
                    return [
                        'success' => false,
                        'message' => "经过{$maxRetries}次重试后{$operationName}仍然失败: " . $result['message']
                    ];
                }
                
            } catch (\Exception $e) {
                $attemptTime = round((microtime(true) - $attemptStartTime) * 1000, 2);
                
                if (!$this->isRetryableError($e->getMessage())) {
                    Log::error("{$operationName}异常，不可重试", [
                        'request_id' => $requestId,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'attempt_time' => $attemptTime . 'ms'
                    ]);
                    return [
                        'success' => false,
                        'message' => "{$operationName}异常: " . $e->getMessage()
                    ];
                }
                
                if ($attempt < $maxRetries) {
                    Log::warning("第{$attempt}次{$operationName}异常，{$retryDelay}秒后重试", [
                        'request_id' => $requestId,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'attempt_time' => $attemptTime . 'ms'
                    ]);
                    sleep($retryDelay);
                } else {
                    Log::error("第{$attempt}次{$operationName}异常，已达最大重试次数", [
                        'request_id' => $requestId,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'attempt_time' => $attemptTime . 'ms'
                    ]);
                    return [
                        'success' => false,
                        'message' => "经过{$maxRetries}次重试后{$operationName}仍然异常: " . $e->getMessage()
                    ];
                }
            }
        }
        
        // 理论上不会执行到这里
        return [
            'success' => false,
            'message' => "{$operationName}重试失败"
        ];
    }

    /**
     * 判断错误是否可以重试
     * @param string $error 错误信息
     * @return bool
     */
    private function isRetryableError(string $error): bool
    {
        // 不可重试的错误类型（业务逻辑错误）
        $nonRetryableErrors = [
            'frequent operation',
            'frequent_operation', 
            'account not found',
            'password incorrect',
            'invalid password',
            'account disabled',
            'account locked',
            'permission denied',
            'unauthorized',
            'forbidden',
            'invalid credentials',
            'auth failed'
        ];
        
        $lowerError = strtolower($error);
        
        foreach ($nonRetryableErrors as $nonRetryable) {
            if (strpos($lowerError, strtolower($nonRetryable)) !== false) {
                return false;
            }
        }
        
        // 可重试的错误类型（网络/系统错误）
        $retryableErrors = [
            'timeout',
            'connection',
            'network',
            'curl',
            'http error 5', // 5xx错误
            'server error',
            'service unavailable',
            'internal server error',
            'bad gateway',
            'gateway timeout'
        ];
        
        foreach ($retryableErrors as $retryable) {
            if (strpos($lowerError, strtolower($retryable)) !== false) {
                return true;
            }
        }
        
        // 默认对未知错误进行重试（保守策略）
        return true;
    }

    /**
     * 获取友好的错误提示信息
     * @param string $error 原始错误信息
     * @return string
     */
    private function getFriendlyErrorMessage(string $error): string
    {
        $lowerError = strtolower($error);
        
        // 频率限制
        if (strpos($lowerError, 'frequent operation') !== false || 
            strpos($lowerError, 'frequent_operation') !== false) {
            return '登录过于频繁，请稍后重试';
        }
        
        // 网络相关错误
        if (strpos($lowerError, 'timeout') !== false) {
            return '网络连接超时，请检查网络后重试';
        }
        
        if (strpos($lowerError, 'connection') !== false || 
            strpos($lowerError, 'network') !== false) {
            return '网络连接失败，请检查网络后重试';
        }
        
        // 服务器错误
        if (strpos($lowerError, 'server error') !== false || 
            strpos($lowerError, 'service unavailable') !== false ||
            strpos($lowerError, 'http error 5') !== false) {
            return '服务器暂时不可用，请稍后重试';
        }
        
        // 认证错误
        if (strpos($lowerError, 'unauthorized') !== false || 
            strpos($lowerError, 'forbidden') !== false ||
            strpos($lowerError, 'auth failed') !== false) {
            return '认证失败，请检查账号信息';
        }
        
        // 账号相关错误
        if (strpos($lowerError, 'account not found') !== false) {
            return '账号不存在';
        }
        
        if (strpos($lowerError, 'password incorrect') !== false || 
            strpos($lowerError, 'invalid password') !== false) {
            return '密码错误';
        }
        
        if (strpos($lowerError, 'account disabled') !== false || 
            strpos($lowerError, 'account locked') !== false) {
            return '账号已被禁用或锁定';
        }
        
        // 重试失败的情况
        if (strpos($lowerError, '经过') !== false && strpos($lowerError, '次重试') !== false) {
            return '网络不稳定，请稍后重试';
        }
        
        // 默认友好提示
        return '登录服务暂时不可用，请稍后重试';
    }

    /**
     * 获取登录验证数据
     * @param string $requestId 请求ID
     * @return array
     */
    private function getVerifyKey(string $requestId): array
    {
        try {
            $url = $this->BASE_URL . '/login/verify-key/get';
            
            Log::info('发送获取验证数据请求', [
                'request_id' => $requestId,
                'url' => $url
            ]);
            
            $response = $this->makeApiRequest($url, 'POST', [], $requestId);

            if (!$response) {
                return ['success' => false, 'message' => '请求失败'];
            }

            if ($response['code'] != 200) {
                return ['success' => false, 'message' => $response['message'] ?? '获取验证数据失败'];
            }

            Log::info('获取验证数据成功', [
                'request_id' => $requestId,
                'data_keys' => array_keys($response['data'])
            ]);

            return [
                'success' => true,
                'data' => $response['data']
            ];

        } catch (\Exception $e) {
            Log::error('获取验证数据异常', [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => '获取验证数据异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 计算验证Key
     * 公式: (10 ^ digit * random) 无条件舍去小数位 - array[0] + array[1] - array[2] + array[3]
     * @param array $data 验证数据
     * @param string $requestId 请求ID
     * @return int
     */
    private function calculateKey(array $data, string $requestId): int
    {
        $digit = $data['digit'];
        $random = $data['random'];
        $array = $data['array'];

        // 计算公式
        $result = floor(pow(10, $digit) * $random) - $array[0] + $array[1] - $array[2] + $array[3];

        Log::info('验证Key计算', [
            'request_id' => $requestId,
            'digit' => $digit,
            'random' => $random,
            'array' => $array,
            'calculated_key' => $result
        ]);

        return (int)$result;
    }

    /**
     * 登录验证
     * @param int $verifyKey 计算出的验证Key
     * @param string $requestId 请求ID
     * @return array
     */
    private function loginVerify(int $verifyKey, string $requestId): array
    {
        try {
            $url = $this->BASE_URL . '/login/verify';
            $data = ['verifyKey' => $verifyKey];
            
            Log::info('发送登录验证请求', [
                'request_id' => $requestId,
                'url' => $url,
                'verify_key' => $verifyKey
            ]);
            
            $response = $this->makeApiRequest($url, 'POST', $data, $requestId);

            if (!$response) {
                return ['success' => false, 'message' => '登录验证请求失败'];
            }

            Log::info('登录验证响应', [
                'request_id' => $requestId,
                'response_code' => $response['code'] ?? 'null',
                'response_message' => $response['message'] ?? 'null'
            ]);

            if ($response['code'] != 200) {
                return ['success' => false, 'message' => $response['message'] ?? '登录验证失败'];
            }

            return ['success' => true];

        } catch (\Exception $e) {
            Log::error('登录验证异常', [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => '登录验证异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 执行最终登录
     * @param string $account 账号
     * @param string $password 密码
     * @param string $requestId 请求ID
     * @return array
     */
    private function executeLogin(string $account, string $password, string $requestId): array
    {
        try {
            $url = $this->BASE_URL . '/login';
            $data = [
                'account' => $account,
                'password' => $password
            ];
            
            Log::info('发送最终登录请求', [
                'request_id' => $requestId,
                'url' => $url,
                'account' => $account
            ]);
            
            $response = $this->makeApiRequest($url, 'POST', $data, $requestId);

            if (!$response) {
                return ['success' => false, 'message' => '登录请求失败'];
            }

            Log::info('最终登录响应', [
                'request_id' => $requestId,
                'response_code' => $response['code'] ?? 'null',
                'response_message' => $response['message'] ?? 'null',
                'has_token' => isset($response['data']) && !empty($response['data'])
            ]);

            if ($response['code'] != 200) {
                return ['success' => false, 'message' => $response['message'] ?? '登录失败'];
            }

            return [
                'success' => true,
                'token' => $response['data']
            ];

        } catch (\Exception $e) {
            Log::error('最终登录异常', [
                'request_id' => $requestId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => '登录异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 发送API请求
     * @param string $url 请求URL
     * @param string $method 请求方法
     * @param array $data 请求数据
     * @param string $requestId 请求ID
     * @return array|null
     */
    private function makeApiRequest(string $url, string $method = 'POST', array $data = [], string $requestId = ''): ?array
    {
        $startTime = microtime(true);
        
        try {
            Log::info('发起API请求', [
                'request_id' => $requestId,
                'url' => $url,
                'method' => $method,
                'data_size' => count($data)
            ]);
            
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 连接超时
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            // 设置请求头
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36',
                'X-Request-ID: ' . $requestId // 添加请求ID到头部便于追踪
            ]);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    $jsonData = json_encode($data);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                    
                    Log::info('请求数据', [
                        'request_id' => $requestId,
                        'json_data' => $jsonData
                    ]);
                }
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $error = curl_error($ch);
            curl_close($ch);

            $requestTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('API请求完成', [
                'request_id' => $requestId,
                'http_code' => $httpCode,
                'request_time' => $requestTime . 'ms',
                'curl_total_time' => round($totalTime * 1000, 2) . 'ms',
                'response_length' => strlen($response)
            ]);

            if ($error) {
                Log::error('CURL请求错误', [
                    'request_id' => $requestId,
                    'url' => $url,
                    'error' => $error,
                    'request_time' => $requestTime . 'ms'
                ]);
                return null;
            }

            if ($httpCode !== 200) {
                Log::error('HTTP请求错误', [
                    'request_id' => $requestId,
                    'url' => $url,
                    'http_code' => $httpCode,
                    'response' => substr($response, 0, 500), // 只记录前500字符
                    'request_time' => $requestTime . 'ms'
                ]);
                return null;
            }

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON解析错误', [
                    'request_id' => $requestId,
                    'url' => $url,
                    'response' => substr($response, 0, 500),
                    'json_error' => json_last_error_msg(),
                    'request_time' => $requestTime . 'ms'
                ]);
                return null;
            }

            Log::info('API响应解析成功', [
                'request_id' => $requestId,
                'response_code' => $result['code'] ?? 'null',
                'request_time' => $requestTime . 'ms'
            ]);

            return $result;

        } catch (\Exception $e) {
            $requestTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error('API请求异常', [
                'request_id' => $requestId,
                'url' => $url,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_time' => $requestTime . 'ms'
            ]);
            return null;
        }
    }
}