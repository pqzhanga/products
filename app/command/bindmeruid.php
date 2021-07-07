<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class bindmeruid extends Command
{
    protected function configure()
    {
        $this->setName('bindmeruid')
        	->setDescription('bindmeruid');
    }

    protected function execute(Input $input, Output $output)
    {
        $num = 0;
        Db::table('eb_merchant')->chunk(500,function($merchants) use (&$num){
            foreach($merchants as $merchant){
                $u_tmp = false;
                $user =  Db::table('eb_user');
                if($merchant['phone'])  $u_tmp = $user->where('phone',$merchant['phone'])->find();
                if($u_tmp) {
                    Db::table('eb_merchant')->where('mer_id',$merchant['mer_id'])->update([
                        'uid'=>$u_tmp['uid'],
                        'money'=>$u_tmp['storemoney'],
                    ]);
                    $num++;
                }
            }
        });
        $output->writeln($num);
    }
}