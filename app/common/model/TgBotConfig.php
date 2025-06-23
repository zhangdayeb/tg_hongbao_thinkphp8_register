<?php
declare(strict_types=1);

namespace app\common\model;

use think\Model;

/**
 * Telegram机器人配置模型
 */
class TgBotConfig extends Model
{
    /**
     * 数据表名
     */
    protected $name = 'tg_bot_config';
    
    /**
     * 类型转换
     */
    protected $type = [
        'id' => 'integer',
    ];
    
    /**
     * 只读字段
     */
    protected $readonly = ['id'];
    
    /**
     * 获取配置 - 固定返回ID=1的记录
     */
    public static function getConfig()
    {
        return self::find(1);
    }
    
    /**
     * 更新配置 - 固定更新ID=1的记录
     */
    public static function updateConfig(array $data)
    {
        // 过滤允许的字段
        $allowedFields = [
            'welcome', 'button1_name', 'button1_url', 'button2_name', 'button2_url',
            'button3_name', 'button3_url', 'button4_name', 'button4_url',
            'button5_name', 'button5_url', 'button6_name', 'button6_url'
        ];
        
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        // 更新配置
        return self::where('id', 1)->update($updateData);
    }
}