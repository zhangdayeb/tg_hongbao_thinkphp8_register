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
 * 认证控制器
 * 处理 Telegram 认证页面显示
 */
class AuthController extends BaseController
{
    /**
     * 显示 Telegram 认证页面
     * GET /auth
     */
    public function auth()
    {
        try {
            // 可以传递一些数据到模板
            $data = [
                'api_base_url' => request()->domain(), // 当前域名
                'page_title' => 'Telegram 认证',
                'timestamp' => time()
            ];
            
            // 使用 ThinkPHP8 的模板引擎加载模板
            // 模板文件路径: /view/Index/auth.html
            return $this->fetch('Index/auth', $data);
            
        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('Auth page error: ' . $e->getMessage());
            
            // 返回错误信息
            return $this->error('页面加载失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 错误响应辅助方法
     */
    private function error($message, $code = 400)
    {
        // 可以返回 JSON 格式的错误信息
        return json([
            'success' => false,
            'message' => $message
        ], $code);
        
        // 或者返回简单的 HTML 错误页面
        // return Response::create(
        //     "<html><body><h1>错误</h1><p>{$message}</p></body></html>", 
        //     'html'
        // );
    }
}