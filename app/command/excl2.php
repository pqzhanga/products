<?php
namespace app\command;

use app\controller\api\Common;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class excl2 extends Command
{
    protected function configure()
    {
        $this->setName('excl2')
        	->setDescription('excl2');
    }

    protected function execute(Input $input, Output $output)
    {

//        $conn =    app()->make( Common::class );
        Common::batsplit();
//        $conn->batsplit();
//        $res = Db::query('CALL  deal_auto_send()');
        $output->writeln('');
    }
}