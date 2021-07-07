<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class updateFourValue extends Command
{
    protected function configure()
    {
        $this->setName('updateFourValue')
        	->addArgument('name', Argument::OPTIONAL, "your name")
            ->addOption('city', null, Option::VALUE_REQUIRED, 'city name')
        	->setDescription('Say Hello');
    }

    protected function execute(Input $input, Output $output)
    {
        $ctime=date("Y-m-d H:i:s");
        bcscale(2);

        $num = 0;
        $list2 = [];
//
//        $list2 = Db::table('eb_user')->whereRaw("sign_time < '2021-05-23 08:53:57'
//                                                     AND sign_time >= '2021-05-23 00:00:00'
//                                                     AND uid IN
//                                                         (SELECT
//                                                            uid
//                                                          FROM
//                                                            eb_user_sign
//                                                          WHERE sign_time < '2021-05-23 00:00:00'
//                                                                AND sign_time >= '2021-05-22 00:00:00') AND brokerage_shuquan > 100")->column('uid');

//        $list2 = Db::table('eb_user')->whereRaw(" uid IN
//                                                         (SELECT
//                                                            uid
//                                                          FROM
//                                                            eb_user_sign
//                                                          WHERE sign_time < '2021-05-29 00:00:00'
//                                                                AND sign_time >= '2021-05-28 00:00:00') AND brokerage_shuquan > 100")->column('uid');

        $list = Db::table('eb_user')->where('brokerage_shuquan','>=',100)->chunk(500,function($users) use($ctime,$list2,&$num){
            foreach($users as $user){

                if($user['sign_time']!==null && ( date('Y-m-d',strtotime($user['sign_time']) + 86400) == date('Y-m-d') ) ){
//                if(in_array($user['uid'],$list2)){ 
//              if($user['sign_time']!==null && ( date('Y-m-d',strtotime($user['sign_time'])) == date('Y-m-d') ) && $user['brokerage_gongxian'] >= 100){
//                    $brokerage_gongxian = bcmul($user['brokerage_gongxian'] , 0.001);
//                    if($brokerage_gongxian > 0 && false){
//                        $user['brokerage_gongxian'] = bcsub($user['brokerage_gongxian'],$brokerage_gongxian);
//                        $user['brokerage_shuquan'] = bcadd($user['brokerage_shuquan'],$brokerage_gongxian);
//
//                        $saveDate = [
//                            'uid' => $user['uid'],
//                            'link_id' => 2,
//                            'pm'=>0,
//                            'title'=>'用户签到',
//                            'category' => 'brokerage_gongxian',
//                            'number'=> $brokerage_gongxian,
//                            'balance'=>  $user['brokerage_gongxian'],
//                            'mark'=>'用户签到，贡献值转换为数权值，贡献值减少'.$brokerage_gongxian,
//                            'create_time'=>$ctime,
//                            'status'=>1
//                        ];
//                        Db::table('eb_user_bill')->save($saveDate);
//
//                        $saveDate = [
//                            'uid' => $user['uid'],
//                            'link_id' => 2,
//                            'pm'=>1,
//                            'title'=>'用户签到',
//                            'category' => 'brokerage_shuquan',
//                            'number'=>$brokerage_gongxian,
//                            'balance'=>$user['brokerage_shuquan'],
//                            'mark'=>'用户签到，贡献值转换为数权值，数权值增加'.$brokerage_gongxian,
//                            'create_time'=>$ctime,
//                            'status'=>1
//                        ];
//                        Db::table('eb_user_bill')->save($saveDate);
//
//                    }

                    $brokerage_shuquan =  intval($user['brokerage_shuquan'] / 100) * 100;
                    if($brokerage_shuquan > 0){
                        $num++;
                        $user['brokerage_shuquan'] =  bcsub($user['brokerage_shuquan'] , $brokerage_shuquan );

                        $brokerage_duihuan = bcdiv ( $brokerage_shuquan , 2  );
                        $brokerage_price = bcdiv ( $brokerage_shuquan , 2  );

                        if($brokerage_duihuan > 0){
                            $user['brokerage_duihuan'] =  bcadd(  $user['brokerage_duihuan']  , $brokerage_duihuan );
                            $saveDate = [
                                'uid' => $user['uid'],
                                'link_id' => 2,
                                'pm'=>1,
                                'title'=>'数权值每满100',
                                'category' => 'brokerage_duihuan',
                                'number'=> $brokerage_duihuan ,
                                'balance'=>  $user['brokerage_duihuan'] ,
                                'mark'=>' 数权值每满100，50%释放为兑换值.',
                                'create_time'=>$ctime,
                                'status'=>1
                            ];
                            Db::table('eb_user_bill')->save($saveDate);
                        }
                        if($brokerage_price > 0) {
                            $user['brokerage_price'] =  bcadd(  $user['brokerage_price']  , $brokerage_price );
                            $saveDate = [
                                'uid' => $user['uid'],
                                'link_id' => 2,
                                'pm' => 1,
                                'title' => '数权值每满100',
                                'category' => 'brokerage_price',
                                'number' => $brokerage_price,
                                'balance' => $user['brokerage_price'],
                                'mark' => '数权值每满100，50%释放为股权值.',
                                'create_time' => $ctime,
                                'status' => 1
                            ];
                            Db::table('eb_user_bill')->save($saveDate);
                        }

                        Db::table('eb_user')->update($user);
                    }


                }
            }
        });

//    	$name = trim($input->getArgument('name'));
//      	$name = $name ?: 'thinkphp';
//
//		if ($input->hasOption('city')) {
//        	$city = PHP_EOL . 'From ' . $input->getOption('city');
//        } else {
//        	$city = '';
//        }

        Db::table('bonus_log')->insert([
            'content'=>'done=2'
        ]);
        $output->writeln($num);
    }
}