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

    private function pay_scan_success($order_sn){
        try {
                //修改支付状态
                $order = Db::table('eb_scan_order')->where(['order_sn'=>$order_sn,'status'=>0])->find();
                if(!$order) return false;
                $res = Db::table('eb_scan_order')->where(['scan_order_id'=>$order['scan_order_id']])->update(['status'=>1,'pay_time'=>date('Y-m-d H:i:s')]);
                if(!$res) return false;
	
	
	try {
				bcscale(2);
	            $user = Db::table('eb_user')->find($order['uid']);
	            $user_mer = Db::table('eb_user')->find($order['mer_uid']);
				
				$user_p1 = Db::table('eb_user')->find($user['spread_uid']);
				$user_mer_p1 = Db::table('eb_user')->find($user_mer['spread_uid']);
				$total_price =  $order['money'] ;
				$prod_rate = bcdiv( $order['rate'] , 100);
	// 买家确认收货后：
	//买家获得 =  付款金额 * 比例 * 10   （贡献值）
	//卖家获得 = 付款金额 * 比例（贡献值）			
	            $gongxian_mer = bcmul($total_price , $prod_rate);
	            $gongxian_user = bcmul($gongxian_mer , 10);

				if($gongxian_mer > 0    ){
                    $res =  Db::table('eb_user')->where(['uid'=>$user_mer['uid']])->update([
                        'brokerage_gongxian'=>$user_mer['brokerage_gongxian'] + $gongxian_mer
                    ]);
                    if($res){
                        Db::table('eb_user_bill')->save([
                            'uid' => $user_mer['uid'],
                            'link_id' =>$order['scan_order_id'],
                            'pm'=>1,
                            'title'=>'团队管理奖线下支付',
                            'category' => 'brokerage_gongxian',
                            'number'=> $gongxian_mer,
                            'balance'=>$user_mer['brokerage_gongxian'] + $gongxian_mer ,
                            'mark'=>'团队管理奖线下支付，贡献值增加'.$gongxian_mer .',订单号：'.$order['order_sn'],
                            'create_time'=>date('Y-m-d H:i:s'),
                            'status'=>1
                        ]);
                    }
                }
			    if($gongxian_user > 0    ){
                    $res =  Db::table('eb_user')->where(['uid'=>$user['uid']])->update([
                        'brokerage_gongxian'=>$user['brokerage_gongxian'] + $gongxian_user
                    ]);
                    if($res){
                        Db::table('eb_user_bill')->save([
                            'uid' => $user['uid'],
                            'link_id' =>$order['scan_order_id'],
                            'pm'=>1,
                            'title'=>'团队管理奖线下支付',
                            'category' => 'brokerage_gongxian',
                            'number'=> $gongxian_user,
                            'balance'=>$user['brokerage_gongxian'] + $gongxian_user ,
                            'mark'=>'团队管理奖线下支付，贡献值增加'.$gongxian_user .',订单号：'.$order['order_sn'],
                            'create_time'=>date('Y-m-d H:i:s'),
                            'status'=>1
                        ]);
                    }
                }
				
	//如果买家或者卖家的上级是推荐人，并且还是渠道商 ：
	//买家-上级渠道商 = 付款金额 * 比例 * 10 * 5% （贡献值）
	//卖家-上级渠道商 = 付款金额 * 比例 * 10 * 5% （贡献值）
				$gongxian_mer_p1 = bcmul($gongxian_user , 0.05);
				$gongxian_user_p1 = $gongxian_mer_p1;
	
				if($user_p1 && $user_p1['is_promoter'] && $gongxian_user_p1 >  0 ){
					$res =  Db::table('eb_user')->where(['uid'=>$user_p1['uid']])->update([
					    'brokerage_gongxian'=>$user_p1['brokerage_gongxian'] + $gongxian_user_p1
					]);
					if($res){
					    Db::table('eb_user_bill')->save([
					        'uid' => $user_p1['uid'],
					        'link_id' =>$order['scan_order_id'],
					        'pm'=>1,
					        'title'=>'团队管理奖线下支付',
					        'category' => 'brokerage_gongxian',
					        'number'=> $gongxian_user_p1,
					        'balance'=>$user_p1['brokerage_gongxian'] + $gongxian_user_p1 ,
                            'mark'=>'团队管理奖线下支付，贡献值增加'.$gongxian_user_p1 .',订单号：'.$order['order_sn'],
					        'create_time'=>date('Y-m-d H:i:s'),
					        'status'=>1
					    ]);
					}
				}
				if($user_mer_p1 && $user_mer_p1['is_promoter']  && $gongxian_mer_p1 >  0){
					$res =  Db::table('eb_user')->where(['uid'=>$user_mer_p1['uid']])->update([
					    'brokerage_gongxian'=>$user_mer_p1['brokerage_gongxian'] + $gongxian_mer_p1
					]);
					if($res){
					    Db::table('eb_user_bill')->save([
					        'uid' => $user_mer_p1['uid'],
					        'link_id' =>$order['scan_order_id'],
					        'pm'=>1,
					        'title'=>'团队管理奖线下支付',
					        'category' => 'brokerage_gongxian',
					        'number'=> $gongxian_mer_p1,
					        'balance'=>$user_mer_p1['brokerage_gongxian'] + $gongxian_mer_p1 ,
                            'mark'=>'团队管理奖线下支付，贡献值增加'.$gongxian_mer_p1 .',订单号：'.$order['order_sn'],
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
					'p2'=>'',
					'p3'=>'',
					'p4'=>'',
					'p5'=>'',
				];
				foreach($user_ps as $v){
					if($v  == $user['uid']) continue;
					$ps_tmp = Db::table('eb_user')->find($v);
					if($ps_tmp && $ps_tmp['memberlevel3'] > 0 ){
						if($ps_arr['p'.$ps_tmp['memberlevel3']]  || !$ps_tmp['is_promoter']  ) continue;
						$ps_arr['p'.$ps_tmp['memberlevel3']] =  $ps_tmp;
					}
				}
				foreach($ps_arr as $vv){
					if($vv){
						$rate = 0;
						switch($vv['memberlevel3']){
							case 1:
							$rate = 0.015;
							break;
							case 2:
							$rate = 0.015;
							break;
							case 3:
							$rate = 0.03;
							break;
							case 4:
							$rate = 0.02;
							break;
						}
						$vv_gongxian = bcmul($gongxian_user , $rate );
						if($vv_gongxian > 0){
                            $res =  Db::table('eb_user')->where(['uid'=>$vv['uid']])->update([
                                'brokerage_gongxian'=>$vv['brokerage_gongxian'] + $vv_gongxian
                            ]);
                            if($res){
                                Db::table('eb_user_bill')->save([
                                    'uid' => $vv['uid'],
                                    'link_id' =>$order['scan_order_id'],
                                    'pm'=>1,
                                    'title'=>'团队管理奖线下支付',
                                    'category' => 'brokerage_gongxian',
                                    'number'=> $vv_gongxian,
                                    'balance'=>$vv['brokerage_gongxian'] + $vv_gongxian ,
                                    'mark'=>'团队管理奖线下支付，贡献值增加'.$vv_gongxian .',订单号：'.$order['order_sn'],
                                    'create_time'=>date('Y-m-d H:i:s'),
                                    'status'=>1
                                ]);
                            }
                        }
					}
				}
				
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


    public function fastpayNotify()
    {
        try {
                $data = $this->request->getContent();
                Log::info('支付回调 :' . json_encode($data));
                if(!$data) return;
                $data = openssl_decrypt(base64_decode($data), 'des-ede3', 'L2DYNQ5YR9P532ZTX8WNTBWX', 1);
                $respData = json_decode($data, true);
                if("100" ==  $respData["transStatus"]){
                Log::info('支付回调4 :支付成功！' );

                if(substr($respData['outOrderId'],0,2) == 'sc'){
                  if(!$this->pay_scan_success($respData['outOrderId']))  return '';
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
