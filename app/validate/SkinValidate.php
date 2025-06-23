<?php

namespace app\validate;

use app\common\model\SkinModel;
use think\Validate;

class SkinValidate extends Validate
{
    protected $rule = [
        'id'      => 'require',
        'title'   => 'require|length:1,30|unique:'.SkinModel::class,
        'domain'  => 'require',
        'img_url' => 'require',
        'status' => 'require',
        'remark' => 'max:512',
    ];


    protected $message = [
        'id.require'      => '参数缺失',
        'title.require'   => '请填写皮肤名称',
        'title.length'    => '名称长度需为1~30个字符',
        'title.unique'    => '名称已存在',
        'domain.require'  => '请填写皮肤地址',
        'img_url.require' => '请上传图片地址',
        'status.require' => '状态必填',
        'remark.max' => '描述最多 512字符',
    ];
    /**
     * 场景
     * @var array[]
     */
    protected $scene = [
        'add'       => [ 'title','domain','img_url', 'status', 'remark'],
        'edit'      => [ 'id','title','domain','img_url', 'status', 'remark'],
        'agentedit' => [ 'id' ],
    ];

}