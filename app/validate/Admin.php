<?php
declare (strict_types = 1);

namespace app\validate;

use app\common\model\AdminModel;
use think\Validate;

class Admin extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'user_name'  =>  'require|max:200',
        'pwd' =>  'alphaNum',
        'role'=>'require',
        'market_level'=>'require',
        'remarks'=>'max:200',
        'phone'=>'max:200',
        'invitation_code'=>'max:200|unique:'.AdminModel::class,
        'id'=>'require',
        'price_single_low'=>'require|float',
        'price_single_high'=>'require|float',
        'free_time'=>'require|integer',
        'price_hour'=>'require|float',
        'price_day'=>'require|float',
        'price_week'=>'require|float',
        'price_month'=>'require|float',
        'price_quarter'=>'require|float',
        'price_year'=>'require|float',
        'price_forever'=>'require|float'
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message  =   [
        'user_name.require' => '名称必填',
        'user_name.max'     => '名称最多不能超过200个字符',
        'pwd.require' => '密码必填',
        'role.require'     => '角色必填',
        'market_level.require' => '市场部级别必填',
        'remarks.max'     => '备注最多不能超过200个字符',
        'invitation_code.max'     => '邀请码最多不能超过200个字符',
        'invitation_code.unique'     => '邀请码重复，请重新再试',
        'price_single_low.require'     => '单片价格最底必填',
        'price_single_high.require'     => '单片价格最高必填',
        'free_time.require'     => '免费时长必填',
        'price_hour.require'     => '包小时必填',
        'price_day.require'     => '包天必填',
        'price_week.require'     => '包周必填',
        'price_month.require'     => '包月必填',
        'price_quarter.require'     => '包季度必填',
        'price_year.require'     => '包年必填',
        'price_forever.require'     => '包永久必填',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['user_name','pwd','role','market_level','remarks','invitation_code'],
        'edit'=>['user_name','pwd','role','market_level','remarks','id'],
        'detail'=>['id'],
        'agent_edit'=>['price_single_low', 'price_single_high', 'price_hour', 'price_day', 'price_week', 'price_month', 'price_quarter', 'price_year', 'price_forever'],
    ];

}
