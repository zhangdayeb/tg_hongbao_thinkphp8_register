<?php

namespace app\common\service;

use app\common\model\SysConfig;

class Pay
{
    // 支付入口
    // pay_id : 通道 ID
    // order_on 订单编号
    // money 支付金额
    public function pay($channel, $channel_id, $order_on, $money)
    {
        switch ($channel) {
            case 'sepay':
                return $this->pay_PayChannel_sepay($channel_id, $order_on, $money);
            case 'alinpay':
                return $this->pay_PayChannel_alinpay($channel_id, $order_on, $money);
            case 'xxpay':
                return $this->pay_PayChannel_xxpay($channel_id, $order_on, $money);
            case 'worldpay':
                return $this->pay_PayChannel_worldpay($channel_id, $order_on, $money);
            default:
                $back_data = [];
                $back_data['msg'] = '请联系客服进行支付！';
                $back_data['url'] = '';
                $back_data['pay_type'] = 'show';
                return $back_data;
        }
    }

    private function pay_PayChannel_sepay($channel_id, $order_on, $money)
    {
        //$SysConfig = new SysConfig();
        //$api_address = $SysConfig->where('name', 'admin_address')->value('value');// 支付地址 接口地址
        //$web_address = $SysConfig->where('name', 'web_address')->value('value');// 前端地址 回调地址

        // 支付提交数据 组装
        $zhi = [];
        $zhi['mchNum'] = '600195';
        $zhi['payType'] = $channel_id;
        $zhi['outOrderNum'] = $order_on;
        $zhi['amount'] = $money;
        $zhi['notifyUrl'] = (string)url('home/notify/sepay', [], true, true);
        $zhi['timestamp'] = time();
        $zhi['sign'] = makeSign($zhi, 'OATSmd28vgwHZ67CSRVfa0DLK3Wp0jB2');
        $url = 'http://sepay-api.tr16688.com/api/order';
        // 数据组装完成
        \think\facade\Log::write('sepay-支付请求信息：' . json_encode($zhi), 'notice');
        $curl_res = curl_request($url, $zhi, ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
        \think\facade\Log::write('sepay-支付响应信息：' . $curl_res, 'notice');
        if ($curl_res) {
            $curls_res = json_decode($curl_res, true);
            if (isset($curls_res['code']) && $curls_res['code'] == 200) {
                $back_data = [];
                $back_data['msg'] = '对接成功，准备跳转';
                $back_data['url'] = $curls_res['attrData']['payUrl'];
                $back_data['pay_type'] = 'jump';
                return show($back_data); // 正常返回 前端 执行 跳转
            } else {
                return show([], config('ToConfig.http_code.error'), $curls_res['msg']); // 错误返回 前端显示错误信息
            }
        } else {
            return show([], config('ToConfig.http_code.error'), 'Invalid payment link');
        }
    }

    private function pay_PayChannel_alinpay($channel_id, $order_on, $money)
    {
        //$SysConfig = new SysConfig();
        //$api_address = $SysConfig->where('name', 'admin_address')->value('value');// 支付地址 接口地址
        //$web_address = $SysConfig->where('name', 'web_address')->value('value');// 前端地址 回调地址

        // 支付提交数据 组装
        $zhi = [];
        $zhi['pay_memberid'] = '241187822';
        $zhi['pay_orderid'] = $order_on;
        $zhi['pay_applydate'] = date('Y-m-d H:i:s');
        $zhi['pay_bankcode'] = $channel_id;
        $zhi['pay_notifyurl'] = (string)url('home/notify/alinpay', [], true, true);
        $zhi['pay_callbackurl'] = 'https://baidu.com#/pages/Benim/Benim?flag=true';
        $zhi['pay_amount'] = $money;
        $zhi['pay_md5sign'] = makeSign($zhi, 'hhnfwjg9s2apc7g7tdk25vfcty8yoq2m');
        $zhi['pay_productname'] = '现金充值';
        $url = 'http://aliviptue.meisuobudamiya.net/Pay_Index.html';
        // 数据组装完成
        \think\facade\Log::write('alinpay-支付请求信息：' . json_encode($zhi), 'notice');
        $curl_res = curl_request($url, $zhi, ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
        \think\facade\Log::write('alinpay-支付响应信息：' . $curl_res, 'notice');
        if ($curl_res) {
            $curls_res = json_decode($curl_res, true);
            if (isset($curls_res['code']) && $curls_res['code'] == 200) {
                $back_data = [];
                $back_data['msg'] = '对接成功，准备跳转';
                $back_data['url'] = $curls_res['data'];
                $back_data['pay_type'] = 'jump';
                return show($back_data); // 正常返回 前端 执行 跳转
            } else {
                return show([], config('ToConfig.http_code.error'), $curls_res['msg']); // 错误返回 前端显示错误信息
            }
        } else {
            return show([], config('ToConfig.http_code.error'), 'Invalid payment link');
        }
    }

    private function pay_PayChannel_xxpay($channel_id, $order_on, $money)
    {
        //$SysConfig = new SysConfig();
        //$api_address = $SysConfig->where('name', 'admin_address')->value('value');// 支付地址 接口地址
        //$web_address = $SysConfig->where('name', 'web_address')->value('value');// 前端地址 回调地址

        // 支付提交数据 组装
        $zhi = [];
        $zhi['mchId'] = '20000702';
        $zhi['productId'] = $channel_id;
        $zhi['mchOrderNo'] = $order_on;
        $zhi['amount'] = $money * 100;
        $zhi['currency'] = 'cny';
        $zhi['notifyUrl'] = (string)url('home/notify/xxpay', [], true, true);
        // $zhi['notifyUrl'] = 'https://fucaiapi.18222hk.cc/home/notify/xxpay.html';
        $zhi['subject'] = '购买商品';
        $zhi['body'] = '购买商品描述';
        $zhi['reqTime'] = date('YmdHis');
        $zhi['version'] = '1.0';
        $zhi['sign'] = makeSign($zhi, 'FRK7TXKI3AEITQ96ITD3VZTHKKAGWLHCMVSY2UYTF7ZQZHHQOP6BDRLGEF2J5K0AKFK6XJKZIL2XQTME07MWRVKDFRN5Y3EP8GWI9NRXPBFABVTFPEF2PMGYIHR9ER9J');
        $url = 'https://yhhb.ohsf.xyz/api/pay/create_order'; // https://yhhb.ohsf.xyz/api/pay/create_order
        // 数据组装完成
        \think\facade\Log::write('xxpay-支付请求信息：' . json_encode($zhi), 'notice');
        $curl_res = curl_request($url, $zhi, ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded']);
        \think\facade\Log::write('xxpay-支付响应信息：' . $curl_res, 'notice');
        if ($curl_res) {
            $curls_res = json_decode($curl_res, true);
            if (isset($curls_res['retCode']) && $curls_res['retCode'] == 0) {
                $back_data = [];
                $back_data['msg'] = '对接成功，准备跳转';
                $back_data['url'] = $curls_res['payUrl'];
                $back_data['pay_type'] = 'jump';
                return show($back_data); // 正常返回 前端 执行 跳转
            } else {
                return show([], config('ToConfig.http_code.error'), $curls_res['retMsg']); // 错误返回 前端显示错误信息
            }
        } else {
            return show([], config('ToConfig.http_code.error'), 'Invalid payment link');
        }
    }


    private function pay_PayChannel_worldpay($channel_id, $order_on, $money)
    {
        // 获取13位的时间戳
        list($t1,$t2) = explode(' ', microtime());
        $str_time = sprintf('%u',(floatval($t1) + floatval($t2)) * 1000);

        // 支付提交数据 组装
        $zhi = [];
        $zhi['mchId'] = 'M1730794746';
        $zhi['wayCode'] = $channel_id;
        $zhi['outTradeNo'] = $order_on;
        $zhi['amount'] = ($money * 100);
        $zhi['notifyUrl'] = (string)url('home/notify/worldpay', [], true, true);
        // $zhi['notifyUrl'] = urlencode((string)url('home/notify/worldpay', [], true, true)) ;
        $zhi['reqTime'] = $str_time;
        $zhi['subject'] = 'productA';
        $zhi['clientIp'] = request()->ip();
        $zhi['sign'] = mb_strtolower(makeSign($zhi, 'c9e31a7tyu4ems9g9x4rbvexf57rxgg3')) ;
        $url = 'https://mo3pg1tf.odasjdiadlqqew.xyz/Pay_SG';
        // 数据组装完成
        \think\facade\Log::write('worldpay-支付请求信息：' . json_encode($zhi), 'notice');
        $curl_res = curl_request_json($url, $zhi, ['Accept: application/json', 'Content-Type: application/json;charset=UTF-8;']);
        \think\facade\Log::write('worldpay-支付响应信息：' . $curl_res, 'notice');
        if ($curl_res) {
            $curls_res = json_decode($curl_res, true);
            if ($curls_res['message'] == 'ok') {
                $back_data = [];
                $back_data['msg'] = '对接成功，准备跳转';
                $back_data['url'] = $curls_res['data']['payUrl'];
                $back_data['pay_type'] = 'jump';
                return show($back_data); // 正常返回 前端 执行 跳转
            } else {
                return show([], config('ToConfig.http_code.error'), $curls_res['msg']); // 错误返回 前端显示错误信息
            }
        } else {
            return show([], config('ToConfig.http_code.error'), 'Invalid payment link');
        }
    }

















// 类结束了    
}
