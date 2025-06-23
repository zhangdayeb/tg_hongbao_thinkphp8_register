<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class Action extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'title'=>'require|max:200',
        'path'  =>  'require',

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
        'pid.require' => '上级必填',
        'pid.integer' => '上级必须是整数',
        'title.require' => '标题必填',
        'title.max' => '标题最多200字',
        'path.require' => '路径必填',

    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['title','path'],
        'edit'=>['id','title','path'],
        'detail'=>['id'],

    ];

}
