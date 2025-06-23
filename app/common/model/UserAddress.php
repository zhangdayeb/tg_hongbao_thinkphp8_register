<?php

namespace app\common\model;

use think\Model;

class UserAddress extends Model
{
    public $name = 'common_user_mailing_address';


    public static function selectByUserid($uid)
    {
        return self::where('uid', $uid)->order(['is_default' => 'desc', 'id' => 'desc'])->select()->toArray();
    }

    public static function findByIdAndUserid($id, $uid)
    {
        return self::where('id', $id)->where('uid', $uid)->findOrEmpty();
    }

    public static function cancelDefault($id, $uid)
    {
        return self::where('id', '<>', $id)->where('uid', $uid)->update(['is_default' => 0]);
    }


}
