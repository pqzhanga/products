<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class excl extends Command
{
    protected function configure()
    {
        $this->setName('excl')
        	->setDescription('excl');
    }

    protected function execute(Input $input, Output $output)
    {
        $res = Db::query('CALL  deal_auto_send()');
        $output->writeln('');
    }
}