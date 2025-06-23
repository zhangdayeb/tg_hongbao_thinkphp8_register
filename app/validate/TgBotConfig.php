<?php

namespace app\validate;

use think\Validate;

/**
 * Telegram机器人配置验证器
 */
class TgBotConfig extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'welcome' => 'require',
        'button1_name' => 'max:200',
        'button2_name' => 'max:200',
        'button3_name' => 'max:200',
        'button4_name' => 'max:200',
        'button5_name' => 'max:200',
        'button6_name' => 'max:200',
    ];

    /**
     * 错误信息
     */
    protected $message = [
        'welcome.require' => '欢迎消息不能为空',
        'button1_name.max' => '按钮1名称长度不能超过200字符',
        'button2_name.max' => '按钮2名称长度不能超过200字符',
        'button3_name.max' => '按钮3名称长度不能超过200字符',
        'button4_name.max' => '按钮4名称长度不能超过200字符',
        'button5_name.max' => '按钮5名称长度不能超过200字符',
        'button6_name.max' => '按钮6名称长度不能超过200字符',
    ];
}