<?php

namespace app\common\model;

use app\common\traites\TraitModel;
use think\Model;

class PayChannel extends Model
{
    use TraitModel;
    public $name = 'common_pay_channel';

    protected $append = [
        'ontime_is_open_or_close_text'
    ];

    public function getOpenCloseList()
    {
        return ['open' => '开启', 'close' => '关闭'];
    }

    public function getOntimeIsOpenOrCloseTextAttr($value, $data)
    {
        $value = $value ?: (isset($data['ontime_is_open_or_close']) ? $data['ontime_is_open_or_close'] : '');
        if ($value) {
            $list = $this->getOpenCloseList();
            return isset($list[$value]) ? $list[$value] : '';
        }
        return '';
    }
}