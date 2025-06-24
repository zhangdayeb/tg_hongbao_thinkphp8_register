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
    private const BASE_URL = 'https://www.cg888.vip/api/core/member/frontend';
    
    // 请求超时时间
    private const TIMEOUT = 30;

    /**
     * 执行完整登录流程
     * @param string $account 账号
     * @param string $password 密码
     * @return array
     */
    public function login(string $account, string $password): array
    {
        try {
            Log::info('开始远程登录流程', ['account' => $account]);

            // 步骤1: 获取登录验证数据
            $verifyKeyResult = $this->getVerifyKey();
            if (!$verifyKeyResult['success']) {
                return [
                    'success' => false,
                    'message' => '获取验证数据失败: ' . $verifyKeyResult['message']
                ];
            }

            // 步骤2: 计算验证Key
            $calculatedKey = $this->calculateKey($verifyKeyResult['data']);
            
            // 步骤3: 登录验证
            $loginVerifyResult = $this->loginVerify($calculatedKey);
            if (!$loginVerifyResult['success']) {
                return [
                    'success' => false,
                    'message' => '登录验证失败: ' . $loginVerifyResult['message']
                ];
            }

            // 步骤4: 处理滑动验证
            // $sliderResult = $this->handleSliderVerification();
            // if (!$sliderResult['success']) {
            //     return [
            //         'success' => false,
            //         'message' => '滑动验证失败: ' . $sliderResult['message']
            //     ];
            // }

            // 步骤5: 执行最终登录
            $finalLoginResult = $this->executeLogin($account, $password);
            if (!$finalLoginResult['success']) {
                return [
                    'success' => false,
                    'message' => '最终登录失败: ' . $finalLoginResult['message']
                ];
            }

            Log::info('远程登录流程完成', [
                'account' => $account,
                'token' => substr($finalLoginResult['token'], 0, 10) . '...'
            ]);

            return [
                'success' => true,
                'message' => '登录成功',
                'token' => $finalLoginResult['token']
            ];

        } catch (\Exception $e) {
            Log::error('远程登录流程异常', [
                'account' => $account,
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
     * 获取登录验证数据
     * @return array
     */
    private function getVerifyKey(): array
    {
        try {
            $url = self::BASE_URL . '/login/verify-key/get';
            $response = $this->makeApiRequest($url, 'POST');

            if (!$response) {
                return ['success' => false, 'message' => '请求失败'];
            }

            if ($response['code'] != 200) {
                return ['success' => false, 'message' => $response['message'] ?? '获取验证数据失败'];
            }

            return [
                'success' => true,
                'data' => $response['data']
            ];

        } catch (\Exception $e) {
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
     * @return int
     */
    private function calculateKey(array $data): int
    {
        $digit = $data['digit'];
        $random = $data['random'];
        $array = $data['array'];

        // 计算公式
        $result = floor(pow(10, $digit) * $random) - $array[0] + $array[1] - $array[2] + $array[3];

        Log::info('验证Key计算', [
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
     * @return array
     */
    private function loginVerify(int $verifyKey): array
    {
        try {
            $url = self::BASE_URL . '/login/verify';
            $data = ['verifyKey' => $verifyKey];
            
            $response = $this->makeApiRequest($url, 'POST', $data);

            if (!$response) {
                return ['success' => false, 'message' => '登录验证请求失败'];
            }

            if ($response['code'] != 200) {
                return ['success' => false, 'message' => $response['message'] ?? '登录验证失败'];
            }

            return ['success' => true];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '登录验证异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 处理滑动验证
     * @return array
     */
    private function handleSliderVerification(): array
    {
        try {
            // 1. 获取滑动验证码
            $getSliderResult = $this->getSliderVerification();
            if (!$getSliderResult['success']) {
                return $getSliderResult;
            }

            // 2. 验证滑动码（这里简化处理，实际项目中需要图像识别）
            $verifySliderResult = $this->verifySliderVerification($getSliderResult['authCodeKey']);
            if (!$verifySliderResult['success']) {
                return $verifySliderResult;
            }

            return ['success' => true];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '滑动验证异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取滑动验证码
     * @return array
     */
    private function getSliderVerification(): array
    {
        try {
            // 生成authCodeKey（时间戳）
            $authCodeKey = (string)(time() * 1000);
            
            $url = self::BASE_URL . '/img-verification/slider/get';
            $data = ['authCodeKey' => $authCodeKey];
            
            $response = $this->makeApiRequest($url, 'POST', $data);

            if (!$response) {
                return ['success' => false, 'message' => '获取滑动验证码请求失败'];
            }

            if ($response['code'] != 200) {
                return ['success' => false, 'message' => $response['message'] ?? '获取滑动验证码失败'];
            }

            return [
                'success' => true,
                'authCodeKey' => $response['data']['authCodeKey']
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '获取滑动验证码异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 验证滑动码
     * @param string $authCodeKey 验证码Key
     * @return array
     */
    private function verifySliderVerification(string $authCodeKey): array
    {
        try {
            $url = self::BASE_URL . '/img-verification/slider/verify';
            
            // 这里简化处理，实际项目中需要图像识别算法
            // 模拟一个验证码（实际应该通过图像识别获得）
            $code = $this->generateMockSliderCode();
            
            $data = [
                'authCodeKey' => $authCodeKey,
                'code' => $code
            ];
            
            $response = $this->makeApiRequest($url, 'POST', $data);

            if (!$response) {
                return ['success' => false, 'message' => '验证滑动码请求失败'];
            }

            if ($response['code'] != 200) {
                return ['success' => false, 'message' => $response['message'] ?? '验证滑动码失败'];
            }

            return ['success' => true];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '验证滑动码异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 执行最终登录
     * @param string $account 账号
     * @param string $password 密码
     * @return array
     */
    private function executeLogin(string $account, string $password): array
    {
        try {
            $url = self::BASE_URL . '/login';
            $data = [
                'account' => $account,
                'password' => $password
            ];
            
            $response = $this->makeApiRequest($url, 'POST', $data);

            if (!$response) {
                return ['success' => false, 'message' => '登录请求失败'];
            }

            if ($response['code'] != 200) {
                return ['success' => false, 'message' => $response['message'] ?? '登录失败'];
            }

            return [
                'success' => true,
                'token' => $response['data']
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '登录异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 生成模拟滑动验证码（实际项目中需要图像识别）
     * @return string
     */
    private function generateMockSliderCode(): string
    {
        // 这里返回一个模拟的验证码
        // 实际项目中需要通过图像识别算法分析滑动距离
        return 'mock_slider_code_' . mt_rand(100000, 999999);
    }

    /**
     * 发送API请求
     * @param string $url 请求URL
     * @param string $method 请求方法
     * @param array $data 请求数据
     * @return array|null
     */
    private function makeApiRequest(string $url, string $method = 'POST', array $data = []): ?array
    {
        try {
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            // 设置请求头
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36'
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
                Log::error('CURL请求错误', [
                    'url' => $url,
                    'error' => $error
                ]);
                return null;
            }

            if ($httpCode !== 200) {
                Log::error('HTTP请求错误', [
                    'url' => $url,
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                return null;
            }

            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON解析错误', [
                    'url' => $url,
                    'response' => $response,
                    'json_error' => json_last_error_msg()
                ]);
                return null;
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('API请求异常', [
                'url' => $url,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }
}