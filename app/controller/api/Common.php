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


namespace app\controller\api;


use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\CacheRepository;
use crmeb\basic\BaseController;
use app\common\repositories\store\shipping\ExpressRepository;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\system\groupData\GroupDataRepository;
use app\common\repositories\user\UserVisitRepository;
use app\common\repositories\wechat\TemplateMessageRepository;
use crmeb\services\AlipayService;
use crmeb\services\MiniProgramService;
use crmeb\services\UploadService;
use crmeb\services\WechatService;
use Exception;
use Joypack\Tencent\Map\Bundle\Location;
use Joypack\Tencent\Map\Bundle\LocationOption;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use think\Response;
use think\facade\Request;
use crmeb\services\HttpService;
use think\response\Html;

/**
 * Class Common
 * @package app\controller\api
 * @author xaboy
 * @day 2020/5/28
 */
class Common extends BaseController
{

    /**
     * @return mixed
     * @author xaboy
     * @day 2020/5/28
     */

    public function checkupdate()
    {
        try{
            $version = $this->request->post('version/s');
            $platform = $this->request->post('platform/s');
            $ver = systemConfig('ios_ver');
            $ver2 = systemConfig('android_ver');

            Log::info('----info---checkupdate:' . " 【{$version}】【{$platform}】【{$ver}】【{$ver2}】"  );

            $need_up = false;
            $url = '';
            if($platform == 'ios') {
                if($version !== systemConfig('ios_ver')){
                    $need_up = true;
                    $url =  systemConfig('ios_url');
                }
            }else{
                if($version !== systemConfig('android_ver')){
                    $need_up = true;
                    $url =  systemConfig('android_url');
                }
            }
        }catch (Exception $e){
            Log::info('----error---版本校验:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
        }
        return app('json')->success(compact('need_up', 'url'));
    }

    public function sc_info()
    {
        $mer = Db::table('eb_merchant')->where(['mer_id'=>$this->request->post('mid/d')])->find();
        $mer_name = $mer?$mer['mer_name']:'';
        $user = $this->request->userInfo();
        $username  = $user['account'];
        return app('json')->success(compact('username','mer_name'));
    }


    public function payInfo()
    {
        if (!$this->request->isLogin()) return app('json')->success();
        $uid = $this->request->uid();
        $money = $this->request->post('money/f');
        $mid = $this->request->post('mid/d');
        $env = $this->request->post('env');
        $url = '';
        //创建订单
        if(!$mid || $money < 0.01) return app('json')->fail('金额错误！');
        $mer = Db::table('eb_merchant')->find($mid);
        if(!$mer ) return app('json')->fail('商户不存在！');
        if(!in_array($env,['alipay','wxpay']) ) return app('json')->fail('请在微信或支付宝中扫码！');

        $data = [
            'money'=>$money,
            'uid'=>$uid,
            'mer_id'=>$mid,
            'mer_uid'=>$mer['uid'],
            'rate'=>$mer['crate'],
            'create_time'=>date('Y-m-d H:i:s'),
            'order_sn'=>$this->scan_order_id(),
            'pay_time'=>'',
            'status'=>0
        ];
        $in_id = Db::table('eb_scan_order')->insertGetId($data);
        if(!$in_id) return app('json')->fail('请稍后再试！');
        //调用云支付 返回url
        $res = app()->make(StoreOrderRepository::class)->yunPay($data['order_sn'],$money,$mer['crate'],$mer['sub_mer'],$env);//$sn,$money,$rate,$sub_mer)
        if($res === false) return app('json')->fail('请稍后再试(2)！');



//        return app('json')->success('',['url'=>$res,'url_base'=>'weixin://'.base64_encode($res)] );
        return app('json')->success('',['url'=>$res,'url_base'=>$res] );
//        return redirect( $url );
    }

    //充值订单状态修改和奖励 byWH
    // private function pay_chongzhi_scan_success($order_sn,$orderCd = '',$gongxian){
    private function pay_chongzhi_scan_success($order_sn,$orderCd = ''){
        $data['content']=$order_sn;//
        $res=Db::table('eb_test')->save($data);
        //$lastdata = Db::table('eb_test')->where(['order_sn'=>$data['order_sn'] ])->order('order_id desc')->find();
        try {
            $order = Db::table('eb_telephone_fare_ordernew')->where(['order_sn' => $order_sn, 'status' => 0])->find();
            //'status' => 2 订购中
            //'status' => 1 充值成功
            //'status' => 0 充值失败
            $chongzhi_order_sn = $this->placeTelephoneFareOrder($order['phone'],$order_sn,$order['product_id']);
            // if($chongzhi_order_sn){
            $res = Db::table('eb_telephone_fare_ordernew')->where(['order_id' => $order['order_id']])->update(['status' => 2,'pay_status' => 1, 'pay_time' => date('Y-m-d H:i:s'), 'out_order_sn' => $orderCd]);
            // }else{
            //     $res = Db::table('eb_telephone_fare_ordernew')->where(['order_id' => $order['order_id']])->update(['status' => 4,'pay_status' => 1, 'pay_time' => date('Y-m-d H:i:s'), 'out_order_sn' => $orderCd]);//'status' => 4则是充值订单添加异常
            // }

            //传订单信息给话费充值接口
            // $recharge_quota = $repository->groupDataId('user_recharge_quota', 0);
            // $chongzhi_order_sn = app()->make(StoreOrderRepository::class)->placeTelephoneFareOrder($order['phone'],$order_sn,$order['product_id']);

            // $order_sn="CZ2021061416301316236594132291";
            // $order['phone']="18978328386";

            // var_dump($chongzhi_order_sn);
            // die;
            // if($chongzhi_order_sn){
            //     $data['content']="已经传到第三方充值";
            //     $res=Db::table('eb_test')->save($data);
            // }else{
            //     $data['content']="没有传到第三方充值";
            //     $res=Db::table('eb_test')->save($data);
            // }

            //这里去掉了给用户发贡献值,放到充值成功后才执行

            if (!$res) return false;//话费充值订单状态


        } catch (Exception $e) {
            Log::info('---------error---------话费充值支付奖励发放失败666:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
            return false;
        }
        return true;
    }

    private function pay_scan_success($order_sn,$orderCd = ''){
        try {
            $orderCd = $orderCd ? $orderCd:'';
            //修改支付状态
            $order = Db::table('eb_scan_order')->where(['order_sn'=>$order_sn,'status'=>0])->find();
            if(!$order) return false;
            $res = Db::table('eb_scan_order')->where(['scan_order_id'=>$order['scan_order_id']])->update(['status'=>1,'pay_time'=>date('Y-m-d H:i:s'),'out_order_sn'=>$orderCd]);
            if(!$res) return false;
            $order['out_order_sn'] = $orderCd;
            try {
                bcscale(2);

                $total_price =  $order['money'] ;
                $prod_rate = bcdiv( $order['rate'] , 100);
                // 买家确认收货后：
                //买家获得 =  付款金额 * 比例 * 10   （贡献值）
                //卖家获得 = 付款金额 * 比例（贡献值）
                $gongxian_mer = bcmul($total_price , $prod_rate);
                $gongxian_user = bcmul($gongxian_mer , 10);

                $user = Db::table('eb_user')->find($order['uid']);
                if($gongxian_user > 0    ){
                    $res =  Db::table('eb_user')->where(['uid'=>$user['uid']])->update([
                        'brokerage_gongxian'=>$user['brokerage_gongxian'] + $gongxian_user
                    ]);
                    if($res){
                        Db::table('eb_user_bill')->save([
                            'uid' => $user['uid'],
                            'link_id' =>$order['scan_order_id'],
                            'pm'=>1,
                            'title'=>'线下支付赠送贡献值',
                            'category' => 'brokerage_gongxian',
                            'number'=> $gongxian_user,
                            'balance'=>$user['brokerage_gongxian'] + $gongxian_user ,
                            'mark'=>'线下支付赠送贡献值，贡献值增加'.$gongxian_user .',订单号：'.$order['order_sn'],
                            'create_time'=>date('Y-m-d H:i:s'),
                            'status'=>1
                        ]);
                    }
                }


                $user_mer = Db::table('eb_user')->find($order['mer_uid']);
                if($gongxian_mer > 0    ){
                    $res =  Db::table('eb_user')->where(['uid'=>$user_mer['uid']])->update([
                        'brokerage_gongxian'=>$user_mer['brokerage_gongxian'] + $gongxian_mer
                    ]);
                    if($res){
                        Db::table('eb_user_bill')->save([
                            'uid' => $user_mer['uid'],
                            'link_id' =>$order['scan_order_id'],
                            'pm'=>1,
                            'title'=>'线下支付让利赠送贡献值',
                            'category' => 'brokerage_gongxian',
                            'number'=> $gongxian_mer,
                            'balance'=>$user_mer['brokerage_gongxian'] + $gongxian_mer ,
                            'mark'=>'线下支付让利赠送贡献值，贡献值增加'.$gongxian_mer .',订单号：'.$order['order_sn'],
                            'create_time'=>date('Y-m-d H:i:s'),
                            'status'=>1
                        ]);
                    }
                }




                //如果买家或者卖家的上级是推荐人，并且还是渠道商 ：
                //买家-上级渠道商 = 付款金额 * 比例 * 10 * 5% （贡献值）
                //卖家-上级渠道商 = 付款金额 * 比例 * 10 * 5% （贡献值）

                $gongxian_mer_p1 = bcmul($gongxian_user , 0.05);//原来的
                // $gongxian_mer_p1 = bcmul($gongxian_user , 0.10);//2021-6-20 byWH 11:10
                $gongxian_user_p1 = $gongxian_mer_p1;

                $user_p1 = Db::table('eb_user')->find($user['spread_uid']);
                if($user_p1 && $user_p1['is_promoter'] && $gongxian_user_p1 >  0 ){
                    $res =  Db::table('eb_user')->where(['uid'=>$user_p1['uid']])->update([
                        'brokerage_gongxian'=>$user_p1['brokerage_gongxian'] + $gongxian_user_p1
                    ]);
                    if($res){
                        Db::table('eb_user_bill')->save([
                            'uid' => $user_p1['uid'],
                            'link_id' =>$order['scan_order_id'],
                            'pm'=>1,
                            'title'=>'线下推广奖励贡献值',
                            'category' => 'brokerage_gongxian',
                            'number'=> $gongxian_user_p1,
                            'balance'=>$user_p1['brokerage_gongxian'] + $gongxian_user_p1 ,
                            'mark'=>'线下推广奖励贡献值，贡献值增加'.$gongxian_user_p1 .',订单号：'.$order['order_sn'],
                            'create_time'=>date('Y-m-d H:i:s'),
                            'status'=>1
                        ]);
                    }
                }

                $user_mer_p1 = Db::table('eb_user')->find($user_mer['spread_uid']);
                if($user_mer_p1 && $user_mer_p1['is_promoter']  && $gongxian_mer_p1 >  0){
                    $res =  Db::table('eb_user')->where(['uid'=>$user_mer_p1['uid']])->update([
                        'brokerage_gongxian'=>$user_mer_p1['brokerage_gongxian'] + $gongxian_mer_p1
                    ]);
                    if($res){
                        Db::table('eb_user_bill')->save([
                            'uid' => $user_mer_p1['uid'],
                            'link_id' =>$order['scan_order_id'],
                            'pm'=>1,
                            'title'=>'线下推广商家奖励贡献值',
                            'category' => 'brokerage_gongxian',
                            'number'=> $gongxian_mer_p1,
                            'balance'=>$user_mer_p1['brokerage_gongxian'] + $gongxian_mer_p1 ,
                            'mark'=>'线下推广商家奖励贡献值，贡献值增加'.$gongxian_mer_p1 .',订单号：'.$order['order_sn'],
                            'create_time'=>date('Y-m-d H:i:s'),
                            'status'=>1
                        ]);
                    }
                }

                //如果 买家 的所有上级里面，只要是业务主任的：
                //业务主任 = 付款金额 * 比例 * 10 * 1.5% （贡献值）
                //memberlevel3    团队会员（0非团、1主任、2经理、3总监、4总裁）
                //主任 0.015  经理 0.015  总监0.03  总裁 0.02

                $user_ps = $user['retree'];
                $user_ps  = explode(',',$user_ps);
                $user_ps = array_reverse(array_unique(array_filter($user_ps)));
                $ps_arr = [
                    'p1'=>'',
                    'p2'=>'',
                    'p3'=>'',
                    'p4'=>'',
                ];
                foreach($user_ps as $v){
                    if($v  == $user['uid']) continue;
                    $ps_tmp = Db::table('eb_user')->find($v);
                    if($ps_tmp && $ps_tmp['memberlevel3'] > 0 ){
                        if(  !isset($ps_arr['p'.$ps_tmp['memberlevel3']]) || $ps_arr['p'.$ps_tmp['memberlevel3']]   || !$ps_tmp['is_promoter']  ) continue;
                        $ps_arr['p'.$ps_tmp['memberlevel3']] =  $ps_tmp;
                    }
                }

                $base_rate = 0;

                foreach($ps_arr as $vv){
                    if($vv){
                        $rate = 0;
                        switch($vv['memberlevel3']){
                            case 1:
                                $rate = Db::table('bonus_level3')->where(['id'=>1])->value('b1');
                                break;
                            case 2:
                                $rate = Db::table('bonus_level3')->where(['id'=>2])->value('b1');
                                break;
                            case 3:
                                $rate =  Db::table('bonus_level3')->where(['id'=>3])->value('b1');
                                break;
                            case 4:
                                $rate =  Db::table('bonus_level3')->where(['id'=>4])->value('b1');
                                break;
                        }
                        if($rate > $base_rate){
                            $vv_gongxian = bcmul($gongxian_user , bcsub( $rate , $base_rate )  );
                            $base_rate = $rate ;
                            if($vv_gongxian > 0){
                                $res =  Db::table('eb_user')->where(['uid'=>$vv['uid']])->update([
                                    'brokerage_gongxian'=>$vv['brokerage_gongxian'] + $vv_gongxian
                                ]);
                                if($res){
                                    Db::table('eb_user_bill')->save([
                                        'uid' => $vv['uid'],
                                        'link_id' =>$order['scan_order_id'],
                                        'pm'=>1,
                                        'title'=>'线下团队奖励贡献值',
                                        'category' => 'brokerage_gongxian',
                                        'number'=> $vv_gongxian,
                                        'balance'=>$vv['brokerage_gongxian'] + $vv_gongxian ,
                                        'mark'=>'线下团队奖励贡献值，贡献值增加'.$vv_gongxian .',订单号：'.$order['order_sn'],
                                        'create_time'=>date('Y-m-d H:i:s'),
                                        'status'=>1
                                    ]);
                                }
                            }
                        }
                    }
                }

                //sc162210399190917180

                Db::query("CALL  deal_quyubaohu_sc({$order['scan_order_id']},'{$order['order_sn']}')");

                //分账
                $this->ordersplit($order);



            } catch (Exception $e) {
                Log::info('-------------error------ 支付奖励发放失败66:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
            }



            //          //结算
            // bcscale(3);
            //          $gongxian_user = $order['money'] *  $order['rate']  / 10 ;
            //          $gongxian_mer = $order['money'] *  $order['rate'] / 100 ;
            //          $money_mer = $order['money'] * ( 100 - $order['rate']) / 100 ;
            //          $user = Db::table('eb_user')->find($order['uid']);
            //          $user_mer = Db::table('eb_user')->find($order['mer_uid']);
            //          $ctime = date('Y-m-d H:i:s');
            //          $res =  Db::table('eb_user')->where(['uid'=>$user['uid']])->update([
            //              'brokerage_gongxian'=>$user['brokerage_gongxian'] + $gongxian_user
            //          ]);
            //          if($res){
            //              Db::table('eb_user_bill')->save([
            //                  'uid' => $user['uid'],
            //                  'link_id' =>$order['scan_order_id'],
            //                  'pm'=>1,
            //                  'title'=>'扫码支付奖励',
            //                  'category' => 'brokerage_gongxian',
            //                  'number'=> $gongxian_user,
            //                  'balance'=>$user['brokerage_gongxian'] + $gongxian_user ,
            //                  'mark'=>'扫码支付奖励，贡献值增加'.$gongxian_user ,
            //                  'create_time'=>$ctime,
            //                  'status'=>1
            //              ]);
            //          }
            //          $res =  Db::table('eb_user')->where(['uid'=>$user_mer['uid']])->update([
            //              'brokerage_gongxian'=>$user_mer['brokerage_gongxian'] + $gongxian_mer
            //          ]);
            //          if($res){
            //              Db::table('eb_user_bill')->save([
            //                  'uid' => $user_mer['uid'],
            //                  'link_id' =>$order['scan_order_id'],
            //                  'pm'=>1,
            //                  'title'=>'扫码支付奖励',
            //                  'category' => 'brokerage_gongxian',
            //                  'number'=> $gongxian_mer,
            //                  'balance'=>$user_mer['brokerage_gongxian'] + $gongxian_mer ,
            //                  'mark'=>'扫码支付奖励，贡献值增加'.$gongxian_mer ,
            //                  'create_time'=>$ctime,
            //                  'status'=>1
            //              ]);
            //          }



        } catch (Exception $e) {
            Log::info('---------error---------扫码支付奖励发放失败666:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
            return false;
        }
        return true;
    }


    public static function batsplit(){

        $orders = Db::table('eb_scan_order')->where(['status'=>1,'is_split'=>0])->where('money','>',0.01)->select();
        foreach ($orders as $v){
            self::ordersplit($v);
        }

    }


    private static function ordersplit($order){
        if($order['is_split'])  return ;
        if(!$order['out_order_sn']){
            $out_order_sn = app()->make(StoreOrderRepository::class)->queryorderinfo(date('Ymd',strtotime($order['pay_time'])),$order['order_sn']);
            if(!$out_order_sn){
                Log::info('---------error---------未查询到快付订单:' .  $order['order_sn'] );
                return ;
            }
            $order['out_order_sn'] = $out_order_sn;
            Db::table('eb_scan_order')->where(['scan_order_id'=>$order['scan_order_id']])->update(['out_order_sn'=>$out_order_sn]);
        }
        $order['fz_order'] =  $order['order_sn'] . date('dHis') ;
        //分账
        if(app()->make(StoreOrderRepository::class)->doordersplit($order)){
            Db::table('eb_scan_order')->where(['scan_order_id'=>$order['scan_order_id']])->update(['is_split'=>1,'fz_order'=> $order['fz_order'] ]);
        }

    }


    private function scan_order_id(){
        list($msec, $sec) = explode(' ', microtime());
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');
        $orderId = 'sc' . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));
        return $orderId;
    }

    public function hotKeyword()
    {
        $keyword = systemGroupData('hot_keyword');
        return app('json')->success($keyword);
    }

    public function express(ExpressRepository $repository)
    {
        return app('json')->success($repository->options());
    }

    public function menus()
    {
        return app('json')->success(['banner' => systemGroupData('my_banner'), 'menu' => systemGroupData('my_menus')]);
    }

    public function refundMessage()
    {
        return app('json')->success(explode("\n", systemConfig('refund_message')));
    }

    public function config()
    {
        $config = systemConfig(['mer_location', 'alipay_open', 'hide_mer_status', 'mer_intention_open', 'share_info', 'share_title', 'share_pic', 'store_user_min_recharge', 'recharge_switch', 'balance_func_status', 'yue_pay_status', 'site_logo', 'routine_logo', 'site_name', 'login_logo']);
        $make = app()->make(TemplateMessageRepository::class);
        $sys_intention_agree = app()->make(CacheRepository::class)->getResult('sys_intention_agree');
        if (!$sys_intention_agree) {
            $sys_intention_agree = systemConfig('sys_intention_agree');
        }
        $config['sys_intention_agree'] = $sys_intention_agree;
        $config['tempid'] = $make->getSubscribe();
        return app('json')->success($config);
    }

    /**
     * @param GroupDataRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020/6/3
     */
    public function userRechargeQuota(GroupDataRepository $repository)
    {
        $recharge_quota = $repository->groupDataId('user_recharge_quota', 0);
        $recharge_attention = explode("\n", systemConfig('recharge_attention'));
        return app('json')->success(compact('recharge_quota', 'recharge_attention'));
    }

    /**
     * @param $field
     * @return mixed
     * @author xaboy
     * @day 2020/5/28
     */
    public function uploadImage($field)
    {
        $file = $this->request->file($field);
        if (!$file)
            return app('json')->fail('请上传图片');
        $file = is_array($file) ? $file[0] : $file;
        validate(["$field|图片" => [
            'fileSize' => 2097152,
            'fileExt' => 'jpg,jpeg,png,bmp,gif',
            'fileMime' => 'image/jpeg,image/png,image/gif',
            function ($file) {
                $ext = $file->extension();
                if ($ext != strtolower($file->extension())) {
                    return '图片后缀必须为小写';
                }
                return true;
            }
        ]])->check([$field => $file]);

        $upload = UploadService::create();
        $info = $upload->to('def')->move($field);
        if ($info === false) {
            return app('json')->fail($upload->getError());
        }
        $res = $upload->getUploadInfo();
        $res['dir'] = tidy_url($res['dir']);
        return app('json')->success(['path' => $res['dir']]);
    }
    public function testfone()
    {
        $order_sn="CZ2021061416301316236594132291";

        $order = Db::table('eb_telephone_fare_ordernew')->where(['order_sn' => $order_sn, 'status' => 1])->find();

        //给用户发贡献值
        $total_price =  $order['money'] ;

        // $gongxian_user=$gongxian;
        $user = Db::table('eb_user')->where(['uid' => $order["uid"]])->find();

        if($user){
            //给用户账户增加贡献值
            $res = Db::table('eb_user')->where(['uid'=>$user["uid"]])->update([
                'brokerage_gongxian'=>$user['brokerage_gongxian'] + $total_price
            ]);
            //记录用户账单
            if($res){
                Db::table('eb_user_bill')->save([
                    'uid' => $user["uid"],
                    'link_id' =>0,
                    'pm'=>1,
                    'title'=>'话费充值支付赠送贡献值',
                    'category' => 'brokerage_gongxian',
                    'number'=> $total_price,
                    'balance'=>$user['brokerage_gongxian'] + $total_price ,
                    'mark'=>'话费充值支付赠送贡献值，贡献值增加'.$total_price .',订单号：'.$order['order_sn'],
                    'create_time'=>date('Y-m-d H:i:s'),
                    'status'=>1
                ]);
            }
            //更新本条订单获得的贡献值
            $res = Db::table('eb_telephone_fare_ordernew')->where(['order_sn'=>$order_sn])->update([
                'contribute'=> $total_price
            ]);
        }else{
            $data['content']="没有获取到uid";
            $res=Db::table('eb_test')->save($data);
        }

        //$test=event('pay_sc_success_order' , ['order_sn' => '1']);//语音播报
        // $orderSn='1';
        // $url=Request::domain().':8080'.'/SzysServer/sendingMsg?order_sn='.$orderSn;
        // $test=HttpService::getRequest($url);
        // var_dump($test);
    }
    public function telephoneFareOrderNotify(){
        //回调实例api_userid=XX&order_no=2020120121451333769116212789248&retcode=1&errcode=100101101002012012145194038466
        // 返回：success (注此处是系统访问商户回调地址时，请商户返回的信息内容)。

        //var_dump(6666);
        try {

            // $temp['content']="话费充值回调成功!";//
            // $restemp=Db::table('eb_test')->save($temp);
            $data["api_userid"] = $this->request->param('api_userid');
            $data["order_no"] = $this->request->param('order_no');
            $data["retcode"] = $this->request->param('retcode');
            $data["errcode"] = $this->request->param('errcode');
            Log::info('话费充值回调 :' . json_encode($data));
            // var_dump($data);
            // die;
            // $restemp1=Db::table('eb_test')->save($data);
            // Log::info('话费回调 :' . json_encode($data));
            $api_userid=$data["api_userid"];
            $order_no=$data["order_no"];//订单号
            $retcode=$data["retcode"];//0=失败，1=成功
            $errcode=$data["errcode"];//凭证(流水号)
            if($retcode==1){
                Log::info('话费充值回调1 :充值成功！'. json_encode($data));
                $order = Db::table('eb_telephone_fare_ordernew')->where(['order_sn' => $order_no])->find();
                $res = Db::table('eb_telephone_fare_ordernew')->where(['order_id' => $order['order_id']])->update(['status' => 1, 'cz_time' => date('Y-m-d H:i:s'), 'out_order_sn_cz' => $errcode]);
                //以下给用户发贡献值
                //再获取一次order
                // $order = Db::table('eb_telephone_fare_ordernew')->where(['order_sn' => $order_no])->find();

                //获取发放贡献值金额
                $total_price =  $order['money'] ;

                $user = Db::table('eb_user')->where(['uid' => $order["uid"]])->find();

                if($user){
                    if((int)$order['contribute']==0){//如果订单里面的获得贡献值金额为0
                        //给用户账户增加贡献值
                        $res = Db::table('eb_user')->where(['uid'=>$user["uid"]])->update([
                            'brokerage_gongxian'=>$user['brokerage_gongxian'] + $total_price
                        ]);
                        //记录用户账单
                        if($res){
                            Db::table('eb_user_bill')->save([
                                'uid' => $user["uid"],
                                'link_id' =>0,
                                'pm'=>1,
                                'title'=>'话费充值支付赠送贡献值',
                                'category' => 'brokerage_gongxian',
                                'number'=> $total_price,
                                'balance'=>$user['brokerage_gongxian'] + $total_price ,
                                'mark'=>'话费充值支付赠送贡献值，贡献值增加'.$total_price .',订单号：'.$order['order_sn'],
                                'create_time'=>date('Y-m-d H:i:s'),
                                'status'=>1
                            ]);
                        }
                        //更新本条订单获得的贡献值
                        $res = Db::table('eb_telephone_fare_ordernew')->where(['order_sn'=>$order_no])->update([
                            'contribute'=> $total_price
                        ]);
                    }else{
                        Log::info('话费充值回调1b :充值成功但已获得过贡献值了！'. json_encode($data));
                    }
                }else{
                    Log::info('话费充值回调1a :充值成功但未获取到uid！'. json_encode($data));
                    // $data['content']="没有获取到uid或已经获得贡献值";
                    // $res=Db::table('eb_test')->save($data);

                }
                return "success";
            }else if($retcode==0){
                Log::info('话费充值回调0 :充值失败！'. json_encode($data));
                $order = Db::table('eb_telephone_fare_ordernew')->where(['order_sn' => $order_no, 'status' => 0])->find();
                $res = Db::table('eb_telephone_fare_ordernew')->where(['order_id' => $order['order_id']])->update(['status' => 0, 'cz_time' => date('Y-m-d H:i:s'), 'out_order_sn' => $order_no]);
                return "充值失败";
            }else if($retcode==2){
                Log::info('话费充值回调2 :订购中！'. json_encode($data));
                $order = Db::table('eb_telephone_fare_ordernew')->where(['order_sn' => $order_no, 'status' => 0])->find();
                $res = Db::table('eb_telephone_fare_ordernew')->where(['order_id' => $order['order_id']])->update(['status' => 2, 'cz_time' => date('Y-m-d H:i:s'), 'out_order_sn' => $order_no]);
                return "订购中";
            }else if($retcode==404){
                return "订单不存在";
            }else{
                return "未知情况";
            }
            //

        } catch (Exception $e) {
            Log::info('话费充值回调失败:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
            // $temp['content']="话费充值回调失败!";//
            // $restemp=Db::table('eb_test')->save($temp);
        }
    }
    /*
     * 查询话费快付账单
     */
    public function tempchongzhilist(){
        echo "<!DOCTYPE html>  
                <html>  
                <head>  
                <meta charset=\"UTF-8\">  
                <title>查询话费快付账单</title>  
                <style type=\"text/css\">  
                .table, .table * {margin: 0 auto; padding: 0;font-size: 14px;font-family: Arial, 宋体, Helvetica, sans-serif;}   
                .table {display: table; width: 80%; border-collapse: collapse;}   
                .table-tr {display: table-row; height: 30px;}   
                .table-th {display: table-cell;font-weight: bold;height: 100%;border: 1px solid gray;text-align: center;vertical-align: middle;}   
                .table-td {display: table-cell; height: 100%;border: 1px solid gray; text-align: center;vertical-align: middle;}   
                </style>  
                </head>  
                <body>  
                    <div class=\"table\">  
                        <div class=\"table-tr\">  
                            <div class=\"table-th\">ID</div>  
                            <div class=\"table-th\">订单号</div>  
                            <div class=\"table-th\">充值金额</div>  
                            <div class=\"table-th\">uid</div>  
                            <div class=\"table-th\">已获贡献值</div>  
                            <div class=\"table-th\">手机号</div>
                            <div class=\"table-th\">运营商</div>  
                            <div class=\"table-th\">城市</div>  
                            <div class=\"table-th\">订单创建时间</div>
                            <div class=\"table-th\">订单支付时间</div> 
                            <div class=\"table-th\">充值状态</div> 
                            <div class=\"table-th\">产品ID</div>  
                            <div class=\"table-th\">支付状态</div>
                            <div class=\"table-th\">外部订单号</div>  
                            <div class=\"table-th\">充值到账时间</div>  
                            <div class=\"table-th\">充值反馈代码</div>
                            <div class=\"table-th\">原快付单号</div>
                        </div>";
        $list = Db::table('eb_telephone_fare_ordernew')->order('order_id desc')->select();
        foreach($list as $v){
            echo"<div class=\"table-tr\">  
                            <div class=\"table-td\">".$v['order_id']."</div>  
                            <div class=\"table-td\">".$v['order_sn']."</div>  
                            <div class=\"table-td\">".$v['money']."</div>
                            <div class=\"table-td\">".$v['uid']."</div>  
                            <div class=\"table-td\">".$v['contribute']."</div>  
                            <div class=\"table-td\">".$v['phone']."</div>
                            <div class=\"table-td\">".$v['correspondence']."</div>  
                            <div class=\"table-td\">".$v['location']."</div>  
                            <div class=\"table-td\">".$v['create_time']."</div>
                            <div class=\"table-td\">".$v['pay_time']."</div>  
                            <div class=\"table-td\">".$v['status'];
            // $this->chongzhistatus($v['status']);
            echo "</div>";
            echo "<div class=\"table-td\">".$v['product_id']."</div>  
                            <div class=\"table-td\">".$v['pay_status'];
            // $this->chongzhistatus($v['pay_status']);
            echo "</div>
                            <div class=\"table-td\">".$v['out_order_sn']."</div>  
                            <div class=\"table-td\">".$v['cz_time']."</div>  
                            <div class=\"table-td\">".$v['out_order_sn_cz']."</div>
                            <div class=\"table-td\">".$v['order_sn_old']."</div>
                        </div>";
        }
        echo"
                    </div>  
                </body>  
                </html>";

//         foreach($list as $v){
//             var_dump($v);

//             // echo $v["order_sn"];

// 		}
        // foreach ($list as $k => $v) {
        //     echo "\$list[$k] => $v.\n";
        // }
        // var_dump($list);
        // die;
    }
    public function chongzhistatus($status){
        if($status==1){
            return "充值成功";
        }else if($status==2){
            return "订购中";
        }else if($status==0){
            return "充值失败";
        }else{
            return "异常";
        }
    }
    public function paystatus($status){
        if($status==1){
            return "已支付";
        }else{
            return "未支付";
        }
    }
    /*
     * 临时快付分账
     */
    public function tempKFfenzhang($order_sn){
        // $order = Db::table('eb_scan_order')->where(['order_sn'=>$order_sn,'status'=>0])->find();
        $order = Db::table('eb_scan_order')->where(['order_sn'=>$order_sn])->find();
        // var_dump($order);
        //分账
        $returndata=$this->ordersplit($order);
        var_dump($returndata);
    }

    /*
    *临时传充值订单接口
    */
    public function tempplaceTelephoneFareOrder($order_sn,$product_id){
        //判断订单是否已经支付
        $ifpay = Db::table('eb_telephone_fare_ordernew')->where(['order_sn' => $order_sn])->find();
        if($ifpay["pay_status"]==1){
            $order = Db::table('eb_telephone_fare_ordernew')->where(['order_sn' => $order_sn])->find();
            $data['api_userid']="guangcai";
            if($product_id==""){//增加传$product_id,用来拆分500元话费
                $data['product_id']=$order["product_id"];
            }else{
                $data['product_id']=$product_id;
            }
            // var_dump($data['product_id']);
            // die;
            // $data['mobile']="18678787227";
            // $data['order_no']="201609220138457462";
            $data['mobile']=$order["phone"];
            $tempnum=1;//增加1
            $data['order_no']=$order["order_sn"].$tempnum;
            // var_dump($data['order_no']);
            // die;
            //更新原对应的快付的订单号
            if($order["order_sn_old"]==''){//如果快付旧订单号不存在
                Db::table('eb_telephone_fare_ordernew')->where(['order_id' => $order['order_id']])->update(['order_sn_old' => $order["order_sn"]]);
            }
            //更新这边数据库的订单号也+1
            Db::table('eb_telephone_fare_ordernew')->where(['order_id' => $order['order_id']])->update(['order_sn' => $data['order_no']]);

            //$arr = array ('a'=>1,'b'=>2,'c'=>3,'d'=>4,'e'=>5);
            //echo json_encode($arr);
            //var_dump($data);
            //array(2) {["api_userid"]=>string(8) "guangcai"["product_id"]=>string(6) "200250"}
            //die;
            $pro_data['api_data_temp']=json_encode($data);
            // var_dump($pro_data);
            //string(97) "{"api_userid":"guangcai","product_id":"200250","mobile":"200250","order_no":"201609220138457462"}"
            //die;
            //$pro_data['api_data_temp']='{"api_userid":"guangcai","product_id":"200250","mobile":"18678787227","order_no":"201609220138457462"}';
            //var_dump($pro_data);
            //die;

            //获取加密充值数据
            $chongzhi_data=HttpService::postRequest("http://ly.wluo.cn/showcz.php",$pro_data);
            // var_dump($chongzhi_data);
            // die;
            //$chongzhi_data=json_decode(HttpService::getRequest('http://ly.wluo.cn/showcz.php?api_data_temp='.$api_data_temp));
            $data['api_data']= $chongzhi_data;
            // var_dump($chongzhi_data);
            // die;
            //返回
            // string(224)
            //"A077C3AB1C1F24972CAE2DCECA9CAE6C5DCE057F42EE79011CF161B4938892125D946BFBE53C1D6DD224049AD813C26465C6ACF7F6C99A5C9B2BDBDEF47713E699B683D66FC5DAAE2EE5152A69EFF96DCB39A3A3E0148CC1B47604B157D690269B8AE5776F6F32B83206EA4AA19F7517"

            //发起充值支付
            // $phone_data=json_decode(HttpService::getRequest('http://8.131.240.243:8081/api/recharge.jsp?api_userid='.$data['api_userid'].'&api_data='.$data['api_data']));
            $phone_data=HttpService::getRequest('http://8.131.240.243:8081/api/recharge.jsp?api_userid='.$data['api_userid'].'&api_data='.$data['api_data']);
            var_dump($phone_data);
            var_dump($data['order_no']);//新增的订单号
            var_dump("已经支付,并成功重新发起充值订单");
        }else{
            var_dump("未支付");
        }
        die;

    }

    /*
     * placeTelephoneFareOrder
     * 发起充值支付
     * 调用1接口(insertTelephoneFareOrder)获得order_no，在终端发起支付，支付完成后调用此接口，修改订单支付状态，并触发调用第三方充值接口；
     * 第三方接口回调充值结果，修改订单状态值，如果成功则修改用户贡献值
     * 输出：调起终端支付的相关信息，如签名等，据此调起支付应用完成订单付款，支付平台回调给服务端修改充值订单状态，
     * 并根据支付状态发起第三方话费充值接口调用
     */

    public function placeTelephoneFareOrder($mobile,$order_no,$product_id){
        //http://test.shuzaiyunshang.com/api/user/placeTelephoneFareOrder
        //http://8.131.240.243:8081/api/recharge.jsp
        //{"api_userid":"1111","product_id":"10005115","mobile":"18678787227","order_no":"201609220138457462"}
        // var_dump($mobile);
        // var_dump($order_no);
        // die;
        $data['api_userid']="guangcai";
        $data['product_id']=$product_id;
        // $data['mobile']="18678787227";
        // $data['order_no']="201609220138457462";
        $data['mobile']=$mobile;
        $data['order_no']=$order_no;
        //$arr = array ('a'=>1,'b'=>2,'c'=>3,'d'=>4,'e'=>5);
        //echo json_encode($arr);
        //var_dump($data);
        //array(2) {["api_userid"]=>string(8) "guangcai"["product_id"]=>string(6) "200250"}
        //die;
        $pro_data['api_data_temp']=json_encode($data);
        // var_dump($pro_data);
        //string(97) "{"api_userid":"guangcai","product_id":"200250","mobile":"200250","order_no":"201609220138457462"}"
        //die;
        //$pro_data['api_data_temp']='{"api_userid":"guangcai","product_id":"200250","mobile":"18678787227","order_no":"201609220138457462"}';
        //var_dump($pro_data);
        //die;

        //获取加密充值数据
        $chongzhi_data=HttpService::postRequest("http://ly.wluo.cn/showcz.php",$pro_data);
        // var_dump($chongzhi_data);
        // die;
        //$chongzhi_data=json_decode(HttpService::getRequest('http://ly.wluo.cn/showcz.php?api_data_temp='.$api_data_temp));
        $data['api_data']= $chongzhi_data;
        // var_dump($chongzhi_data);
        // die;
        //返回
        // string(224)
        //"A077C3AB1C1F24972CAE2DCECA9CAE6C5DCE057F42EE79011CF161B4938892125D946BFBE53C1D6DD224049AD813C26465C6ACF7F6C99A5C9B2BDBDEF47713E699B683D66FC5DAAE2EE5152A69EFF96DCB39A3A3E0148CC1B47604B157D690269B8AE5776F6F32B83206EA4AA19F7517"

        //发起充值支付
        // $phone_data=json_decode(HttpService::getRequest('http://8.131.240.243:8081/api/recharge.jsp?api_userid='.$data['api_userid'].'&api_data='.$data['api_data']));
        $phone_data=HttpService::getRequest('http://8.131.240.243:8081/api/recharge.jsp?api_userid='.$data['api_userid'].'&api_data='.$data['api_data']);
        // var_dump($phone_data);
        // die;
        // if($phone_data){
        //     // var_dump("第三方充值订单提交成功");
        // }else{
        //     return false;
        // }
        // var_dump($phone_data);
        // die;



    }

    public function fastpayNotify()
    {

        try {
            $data = $this->request->getContent();
            Log::info('支付回调 :' . json_encode($data));
            if(!$data) return;
            $data = openssl_decrypt(base64_decode($data), 'des-ede3', 'L2DYNQ5YR9P532ZTX8WNTBWX', 1);
            $respData = json_decode($data, true);
            if("100" ==  $respData["transStatus"]){
                Log::info('支付回调4 :支付成功！'. json_encode($respData));

                if(substr($respData['outOrderId'],0,2) == 'sc'){//线下扫码订单
                    $order_Sn = $respData['outOrderId'];
                    $url='http://test.shuzaiyunshang.com:8080/SzysServer/sendingMsg?oid='.$order_Sn;//测试通过
                    //$url=Request::domain().':8080'.'/SzysServer/sendingMsg?oid='.$order_Sn;//测试通过
                    $msg_s_data=HttpService::getRequest($url);
                    Log::info('语音播报：'. $msg_s_data);
                    // event('pay_sc_success_order' , ['order_sn' => $respData['outOrderId']]);//语音播报
                    if(!$this->pay_scan_success($respData['outOrderId'],$respData['orderCd']))  return '';
                }else if(substr($respData['outOrderId'],0,2) == 'CZ'){
                    //"orderCd": "3e9820f6e4fa40fa94a0fc31065be270",
                    if(!$this->pay_chongzhi_scan_success($respData['outOrderId'],$respData['orderCd']))  {
                        return '';
                    };
                }else{
                    event('pay_success_order' , ['order_sn' => $respData['outOrderId']]);
                }
                /*交易成功*/
                //                        echo "外部订单号：" . $respData["outOrderId"] . "<br/>";
                //                        echo "支付订单号：" . $respData["orderCd"] . "<br/>";
                //                        echo "交易金额：" . $respData["transAmt"] . "<br/>";
            }else if("102" == $respData["transStatus"]){
                /*交易失败*/
                Log::info('支付失败 ！！！' );
            } else {
                /*未知结果，这种情况不会有。*/
            }
            echo "{\"respCode\" : \"0000\", \"respMsg\" : \"成功\"}";
            return '';
        } catch (Exception $e) {
            Log::info('支付回调失败:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
        }
    }


    /**
     * @return Response
     * @author xaboy
     * @day 2020/6/3
     */
    public function wechatNotify()
    {
        try {
            return response(WechatService::create()->handleNotify()->getContent());
        } catch (Exception $e) {
            Log::info('支付回调失败:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
        }
    }

    public function routineNotify()
    {
        try {
            return response(MiniProgramService::create()->handleNotify()->getContent());
        } catch (Exception $e) {
            Log::info('支付回调失败:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
        }
    }

    public function alipayNotify($type)
    {
        if (!in_array($type, ['order', 'user_recharge', 'presell']))
            throw new ValidateException('参数错误');
        try {
            AlipayService::create()->notify($type, $this->request->post());
        } catch (Exception $e) {
            Log::info('支付宝回调失败:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
        }
    }

    /**
     * 获取图片base64
     * @return mixed
     */
    public function get_image_base64()
    {
        list($imageUrl, $codeUrl) = $this->request->params([
            ['image', ''],
            ['code', ''],
        ], true);
        try {
            $codeTmp = $code = $codeUrl ? image_to_base64($codeUrl) : '';
            if (!$codeTmp) {
                $putCodeUrl = put_image($codeUrl);
                $code = $putCodeUrl ? image_to_base64('./runtime/temp' . $putCodeUrl) : '';
                $code && unlink('./runtime/temp' . $putCodeUrl);
            }

            $imageTmp = $image = $imageUrl ? image_to_base64($imageUrl) : '';
            if (!$imageTmp) {
                $putImageUrl = put_image($imageUrl);
                $image = $putImageUrl ? image_to_base64('./runtime/temp' . $putImageUrl) : '';
                $image && unlink('./runtime/temp' . $putImageUrl);
            }
            return app('json')->success(compact('code', 'image'));
        } catch (Exception $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function home()
    {
        $banner = systemGroupData('home_banner', 1, 10);
        $menu = systemGroupData('home_menu');
        $hot = systemGroupData('home_hot', 1, 4);
        $ad = systemConfig(['home_ad_pic', 'home_ad_url']);
        $category = app()->make(StoreCategoryRepository::class)->getTwoLevel();
        return app('json')->success(compact('banner', 'menu', 'hot', 'ad', 'category'));
    }

    public function visit()
    {
        if (!$this->request->isLogin()) return app('json')->success();
        [$page, $type] = $this->request->params(['page', 'type'], true);
        $uid = $this->request->uid();
        if (!$page || !$uid) return app('json')->fail();
        $userVisitRepository = app()->make(UserVisitRepository::class);
        $type == 'routine' ? $userVisitRepository->visitSmallProgram($uid, $page) : $userVisitRepository->visitPage($uid, $page);
        return app('json')->success();
    }

    public function hotBanner($type)
    {
        if (!in_array($type, ['new', 'hot', 'best', 'good']))
            $data = [];
        else
            $data = systemGroupData($type . '_home_banner');
        return app('json')->success($data);
    }

    public function pay_key($key)
    {
        $cache = Cache::store('file');
        if (!$cache->has('pay_key' . $key)) {
            return app('json')->fail('支付链接不存在');
        }
        return app('json')->success($cache->get('pay_key' . $key));
    }

    public function lbs_geocoder()
    {
        $data = explode(',', $this->request->param('location', ''));
        $locationOption = new LocationOption(systemConfig('tx_map_key'));
        $locationOption->setLocation($data[0] ?? '', $data[1] ?? '');
        $location = new Location($locationOption);
        $res = $location->request();
        if ($res->error) {
            return app('json')->fail($res->error);
        }
        if ($res->status) {
            return app('json')->fail($res->message);
        }
        if (!$res->result) {
            return app('json')->fail('获取失败');
        }
        return app('json')->success($res->result);
    }
}
