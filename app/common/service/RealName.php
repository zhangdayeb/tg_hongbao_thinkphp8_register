<?php
namespace app\common\service;

use app\common\model\SysConfig;

class RealName
{
    // 实名认证 
    public function check_real_name($name,$idcard){
        
        $secretId = 'AKID7H3rnglHziaeQv9nTofbg28sqIDhgf85a4oQ';// 云市场分配的密钥Id
        $secretKey = 'hCZLWSEG1pf04qXtqHKmf8QmHmfZa72KLMrn8oQG';// 云市场分配的密钥Key
        $source = 'market';

        // 签名
        $datetime = gmdate('D, d M Y H:i:s T');
        $signStr = sprintf("x-date: %s\nx-source: %s", $datetime, $source);
        $sign = base64_encode(hash_hmac('sha1', $signStr, $secretKey, true));
        $auth = sprintf('hmac id="%s", algorithm="hmac-sha1", headers="x-date x-source", signature="%s"', $secretId, $sign);

        // 请求方法
        $method = 'POST';
        // 请求头
        $headers = array(
            'X-Source' => $source,
            'X-Date' => $datetime,
            'Authorization' => $auth,
            
        );
        // 查询参数
        $queryParams = array (

        );
        // body参数（POST方法下）
        $bodyParams = array (
            'idcard' => $idcard,
            'name' => $name,
        );
        // url参数拼接
        $url = 'https://service-ngxzc2ub-1310072863.sh.apigw.tencentcs.com/release/web/interface/grsfyz';
        if (count($queryParams) > 0) {
            $url .= '?' . http_build_query($queryParams);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($v, $k) {
            return $k . ': ' . $v;
        }, array_values($headers), array_keys($headers)));
        if (in_array($method, array('POST', 'PUT', 'PATCH'), true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($bodyParams));
        }

        $data = curl_exec($ch);
        if (curl_errno($ch)) {
            echo "Error: " . curl_error($ch);
        } else {
            // print_r($data);
            $r = json_decode($data);
            if(!isset($r->code) || $r->code != 200){
                return 2;
            }
            // dump($r);
            $res = json_decode($r->data);
            return $res->data->result;
            
        }
        curl_close($ch);
    } 

    // {

    //     "status":0, "message":"成功",
        
    //     "seqNum":"0291478157870886",
        
    //     "data":{
        
    //     "result":1,
        
    //     "resultMsg":"认证成功"
        
    //     }
        
    //     }


    // {"code":200,"data":"{\"data\":{\"result\":2,\"resultMsg\":\"认证失败\"},\"seqNum\":\"7622040200564543\",\"message\":\"成功\",\"status\":0}","msg":"接口调用成功！"}

// 各种支付通道    
}