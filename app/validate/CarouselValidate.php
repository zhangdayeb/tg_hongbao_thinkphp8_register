<?php

namespace app\validate;

use think\Validate;

class CarouselValidate extends Validate
{
    protected $rule = [
        'id'      => 'require',
        'title'   => 'require|length:1,30',
        'img_url' => 'require',
        'url'     => 'require',
        'status'  => 'require|in:0,1',
    ];


    protected $message = [
        'id.require'      => '参数缺失',
        'title.require'   => '请填写名称',
        'title.length'    => '名称长度需为1~30个字符',
        'url.require'     => '请填写跳转地址',
        'img_url.require' => '请上传图片地址',
        'status.require'  => '请选择状态',
        'status.in'       => '状态选择错误',
    ];
    /**
     * 场景
     * @var array[]
     */
    protected $scene = [
        'add'       => [ 'title','img_url','url','status' ],
        'edit'      => [ 'id','title','img_url','url','status' ],
    ];
}