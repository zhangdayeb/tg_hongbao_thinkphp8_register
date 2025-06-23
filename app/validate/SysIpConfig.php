<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class SysIpConfig extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'domains'=>'require',
        'domain'=>'require',
        'type'=>'require|integer',
        'status'=>'require|integer',
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message  =   [
        'id.require' => 'ID必填',
        'domains.require' => '域名必填 ',
        'domain.require' => '域名必填 ',
        'type.require' => '类型必填',
        'type.integer' => '类型为整数',
        'status.require' => '状态必填',
        'status.integer' => '状态为整数',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['domains', 'type', 'status'],
        'edit'=>['domain', 'type', 'status', 'id'],
        'detail'=>['id'],
    ];

}
