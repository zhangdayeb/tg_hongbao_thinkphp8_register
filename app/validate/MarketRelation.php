<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class MarketRelation extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'aid'=>  'require|integer',
        'a_level'=>  'require|integer',
        'pid'=>  'require|integer',
        'p_level'=>  'require|integer',
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
        'aid.require' => 'aid必填',
        'aid.integer' => 'aid必须是整数',
        'a_level.require' => '等级必填',
        'a_level.integer' => '等级必须是整数',
        'pid.require' => 'pid必填',
        'pid.integer' => 'pid必须是整数',
        'p_level.require' => '父级等级必填',
        'p_level.integer' => '父级等级必须是整数',

    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['aid','a_level','pid','p_level'],
        'edit'=>['id','aid','a_level','pid','p_level'],
        'detail'=>['id'],
        'status'=>['id','status'],
    ];

}
