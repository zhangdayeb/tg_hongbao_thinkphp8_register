<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class SysBank extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'  =>  'require',
        'status'  =>  'require',
        'is_default' =>  'require',
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message  =   [
        'id.require'     => 'ID必填',
        'status.require' => '是否删除必填',
        'is_default.require' => '是否默认必填',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'del'=>['id'],
        'default'=>['id'],
    ];

}
