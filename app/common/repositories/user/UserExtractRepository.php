<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\common\repositories\user;

use app\common\model\user\UserExtract;
use app\common\repositories\BaseRepository;
use app\common\dao\user\UserExtractDao as dao;
use crmeb\jobs\SendTemplateMessageJob;
use crmeb\services\SwooleTaskService;
use think\facade\Db;
use think\facade\Queue;

class UserExtractRepository extends BaseRepository
{

    /**
     * @var dao
     */
    protected $dao;


    /**
     * UserExtractRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }


    /**
     * TODO
     * @param $id
     * @return bool
     * @author Qinii
     * @day 2020-06-16
     */
    public function getWhereCount($id)
    {
        $where['extract_id'] = $id;
        $where['status'] = 0;
        return $this->dao->getWhereCount($where) > 0;
    }

    /**
     * TODO
     * @param array $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2020-06-16
     */
    public function search(array $where, $page, $limit)
    {
        $query = $this->dao->search($where);
        $count = $query->count();
        $list = $query->page($page,$limit)->with('user')->select();
        $sum['total'] = $this->dao->search($where)->sum('extract_price');
        return compact('count','list','sum');
    }

    /**
     * @param $uid
     * @return mixed
     * @author xaboy
     * @day 2020/6/22
     */
    public function userTotalExtract($uid)
    {
       return UserExtract::where(['status' => 1, 'uid' => $uid])->sum('extract_price');
    }

    /**
     * TODO
     * @param $user
     * @param $data
     * @author Qinii
     * @day 2020-06-16
     */
    public function create($user,$data,$mer)
    {
        bcscale(2);
        if($data['use_type'] == 'mer'){
            $ex_rate = systemConfig('ex_rate2'); //提现手续费 百分比
            $base_rate = systemConfig('base_rate2');//基金 （元）

            unset($data['use_type']);
            $data = Db::transaction(function()use($mer,$user,$data,$ex_rate,$base_rate){
                $brokerage_price = bcsub($mer['money'],$data['extract_price'],2);

                if($brokerage_price < 0) return false;

                $res = Db::table('eb_merchant')->where(['mer_id'=>$mer['mer_id']])->update([
                    'money'=>$brokerage_price
                ]);
                if(!$res)return false;

                $data['pay_money'] = $data['extract_price'] ;
//                 Db::table('eb_merchant_bill')->save([
//                    'mer_id'=>$mer['mer_id'],
//                    'create_time'=>date('Y-m-d H:i:s',time()),
//                    'money'=>$data['extract_price'],
//                    'mark'=>'提现',
//                    'type'=>'del',
//                ]);

                $data['sp'] = bcmul(($ex_rate/100) , $data['extract_price']  ,2); // 手续费
                $data['base'] = $base_rate;//  基金
                $data['pay'] =  bcsub(bcsub($data['extract_price'] ,  $data['sp']  ,2),  $data['base']  , 2);
                $data['pay'] =     $data['pay'] < 0?0:    $data['pay']; //应打款金额


                $data['mer_id'] = $mer['mer_id'];
                $data['status'] = 0;
                $data['uid'] = $user['uid'];
                $data['balance'] = $mer['money'];

                $data['use_type'] = 'mer';
                return $this->dao->create($data);
            });

        }else{

            $ex_rate = systemConfig('ex_rate'); //提现手续费 百分比
            $base_rate = systemConfig('base_rate');//基金 （元）

            unset($data['use_type']);
            $data = Db::transaction(function()use($user,$data,$base_rate,$ex_rate){
                $brokerage_price = bcsub($user['brokerage_price'],$data['extract_price'],2);

                if($brokerage_price < 0) return false;

                $user->brokerage_price = $brokerage_price;
                $user->save();

                $data['pay_money'] = $data['extract_price'] ;

//                $make = app()->make(UserBillRepository::class);
//                $make->decBill($user['uid'], 'brokerage_price', '', [
//                    'link_id' => 1,
//                    'status' => 1,
//                    'title' => '提现',
//                    'number' => $data['extract_price'],
//                    'balance'=>$brokerage_price,
//                    'mark' => '提现' . floatval($data['extract_price']) . '股权值',
//                ]);


                Db::table('eb_user_bill')->save([
                    'uid' => $user['uid'],
                    'link_id' =>0,
                    'pm'=>0,
                    'title'=>'提现冻结',
                    'category' => 'brokerage_price',
                    'number'=> $data['extract_price'] ,
                    'balance'=>$brokerage_price ,
                    'mark'=>'提现冻结，股权值减少'. $data['extract_price']  ,
                    'create_time'=>date('Y-m-d H:i:s'),
                    'status'=>1
                ]);




                $data['sp'] = bcmul(($ex_rate/100) , $data['extract_price']  ,2);
                $data['base'] = $base_rate;
                $data['pay'] =  bcsub(bcsub($data['extract_price'] ,  $data['sp']  ,2),  $data['base']  , 2);
                $data['pay'] =     $data['pay'] <0?0:    $data['pay'];

                $data['status'] = 0;
                $data['uid'] = $user['uid'];
                $data['use_type'] = 'brokerage_price';
                $data['balance'] = $user['brokerage_price'];
                return $this->dao->create($data);
            });
        }


        SwooleTaskService::admin('notice', [
            'type' => 'extract',
            'title' => '您有一条新的提现申请',
            'id' => $data->extract_id
        ]);


        return true;
    }

    public function switchStatus($id,$data)
    {
        bcscale(2);




        Db::transaction(function()use($id,$data){

            $extract = $this->dao->getWhere(['extract_id' => $id]);
            $user = app()->make(UserRepository::class)->get($extract['uid']);

            if($data['status'] == '-1'){

                if($extract['use_type'] == 'mer'){

                    if($extract['pay_money'] > 0 &&  bcsub($extract['extract_price'] , $extract['pay_money'],2)  == 0 ){
                        $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
                        $brokerage_price = bcadd($mer['money'] ,$extract['extract_price'],2);
                        Db::table('eb_merchant')->where(['mer_id'=>$extract['mer_id']])->update([
                                'money'=>$brokerage_price
                        ]);
                    }
//                    $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
//                    $brokerage_price = bcadd($mer['money'] ,$extract['extract_price'],2);
//                    Db::table('eb_merchant')->where(['mer_id'=>$extract['mer_id']])->update([
//                        'money'=>$brokerage_price
//                    ]);

//                      Db::table('eb_merchant_bill')->save([
//                        'mer_id'=>$extract['mer_id'],
//                        'create_time'=>date('Y-m-d H:i:s',time()),
//                        'money'=>$extract['extract_price'],
//                        'mark'=>'提现失败退回',
//                        'type'=>'add',
//                    ]);


                }else{

                    if($extract['pay_money'] > 0 &&  bcsub($extract['extract_price'] , $extract['pay_money'],2)  == 0 ){
                        $brokerage_price = bcadd($user['brokerage_price'] ,$extract['extract_price'],2);
                        $user->brokerage_price = $brokerage_price;
                        $user->save();

                        Db::table('eb_user_bill')->save([
                            'uid' => $extract['uid'],
                            'link_id' =>$id,
                            'pm'=>1,
                            'title'=>'提现失败',
                            'category' => 'brokerage_price',
                            'number'=> $extract['extract_price'] ,
                            'balance'=>$brokerage_price ,
                            'mark'=>'提现失败，股权值增加'. $extract['extract_price'] .',提现id：'.$id,
                            'create_time'=>date('Y-m-d H:i:s'),
                            'status'=>1
                        ]);


                    }
//                    $brokerage_price = bcadd($user['brokerage_price'] ,$extract['pay'],2);
//                    $user->brokerage_price = $brokerage_price;
//                    $user->save();
                }

            }
            elseif ($data['status'] == '1'){

                if($extract['use_type'] == 'mer'){

                    if($extract['pay_money'] > 0 &&  bcsub($extract['extract_price'] , $extract['pay_money'],2)  == 0 ){

                    }else{
                        $mer = Db::table('eb_merchant')->where(['mer_id'=>$extract['mer_id']])->find();
                        $brokerage_price = bcsub($mer['money'] ,$extract['extract_price'],2);
                        if($brokerage_price < 0 ) return false;
                        Db::table('eb_merchant')->where(['mer_id'=>$extract['mer_id']])->update([
                            'money'=>$brokerage_price
                        ]);
                    }

//                    $mer = Db::table('eb_merchant')->where(['mer_id'=>$extract['mer_id']])->find();
//                    $brokerage_price = bcsub($mer['money'] ,$extract['extract_price'],2);
//                    if($brokerage_price < 0 ) return false;
//                    Db::table('eb_merchant')->where(['mer_id'=>$extract['mer_id']])->update([
//                        'money'=>$brokerage_price
//                    ]);

                      Db::table('eb_merchant_bill')->save([
                        'mer_id'=>$extract['mer_id'],
                        'create_time'=>date('Y-m-d H:i:s',time()),
                        'money'=>$extract['extract_price'],
                        'mark'=>'提现扣除',
                        'type'=>'del',
                    ]);


                }else{

                    if($extract['pay_money'] > 0 &&  bcsub($extract['extract_price'] , $extract['pay_money'],2)  == 0 ){
                        $brokerage_price = $user['brokerage_price'] ;
                    }else{
                        $brokerage_price = bcsub($user['brokerage_price'] ,$extract['extract_price'],2);
                        if($brokerage_price < 0 ) return false;
                        $user->brokerage_price = $brokerage_price;
                        $user->save();
                    }

//                    $brokerage_price = bcsub($user['brokerage_price'] ,$extract['extract_price'],2);
//                    if($brokerage_price < 0 ) return false;
//                    $user->brokerage_price = $brokerage_price;
//                    $user->save();


                    Db::table('eb_user_bill')->save([
                        'uid' => $extract['uid'],
                        'link_id' =>$id,
                        'pm'=>0,
                        'title'=>'提现扣除',
                        'category' => 'brokerage_price',
                        'number'=> $extract['extract_price'] ,
                        'balance'=>$brokerage_price ,
                        'mark'=>'提现扣除，股权值减少'. $extract['extract_price'] .',提现id：'.$id,
                        'create_time'=>date('Y-m-d H:i:s'),
                        'status'=>1
                    ]);

                }

            }

            $this->dao->update($id,$data);


        });




        Queue::push(SendTemplateMessageJob::class,[
            'tempCode' => 'ORDER_DELIVER_SUCCESS',
            'id' =>$id
        ]);
        
        return true;

    }
}
