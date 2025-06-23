<?php

namespace app\admin\controller\telegram;

use app\admin\controller\Base;
use app\common\model\TgBotConfig;
use app\common\traites\PublicCrudTrait;

/**
 * Telegram机器人配置管理控制器
 */
class TGConfig extends Base
{
    protected $model;
    use PublicCrudTrait;

    /**
     * 初始化
     */
    public function initialize()
    {
        $this->model = new TgBotConfig();
        parent::initialize();
    }

    /**
     * 获取机器人配置
     */
    public function getConfig()
    {
        try {
            $config = TgBotConfig::getConfig();
            
            if (!$config) {
                return $this->failed('配置不存在');
            }
            
            return $this->success($config);
            
        } catch (\Exception $e) {
            return $this->failed('获取配置失败：' . $e->getMessage());
        }
    }

    /**
     * 更新机器人配置
     */
    public function updateConfig()
    {
        try {
            // 获取POST数据
            $postFields = [
                'welcome', 'button1_name', 'button1_url', 'button2_name', 'button2_url',
                'button3_name', 'button3_url', 'button4_name', 'button4_url',
                'button5_name', 'button5_url', 'button6_name', 'button6_url'
            ];
            
            $post = $this->request->only($postFields, 'post', null);
            
            // 基本验证
            if (empty($post['welcome'])) {
                return $this->failed('欢迎消息不能为空');
            }
            
            // 更新配置
            $result = TgBotConfig::updateConfig($post);
            
            // ThinkPHP的update方法：影响行数>=0表示成功，false表示失败
            if ($result !== false) {
                return $this->success([], 1, '配置更新成功');
            } else {
                return $this->failed('配置更新失败');
            }
            
        } catch (\Exception $e) {
            return $this->failed('更新配置失败：' . $e->getMessage());
        }
    }
}