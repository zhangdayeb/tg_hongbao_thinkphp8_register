<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class MarketLevel extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'mkey'=>  'require|integer',
        'mvalue'=>  'require|max:200',
        'morder'=>  'integer',
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
        'mkey.require' => 'key必须填写',
        'mkey.integer' => 'key必须是数字',
        'mvalue.require' => 'value必填',
        'mvalue.max' => 'value不可大于200字符',
        'morder.require' => '排序必须是数字',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['mkey','mvalue','morder',],
        'edit'=>['id','mkey','mvalue','morder',],
        'detail'=>['id'],
    ];

}
