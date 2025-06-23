<?php
declare (strict_types=1);

namespace app\validate;

use think\Validate;

class AgentManage extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'user_name' => 'require|max:200', //账号
        'pwd' => 'alphaNum',                // 密码
        'role' => 'require',                // 组别
        'withdraw_pwd' => 'max:200',        // 提现密码
        'kou_start' => 'require',           //扣量起始
        'kou_rate' => 'require',           //扣量比例
        'profit_rate' => 'require',         //代理抽成
        'channel_id' => 'max:200',       //支付渠道
        'status' => 'require',       //状态
        'id' => 'require',       //状态
        'pid' => 'require',       //状态
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'user_name.require' => '代理账号必填',
        'user_name.max' => '代理账号最多不能超过200个字符',
        'pwd.require' => '密码必填',
        'role.require' => '所属组必填',
        'kou_start.require' => '扣量起始必填',
        'kou_rate.require' => '扣量比例必填',
        'withdraw_pwd.max' => '提现密码最大 200字符',        // 提现密码
        'profit_rate.require' => '代理抽成必填',
        'channel_id.max' => '支付渠道字符串不超过 200个字符',
        'status.require' => '状态必填',
        'id.require' => 'ID必填',
        'pid.require' => '上级代理商ID必填',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene = [
        'add' => ['user_name', 'pwd', 'role', 'kou_start', 'withdraw_pwd', 'pid', 'kou_rate', 'profit_rate', 'channel_id'],
        'edit' => ['user_name', 'profit_rate', 'role', 'kou_start', 'pwd', 'channel_id', 'status', 'id', 'withdraw_pwd', 'pid', 'kou_rate'],
        'detail' => ['id'],
        'chanel' => ['id', 'channel_id'],
    ];

}
