<?php


namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use \app\common\service\TodayCount as TodayCountServer;
/**
 * 每日晚上靠近12点执行
 * 命令 php think admin:today_count
 * CreateTime: 2021/04/01 14:01
 * UserName: fyclover
 **/
class TodayCount extends Command
{
    protected function configure()
    {
        $this->setName('today_count')->setDescription('Here is the today_count');
    }

    protected function execute(Input $input, Output $output)
    {
        $service = new TodayCountServer();
        $service->recharge()->register()->withdrawal()->update();
        $output->writeln('执行成功');

    }
// 类结束了    
}