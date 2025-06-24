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
                'invite_code' => $inviteCode,
                'token' => substr($token, 0, 10) . '...'
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
                    'old_invite_code' => $user['game_invitation_code'],
                    'new_invite_code' => $inviteCode
                ]);

                return [
                    'success' => true,
                    'message' => '邀请码更新成功'
                ];
            } else {
                Log::warning('邀请码更新失败', [
                    'user_id' => $userId,
                    'invite_code' => $inviteCode
                ]);

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
     * 使用token访问邀请页面 - 使用Chrome headless
     * @param string $token 登录token
     * @return string|null
     */
    private function fetchInvitePage(string $token): ?string
    {
        Log::info('=== 开始使用Chrome headless获取邀请页面 ===', [
            'token_length' => strlen($token),
            'token_preview' => substr($token, 0, 10) . '...'
        ]);

        try {
            // 构造正确的URL，将token作为参数t传递
            $url = self::INVITE_URL . '?fr=tg&t=' . $token;
            
            Log::info('准备使用Chrome headless访问', [
                'url' => $url,
                'token_in_url' => strpos($url, $token) !== false
            ]);

            // 检查Chrome是否可用
            $chromeCmd = $this->findChromeCommand();
            if (!$chromeCmd) {
                Log::error('未找到Chrome命令，请先安装Chrome');
                return null;
            }

            Log::info('找到Chrome命令', ['chrome_cmd' => $chromeCmd]);

            // 先测试Chrome版本
            $chromeTest = shell_exec("$chromeCmd --version 2>&1");
            Log::info('Chrome版本测试', [
                'chrome_version' => $chromeTest,
                'chrome_cmd' => $chromeCmd
            ]);

            // 使用Chrome headless获取页面
            $html = $this->fetchWithChromeHeadless($url, $chromeCmd);
            
            if ($html) {
                Log::info('Chrome headless获取成功', [
                    'response_length' => strlen($html),
                    'html_preview' => substr($html, 0, 1000)
                ]);
                
                return $html;
            }

            Log::error('Chrome headless获取失败');
            return null;

        } catch (\Exception $e) {
            Log::error('Chrome headless获取异常', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }

    /**
     * 使用Chrome headless获取页面内容
     * @param string $url
     * @param string $chromeCmd
     * @return string|null
     */
    private function fetchWithChromeHeadless(string $url, string $chromeCmd): ?string
    {
        try {
            // 创建临时目录用于Chrome用户数据
            $tempDir = $this->createSafeTempDir();
            if (!$tempDir) {
                Log::error('无法创建临时目录');
                return null;
            }
            
            // 创建输出文件
            $outputFile = $tempDir . '/chrome_output.html';
            
            Log::info('临时目录创建成功', [
                'temp_dir' => $tempDir,
                'output_file' => $outputFile,
                'temp_dir_writable' => is_writable(dirname($tempDir)),
                'temp_dir_exists' => is_dir($tempDir)
            ]);
            
            // 简化测试，只测试目标URL，重点解决JavaScript执行问题
            Log::info('开始测试目标URL，重点解决JS执行时间问题');
            
            // 方法1: 使用virtual-time-budget等待JavaScript
            $commandParts = [
                $chromeCmd,
                '--headless',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--user-data-dir=' . $tempDir,
                '--virtual-time-budget=15000',  // 等待15秒
                '--run-all-compositor-stages-before-draw',
                '--dump-dom',
                '"' . $url . '"',
                '>',
                $outputFile,
                '2>&1'
            ];
            
            $command = implode(' ', $commandParts);

            Log::info('执行Chrome命令', [
                'command' => $command,
                'url' => $url,
                'output_file' => $outputFile
            ]);

            // 执行命令，设置更长的超时时间
            $result = shell_exec("timeout 60 $command");
            
            Log::info('Chrome命令执行完成', [
                'shell_result' => $result ? substr($result, 0, 500) : 'NULL',
                'shell_result_type' => gettype($result),
                'output_file_exists' => file_exists($outputFile),
                'output_file_size' => file_exists($outputFile) ? filesize($outputFile) : 0
            ]);
            
            // 读取输出文件
            $content = null;
            if (file_exists($outputFile)) {
                $content = file_get_contents($outputFile);
                Log::info('读取输出文件', [
                    'file_size' => filesize($outputFile),
                    'content_length' => strlen($content ?? ''),
                    'content_preview' => substr($content ?? '', 0, 500)
                ]);
                @unlink($outputFile);
            }
            
            // 清理临时目录
            $this->removeDirectory($tempDir);

            if (empty($content)) {
                Log::warning('Chrome输出为空');
                return null;
            }

            // 检查是否包含错误信息
            if (strpos($content, 'ERR_') !== false || strpos($content, 'Chrome') === 0) {
                Log::warning('Chrome返回错误信息', [
                    'content_preview' => substr($content, 0, 500)
                ]);
                return null;
            }

            return $content;

        } catch (\Exception $e) {
            Log::error('Chrome headless执行异常', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 查找Chrome/Chromium命令
     * @return string|null
     */
    private function findChromeCommand(): ?string
    {
        $possibleCommands = [
            'chromium-browser',
            'chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            'google-chrome-stable',
            'google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/opt/google/chrome/chrome',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
        ];

        foreach ($possibleCommands as $cmd) {
            $result = shell_exec("which $cmd 2>/dev/null");
            if (!empty($result)) {
                $foundCmd = trim($result);
                Log::info('找到浏览器命令', [
                    'command' => $foundCmd,
                    'type' => strpos($cmd, 'chromium') !== false ? 'Chromium' : 'Chrome'
                ]);
                return $foundCmd;
            }
            
            // 直接检查文件是否存在
            if (file_exists($cmd)) {
                Log::info('直接找到浏览器', [
                    'command' => $cmd,
                    'type' => strpos($cmd, 'chromium') !== false ? 'Chromium' : 'Chrome'
                ]);
                return $cmd;
            }
        }

        Log::error('未找到Chrome或Chromium命令');
        return null;
    }

    /**
     * 创建runtime临时目录
     * @return string|null
     */
    private function createSafeTempDir(): ?string
    {
        try {
            // 使用项目runtime目录
            $runtimePath = dirname(__FILE__) . '/../../runtime';
            
            // 确保runtime目录存在
            if (!is_dir($runtimePath)) {
                mkdir($runtimePath, 0755, true);
            }
            
            // 创建temp子目录
            $tempBasePath = $runtimePath . '/temp';
            if (!is_dir($tempBasePath)) {
                mkdir($tempBasePath, 0755, true);
            }
            
            // 创建Chrome专用临时目录
            $tempDir = $tempBasePath . '/chrome_' . uniqid();
            
            if (mkdir($tempDir, 0755, true)) {
                Log::info('runtime临时目录创建成功', [
                    'temp_dir' => $tempDir,
                    'permissions' => decoct(fileperms($tempDir))
                ]);
                return $tempDir;
            }
            
            Log::error('无法创建runtime临时目录');
            return null;
            
        } catch (\Exception $e) {
            Log::error('创建runtime临时目录异常', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 从HTML中解析邀请码 - 占位函数
     * @param string $html HTML内容
     * @return string|null
     */
    public function parseInviteCodeFromHtml(string $html): ?string
    {
        try {
            Log::info('开始解析HTML', [
                'html_length' => strlen($html)
            ]);

            // 先记录原始HTML内容用于调试
            Log::info('=== HTML内容开始 ===');
            Log::info('HTML: ' . substr($html, 0, 2000));
            Log::info('=== HTML内容结束 ===');

            // TODO: 这里暂时返回null，先看看HTML内容
            Log::warning('暂时返回null，需要先查看HTML内容');
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
     * 递归删除目录
     * @param string $dir
     */
    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->removeDirectory($path) : unlink($path);
            }
            rmdir($dir);
        }
    }
}