<?php


namespace app\common\traites;

use think\Response;

trait ApiResponseTrait
{
    protected $code = 1;
    protected $message = 'ok';

    protected $headers = [];

    /**
     * return 会出现重复请求某个方法的问题
     * @param $data
     * @param int $code
     * @param string $message
     * @return mixed
     */
    final public function success($data, int $code = 1, string $message = 'ok')
    {
        header('Access-Control-Allow-Origin:*');
        echo json_encode(['data'=>$data,'code'=>$code,'msg'=>$message]);
        exit();
        return $this->setCode($code)->setMessage($message)->respond($data);
    }

    final public function failed(string $message = 'invalid argument', int $code = 0, $data = [])
    {   header('Access-Control-Allow-Origin:*');
        echo json_encode(['data'=>$data,'code'=>$code,'msg'=>$message]);
        exit();
        return $this->setCode($code)->setMessage($message)->respond($data);
    }

    final public function setCode(int $code = 1): object
    {
        $this->code = $code;
        return $this;
    }

    final public function setMessage(string $message = 'ok'): object
    {
        $this->message = $message;
        return $this;
    }

    final public function setHeader(array $headers = []): object
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    private function respond($data)
    {
        return json([
            'code' => $this->code,
            'msg' => $this->message,
            'data' => $data
        ])->header($this->headers);
    }

    private function crossDomain(){
        //header('content-type:text/html;charset=utf-8');
        //header('Access-Control-Allow-Origin: *');
        //header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
        //header('Access-Control-Allow-Methods: GET, POST, PUT,OPTIONS,DELETE');
    }
}
