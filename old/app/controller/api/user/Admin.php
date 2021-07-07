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


namespace app\controller\api\user;


use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\controller\merchant\Common;
use crmeb\basic\BaseController;
use crmeb\services\QrcodeService;
use think\App;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Route;
use think\response\Json;

class Admin extends BaseController
{
    protected $merId;
    protected $mer;

    public function __construct(App $app)
    {
        parent::__construct($app);

        $user = $this->request->userInfo();
        if (isset($user->service->customer) && $user->service->customer == 1) {
            $this->merId = $user->service->mer_id;
            $this->mer = $user->service;
        } else {
            throw new HttpResponseException(app('json')->fail('没有权限'));
        }
    }


    public function crate(MerchantRepository $repository)
    {
        $crate = $this->request->post('crate/d');
        Db::table('eb_merchant')->where(['mer_id'=>$this->merId])->update(['crate'=>$crate]);
        return app('json')->success('修改成功！');
    }

    public function regquery( )
    {
        $outMchtCd = 'outmch'.$this->merId;
//        $outMchtCd = 'MCHT965103250';

        $typeField = "ORG";
        $secretKey = "L2DYNQ5YR9P532ZTX8WNTBWX";
        $orgCd = "202010209693553";
        $encReqData =   openssl_encrypt(json_encode([
            'trscode'=>'AGM0207',
            'orgCd'=>'202010209693553',
            'outMchtCd'=>$outMchtCd,
            'proCd'=>'mcht',
            'chanelType'=>'1',
            'checkStatus'=>'N',
        ]),'des-ede3', $secretKey, 0);
        $data = [];
        $data["typeField"] = $typeField;
        $data["keyField"] = $orgCd;
        $data["dataField"] = $encReqData;

        $encRespStr = $this->send_request('https://api.yunfastpay.com', json_encode($data));
        $encRespStr = json_decode($encRespStr,true);
        if($encRespStr['dataField']){
            $resdata =  openssl_decrypt(base64_decode($encRespStr['dataField']), 'des-ede3', $secretKey , 1);
            $resdata =  json_decode($resdata,true) ;
            if($resdata['respCode'] == '0000'){
                //0成功，1处理中，3，审核拒绝（原因参考应答描述）
                    switch ($resdata['mchtStatus']){
                        case 'A':
                            return app('json')->success('请先完善资料！');
                            break;
                        case '0':
                            //
                            if($resdata['mchtCd']){
                                Db::table('eb_merchant')->where('mer_id',$this->merId)->whereNull('sub_mer')->update([
                                    'sub_mer'=>$resdata['mchtCd'],
                                ]);
                            }
                            return app('json')->success('审核已通过！');
                            break;
                        case '1':
                            return app('json')->success('资料审核中.....');
                            break;
                        case '3':
                            return app('json')->success('审核未通过：'.$resdata['respMsg']);
                            break;
                        default:
                            return app('json')->success('查询状态未知！');
                    }
            }
        }
        return app('json')->success('查询错误，请稍后再试！');
    }

    private function send_request($url, $params = [], $method = 'POST', $options = [])
    {
        Log::write('send_request2_1');
        $method = strtoupper($method);
        $protocol = substr($url, 0, 5);
        $query_string = is_array($params) ? http_build_query($params) : $params;

        $ch = curl_init();
        $defaults = [];
        if ('GET' == $method)
        {
            $geturl = $query_string ? $url . (stripos($url, "?") !== FALSE ? "&" : "?") . $query_string : $url;
            $defaults[CURLOPT_URL] = $geturl;
        }
        else
        {
            Log::write('send_request2_2');
            $defaults[CURLOPT_URL] = $url;
            if ($method == 'POST')
            {
                $defaults[CURLOPT_POST] = 1;
            }
            else
            {
                $defaults[CURLOPT_CUSTOMREQUEST] = $method;
            }
            $defaults[CURLOPT_POSTFIELDS] = $query_string;
        }
        Log::write('send_request3_3');
        $defaults[CURLOPT_HEADER] = FALSE;
        $defaults[CURLOPT_USERAGENT] = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.98 Safari/537.36";
        $defaults[CURLOPT_FOLLOWLOCATION] = TRUE;
        $defaults[CURLOPT_RETURNTRANSFER] = TRUE;
        $defaults[CURLOPT_CONNECTTIMEOUT] = 30;
        $defaults[CURLOPT_TIMEOUT] = 30;

        // disable 100-continue
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Expect:", "Content-Type:application/json"));

        if ('https' == $protocol)
        {
            $defaults[CURLOPT_SSL_VERIFYPEER] = FALSE;
            $defaults[CURLOPT_SSL_VERIFYHOST] = FALSE;
        }

        curl_setopt_array($ch, (array) $options + $defaults);

        $ret = curl_exec($ch);
        $err = curl_error($ch);
        Log::write('send_request4_4');
        Log::write('-------');
        Log::write($ret);
        Log::write($err);
        Log::write('-------');
        if (FALSE === $ret || !empty($err))
        {
            $errno = curl_errno($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            return [
                'ret'   => FALSE,
                'errno' => $errno,
                'msg'   => $err,
                'info'  => $info,
            ];
        }
        curl_close($ch);
        return $ret;
    }

    public function orderStatistics(StoreOrderRepository $repository)
    {
        $order = $repository->OrderTitleNumber($this->merId, null);
        $common = app()->make(Common::class);
        $data = [];
        $data['today'] = $common->mainGroup('today', $this->merId);
        $data['yesterday'] = $common->mainGroup('yesterday', $this->merId);
        $data['month'] = $common->mainGroup('month', $this->merId);
        $crate =   Db::table('eb_merchant')->where(['mer_id'=>$this->merId])->field('crate')->find();
//        $qr_url = 'https://api.pwmqr.com/qrcode/create/?url=' . $this->request->domain(true).'?mid='.$this->merId;
        $qr_url = app()->make(QrcodeService::class)->getQRCodePath( $this->request->domain(true).'?mid='.$this->merId  , md5($this->request->domain(true).'?mid='.$this->merId) . '.jpg' )['thumb_path'];

        $outMchtCd = 'outmch'.$this->merId;
//        $outMchtCd = 'MCHT965103250';
        $sign = strtoupper(md5('orgCd=202010209693553&chnlCd=sxf&outMchtCd='.$outMchtCd.'&L2DYNQ'));
        $reg_url =  "https://mcht.yunfastpay.com/html#/mcht_protocol?orgCd=202010209693553&chnlCd=sxf&outMchtCd={$outMchtCd}&sign={$sign}";

        $today = date('Y-m-d 00:00:00');
        $yesterday =  date("Y-m-d 00:00:00", strtotime(date("Y-m-d"))-86399);
        $month =   date('Y')."-".date('m')."-01 00:00:00";

        $scan_info = [
            'total_num'=>Db::table('eb_scan_order')->where(['mer_id'=>$this->merId,'status'=>1])->count(),
            'total_coin'=>Db::table('eb_scan_order')->where(['mer_id'=>$this->merId,'status'=>1])->sum('money'),

            'today_coin'=>Db::table('eb_scan_order')->where(['mer_id'=>$this->merId,'status'=>1 ])->where('pay_time','>=',$today)->sum('money'),
            'yesterday_coin'=>Db::table('eb_scan_order')->where(['mer_id'=>$this->merId,'status'=>1])->where('pay_time','between',"{$yesterday},{$today}")->sum('money'),
            'month_coin'=>Db::table('eb_scan_order')->where(['mer_id'=>$this->merId,'status'=>1 ])->where('pay_time','>=',$month)->sum('money'),

            'today_num'=>Db::table('eb_scan_order')->where(['mer_id'=>$this->merId,'status'=>1 ])->where('pay_time','>=',$today)->count(),
            'yesterday_num'=>Db::table('eb_scan_order')->where(['mer_id'=>$this->merId,'status'=>1])->where('pay_time','between',"{$yesterday},{$today}")->count(),
            'month_num'=>Db::table('eb_scan_order')->where(['mer_id'=>$this->merId,'status'=>1 ])->where('pay_time','>=',$month)->count(), 

        ];

        $mer = Db::table('eb_merchant')->where(['mer_id'=>$this->merId])->find();
        $mer_name = $mer?$mer['mer_name'] :'';
        $mer_money = $mer?$mer['money']+0:'0.00';


        //yesteraday
        $mer_yes =  Db::table('eb_merchant_bill')->where(['mer_id'=>$this->merId,'type'=>'add'])->where('create_time','between',"{$yesterday},{$today}")->sum('money');
        $mer_total =  Db::table('eb_user_extract')->where(['mer_id'=>$this->merId,'status'=>1])->sum('extract_price');

        return app('json')->success(compact('mer_yes','mer_total','mer_money','mer_name','order', 'data','crate','qr_url','reg_url','scan_info'));
    }


    public function orderDetail(StoreOrderRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        list($start, $stop) = $this->request->params([
            ['start', strtotime(date('Y-m'))],
            ['stop', time()],
        ], true);
        if ($start == $stop) return app('json')->fail('参数有误');
        if ($start > $stop) {
            $middle = $stop;
            $stop = $start;
            $start = $middle;
        }
        $where = $this->request->has('start') ? ['dateRange' => compact('start', 'stop')] : [];
        $list = $repository->orderGroupNumPage($where, $page, $limit, $this->merId);
        return app('json')->success($list);
    }

    public function merOrderList( )
    {
        [$page, $limit] = $this->getPage();
        $list = Db::table('eb_store_order')->whereBetween('create_time',[date('Y-m-d 00:00:00'),date('Y-m-d H:i:s')])->where(['mer_id'=>$this->merId,'paid'=>1])->where('status','>',0)->order('order_id desc')->page($page, $limit)->field('order_sn ,pay_time,pay_price,rangli_coin')->select()->toArray();
        foreach ($list as &$v){
            $v['pay_price'] = $v['pay_price'] - $v['rangli_coin'];
        }
        unset($v);
        return app('json')->success($list);
    }




    public function scanOrderList( )
    {
        [$page, $limit] = $this->getPage();
        $list = Db::table('eb_scan_order')->where(['mer_id'=>$this->merId,'status'=>1 ])->order('scan_order_id desc')->page($page, $limit)->field('scan_order_id id,money,order_sn,pay_time')->select();
        return app('json')->success($list);
    }

    public function orderOrderList( )
    {
        [$page, $limit] = $this->getPage();
        $list = Db::table('eb_scan_order')->where(['mer_id'=>$this->merId,'status'=>1 ])->order('scan_order_id desc')->page($page, $limit)->field('scan_order_id id,money,order_sn,pay_time')->select();
        return app('json')->success($list);
    }


    public function orderList(StoreOrderRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['status']);
        $where['mer_id'] = $this->merId;
        $where['is_del'] = 0;
        return app('json')->success($repository->merchantGetList($where, $page, $limit));
    }

    public function order($id, StoreOrderRepository $repository)
    {
        $detail = $repository->getDetail($id);
        if (!$detail)
            return app('json')->fail('订单不存在');
        if ($detail['mer_id'] != $this->merId)
            return app('json')->fail('没有权限');
        return app('json')->success($detail->toArray());
    }


    protected function checkOrderAuth($id)
    {
        if (!app()->make(StoreOrderRepository::class)->existsWhere(['mer_id' => $this->merId, 'order_id' => $id]))
            throw new ValidateException('没有权限');
    }

    public function mark($id, StoreOrderRepository $repository)
    {
        $this->checkOrderAuth($id);
        $data = $this->request->params(['remark']);
        $repository->update($id, $data);
        return app('json')->success('备注成功');
    }

    public function price($id, StoreOrderRepository $repository)
    {
        $this->checkOrderAuth($id);

        $data = $this->request->params(['total_price', 'pay_postage']);

        if ($data['total_price'] < 0 || $data['pay_postage'] < 0)
            return app('json')->fail('金额不可未负数');
        if (!$repository->merStatusExists((int)$id, $this->merId))
            return app('json')->fail('订单信息或状态错误');
        $repository->eidt($id, $data);
        return app('json')->success('修改成功');
    }

    public function delivery($id, StoreOrderRepository $repository)
    {
        $this->checkOrderAuth($id);
        if (!$repository->merDeliveryExists((int)$id, $this->merId))
            return app('json')->fail('订单信息或状态错误');
        $data = $this->request->params(['delivery_type', 'delivery_name', 'delivery_id']);
        if (!in_array($data['delivery_type'], [1, 2, 3]))
            return app('json')->fail('发货类型错误');
        $repository->delivery($id, $data);
        return app('json')->success('发货成功');
    }

    public function payPrice(StoreOrderRepository $repository)
    {
        list($start, $stop, $month) = $this->request->params([
            ['start', strtotime(date('Y-m'))],
            ['stop', time()],
            'month'
        ], true);

        if ($month) {
            $start = date('Y/m/d', strtotime(getStartModelTime('month')));
            $stop = date('Y/m/d H:i:s', strtotime('+ 1day'));
            $front = date('Y/m/d', strtotime('first Day of this month', strtotime('-1 day', strtotime('first Day of this month'))));
            $end = date('Y/m/d H:i:s', strtotime($start . ' -1 second'));
        } else {
            if ($start == $stop) return app('json')->fail('参数有误');
            if ($start > $stop) {
                $middle = $stop;
                $stop = $start;
                $start = $middle;
            }
            $space = bcsub($stop, $start, 0);//间隔时间段
            $front = bcsub($start, $space, 0);//第一个时间段

            $front = date('Y/m/d H:i:s', $front);
            $start = date('Y/m/d H:i:s', $start);
            $stop = date('Y/m/d H:i:s', $stop);
            $end = date('Y/m/d H:i:s', strtotime($start . ' -1 day'));
        }
        $frontPrice = $repository->dateOrderPrice($front . '-' . $end, $this->merId);
        $afterPrice = $repository->dateOrderPrice($start . '-' . date('Y/m/d H:i:s', strtotime($stop . '-1 day')), $this->merId);
        $chartInfo = $repository->chartTimePrice($start, date('Y/m/d H:i:s', strtotime($stop . '-1 day')), $this->merId);
        $data['chart'] = $chartInfo;//营业额图表数据
        $data['time'] = $afterPrice;//时间区间营业额
        $increase = (float)bcsub((string)$afterPrice, (string)$frontPrice, 2); //同比上个时间区间增长营业额
        $growthRate = abs($increase);
        if ($growthRate == 0) $data['growth_rate'] = 0;
        else if ($frontPrice == 0) $data['growth_rate'] = $growthRate * 100;
        else $data['growth_rate'] = (int)bcmul((string)bcdiv((string)$growthRate, (string)$frontPrice, 2), '100', 0);//时间区间增长率
        $data['increase_time'] = abs($increase); //同比上个时间区间增长营业额
        $data['increase_time_status'] = $increase >= 0 ? 1 : 2; //同比上个时间区间增长营业额增长 1 减少 2

        return app('json')->success($data);
    }

    /**
     * @param StoreOrderRepository $repository
     * @return Json
     * @author xaboy
     * @day 2020/8/27
     */
    public function payNumber(StoreOrderRepository $repository)
    {
        list($start, $stop, $month) = $this->request->params([
            ['start', strtotime(date('Y-m'))],
            ['stop', time()],
            'month'
        ], true);

        if ($month) {
            $start = date('Y/m/d', strtotime(getStartModelTime('month')));
            $stop = date('Y/m/d H:i:s', strtotime('+ 1day'));
            $front = date('Y/m/d', strtotime('first Day of this month', strtotime('-1 day', strtotime('first Day of this month'))));
            $end = date('Y/m/d H:i:s', strtotime($start . ' -1 second'));
        } else {
            if ($start == $stop) return app('json')->fail('参数有误');
            if ($start > $stop) {
                $middle = $stop;
                $stop = $start;
                $start = $middle;
            }
            $space = bcsub($stop, $start, 0);//间隔时间段
            $front = bcsub($start, $space, 0);//第一个时间段

            $front = date('Y/m/d H:i:s', $front);
            $start = date('Y/m/d H:i:s', $start);
            $stop = date('Y/m/d H:i:s', $stop);
            $end = date('Y/m/d H:i:s', strtotime($start . ' -1 day'));
        }
        $frontNumber = $repository->dateOrderNum($front . '-' . $end, $this->merId);
        $afterNumber = $repository->dateOrderNum($start . '-' . date('Y/m/d H:i:s', strtotime($stop . '-1 day')), $this->merId);
        $chartInfo = $repository->chartTimeNum($start . '-' . date('Y/m/d H:i:s', strtotime($stop . '-1 day')), $this->merId);
        $data['chart'] = $chartInfo;//订单数图表数据
        $data['time'] = $afterNumber;//时间区间订单数
        $increase = $afterNumber - $frontNumber; //同比上个时间区间增长订单数
        $growthRate = abs($increase);
        if ($growthRate == 0) $data['growth_rate'] = 0;
        else if ($frontNumber == 0) $data['growth_rate'] = $growthRate * 100;
        else $data['growth_rate'] = (int)bcmul((string)bcdiv((string)$growthRate, (string)$frontNumber, 2), '100', 0);//时间区间增长率
        $data['increase_time'] = abs($increase); //同比上个时间区间增长营业额
        $data['increase_time_status'] = $increase >= 0 ? 1 : 2; //同比上个时间区间增长营业额增长 1 减少 2

        return app('json')->success($data);
    }
}
