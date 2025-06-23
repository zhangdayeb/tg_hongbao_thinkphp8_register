<?php
declare (strict_types=1);

namespace app\validate;

use think\Validate;

class User extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'user_name' => 'require|max:200',
        'pwd' => 'alphaNum',
        'withdraw_pwd' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
        'is_real_name' => 'integer',
        'is_fictitious' => 'integer',
        'id' => 'require|integer',
        'money_balance'=>'integer',
        'agent_rate' => 'float',
        'money_freeze' => 'require|float',
        'invitation_code' => 'alphaNum|max:200',
        'market_uid'=>'require',
        'state'=>'require|integer',
        'change_money'=>'require|integer',
        'uid' => 'require|integer',
        'money_ststus' => 'require|integer',
        'money_change_type' => 'require|integer',
        'presenter_day' => 'integer'
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [
        'user_name.max' => '名称最多不能超过200个字符',
        'pwd.alphaNum' => '密码必须是字母和数字',
        'withdraw_pwd.integer' => '提现密码必须是数字',
        'type.integer' => '类型必须是数字',
        'status.integer' => '状态必须是数字',
        'is_real_name.integer' => '实名必须是数字',
        'is_fictitious.integer' => '虚拟账号必须是数字',
        'agent_rate.integer' => '分销必须是数字',
        'invitation_code.max' => '邀请码最多不能超过200个字符',
        'id.require' => 'ID必填',
        'id.integer' => 'ID必须是整数',
//        'admin.require' => '业务员ID必须是整数',
        'state.integer' => '修改状态必填必须是整数',
        'state.require' => '修改状态必填',
        'presenter_day.integer' => '赠送会员天数为整数',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene = [
        'edit' => ['admin','user_name','agent_rate', 'pwd', 'withdraw_pwd', 'type', 'status','is_real_name','is_fictitious','id','invitation_code','presenter_day'],
        'add' => ['admin','user_name','agent_rate', 'pwd', 'withdraw_pwd', 'type', 'status','is_real_name','is_fictitious','invitation_code', 'presenter_day'],
        'detail' => ['id'],
        'status'=> ['id','status'],
        'money'=>['uid','change_money','money_ststus','money_change_type'],
    ];

}
