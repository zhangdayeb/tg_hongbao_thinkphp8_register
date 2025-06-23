<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class Status extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'status'=>  'require|integer',
        'show'=>  'require|integer',
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message  =   [
        'id.require' => 'ID必填',
        'id.integer' => 'ID必须是整数',
        'status.require' => '状态必填',
        'show.require' => '状态必填',
        'status.integer' => '状态必须是整数',
        'show.integer' => '状态必须是整数',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'edit'=>['id','status'],
        'status'=>['id','status'],
        'show'=>['id','show'],
        'detail'=>['id'],

    ];

}
