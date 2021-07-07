<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class qd extends Command
{
    protected function configure()
    {
        $this->setName('qd') ->setDescription('qd');
    }

    protected function execute(Input $input, Output $output)
    {
         Db::table('eb_user')->chunk(500,function($users)  {
            foreach ($users as $user){
                Db::table('eb_user')->where(['uid'=>$user['uid']])->update(['retree'=> ltrim(  $user['retree'] ,',') ]);
            }
        });
    }
}