<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class Notify extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'type'=>  'require|integer',
        'status'=>  'integer',
//        'unique'=>  'require',
        'mark'=>  'require',
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
        'status.integer' => '状态必须是整数',
        'type.require' => '类型必填',
        'type.integer' => '类型必须是整数',
        'mark.require' => '通知内容必填',
        'unique.require' => '通知人必填',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['status','type','mark',],
        'edit'=>['id','status','type','mark',],
        'detail'=>['id'],
        'status'=>['id','status'],
    ];

}
