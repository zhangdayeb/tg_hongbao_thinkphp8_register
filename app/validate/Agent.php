<?php
declare (strict_types=1);

namespace app\validate;

use think\Validate;

class Agent extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'agent_account' => 'require|max:32',
        'agent_pwd' => 'alphaNum|max:64',
        'agent_type' => 'require|integer',
        'status' => 'integer',
        'id' => 'require',
        'tg_url' => 'alphaNum|max:200',
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
        'status.integer' => '状态必须是数字',
        'id.require' => 'ID必填',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene = [
        'add' => ['agent_account', 'agent_pwd', 'agent_type', 'status'],
        'edit' => ['agent_account', 'agent_pwd', 'agent_type', 'status', 'id'],
        'detail' => ['id'],
        'status' => ['id', 'status'],
    ];

}
