<?php


namespace app\common\logic;
use app\common\model\SysConfig;
use app\common\model\TouziSms;
use think\exception\ValidateException;

/**
 * 短信验证码
 * */
class Sms
{

    /**
     * 基础设置-单个手机号码发送
     * @param string $mobile  手机号
     * @param string $content 内容
     * @param string $template 变量模板ID
     * */
    public function base_one($mobile,$content,$template){
        /*--------------------------------
        功能: 使用smsapi.class.php类发送短信
        说明: http://api.sms.cn/sms/?ac=send&uid=用户账号&pwd=MD5位32密码&mobile=号码&content=内容
        官网: www.sms.cn
        状态: {"stat":"100","message":"发送成功"}
        100 发送成功
        101 验证失败
        102 短信不足
        103 操作失败
        104 非法字符
        105 内容过多
        106 号码过多
        107 频率过快
        108 号码内容空
        109 账号冻结
        112 号码错误
        116 禁止接口发送
        117 绑定IP不正确
        161 未添加短信模板
        162 模板格式不正确
        163 模板ID不正确
        164 全文模板不匹配
        --------------------------------*/
        $account=config('sms.account');
        $key=config('sms.key');
        $api = new SmsApi($account,$key);
        /*
        * 变量模板发送示例
        * 模板内容：您的验证码是：{$code}，对用户{$username}操作绑定手机号，有效期为5分钟。如非本人操作，可不用理会。【云信】
        * 变量模板ID：100003
        */
        //发送的手机 多个号码用,英文逗号隔开
       // $mobile = '15900000000';
        //短信内容参数
        $content='{"code":"'.$content.'"}';
        $contentParam = array(
            'code' => $content,
           // 'username' => '您好'
        );
        //变量模板ID
        //$template = '100005';
        //发送变量模板短信
        //$content=urlencode('{"code":"'.$content.'"}');
      //  dump($content);
//        $pwd=md5('Abc123@#456bajieyun998866');
//        $uid='bajieyun998866';
//      //  $url='http://api.sms.cn/sms/?ac=sendint&uid='.$uid.'&pwd='.$pwd.'&mobile='.$mobile.'&'.$content.'&template='.$template.'';
//        $url='http://api.sms.cn/sms/?ac=sendint&uid=bajieyun998866&pwd=38de680c8840ae3dbb593cd0e884c478&template='.$template.'&mobile='.$mobile.'&content='.$content.'';
//        dump($url);
//        $ch = curl_init ();
//        curl_setopt ( $ch, CURLOPT_POST, 1 );
//        curl_setopt ( $ch, CURLOPT_HEADER, 0 );
//        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
//        curl_setopt ( $ch, CURLOPT_URL, $url );
//        $result = curl_exec ( $ch );
//        curl_close ( $ch );
//        return $result;
        $result = $api->send($mobile,$contentParam,$template);
        if($result['stat']=='100')
        {
           return true;
        }
        else
        {
            return false;
            //echo '发送失败:'.$result['stat'].'('.$result['message'].')';
        }

        /*
        * 全文模板发送示例
        * 模板内容：登录验证码：{**}。如非本人操作，可不用理会！【云信】
        *
        */
        //发送的手机 多个号码用,英文逗号隔开
//        $mobile = '15900000000';
//
//        //短信内容
//        $content = '登录验证码：'.$api->randNumber().'。如非本人操作，可不用理会！【云信】';
//        //发送全文模板短信
//        $result = $api->sendAll($mobile,$content);
//
//        if($result['stat']=='100')
//        {
//            echo '发送成功';
//        }
//        else
//        {
//            echo '发送失败:'.$result['stat'].'('.$result['message'].')';
//        }

        //当前请求返回的原始信息
        //echo $api->getResult();

        //取剩余短信条数
        //print_r($api->getNumber());

        //获取发送状态
        //print_r($api->getStatus());

        //接收上行短信（回复）
        //print_r($api->getReply());
    }
    /**
     *
     * 发送验证码
     * @param string $ip
     * @param number $type
     * @param string $phone
     * */
    public function sendCode($ip,$type,$phone){
        $message['status']=0;
        $message['msg']='';
        // 测试临时开启
        $message['status']=1;
        $message['msg']='这里是测试信息，没有真实对接接口';
        return $message;
        // 结束地址

        $today=strtotime(date('Y-m-d'));
        $sms= new TouziSms();
        //检测评率
        $count=$sms->where('phone',$phone)->where('type',$type)->where('ip',$ip)->where('add_time','>=',$today)->count();
        if ($count>3){
            $message['msg']='sending is too frequent, please try again tomorrow';
            return $message;
        }
        $max_time=$sms->where('phone',$phone)->where('type',$type)->max('add_time');
        if ($max_time+120>time()){
            $message['msg']='code is sent frequently. Please try again later';
            return $message;
        }
        try {
            $rand=rand(1000,9999);
            //调用短信
            //$ms=$this->base_one('+63'.$phone,$rand,'6016736');
            $ms=$this->buka_one('63'.$phone,$rand);
            if (!$ms){
                $message['msg']='SMS call failed';
                return $message;
            }
            $data['ip']=$ip;
            $data['add_time']=time();
            $data['code']=$rand;
            $data['type']=$type;
            $data['phone']=$phone;
            $sms->insert($data);
            $message['status']=1;
            return $message;
        }catch (ValidateException $e){
            $message['msg']=$e->getMessage();
            return $message;
        }

    }
    /**
     * 检测验证码
     * @param number $type
     * @param string $phone
     * @param number $code
     * */
    public function checkCode($type,$phone,$code){
        $message['status']=0;
        // $message['msg']='';
        // $sms= new TouziSms();
        // //万能验证码跳过验证
        // $SysConfig=new SysConfig();
        // $sms_universal_code=$SysConfig->where('name','sms_universal_code')->value('value');
        // if ($code!=$sms_universal_code){
        //     $res=$sms->where('phone',$phone)->where('type',$type)->order('add_time desc')->find();
        //     if (!$res){
        //         $message['msg']='No relevant short interest found, please do not try again';
        //         return $message;
        //     }
        //     if (time()>$res['add_time']+300){
        //         $message['msg']='code has expired';
        //         return $message;
        //     }
        //     if ($res['code']!=$code){
        //         $message['msg']='code error';
        //         return $message;
        //     }
        // }
        $message['status']=1;
        return $message;
    }
      /**
     * buka短信
     * @param string $mobile  手机号
     * @param string $content 内容
     * */
    public function buka_one($mobile,$content){

        header('content-type:text/html;charset=utf8');

        $apiKey = "U9MVFD4d";
        $apiSecret = "L7MOfNRp";
        $appId = "n3XGAAqB";

        $url = "https://api.onbuka.com/v3/sendSms";

        $timeStamp = time();
        $sign = md5($apiKey . $apiSecret . $timeStamp);

        $dataArr['appId'] = $appId;
        $dataArr['numbers'] = $mobile;
        $dataArr['content'] = $content.' is your verification code';
        $dataArr['senderId'] = '';


        $data = json_encode($dataArr);
        $headers = array('Content-Type:application/json;charset=UTF-8', "Sign:$sign", "Timestamp:$timeStamp", "Api-Key:$apiKey");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); // 不从证书中检查SSL加密算法是否存在
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 600);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $output = curl_exec($ch);
        curl_close($ch);
        $output=json_decode($output,true);
        if ($output['status']==0){
            return true;
        }else{
            return false;
        }
    }
}