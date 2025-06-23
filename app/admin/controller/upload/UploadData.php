<?php

namespace app\admin\controller\upload;

use app\BaseController;

class UploadData extends BaseController
{
    /**
     * 二维码图片上传 - 使用ThinkPHP Filesystem
     * @return mixed
     */
    public function qrcode()
    {
        $files = request()->file();
        if (empty($files)) {
            return json(['code' => 0, 'message' => '未检测到上传文件']);
        }

        $savename = [];
        try {
            // 验证图片文件 - 参考image方法，但修改为二维码适用的格式和大小
            validate(['file' => 'filesize:2097152|fileExt:jpg,jpeg,png,gif,bmp'])->check($files);
            
            foreach ($files as $file) {
                // 使用ThinkPHP的Filesystem，但指定为qrcode目录
                $savedPath = \think\facade\Filesystem::putFile('qrcode', $file);
                if ($savedPath) {
                    $savename[] = $savedPath;
                }
            }
        } catch (\think\exception\ValidateException $e) {
            return json(['code' => 0, 'message' => $e->getMessage()]);
        }

        if (empty($savename)) {
            return json(['code' => 0, 'message' => '上传失败']);
        }

        // 构建访问URL - 二维码文件会保存在storage/qrcode/目录下
        $baseUrl = config('ToConfig.app_update.image_url', 'https://authapi.wuming888.com/');
        $fileUrl = $baseUrl . '/' . $savename[0];

        return json([
            'code' => 1,
            'message' => '上传成功',
            'data' => [
                'url' => $fileUrl,
                'filename' => basename($savename[0]),
                'path' => $savename[0]
            ]
        ]);
    }

    /**
     * 获取上传的二维码列表
     * @return mixed
     */
    public function qrcodeList()
    {
        try {
            // 使用storage路径
            $qrcodePath = app()->getRootPath() . '../public/storage/qrcode/';
            
            if (!is_dir($qrcodePath)) {
                return json(['code' => 1, 'message' => '获取成功', 'data' => []]);
            }

            $files = scandir($qrcodePath);
            $qrcodes = [];
            
            foreach ($files as $file) {
                if ($file != '.' && $file != '..' && is_file($qrcodePath . $file)) {
                    $baseUrl = config('ToConfig.app_update.image_url', 'https://authapi.wuming888.com/');
                    $qrcodes[] = [
                        'filename' => $file,
                        'url' => $baseUrl . 'storage/qrcode/' . $file,
                        'size' => filesize($qrcodePath . $file),
                        'created' => date('Y-m-d H:i:s', filemtime($qrcodePath . $file))
                    ];
                }
            }

            return json(['code' => 1, 'message' => '获取成功', 'data' => $qrcodes]);
        } catch (\Exception $e) {
            return json(['code' => 0, 'message' => '获取列表失败：' . $e->getMessage()]);
        }
    }

    /**
     * 视频上传（原有方法）
     * @return mixed
     */
    public function video()
    {
        $files = request()->file();
        if (empty($files))
            return json(['code' => 0, 'message' => '未检测到上传文件']);
        
        $savename = [];
        try {
            validate(['image' => 'filesize:100000|fileExt:,mp4'])->check($files);
            foreach ($files as $file) {
                $path = app()->getRootPath().'../..';
                $uploadImg=$file->getRealPath();
                $savename[] = $uploadImgName = 'adminimg/'.image_update_name($file);
                move_uploaded_file($uploadImg, $path."/resources/" . $uploadImgName);
            }
        } catch (\think\exception\ValidateException $e) {
            return json(['code' => 0, 'message' => $e->getMessage()]);
        }

        if (empty($savename))
            return json(['code' => 0, 'message' => '上传失败']);
        
        foreach ($savename as $key => &$value) {
            $value = config('ToConfig.app_update.image_url') . 'resources/' . $value;
            $value = str_replace('\\',"/",$value);
        }
        return json(['code' => 1, 'message' => '上传成功', 'data' => $savename]);
    }

    /**
     * 图片上传（原有方法）
     * @return mixed
     */
    public function image()
    {
        $files = request()->file();
        if (empty($files))
            return json(['code' => 0, 'message' => '未检测到上传文件']);
        
        $savename = [];
        try {
            validate(['image' => 'filesize:100000|fileExt:,jpg,gpg,png'])->check($files);
            $baseUrl = config('ToConfig.app_update.image_url', 'https://authapi.wuming888.com/');
            foreach ($files as $file) {
                $img_path = \think\facade\Filesystem::putFile('topic', $file);
                $savename[] = $baseUrl.'/'.$img_path;
            }
        } catch (\think\exception\ValidateException $e) {
            return json(['code' => 0, 'message' => $e->getMessage()]);
        }

        if (empty($savename))
            return json(['code' => 0, 'message' => '上传失败']);
        
        return json(['code' => 1, 'message' => '上传成功', 'data' => $savename]);
    }
}