<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class Login extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'user_name'=>  'require|max:200',
        'pwd'=>'require|max:200',
        'captcha'  =>  'require',

    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message  =   [
        'user_name.require' => '用户名必填',
        'user_name.max' => '用户名不能大于200字符',
        'pwd.require' => '密码必填',
        'pwd.max' => '密码不能大于200字符',
        'captcha.require' => '验证码必填',
        'captcha.integer' => '验证码必须是数字',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'admin_login'=>['user_name','pwd','captcha'],
    ];

}
