<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class SysConfig extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'name'=>'require',
        'value'=>'require',
        'mark'=>'max:200',
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
        'name.require' => '配置名称必填',
        'value.require' => '约束条件必填',
        'mark.max' => '备注说明最多 200字符',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['name','value', 'mark'],
        'edit'=>['id','name','value', 'mark'],
        'detail'=>['id'],
    ];

}
