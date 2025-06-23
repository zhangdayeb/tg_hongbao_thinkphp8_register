<?php
declare (strict_types = 1);
namespace app\validate;

use think\Validate;
use app\common\model\SpreadModel;

class Spread extends Validate
{
    protected $rule = [
        'id'      => 'require',
        'title'   => 'require|length:1,30|unique:'.SpreadModel::class,
        'url'     => 'require',
        'status'  => 'require|in:0,1',
        'remarks' => 'length:1,200',
    ];


    protected $message = [
        'id.require'     => '参数缺失',
        'title.require'  => '请填写推广名称',
        'title.length'   => '名称长度需为1~30个字符',
        'title.unique'   => '名称已存在',
        'status.require' => '请选择状态',
        'status.in'      => '状态选择错误',
        'url.require'    => '请填写url',
        'remarks.length' => '备注长度需为1~30个字符',
    ];
    /**
     * 场景
     * @var array[]
     */
    protected $scene  = [
        'add'    => [ 'title','status', 'remarks','url'],
        'edit'   => [ 'id','title','status','remarks', 'url' ],
        'detail' => [ 'id' ],
    ];
}