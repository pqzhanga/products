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


namespace app\controller\api\store\order;


use app\validate\api\UserReceiptValidate;
use crmeb\basic\BaseController;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\services\ExpressService;
use crmeb\services\SwooleTaskService;
use think\App;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use crmeb\services\HttpService;

/**
 * Class StoreOrder
 * @package app\controller\api\store\order
 * @author xaboy
 * @day 2020/6/10
 */
class StoreOrder extends BaseController
{
    /**
     * @var StoreOrderRepository
     */
    protected $repository;

    /**
     * StoreOrder constructor.
     * @param App $app
     * @param StoreOrderRepository $repository
     */
    public function __construct(App $app, StoreOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function paychannel()
    {
        $userinfo = $this->request->userInfo();
        $sw_pay_channel =  systemConfig('sw_pay_channel') ;
        $list = [
            [ "name"=>"支付宝支付",
                "icon"=>"icon-zhifubao",
                "value"=>'alipay',
                "title"=>'支付宝支付',
                "payStatus"=>in_array(4,$sw_pay_channel)?1:0,
            ],
            [ "name"=>"微信支付",
                "icon"=>"icon-weixin2",
                "value"=>'weixin',
                "title"=>'微信支付',
                "payStatus"=>in_array(1,$sw_pay_channel)?1:0,
            ],

            [ "name"=>"微信(快付)",
                "icon"=>"icon-weixin2",
                "value"=>'yunfastpay_wx',
                "title"=>'微信支付',
                "payStatus"=>in_array(7,$sw_pay_channel)?1:0,
            ],
            [ "name"=>"支付宝(快付)",
                "icon"=>"icon-zhifubao",
                "value"=>'yunfastpay_zfb',
                "title"=>'支付宝支付',
                "payStatus"=>in_array(8,$sw_pay_channel)?1:0,
            ],

            [ "name"=>"余额支付",
                "icon"=>"icon-icon-test",
                "value"=>'balance',
                "title"=>'可用余额:',
                "number"=>$userinfo['now_money'],
                "payStatus"=>in_array(0,$sw_pay_channel)?1:0,
            ],

//            [ "name"=>"快付",
//                "icon"=>"icon-yinhangqia",
//                "value"=>'yunfastpay',
//                "title"=>'快付',
//                "payStatus"=>1,
//            ],

        ];
        return app('json')->success($list);
    }



    /**
     * @param StoreCartRepository $cartRepository
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author xaboy
     * @day 2020/6/10
     */
    public function checkOrder(StoreCartRepository $cartRepository)
    {
        $cartId = (array)$this->request->param('cart_id', []);
        $addressId = (int)$this->request->param('address_id');
        $uid = $this->request->uid();
        if (!($count = count($cartId)) || $count != count($cartRepository->validIntersection($cartId, $uid)))
            return app('json')->fail('数据无效');
        $orderInfo = $this->repository->cartIdByOrderInfo($uid, [],$cartId, $addressId);

        return app('json')->success($orderInfo);
    }



    public function selectTelephoneFareOrder(StoreCartRepository $cartRepository){
        //var_dump("8888");
        // return app('json')->fail('8888!');//对应error
        // die;
        //$uid = (int)$this->request->param('uid');
        $uid =$this->request->uid();
        // return app('json')->fail($uid);
        // die;
        if ($uid <= 0)
            return app('json')->fail('请输入正确的用户id!');
        $order_no = (int)$this->request->param('order_no');
        if ($order_no == ''){

        }else if($order_no <= 0){
            return app('json')->fail('请输入正确的订单编号!');
        }else{

        }
        $page_index = $this->request->param('page_index');
        if ($page_index == ''){

        }else if($page_index <= 0){
            return app('json')->fail('请输入正确的分页首!');
        }else{

        }

        $page_size = $this->request->param('page_size');
        if ($page_size == ''){

        }else if($page_size <= 0){
            return app('json')->fail('请输入正确的分页长!');
        }else{

        }
        /*
        $res = Db::table('user')
        ->field('user_id,name,age')
        // ->order('age desc') //asc 升序 desc降序
        // ->order('age','desc')  //按照年龄降序排列
        //多字段排序，恋情数组参数
        ->order(['user_id'=>'asc','age'=>'desc'])
        ->limit(2) //limit(2,3) fen分页参数
        ->select();
        dump($res);
        */
        /*
        //输出年龄大于30的数据

         //1. 字符串（查询条件)  user_id = 3
         //  ->where('age > 30')

        //2.表达式：（字段，操作符，值）
        //  ->where('age','>','30')
        //  ->where('user_id','=','2')  //等于 ->where('user_id','2')

        //区间，模糊查询
        // ->where('age','between',[20,30])

        //3.数组
        //关联数组：等值查询,AND
        // ->where(['user_id'=>2,'age'=>32])
        //索引数组：批量查询
           ->where([
            0 => ['age','between',[20,40]],
            // 1=>  ['age','>',30]
               ])
        */
        //var_dump("9999");
        //die;
        //$lastdata = Db::table('eb_telephone_fare_ordernew')->where(['uid'=>$uid])->order('order_id desc')->find();
        //$lastdata = Db::table('eb_telephone_fare_ordernew')->where(['uid'=>$uid])->order('order_id desc');
        //$list = Db::table('eb_telephone_fare_ordernew')->where(['uid'=>$uid])->order('order_id desc')->select();
        $list = Db::table('eb_telephone_fare_ordernew')->where(['uid'=>$uid])->order('order_id desc')->select();
        //->where([ 0 => ['age','between',[20,40]],1=>  ['age','>',30]])
        //$lastdata = Db::table('eb_telephone_fare_ordernew')->where(['$uid'=>$uid,'order_sn'=>$order_no,'page_index'=>$page_index,'$page_size'=>$page_size])->order('order_id desc')->find();
        // var_dump($list);
        if($list){
            return app('json')->success($list);//返回订单详细数据
        }else{
            return app('json')->fail('暂无订单!');
        }

    }

    public function tempinsertTelephoneFareOrder(StoreCartRepository $cartRepository){
        // return app('json')->fail("999999");
        // die;
        // $order_sn="CZ2021061416301316236594132291";
        // $order['phone']="18978328386";
        // $chongzhi_order_sn = app()->make(StoreOrderRepository::class)->placeTelephoneFareOrder($order['phone'],$order_sn,$order['product_id']);
        // var_dump($chongzhi_order_sn);
        // die;

        /*
        输入:
        money=50;
        uid=42044;
        phone=18978328386;
        返回=单个订单创建的详细数据
        */
        //return app('json')->success("888888");
        //die;

        //var_dump($res);
        //die;
        // $test = app()->make(StoreOrderRepository::class)->payyunfastpay_app_chongzhi();

        // return app('json')->success($test);
        // return app('json')->success("9999");

        // return app('json')->fail($this->request->param('money'));
        // die;
        //检查所有需要获取的post数据
        $data['money'] = (int)$this->request->param('money');
        if ($data['money'] <= 0)
            return app('json')->fail('请输入正确的充值金额!');

        $data['uid'] = (int)$this->request->param('uid');
        if ($data['uid'] <= 0)
            return app('json')->fail('请输入正确的uid!');

        //根据uid查询用户贡献值信息(已不用,后续按话费金额给贡献值)
        //$contribute_temp = Db::table('eb_user')->where(['uid'=>$data['uid'] ])->order('uid desc')->find();
        //$data['contribute']=(float)$contribute_temp["brokerage_gongxian"];//1140.44
        $data['contribute']=0;//贡献值信息初始设置为0,用于判断是否获得了贡献值
        // return app('json')->fail($data['contribute']);;
        // die;
        //var_dump($data['contribute']);
        //die;
        //根据uid查询用户让利比例信息
        //测试uid=42044
        // $rangli_temp= Db::table('eb_merchant')->field("crate")->where(['uid'=>$data['uid'] ])->order('uid desc')->find();
        $rangli_temp = Db::table('eb_merchant')->where(['uid'=>$data['uid'] ])->order('uid desc')->find();
        //var_dump($rangli_temp);
        //die;
        $data['rate']=$rangli_temp["crate"];//10
        // return app('json')->fail($rangli_temp["crate"]);;
        // die;
        // return app('json')->fail($this->request->param('phone'));;
        // die;
        $data['phone'] = $this->request->param('phone');
        $phonetemp = substr($data['phone'],0,3);
        // return app('json')->success($phonetemp);
        // return $phonetemp;
        // die;
        if($phonetemp=='162' ||$phonetemp=='165' || $phonetemp=='167') return app('json')->fail('温馨提示:虚拟运营商号码无法提交充值,请提交其他手机号码充值');
        if (!$data['phone'] || $data['phone']=='') return app('json')->fail('请输入手机号');
        // $phone_data=json_decode(HttpService::getRequest('https://cx.shouji.360.cn/phonearea.php?number='.$data['phone']));
        // return app('json')->fail($phone_data);;
        // die;
        //var_dump($phone_data);
        //die;
        //var_dump($phone_data->code);
        //die;
        // if($phone_data->code!=0) return app('json')->fail('查询手机号码有误！');
        //var_dump($phone_data->code!=0);
        //die;
        //$correspondence = $this->request->param('correspondence');
        //if ($correspondence <= 0 && $correspondence!="移动" && $correspondence!="联通" && $correspondence!="电信")
        //return app('json')->fail('请输入正确的通讯运营商：移动；联通；电信!');
        //$location_province = $this->request->param('location_province');
        //var_dump($phone_data->data->sp);
        //die;
        // $data['correspondence']=$phone_data->data->sp;
        $data['correspondence']=$this->request->param('correspondence');
        // return app('json')->fail($phone_data->data->sp);;
        //     die;
        // var_dump($data['correspondence']);
        // die;
        //if ($data['correspondence'] <= 0)
        //return app('json')->fail('请输入正确的号码归属地省份!');
        // $data['location']=$phone_data->data->city;
        $data['location']=$this->request->param('city');
        //var_dump($data['location']);
        //die;
        //$location_city = $this->request->param('location_city');
        //if ($data['location'] <= 0)
        //return app('json')->fail('请输入正确的号码归属地城市!');
        //根据产品的选择获取第三方产品ID(product_id)
        //$product_id=this—>getTelProduct();
        // $data['product_id'] = (float)$this->request->param('product_id');//200250
        //根据金额、运营商获取第三方产品ID(product_id)
        /*
        string(1430)
"{"retcode":200,"retmsg":"成功获取产品列表","items":[{"product_code":"100250","product_title":"移动话费全国50元","product_fee":"47.0"},{"product_code":"1002500","product_title":"移动话费全国500元","product_fee":"470.0"},{"product_code":"1002300","product_title":"移动话费全国300元","product_fee":"282.0"},{"product_code":"1002200","product_title":"移动话费全国200元","product_fee":"188.0"},{"product_code":"1002100","product_title":"移动话费全国100元","product_fee":"94.0"},{"product_code":"2002500","product_title":"联通话费全国500元","product_fee":"470.0"},{"product_code":"2002300","product_title":"联通话费全国300元","product_fee":"282.0"},{"product_code":"2002200","product_title":"联通话费全国200元","product_fee":"188.0"},{"product_code":"2002100","product_title":"联通话费全国100元","product_fee":"94.0"},{"product_code":"200250","product_title":"联通话费全国50元","product_fee":"47.0"},{"product_code":"3002500","product_title":"电信话费全国500元","product_fee":"470.0"},{"product_code":"3002300","product_title":"电信话费全国300元","product_fee":"282.0"},{"product_code":"3002200","product_title":"电信话费全国200元","product_fee":"188.0"},{"product_code":"3002100","product_title":"电信话费全国100元","product_fee":"94.0"},{"product_code":"300250","product_title":"电信话费全国50元","product_fee":"47.0"}
]}"
        */
        //定义一个数组
        $hfarray = array(
            "100250" => '移动话费全国50元',
            "200250" => '联通话费全国50元',
            "300250" => '电信话费全国50元',
            "1002100" => '移动话费全国100元',
            "2002100" => '联通话费全国100元',
            "3002100" => '电信话费全国100元',
            "1002200" => '移动话费全国200元',
            "2002200" => '联通话费全国200元',
            "3002200" => '电信话费全国200元',
            "1002300" => '移动话费全国300元',
            "2002300" => '联通话费全国300元',
            "3002300" => '电信话费全国300元',
            "1002500" => '移动话费全国500元',
            "2002500" => '联通话费全国500元',
            "3002500" => '电信话费全国500元');
        //使用 array_search('要搜索的值',数组);
        //$a=array("a"=>"red","b"=>"green","c"=>"blue");
        // echo array_search("移动话费全国50元",$array);
        // die;
        // var_dump($array);
        // die;
        // var_dump($data['correspondence']=='电信' && $data['money']==49);
        // die;


        if($data['correspondence']=='移动' && $data['money']=='49'){
            $data['product_id']=array_search('移动话费全国50元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']=='98'){
            $data['product_id']=array_search('移动话费全国100元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']=='196'){
            $data['product_id']=array_search('移动话费全国200元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']=='294'){
            $data['product_id']=array_search('移动话费全国300元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']=='490'){
            $data['product_id']=array_search('移动话费全国500元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']=='49'){
            $data['product_id']=array_search('联通话费全国50元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']=='98'){
            $data['product_id']=array_search('联通话费全国100元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']=='196'){
            $data['product_id']=array_search('联通话费全国200元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']=='294'){
            $data['product_id']=array_search('联通话费全国300元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']=='490'){
            $data['product_id']=array_search('联通话费全国500元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']=='49'){
            $data['product_id']=array_search('电信话费全国50元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']=='98'){
            $data['product_id']=array_search('电信话费全国100元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']=='196'){
            $data['product_id']=array_search('电信话费全国200元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']=='294'){
            $data['product_id']=array_search('电信话费全国300元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']=='490'){
            $data['product_id']=array_search('电信话费全国500元', $hfarray);
        }else{
            return app('json')->fail('运营商和金额不匹配!');
        }

        if($data['product_id']=='1002500' || $data['product_id']=='2002500' || $data['product_id']=='3002500'){
            return app('json')->success('暂不支持500元话费充值订单!');
        }

        if($data['product_id']=='1002300' || $data['product_id']=='2002300' || $data['product_id']=='3002300'){
            return app('json')->success('暂不支持300元话费充值订单!');
        }

        //var_dump($data['product_id']);
        // $key = array_search('移动话费全国50元', $array); // $key = 1;
        // $key1 = array_search('联通话费全国50元', $array);   // $key = 0;
        // var_dump($key);
        //die;

        // $data=$this->getTelProduct();
        // $product_list = json_decode($data,true);
        // var_dump($product_list);
        // die;
        // $result = array_search('300250', array_column($product_list, 'items'));
        // var_dump($result);
        // die;
        //print("Search 1: ".array_search("PHP",$array)."\n");


        //生成并创建订单号
        //list($msec, $sec) = explode(' ', microtime());
        //var_dump(explode(' ', microtime()));
        //die;
        //$msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');
        //$data['order_sn'] = 't' . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));
        //var_dump($product_id);
        //die;
        $data['order_sn'] ='CZ'.date('YmdHis').time().rand(1000,9999);
        //var_dump($data['order_sn']);
        //die;
        //$data['create_time']=time();//订单创建时间
        //var_dump($data['create_time']);
        //die;
        //$data['pay_time']=0;//订单支付时间
        // var_dump($data['pay_time']);
        // die;
        $data['pay_status']=0;//支付状态（0：未支付；1-已支付）
        $data['status']=0;//订单状态//订单状态（-1：充值未成功；0：充值中；1：充值成功 ）

        $res=Db::table('eb_telephone_fare_ordernew')->save($data);//写入订单记录表
        // return app('json')->fail($res);;
        // die;
        //$res=1 则添加成功
        if($res===1){
            //返回订单详细数据
            //$query = Db::table('eb_telephone_fare_ordernew')->where(['order_sn' =>$data['order_sn']]);
            $lastdata = Db::table('eb_telephone_fare_ordernew')->where(['order_sn'=>$data['order_sn'] ])->order('order_id desc')->find();//不用返回订单数据了
            // var_dump($lastdata["order_sn"]);
            $order_sn=$lastdata["order_sn"];
            $money=$lastdata["money"];
            // $gongxian=$lastdata["contribute"];
            //return app('json')->success($lastdata);//返回订单详细数据
            //$this-placeTelephoneFareOrder($data['order_sn']);
            // die;
            // $resyunfast = $this->repository->payyunfastpay_app_chongzhi($order_sn,$money,$gongxian);
            $resyunfast = $this->repository->temppayyunfastpay_app_chongzhi($order_sn,$money);
            return $resyunfast;
        }else{
            return app('json')->fail('创建充值订单失败!');
        }
        //var_dump($res);
        //die;
        //return app('json')->success($res);//返回订单详细数据

    }



    /*
     * insertTelephoneFareOrder
     * 创建充值订单
     * 发起支付前，获得输入号码运营商、归属地(省份和城市)、金额，连同用户ID提交接口，
     * 根据uid查询用户让利比例和贡献值等信息，根据金额、运营商获取第三方产品ID(product_id)，写入订单记录表，返回订单详细数据
     */

    public function insertTelephoneFareOrder(StoreCartRepository $cartRepository){
        // return app('json')->fail('充值功能维护中!');
        // die;
        // $order_sn="CZ2021061416301316236594132291";
        // $order['phone']="18978328386";
        // $chongzhi_order_sn = app()->make(StoreOrderRepository::class)->placeTelephoneFareOrder($order['phone'],$order_sn,$order['product_id']);
        // var_dump($chongzhi_order_sn);
        // die;

        /*
        输入:
        money=50;
        uid=42044;
        phone=18978328386;
        返回=单个订单创建的详细数据
        */
        //return app('json')->success("888888");
        //die;

        //var_dump($res);
        //die;
        // $test = app()->make(StoreOrderRepository::class)->payyunfastpay_app_chongzhi();

        // return app('json')->success($test);
        // return app('json')->success("9999");

        // return app('json')->fail($this->request->param('money'));
        // die;
        //检查所有需要获取的post数据
        $data['money'] = (int)$this->request->param('money');
        if ($data['money'] <= 0)
            return app('json')->fail('请输入正确的充值金额!');

        $data['uid'] = (int)$this->request->param('uid');
        if ($data['uid'] <= 0)
            return app('json')->fail('请输入正确的uid!');

        //根据uid查询用户贡献值信息(已不用,后续按话费金额给贡献值)
        //$contribute_temp = Db::table('eb_user')->where(['uid'=>$data['uid'] ])->order('uid desc')->find();
        //$data['contribute']=(float)$contribute_temp["brokerage_gongxian"];//1140.44
        $data['contribute']=0;//贡献值信息初始设置为0,用于判断是否获得了贡献值
        // return app('json')->fail($data['contribute']);;
        // die;
        //var_dump($data['contribute']);
        //die;
        //根据uid查询用户让利比例信息
        //测试uid=42044
        // $rangli_temp= Db::table('eb_merchant')->field("crate")->where(['uid'=>$data['uid'] ])->order('uid desc')->find();
        $rangli_temp = Db::table('eb_merchant')->where(['uid'=>$data['uid'] ])->order('uid desc')->find();
        //var_dump($rangli_temp);
        //die;
        $data['rate']=$rangli_temp["crate"];//10
        // return app('json')->fail($rangli_temp["crate"]);;
        // die;
        // return app('json')->fail($this->request->param('phone'));;
        // die;
        $data['phone'] = $this->request->param('phone');
        $phonetemp = substr($data['phone'],0,3);
        // return app('json')->fail($phonetemp);
        // die;
        if($phonetemp=='162' ||$phonetemp=='165' || $phonetemp=='167') return app('json')->fail('温馨提示:虚拟运营商号码无法提交充值,请提交其他手机号码充值');
        if (!$data['phone'] || $data['phone']=='') return app('json')->fail('请输入手机号');
        // $phone_data=json_decode(HttpService::getRequest('https://cx.shouji.360.cn/phonearea.php?number='.$data['phone']));
        // return app('json')->fail($phone_data);;
        // die;
        //var_dump($phone_data);
        //die;
        //var_dump($phone_data->code);
        //die;
        // if($phone_data->code!=0) return app('json')->fail('查询手机号码有误！');
        //var_dump($phone_data->code!=0);
        //die;
        //$correspondence = $this->request->param('correspondence');
        //if ($correspondence <= 0 && $correspondence!="移动" && $correspondence!="联通" && $correspondence!="电信")
        //return app('json')->fail('请输入正确的通讯运营商：移动；联通；电信!');
        //$location_province = $this->request->param('location_province');
        //var_dump($phone_data->data->sp);
        //die;
        // $data['correspondence']=$phone_data->data->sp;
        $data['correspondence']=$this->request->param('correspondence');
        // return app('json')->fail($phone_data->data->sp);;
        //     die;
        // var_dump($data['correspondence']);
        // die;
        //if ($data['correspondence'] <= 0)
        //return app('json')->fail('请输入正确的号码归属地省份!');
        // $data['location']=$phone_data->data->city;
        $data['location']=$this->request->param('city');
        //var_dump($data['location']);
        //die;
        //$location_city = $this->request->param('location_city');
        //if ($data['location'] <= 0)
        //return app('json')->fail('请输入正确的号码归属地城市!');
        //根据产品的选择获取第三方产品ID(product_id)
        //$product_id=this—>getTelProduct();
        // $data['product_id'] = (float)$this->request->param('product_id');//200250
        //根据金额、运营商获取第三方产品ID(product_id)
        /*
        string(1430)
"{"retcode":200,"retmsg":"成功获取产品列表","items":[{"product_code":"100250","product_title":"移动话费全国50元","product_fee":"47.0"},{"product_code":"1002500","product_title":"移动话费全国500元","product_fee":"470.0"},{"product_code":"1002300","product_title":"移动话费全国300元","product_fee":"282.0"},{"product_code":"1002200","product_title":"移动话费全国200元","product_fee":"188.0"},{"product_code":"1002100","product_title":"移动话费全国100元","product_fee":"94.0"},{"product_code":"2002500","product_title":"联通话费全国500元","product_fee":"470.0"},{"product_code":"2002300","product_title":"联通话费全国300元","product_fee":"282.0"},{"product_code":"2002200","product_title":"联通话费全国200元","product_fee":"188.0"},{"product_code":"2002100","product_title":"联通话费全国100元","product_fee":"94.0"},{"product_code":"200250","product_title":"联通话费全国50元","product_fee":"47.0"},{"product_code":"3002500","product_title":"电信话费全国500元","product_fee":"470.0"},{"product_code":"3002300","product_title":"电信话费全国300元","product_fee":"282.0"},{"product_code":"3002200","product_title":"电信话费全国200元","product_fee":"188.0"},{"product_code":"3002100","product_title":"电信话费全国100元","product_fee":"94.0"},{"product_code":"300250","product_title":"电信话费全国50元","product_fee":"47.0"}
]}"
        */
        //定义一个数组
        $hfarray = array(
            "100250" => '移动话费全国50元',
            "200250" => '联通话费全国50元',
            "300250" => '电信话费全国50元',
            "1002100" => '移动话费全国100元',
            "2002100" => '联通话费全国100元',
            "3002100" => '电信话费全国100元',
            "1002200" => '移动话费全国200元',
            "2002200" => '联通话费全国200元',
            "3002200" => '电信话费全国200元',
            "1002300" => '移动话费全国300元',
            "2002300" => '联通话费全国300元',
            "3002300" => '电信话费全国300元',
            "1002500" => '移动话费全国500元',
            "2002500" => '联通话费全国500元',
            "3002500" => '电信话费全国500元');
        //使用 array_search('要搜索的值',数组);
        //$a=array("a"=>"red","b"=>"green","c"=>"blue");
        // echo array_search("移动话费全国50元",$array);
        // die;
        // var_dump($array);
        // die;
        // var_dump($data['correspondence']=='电信' && $data['money']==49);
        // die;


        if($data['correspondence']=='移动' && $data['money']=='49'){
            $data['product_id']=array_search('移动话费全国50元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']=='98'){
            $data['product_id']=array_search('移动话费全国100元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']=='196'){
            $data['product_id']=array_search('移动话费全国200元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']=='294'){
            $data['product_id']=array_search('移动话费全国300元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']=='490'){
            $data['product_id']=array_search('移动话费全国500元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']=='49'){
            $data['product_id']=array_search('联通话费全国50元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']=='98'){
            $data['product_id']=array_search('联通话费全国100元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']=='196'){
            $data['product_id']=array_search('联通话费全国200元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']=='294'){
            $data['product_id']=array_search('联通话费全国300元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']=='490'){
            $data['product_id']=array_search('联通话费全国500元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']=='49'){
            $data['product_id']=array_search('电信话费全国50元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']=='98'){
            $data['product_id']=array_search('电信话费全国100元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']=='196'){
            $data['product_id']=array_search('电信话费全国200元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']=='294'){
            $data['product_id']=array_search('电信话费全国300元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']=='490'){
            $data['product_id']=array_search('电信话费全国500元', $hfarray);
        }else{
            return app('json')->fail('运营商和金额不匹配!');
        }

        if($data['product_id']=='1002500' || $data['product_id']=='2002500' || $data['product_id']=='3002500'){
            return app('json')->success('暂不支持500元话费充值订单!');
        }

        if($data['product_id']=='1002300' || $data['product_id']=='2002300' || $data['product_id']=='3002300'){
            return app('json')->success('暂不支持300元话费充值订单!');
        }

        //var_dump($data['product_id']);
        // $key = array_search('移动话费全国50元', $array); // $key = 1;
        // $key1 = array_search('联通话费全国50元', $array);   // $key = 0;
        // var_dump($key);
        //die;

        // $data=$this->getTelProduct();
        // $product_list = json_decode($data,true);
        // var_dump($product_list);
        // die;
        // $result = array_search('300250', array_column($product_list, 'items'));
        // var_dump($result);
        // die;
        //print("Search 1: ".array_search("PHP",$array)."\n");


        //生成并创建订单号
        //list($msec, $sec) = explode(' ', microtime());
        //var_dump(explode(' ', microtime()));
        //die;
        //$msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');
        //$data['order_sn'] = 't' . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));
        //var_dump($product_id);
        //die;
        $data['order_sn'] ='CZ'.date('YmdHis').time().rand(1000,9999);
        //var_dump($data['order_sn']);
        //die;
        //$data['create_time']=time();//订单创建时间
        //var_dump($data['create_time']);
        //die;
        //$data['pay_time']=0;//订单支付时间
        // var_dump($data['pay_time']);
        // die;
        $data['pay_status']=0;//支付状态（0：未支付；1-已支付）
        $data['status']=0;//订单状态//订单状态（-1：充值未成功；0：充值中；1：充值成功 ）

        $res=Db::table('eb_telephone_fare_ordernew')->save($data);//写入订单记录表
        // return app('json')->fail($res);;
        // die;
        //$res=1 则添加成功
        if($res===1){
            //返回订单详细数据
            //$query = Db::table('eb_telephone_fare_ordernew')->where(['order_sn' =>$data['order_sn']]);
            $lastdata = Db::table('eb_telephone_fare_ordernew')->where(['order_sn'=>$data['order_sn'] ])->order('order_id desc')->find();//不用返回订单数据了
            // var_dump($lastdata["order_sn"]);
            $order_sn=$lastdata["order_sn"];
            $money=$lastdata["money"];
            // $gongxian=$lastdata["contribute"];
            //return app('json')->success($lastdata);//返回订单详细数据
            //$this-placeTelephoneFareOrder($data['order_sn']);
            // die;
            // $resyunfast = $this->repository->payyunfastpay_app_chongzhi($order_sn,$money,$gongxian);
            $resyunfast = $this->repository->payyunfastpay_app_chongzhi($order_sn,$money);
            return $resyunfast;
        }else{
            return app('json')->fail('创建充值订单失败!');
        }
        //var_dump($res);
        //die;
        //return app('json')->success($res);//返回订单详细数据

    }

    /**
     * @param StoreCartRepository $cartRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function createOrder(StoreCartRepository $cartRepository)
    {
        // print_r($this->request->param());
        $cartId = (array)$this->request->param('cart_id', []);
        $addressId = (int)$this->request->param('address_id');
        $order_type = (int)$this->request->param('order_type', 0);
        $receipt_data = (array)$this->request->param('receipt_data', []);
        $coupon = (array)$this->request->param('coupon', []);
        $take = (array)$this->request->param('take', []);
        $mark = (array)$this->request->param('mark', []);
        $payType = $this->request->param('pay_type');
        $duihuan = $this->request->param('duihuan');
        $is_app = $this->request->param('is_app');

//        if (!in_array($payType, StoreOrderRepository::PAY_TYPE))
//            return app('json')->fail('请选择正确的支付方式');
        if (!in_array($payType,  ['balance', 'weixin',  'alipay', 'yunfastpay_wx','yunfastpay_zfb'] ))
            return app('json')->fail('不支持的数据，请更新app！');

        $sw_pay_channel =  systemConfig('sw_pay_channel') ;
        foreach ([0=>'balance',1=> 'weixin',  4=>'alipay', 7=>'yunfastpay_wx',8=>'yunfastpay_zfb'] as $kk=>$vv){
            if($vv == $payType && !in_array($kk,$sw_pay_channel) ){
                return app('json')->fail('请选择正确的支付方式！');
            }
        }
        $fastpay_type = '';
        if($payType == 'yunfastpay_wx' || $payType == 'yunfastpay_zfb'){
            $fastpay_type = $payType == 'yunfastpay_wx'?'wxpay':'alipay' ;
            $payType = 'yunfastpay';
        }


//        if(in_array($payType,['balance']) && $this->request->uid() != 53322){
//            return app('json')->fail('余额支付暂时关闭，请使用其它支付方式！');
//        }


        if (!in_array($order_type, [0, 1, 2, 3, 4]))
            return app('json')->fail('订单类型错误');

        $validate = app()->make(UserReceiptValidate::class);

        foreach ($receipt_data as $receipt) {
            if (!is_array($receipt)) throw new ValidateException('发票信息有误');
            $validate->check($receipt);
        }

        $uid = $this->request->uid();
        if (!($count = count($cartId)) || $count != count($cartRepository->validIntersection($cartId, $uid)))
            return app('json')->fail('数据无效');
        if (!$addressId)
            return app('json')->fail('请选择地址');
        makeLock()->lock();
        try {
            if ($order_type == 2) {
                return app('json')->fail('不支持的订单类型(2)！');
//                $groupOrder = $this->repository->createPresellOrder($this->request->userInfo(), array_search($payType, StoreOrderRepository::PAY_TYPE), $cartId, $addressId, $coupon, $take, $mark, $receipt_data);
            } else {
                $groupOrder = $this->repository->createOrder($this->request->userInfo(), array_search($payType, StoreOrderRepository::PAY_TYPE), $cartId, $addressId, $coupon, $take, $mark, $receipt_data);
            }
        } catch (\Throwable $e) {
            makeLock()->unlock();
            throw $e;
        }
        makeLock()->unlock();

        SwooleTaskService::admin('notice', [
            'type' => 'order',
            'title' => '您有新的订单',
            'message' => '您有新的订单',
        ]);


        // 结算兑换值
        if($groupOrder['pay_duihuan'] > 0){
            $duihuan = $groupOrder['pay_duihuan'];
        }else{
            $duihuan = 0;
        }

// print_r($groupOrder->toArray());
        if ($groupOrder['pay_price'] == 0) {
            $this->repository->paySuccess($groupOrder);

            if($duihuan>0) {
                $val = $this->request->userInfo()->brokerage_duihuan - $duihuan;
                Db::name('user')->where('uid',$this->request->userInfo()->uid)->update(['brokerage_duihuan'=>$val]);

                $ctime=date("Y-m-d H:i:s");
                $saveDate = [
                    'uid' => $this->request->userInfo()->uid,
                    'link_id' => $groupOrder['group_order_id'],
                    'pm'=>0,
                    'title'=>'购物消费',
                    'category' => 'brokerage_duihuan',
                    'number'=>$duihuan,
                    'balance'=>$val,
                    'mark'=>'使用兑换值购物，兑换值减少'.$duihuan . ' 备注:'.$groupOrder['group_order_id'] ,
                    'create_time'=>$ctime,
                    'status'=>1
                ];
                Db::table('eb_user_bill')->save($saveDate);
            }
            return app('json')->status('success', '支付成功', ['order_id' => $groupOrder['group_order_id']]);
        }
        try {
            $res = $this->repository->pay($payType, $this->request->userInfo(), $groupOrder, $this->request->param('return_url'),$is_app,$fastpay_type);
            // 结算兑换值
            if($duihuan>0) {
                $val = $this->request->userInfo()->brokerage_duihuan - $duihuan;
                Db::name('user')->where('uid',$this->request->userInfo()->uid)->update(['brokerage_duihuan'=>$val]);

                $ctime=date("Y-m-d H:i:s");
                $saveDate = [
                    'uid' => $this->request->userInfo()->uid,
                    'link_id' => $groupOrder['group_order_id'],
                    'pm'=>0,
                    'title'=>'购物消费',
                    'category' => 'brokerage_duihuan',
                    'number'=>$duihuan,
                    'balance'=>$val,
                    'mark'=>'使用兑换值购物，兑换值减少'.$duihuan . ' 备注:'.$groupOrder['group_order_id'] ,
                    'create_time'=>$ctime,
                    'status'=>1
                ];
                Db::table('eb_user_bill')->save($saveDate);
            }
            return $res;
        } catch (\Exception $e) {
            return app('json')->status('error', $e->getMessage(), ['order_id' => $groupOrder->group_order_id]);
        }
    }

    /**
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author xaboy
     * @day 2020/6/10
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList([
            'uid' => $this->request->uid(),
            'paid' => 1,
            'status' => (int)$this->request->get('status', 0)
        ], $page, $limit));
    }

    /**
     * @param $id
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function detail($id)
    {
        $order = $this->repository->getDetail((int)$id, $this->request->uid());
        if (!$order)
            return app('json')->fail('订单不存在');
        if ($order->order_type == 1) {
            $order->append(['take']);
        }
        return app('json')->success($order->toArray());
    }

    /**
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function number()
    {
        return app('json')->success(['orderPrice' => $this->request->userInfo()->pay_price] + $this->repository->userOrderNumber($this->request->uid()));
    }

    /**
     * @param StoreGroupOrderRepository $groupOrderRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function groupOrderList(StoreGroupOrderRepository $groupOrderRepository)
    {
        [$page, $limit] = $this->getPage();
        $list = $groupOrderRepository->getList(['uid' => $this->request->uid(), 'paid' => 0], $page, $limit);
        return app('json')->success($list);
    }

    /**
     * @param $id
     * @param StoreGroupOrderRepository $groupOrderRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function groupOrderDetail($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        $groupOrder = $groupOrderRepository->detail($this->request->uid(), (int)$id);
        if (!$groupOrder)
            return app('json')->fail('订单不存在');
        else
            return app('json')->success($groupOrder->append(['cancel_time'])->toArray());
    }

    public function groupOrderStatus($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        $groupOrder = $groupOrderRepository->status($this->request->uid(), intval($id));
        if (!$groupOrder)
            return app('json')->fail('订单不存在');
        if ($groupOrder->paid) $groupOrder->append(['give_coupon']);
        $activity_type = 0;
        $activity_id = 0;
        foreach ($groupOrder->orderList as $order) {
            $activity_type = max($order->activity_type, $activity_type);
            if ($order->activity_type == 4 && $groupOrder->paid) {
                $order->append(['orderProduct']);
                $activity_id = $order->orderProduct[0]['activity_id'];
            }
        }
        $groupOrder->activity_type = $activity_type;
        $groupOrder->activity_id = $activity_id;
        return app('json')->success($groupOrder->toArray());
    }

    /**
     * @param $id
     * @param StoreGroupOrderRepository $groupOrderRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/10
     */
    public function cancelGroupOrder($id, StoreGroupOrderRepository $groupOrderRepository)
    {
        $groupOrderRepository->cancel((int)$id, $this->request->uid());
        return app('json')->success('取消成功');
    }

    public function groupOrderPay($id, StoreGroupOrderRepository $groupOrderRepository)
    {

        //TODO 佣金结算,佣金退回,物流查询
        $type = $this->request->param('type');
        $is_app = $this->request->param('is_app');
        $is_app = $is_app?1:0;
//        if (!in_array($type, StoreOrderRepository::PAY_TYPE)) yunfastpay_wx
//            return app('json')->fail('请选择正确的支付方式');
        $groupOrder = $groupOrderRepository->detail($this->request->uid(), (int)$id, false);

        if (!in_array($type,  ['balance', 'weixin',  'alipay', 'yunfastpay_wx','yunfastpay_zfb'] ))
            return app('json')->fail('不支持的数据，请更新app！');
        $sw_pay_channel =  systemConfig('sw_pay_channel') ;
        foreach ([0=>'balance',1=> 'weixin',  4=>'alipay', 7=>'yunfastpay_wx',8=>'yunfastpay_zfb'] as $kk=>$vv){
            if($vv == $type && !in_array($kk,$sw_pay_channel) ){
                return app('json')->fail('请选择正确的支付方式！');
            }
        }
        $fastpay_type = '';
        if($type == 'yunfastpay_wx' || $type == 'yunfastpay_zfb'){
            $fastpay_type = $type == 'yunfastpay_wx'?'wxpay':'alipay' ;
            $type = 'yunfastpay';
        }

        if (!$groupOrder)
            return app('json')->fail('订单不存在或已支付');
        $this->repository->changePayType($groupOrder, array_search($type, StoreOrderRepository::PAY_TYPE));
        if ($groupOrder['pay_price'] == 0) {
            $this->repository->paySuccess($groupOrder);
            return app('json')->status('success', '支付成功', ['order_id' => $groupOrder['group_order_id']]);
        }


        try {
            return $this->repository->pay($type, $this->request->userInfo(), $groupOrder, $this->request->param('return_url'),$is_app,$fastpay_type);
        } catch (\Exception $e) {
            return app('json')->status('error', $e->getMessage(), ['order_id' => $groupOrder->group_order_id]);
        }
    }

    public function take($id)
    {
        $this->repository->takeOrder($id, $this->request->userInfo());
        return app('json')->success('确认收货成功');
    }

    public function express($id)
    {
        $order = $this->repository->getWhere(['order_id' => $id, 'uid' => $this->request->uid(), 'is_del' => 0]);
        if (!$order)
            return app('json')->fail('订单不存在');
        if (!$order->delivery_type || !$order->delivery_id)
            return app('json')->fail('订单未发货');
        $express = ExpressService::express($order->delivery_id);
        $order->append(['orderProduct']);
        return app('json')->success(compact('express', 'order'));
    }

    public function verifyCode($id)
    {
        $order = $this->repository->getWhere(['order_id' => $id, 'uid' => $this->request->uid(), 'is_del' => 0, 'order_type' => 1]);
        if (!$order)
            return app('json')->fail('订单状态有误');
//        $type = $this->request->param('type');
        return app('json')->success(['qrcode' => $this->repository->wxQrcode($id, $order->verify_code)]);
//        return app('json')->success(['qrcode' => $type == 'routine' ? $this->repository->routineQrcode($id, $order->verify_code) : $this->repository->wxQrcode($id, $order->verify_code)]);
    }

    public function del($id)
    {
        $this->repository->userDel($id, $this->request->uid());
        return app('json')->success('删除成功');
    }

}
