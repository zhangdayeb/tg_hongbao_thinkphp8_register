<?php

namespace app\validate;

use think\Validate;

class Channel extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'channel_name'=>'require',
        'app_id'=>'require',
        'app_key'=>'require',
        'pay_channel'=>'require',
        'gateway'=>'require',
        'channel_tag'=>'require',
        'status'=>'require|integer',
        'show_sort'=>'integer',
        'mid'=>'max:200',
        'pay_user_tag'=>'max:200',
        'ontime_is_open_or_close' => 'in:open,close'
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message  =   [
        'id.require' => 'ID必填',
        'id.integer' => 'ID必须为整数',
        'channel_name.require' => '标题必填 ',
        'app_id.require' => 'app_id必填',
        'app_key.require' => 'app_key必填',
        'pay_channel.require' => '支付渠道必填 ',
        'gateway.require' => '网关地址必填',
        'channel_tag.require' => '标识必填',
        'status.require' => '状态必填',
        'status.integer' => '状态为整数',
        'show_sort.integer' => '排序为整数',
        'mid.max' => '商户号最大 200字符',
        'pay_user_tag.max' => '商户号最大 200字符',
        'ontime_is_open_or_close.in' => '状态值为open或close',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['channel_name', 'app_id', 'app_key', 'pay_channel', 'gateway', 'channel_tag', 'status', 'mid', 'pay_user_tag', 'show_sort', 'mini_amount','max_amount','max_number', 'day_start_time','day_end_time', 'ontime_is_open_or_close'],
        'edit'=>['channel_name', 'app_id', 'app_key', 'pay_channel', 'gateway', 'channel_tag', 'status', 'id', 'mid', 'pay_user_tag', 'show_sort','mini_amount','max_amount','max_number', 'day_start_time','day_end_time', 'ontime_is_open_or_close'],
        'detail'=>['id'],
    ];
}