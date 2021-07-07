<?php
namespace app\command;

use app\common\model\user\User;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class refreshUserTree extends Command
{
    protected function configure()
    {
        $this->setName('refreshUserTree')
        	->setDescription('refreshUserTree');
    }

    protected function execute(Input $input, Output $output)
    {
        $user_list = [];
        Db::table('eb_user')->chunk(500,function($users) use (&$user_list){
            foreach($users as $user){
                $user_list[] = [
                    'uid'=>$user['uid'],
                    'spread_uid'=>$user['spread_uid'],
                ];
            }
        });
        foreach ( $user_list as &$v){
            $v['retree'] =  $this->getUserPTree($user_list,$v['uid']);
            $v['layer'] = count( $v['retree'] );
            $v['retree'] =  implode(',',$v['retree']).',';
        }
        unset($v);
        app()->make(User::class)->saveAll($user_list);
//        while ($intd = array_splice($user_list,0,500)){
//            Db::table('eb_user')->saveAll();
//        }

        Log::info('用户树重建完成！');
    }

    private  function  getUserPTree($userlist,$uid){
            $allow_num = 100;
            $tree = [];
            while ($p  = $this->getUserParent($userlist,$uid)){
                $tree[] = $p['uid'];
                $uid =$p['spread_uid'];
                $allow_num--;
                if($allow_num < 0 ) return [$uid];
            }
            return array_reverse($tree);
    }


    private  function getUserParent($userlist,$curr_uid){
        if(!$curr_uid) return false;
           foreach ($userlist as $v){
               if($v['uid'] == $curr_uid) return $v;
           }
           return false;
    }

}