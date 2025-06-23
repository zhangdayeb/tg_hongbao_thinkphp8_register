<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class Notice extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'status'=>  'integer',
        'position'=>  'require|integer',
        'content'=>'require',
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
        'position.require' => '公告位置必填',
        'position.integer' => '公告位置必须是整数',
        'content.require' => '内容必填',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['status','position','content'],
        'edit'=>['id','status','position','content'],
        'detail'=>['id'],
        'status'=>['id','status'],
    ];

}
