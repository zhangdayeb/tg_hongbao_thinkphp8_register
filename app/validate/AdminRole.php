<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class AdminRole extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'name'=>'require|max:200',
        'status'  =>  'integer',

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
        'name.require' => '标题必填',
        'name.max' => '标题最多200字',
        'status.require' => '状态必填',
        'status.integer' => '状态必须是整数',

    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['name','status'],
        'edit'=>['id','name','status'],
        'detail'=>['id'],
    ];

}
