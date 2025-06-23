<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class Menu extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'pid'  =>  'integer',
        'title'=>'require|max:200',
        'path'=>'max:200',
        'icon'=>'max:200',
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
//        'pid.require' => '上级必填',
        'pid.integer' => '上级必须是整数',
        'title.require' => '标题必填',
        'title.max' => '标题最多200字',
        'path.max' => '路径最多200字符',
        'icon.max' => '图标最多200字符',
        'status.require' => '状态必填',
        'status.integer' => '状态必须是整数',

    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['pid','title','status', 'path', 'icon'],
        'edit'=>['id','pid','title','status', 'path', 'icon'],
        'detail'=>['id'],

    ];

}
