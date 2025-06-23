<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class Article extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'id'=>  'require|integer',
        'type'=>  'require|integer',
        'content'=>'require',
        'title'=>'require|max:200',
        'author'=>'require|max:200',
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
        'type.require' => '分类必填',
        'type.integer' => '分类ID必须是整数',
        'title.require' => '标题必填',
        'title.max' => '标题最多200字',
        'author.require' => '作者必填',
        'author.max' => '作者最多200字',
        'content.require' => '内容必填',
        'thumb_url.require' => '缩略图必填',
    ];

    /**
     * 验证场景
     * @var \string[][]
     */
    protected $scene  = [
        'add'=>['type','title','author','content'],
        'edit'=>['id','type','title','author','content','thumb_url'],
        'detail'=>['id'],

    ];

}
