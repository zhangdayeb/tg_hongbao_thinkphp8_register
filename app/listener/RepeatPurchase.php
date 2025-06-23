<?php
declare (strict_types = 1);

namespace app\listener;

use think\facade\Request;

class RepeatPurchase
{
    /**
     * @param $event
     * 事件监听处理 监听重复购买操作
     * @return mixed
     */
    public function handle($event)
    {
        if (empty($event['repeat'])) return;
        //添加缓存
        if (cache(Request::action().'_user_'.$event['id'])){
            abort(404,'10秒内只能进行一次购买');
         }
        cache(Request::action().'_user_'.$event['id'],time(),10);
    }
}
