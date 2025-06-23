<?php
return [
    'app_system' => [
        'app_system' => true,//false win系统 true linux
    ],
    'captcha'=>'aa123456',//万能验证码
    'http_code'=>[ //返回code
      'error'=>0,
      'success'=>1,
    ],
    'app_update' => [
        'image_url' => request()->domain().'/storage', //'https://api.18222hk.cc/storage',//上传文件域名 图片视频等
        'erweim_url' => request()->domain().'/', //'https://api.18222hk.cc/',// 二维码 存放的 地址前端
        'app_qrcode' => 'https://www.fcgyj.top/#/pages/login/register?code=',//二维码地址 前端的 用来生成 二维码的
    ],
    'app_tg' => [
        'tg_url' => '.tp.com/'
    ],
    'admin_vip' => [
        'admin_vip_id' => 1,// 超级管理员权限ID免控制器和方法权限
    ],
    'admin_agent' => [
        'admin_agent' => 2,// 代理商管理员分类 登陆选择的分类，需要查 token 的标示
        'admin_agent_id' => 2,// 代理商管理员权限ID
    ],
    'action_log' => [
        'edit', 'status', 'add', 'del', 'is_status'
    ],
    'wx' => [
        'appid'=>"wx555d1708ca97df9f",
        'wxurl'=>'https://open.weixin.qq.com/connect/oauth2/authorize',//微信授权地址
        'url'=>"https://www.yhvip666.net",//微信授权回调地址,也是前台网址
        'secret'=>"502fbea5bb604e94a1b35804a799e71d",//微信secret
    ],
    'order_ignore'=>3,//订单忽略开始数
    //加密公钥
    'public_key'=>'-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA3aJf8uY42jh6/UxZ5Ij+
TbZYs+FlkAcjCgKmk1aHWofrBI2XZuPgP4TP+vhtLNTDP9S19fuocGClJiXRmiDJ
LZHjaJam6xQoH01eRCfibI4p23LTiNe8bKgz5LYYPQ5xFzwC4Ry3E55fwsH33Ljx
S1WJrFlVX59k/Hzz91Uw+UjnUUEXSEl+j78nrxE31suysYTmK+3+vHma0GH4hT34
gdb1dBNJk9Ub7JVBFfXLYtfNoyetL8iic57pB+aaab0GCQK/aYo+E7RrVAk4pv9s
b3BMTGr7ipjIUfYGrMOBfLzp5htZN6ZQw1h4WHIBayGsOBElLhKnuRKJ7nmR0e4Y
0wIDAQAB
-----END PUBLIC KEY-----',
    'private_key'=>'-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDdol/y5jjaOHr9
TFnkiP5Ntliz4WWQByMKAqaTVodah+sEjZdm4+A/hM/6+G0s1MM/1LX1+6hwYKUm
JdGaIMktkeNolqbrFCgfTV5EJ+JsjinbctOI17xsqDPkthg9DnEXPALhHLcTnl/C
wffcuPFLVYmsWVVfn2T8fPP3VTD5SOdRQRdISX6PvyevETfWy7KxhOYr7f68eZrQ
YfiFPfiB1vV0E0mT1RvslUEV9cti182jJ60vyKJznukH5pppvQYJAr9pij4TtGtU
CTim/2xvcExMavuKmMhR9gasw4F8vOnmG1k3plDDWHhYcgFrIaw4ESUuEqe5Eonu
eZHR7hjTAgMBAAECggEBAMFgNDE9l+smjoDFBkW1FZT+fYRtK+0vnO3WBDrXq39c
ybyOQcRfHMCvA7wo1zDfboAZ+q1l5sAuQsn3A1tkMcOV34HYuEixrJQrMA1tc0xd
+b1kAZcLDHcNh0GNc7aKDDhGfwikwkPW0hyemsG1h6rANj/vLeMhsr3t0/tAFFvb
FTs7i1Cg1AflJM9DxCKizMAP9WOlZmOHmNGTwPjpnMyGfISHQGSXNku8qO7mflNR
HL0230gqY4AbOnnzb2JccBbSXPP+o5+PhMoC8AIwrVKfyXyqrBdStoXNED/bOj1e
1kqykFe3Ti5LE2+GT9wbj60I/WdoXm6ojMd+tVwWkEECgYEA9lXoFqeCVSmd07vo
3m4IaOU/e5zVTpVqgJraKVBFW8t/ZsE512fHKl+4Rhu1no1FOXEjPTqiyjuO58LN
VvTx632iyPrtERvVr2iTy7AQGuDcci02rXXBs6tJZHsfGoggVhNebmzOaVNV6aMc
8Xp5IdEWVwNgwkoZHJoPSZr5Z/MCgYEA5lRg5uS2JWgc21HEiXazN/LeaDpsdRHc
Q6yl1ZH7oGDaKkLjqtfCMo+oNPPMImvtM8pZcdHja7GrFyuwBDORjwNCHGVCQz/a
9DKpjSiAUVc4A7z4EWHRmyEWLvFL5CcICUMDWpK4nDZM11vMMsPOR0DdKmHqVrA2
ooMQPbeMo6ECgYB3hRsE0uWj2HthXk0Qjya5bnGs0l2UsV5pY7jyTqY4cbYw7xPX
ddzmrGbGbW9jrHun8UL91FNj+B3QSW5EALjYX676APXBVVYKs5zyOUy3Hd8X7uQW
qYoAWN1VSX+/6ch2uxMYVOaZp/uJTsEeUSQwyjgio9rwqe8hN4avWeglDQKBgHkg
jKlAQ+3eF7bbBHGKI+vbZE0J1Hmof95zD+8Fy39nD7RD4vi4aJ8wXzQhtguwGFkx
I+Kwj1nWYHRZ/EHpYLYF76GBOtyk2x+q+PGMCBc+t+13VjnF6HYda04ahV+hix9b
x4q9OCqmf7iNxRA5WuSr3uNoBNW766+BH2xld6ehAoGALuuQFKPl+uYfFeI4WGdJ
vfkk0FRI+wKIEdYHSGIP3MlXZcU88xrc73bmrdZSkYRT7KlgdGN6nE3O7E+JyhiQ
KF4Fwe44WaDT0vGMmFi1Wb8CMJJYPPjJOnNJmYekLJtJo/VO3rVAM1m65C34TvW9
2zge+4YxYzRZLCBjvaaAEEs=
-----END PRIVATE KEY-----'
];