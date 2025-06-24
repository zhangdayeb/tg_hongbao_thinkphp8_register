<?php
namespace app\common\google;

use my\GoogleAuthenticator;

/**
 *user_name:fyclover
 * Class GoogleAuth
 * @package app\common\google
 */
class GoogleAuth
{
    public $google;

    /**
     * google身份验证
     */
    public function model()
    {
        $this->google = new GoogleAuthenticator();
        return $this;
    }

    /**
     * 第一步
     * 生成secret 即使前后台用户的邀请码 必须这样才能进行google验证
     * @param int $len
     * @return mixed
     */
    public function generate_code($len = 16)
    {
        return $this->google->createSecret($len);
    }

    /**
     * 第二步
     * 生成二维码
     * @param $secret /用户 secret
     * @return mixed
     */
    public function google_qrcode($secret)
    {
        return $this->google->getQRCodeGoogleUrl('Blog', $secret);
    }

    /**
     * 第三步
     * 验证 code
     * @param $secret
     * @param $code /验证码
     * @return bool
     */
    public function google_verify_code($secret, $code)
    {
        $checkResult = $this->google->verifyCode($secret, $code, 2);

        if ($checkResult) {
            return true;
        } else {
            return false;
        }
    }


}