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

    }
    

}