<?php
declare (strict_types=1);

namespace app\validate;

use think\Validate;

class Order extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'status' => 'integer|require',
        'id' => 'require|integer'
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'agent_account.require' => '账号名称必填',
        'agent_account.max' => '账号名称最多不能超过32个字符',
        'agent_pwd.require' => '密码必填',
        'agent_pwd.max' => '密码最多不能超过32个字符',
        'agent_pwd.alphaNum' => '密码必须是字母和数字',
        'agent_type.require' => '代理类型必填',
        'agent_type.integer' => '代理类型必须是数字',
        //'status.require' => '状态必填',
        'status.integer' => 'status必须是数字',
        'status.require' => 'status必须填写',
        'id.require' => 'ID必填',
        'id.integer' => 'ID必须是数字',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene = [
        'add' => [],
        'edit' => ['id', 'order'],
    ];

}
