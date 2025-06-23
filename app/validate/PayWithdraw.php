<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class PayWithdraw extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'pwd'=>  'require',
        'money'=>  'require|integer',
        // 'pay_type'=>  'require|max:200',
        // 'u_bank_name'=>  'require|max:200',
        // 'u_back_card'=>  'require|max:200',
        'u_back_user_name'=>  'require|max:200',
        // 'img_url'=> 'require|max:200',
        // 'title'=>'require|max:200',
        // 'path'  =>  'require',

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
        'pwd.require' => '登录密码必填',
        'money.require' => '提现必填',
        'money.integer' => '提现必须是整数',
        'pay_type.require' => '支付方式必填',
        'pay_type.max' => '支付方式最多200字',
        'u_bank_name.require' => '收款银行名必填',
        'u_bank_name.max' => '收款银行名最多200字',
        'u_back_card.require' => '收款账号必填',
        'u_back_card.max' => '收款账号最多200字',
        'u_back_user_name.require' => '收款名必填',
        'u_back_user_name.max' => '收款名最多200字',
        'img_url.require' => '请上传收款码',
        'img_url.max' => '收款名最多200长度',

    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['money','pay_type', 'u_bank_name', 'u_back_card', 'u_back_user_name', 'img_url'],
        'edit'=>['id', 'money','pay_type', 'u_bank_name', 'u_back_card', 'u_back_user_name'],
        'detail'=>['id'],
    ];

}
