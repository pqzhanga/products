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


use crmeb\basic\BaseController;
use app\common\repositories\system\groupData\GroupDataRepository;
use app\common\repositories\user\UserRechargeRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\WechatService;
use think\App;
use app\common\repositories\store\order\StoreOrderRepository;

class UserRecharge extends BaseController
{
    protected $repository;

    public function __construct(App $app, UserRechargeRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }
    
    
    /*
     * insertTelephoneFareOrder
     * 创建充值订单
     * 发起支付前，获得输入号码运营商、归属地(省份和城市)、金额，连同用户ID提交接口，
     * 根据uid查询用户让利比例和贡献值等信息，根据金额、运营商获取第三方产品ID(product_id)，写入订单记录表，返回订单详细数据
     */

    public function insertTelephoneFareOrder(UserRepository $userRepository){
        /*
        输入:
        money=50;
        uid=42044;
        phone=18978328386;
        返回=单个订单创建的详细数据
        */
        //return app('json')->success("888888");
        //die;
        //$res = $this->repository->payyunfastpay_app_chongzhi();
        //var_dump($res);
        //die;
        $test = app()->make(StoreOrderRepository::class)->payyunfastpay_app_chongzhi();
        
        // return app('json')->success($test);
        // return app('json')->success("9999");
        die;
        
        
        //检查所有需要获取的post数据
        $data['money'] = (int)$this->request->param('money');
        if ($data['money'] <= 0)
            return app('json')->fail('请输入正确的充值金额!');
        $data['uid'] = (int)$this->request->param('uid');
        if ($data['uid'] <= 0)
            return app('json')->fail('请输入正确的uid!');
       
        //根据uid查询用户贡献值信息
        $contribute_temp = Db::table('eb_user')->where(['uid'=>$data['uid'] ])->order('uid desc')->find();
        $data['contribute']=(float)$contribute_temp["brokerage_gongxian"];//1140.44
        //var_dump($data['contribute']);
        //die;
        //根据uid查询用户让利比例信息
        //测试uid=42044
        $rangli_temp= Db::table('eb_merchant')->field("crate")->where(['uid'=>$data['uid'] ])->order('uid desc')->find();
        //var_dump($rangli_temp);
        //die;
        $data['rate']=$rangli_temp["crate"];//10
            
        $data['phone'] = $this->request->param('phone');
        if (!$data['phone'] || $data['phone']=='') return app('json')->fail('请输入手机号');
        $phone_data=json_decode(HttpService::getRequest('https://cx.shouji.360.cn/phonearea.php?number='.$data['phone']));
        //var_dump($phone_data);
        //die;
        //var_dump($phone_data->code);
        //die;
        if($phone_data->code!=0) return app('json')->fail('查询手机号码有误！');
        //var_dump($phone_data->code!=0);
        //die;
        //$correspondence = $this->request->param('correspondence');
        //if ($correspondence <= 0 && $correspondence!="移动" && $correspondence!="联通" && $correspondence!="电信")
            //return app('json')->fail('请输入正确的通讯运营商：移动；联通；电信!');
        //$location_province = $this->request->param('location_province');
        //var_dump($phone_data->data->sp);
        //die;
        $data['correspondence']=$phone_data->data->sp;
        // var_dump($data['correspondence']);
        // die;
        //if ($data['correspondence'] <= 0)
            //return app('json')->fail('请输入正确的号码归属地省份!');
        $data['location']=$phone_data->data->city;
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
        // var_dump($data['correspondence']=='移动' && $data['money']=='50');
        // die;
        if($data['correspondence']=='移动' && $data['money']='50'){
            $data['product_id']=array_search('移动话费全国50元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']='100'){
            $data['product_id']=array_search('移动话费全国100元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']='200'){
            $data['product_id']=array_search('移动话费全国200元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']='300'){
            $data['product_id']=array_search('移动话费全国300元', $hfarray);
        }else if($data['correspondence']=='移动' && $data['money']='500'){
            $data['product_id']=array_search('移动话费全国500元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']='50'){
            $data['product_id']=array_search('联通话费全国50元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']='100'){
            $data['product_id']=array_search('联通话费全国100元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']='200'){
            $data['product_id']=array_search('联通话费全国200元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']='300'){
            $data['product_id']=array_search('联通话费全国300元', $hfarray);
        }else if($data['correspondence']=='联通' && $data['money']='500'){
            $data['product_id']=array_search('联通话费全国500元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']='50'){
            $data['product_id']=array_search('电信话费全国50元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']='100'){
            $data['product_id']=array_search('电信话费全国100元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']='200'){
            $data['product_id']=array_search('电信话费全国200元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']='300'){
            $data['product_id']=array_search('电信话费全国300元', $hfarray);
        }else if($data['correspondence']=='电信' && $data['money']='500'){
            $data['product_id']=array_search('电信话费全国500元', $hfarray);
        }else{
            return app('json')->fail('运营商和金额不匹配!');
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
        $res=Db::table('eb_telephone_fare_order')->save($data);//写入订单记录表
        //$res=1 则添加成功
        if($res===1){
            //返回订单详细数据
            //$query = Db::table('eb_telephone_fare_order')->where(['order_sn' =>$data['order_sn']]);
            $lastdata = Db::table('eb_telephone_fare_order')->where(['order_sn'=>$data['order_sn'] ])->order('order_id desc')->find();
            //var_dump($lastdata);
            return app('json')->success($lastdata);//返回订单详细数据
            //$this-placeTelephoneFareOrder($data['order_sn']);
            //die;
        }else{
            return app('json')->fail('创建充值订单失败!');
        }
        //var_dump($res);
        //die;
        //return app('json')->success($res);//返回订单详细数据
        
    }



    /*
     * placeTelephoneFareOrder
     * 发起充值支付
     * 调用1接口(insertTelephoneFareOrder)获得order_no，在终端发起支付，支付完成后调用此接口，修改订单支付状态，并触发调用第三方充值接口；
     * 第三方接口回调充值结果，修改订单状态值，如果成功则修改用户贡献值
     * 输出：调起终端支付的相关信息，如签名等，据此调起支付应用完成订单付款，支付平台回调给服务端修改充值订单状态，
     * 并根据支付状态发起第三方话费充值接口调用
     */

    public function placeTelephoneFareOrder(){
        //http://test.shuzaiyunshang.com/api/user/placeTelephoneFareOrder
        //http://8.131.240.243:8081/api/recharge.jsp
        //{"api_userid":"1111","product_id":"10005115","mobile":"18678787227","order_no":"201609220138457462"}
        $data['api_userid']="guangcai";
        $data['product_id']="200250";
        $data['mobile']="18678787227";
        $data['order_no']="201609220138457462";
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
        var_dump($chongzhi_data);
        die;
        //$chongzhi_data=json_decode(HttpService::getRequest('http://ly.wluo.cn/showcz.php?api_data_temp='.$api_data_temp));
        $data['api_data']= $chongzhi_data;
        //var_dump($chongzhi_data);
        //die;
        //返回
        // string(224)
        //"A077C3AB1C1F24972CAE2DCECA9CAE6C5DCE057F42EE79011CF161B4938892125D946BFBE53C1D6DD224049AD813C26465C6ACF7F6C99A5C9B2BDBDEF47713E699B683D66FC5DAAE2EE5152A69EFF96DCB39A3A3E0148CC1B47604B157D690269B8AE5776F6F32B83206EA4AA19F7517"
        
        //发起充值支付
        $phone_data=json_decode(HttpService::getRequest('http://8.131.240.243:8081/api/recharge.jsp?api_userid='.$data['api_userid'].'&api_data='.$data['api_data']));
        
        var_dump($phone_data);
        die;
        
        

    }
    
    public function selectTelephoneFareOrder(UserRepository $userRepository){
        //var_dump("8888");
        // return app('json')->fail('8888!');//对应error
        // die;
        $uid = (int)$this->request->param('uid');
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
        //$lastdata = Db::table('eb_telephone_fare_order')->where(['uid'=>$uid])->order('order_id desc')->find();
        //$lastdata = Db::table('eb_telephone_fare_order')->where(['uid'=>$uid])->order('order_id desc');
        //$list = Db::table('eb_telephone_fare_order')->where(['uid'=>$uid])->order('order_id desc')->select();
        $list = Db::table('eb_telephone_fare_order')->where(['uid'=>$uid])->order('order_id desc')->select();
        //->where([ 0 => ['age','between',[20,40]],1=>  ['age','>',30]]) 
        //$lastdata = Db::table('eb_telephone_fare_order')->where(['$uid'=>$uid,'order_sn'=>$order_no,'page_index'=>$page_index,'$page_size'=>$page_size])->order('order_id desc')->find();
        // var_dump($list);
        return app('json')->success($list);//返回订单详细数据
            
    }

    /*
    * huafei_recharge_result
    * 查询话费充值返回结果
    * 2021-6-10 暂时不用
    * 
    */

    public function huafei_recharge_result(){
        
        
        //http://8.131.240.243:8081/api/recharge_result.jsp
        //{"api_userid":"1111" ,"mobile":"18678787227","order_no","201609220138457462"}
        $data['api_userid']="guangcai";
        $data['product_id']="200250";
        $data['mobile']="18678787227";
        $data['order_no']="201609220138457462";
        $pro_data['api_data_temp']=json_encode($data);
        $chongzhi_data=HttpService::postRequest("http://ly.wluo.cn/showcz.php",$pro_data);
        //var_dump($chongzhi_data);
        //die;
        $data['api_data']= $chongzhi_data;
        //var_dump($data['api_data']);
        //die;
        //var_dump("http://8.131.240.243:8081/api/recharge_result.jsp?api_userid='.$data['api_userid'].'&app_data='.$data['api_data']");
        //die;
        
        //$cz_result_data=json_decode(HttpService::getRequest('http://8.131.240.243:8081/api/recharge_result.jsp?api_userid='.$data['api_userid'].'&app_data='.$data['api_data']));
        $cz_result_data=json_decode(HttpService::getRequest('http://8.131.240.243:8081/api/recharge_result.jsp?api_userid='.$data['api_userid'].'&api_data='.$data['api_data']));
        //string(50) "{"retcode":0,"retmsg":"充值失败","errcode":""}"
        //var_dump($cz_result_data);
        //var_dump($cz_result_data["retmsg"]);
        //var_dump($cz_result_data->retcode);
        //die;
        if($cz_result_data->retcode==0){
            return app('json')->fail($cz_result_data->retmsg);
        }else{
            return app('json')->success($cz_result_data->retmsg);
        }
        
        

        /*
        $uid = (float)$this->request->param('uid');
        if ($uid <= 0)
            return app('json')->fail('请输入正确的用户id!');
        $order_no = (int)$this->request->param('order_no');
        if ($order_no <= 0)
            return app('json')->fail('请输入正确的订单编号!');
        $page_index = $this->request->param('page_index');
        if ($page_index <= 0)
            return app('json')->fail('请输入正确的分页首!');
        $page_size = $this->request->param('page_size');
        if ($page_size <= 0)
            return app('json')->fail('请输入正确的分页长!');
            */
    }
    
    /*
     * getTelProduct
     * 获取产品
     */
    public function getTelProduct()
    {
        /*
        $api_userid = $this->request->param('api_userid');
        if ($api_userid <= 0)
            return app('json')->fail('请输入正确的商户号!');
        $api_data = $this->request->param('api_data');
        if ($api_data <= 0)
            return app('json')->fail('请输入正确的api_data!');
        */
        //密钥
        //$keyStr = $api_userid;
        //$keyStr = '6D6A39C7078F6783E561B0D1A9EB2E68';
        //$keyStr = "D3014CC1F382CDFCBAF863AAB55DDAE3";
        //加密的字符串
        //$plainText = $api_data;
        //$plainText = '{"api_userid":"hbrx"}';
        //$plainText = '{"api_userid":"guangcai"}';

        //$aes = new AES();
        //$aes->set_key($keyStr);
        //$aes->require_pkcs5();
        //$encText = $aes->encrypt($plainText);

        //echo $encText;
        //return app('json')->success($encText);
        //$pro_data['api_userid']="guangcai";
        //$vi =  openssl_encrypt(json_decode('{"api_userid":"guangcai"}'), 'aes-128-ecb', base64_decode($key), OPENSSL_RAW_DATA);
        //$pro_data['api_data']=base64_encode($vi);
        //$pro_data['api_data']="A077C3AB1C1F24972CAE2DCECA9CAE6C6D105F6A0EF88516523576E577063470";
        //$resultone=HttpService::postRequest("http://8.131.240.243:8081/api/products.jsp",$pro_data);

        


        //$a = file_get_contents("http://8.131.240.243:8081/api/products.jsp");
        //return app('json')->success($a);
        //die;
        
        //参数设置：
        //$url = 'http://8.131.240.243:8081/api/products.jsp';//POST指向的链接      
        // $data = array(      
        //     'api_userid'=>'guangcai',
        //     'api_data'=>'A077C3AB1C1F24972CAE2DCECA9CAE6C6D105F6A0EF88516523576E577063470'
        // );    
        //return 0;
        //die;
        //密钥
        $keyStr = 'D3014CC1F382CDFCBAF863AAB55DDAE3';
        //商户号
        $data['api_userid']="guangcai";
        
        //加密前数据
        $plainText = '{"api_userid":"guangcai"}';
        //获取加密后数据
        //$vi=openssl_encrypt(json_decode('{"api_userid":"guangcai"}'), 'aes-256-ecb', $keyStr, OPENSSL_RAW_DATA);
        //$vi=openssl_encrypt(json_decode('{"api_userid":"guangcai"}'), 'aes-256-cbc', $keyStr, OPENSSL_RAW_DATA);//试一下cbc
        //$data = openssl_encrypt($plainText, 'aes-128-ecb', $keyStr, OPENSSL_RAW_DATA);
        //$data = openssl_encrypt($plainText, 'aes-128-ecb', $keyStr, OPENSSL_RAW_DATA);
        //openssl_encrypt($data, 'aes-256-cbc', $encryption_key, 0, $iv);
        //return $data;
        //die;
        //从php5.6网站获取加密后的api_data
        //$plainText=json_encode($plainText);
        //$data['api_data']=HttpService::postRequest("http://ly.wluo.cn/showproduct.php",$plainText);
        $data['api_data']=HttpService::postRequest("http://ly.wluo.cn/showproduct.php");
        //var_dump($data['api_data']);
        //die;
        //$data['api_data'] = "A077C3AB1C1F24972CAE2DCECA9CAE6C6D105F6A0EF88516523576E577063470";
        //加密的字符串
        //$plainText = $api_data;
        //$plainText = '{"api_userid":"hbrx"}';
        //$plainText = '{"api_userid":"guangcai"}';
        //return 'http://8.131.240.243:8081/api/products.jsp?api_userid='.$data['api_userid'].'&api_data='.$data['api_data'];
        //die;
        //$product_data=json_decode(HttpService::getRequest('http://8.131.240.243:8081/api/products.jsp?api_userid='.$data['api_userid'].'&api_data='.$data['api_data']));
        $product_data=HttpService::getRequest('http://8.131.240.243:8081/api/products.jsp?api_userid='.$data['api_userid'].'&api_data='.$data['api_data']);
        return $product_data;
        //return app('json')->success($product_data);
    
        //接口调用
        // $aes = new AES();
        // $json_data = $aes->postData($url, $data);      
        // $array = json_decode($json_data,true);      
        //echo '<pre>';print_r($array);
        //return app('json')->success('{"retcode":200,"retmsg":"成功获取产品列表","items":[{"product_code":"10005135","product_title":"山东联通省内40M","product_fee":"2.4"},{"product_code":"10005112","product_title":"山东联通省内全网20M","product_fee":"2.64"} ]}');
    }
    
        

    public function brokerage(UserRepository $userRepository)
    {
        $brokerage = (float)$this->request->param('brokerage');
        if ($brokerage <= 0)
            return app('json')->fail('请输入正确的充值金额!');

        $config = systemConfig(['recharge_switch', 'balance_func_status','sw_gq_ye','store_user_min_recharge']);
        if ($brokerage < floatval($config['store_user_min_recharge']))
            return app('json')->fail('最低兑换 ' . floatval($config['store_user_min_recharge']).' 股权值');

        $user = $this->request->userInfo();
        if ($user->brokerage_price < $brokerage)
            return app('json')->fail('剩余股权值不足' . $brokerage);

        if (!$config['recharge_switch'] || !$config['balance_func_status'])
            return app('json')->fail('余额充值功能已关闭');

        if (!$config['sw_gq_ye'] )
            return app('json')->fail('兑换功能暂时关闭！');

        if (!$user->sw_gq_ye)
            return app('json')->fail('兑换功能暂时关闭！');

        $userRepository->switchBrokerage($user, $brokerage);
        return app('json')->success('转换成功');
    }

    public function recharge(GroupDataRepository $groupDataRepository)
    {
        [$type, $price, $rechargeId] = $this->request->params(['type', 'price', 'recharge_id'], true);
        if (!in_array($type, ['wechat', 'routine', 'h5']))
            return app('json')->fail('请选择正确的支付方式!');
        $wechatUserId = $this->request->userInfo()['wechat_user_id'];
        if (!$wechatUserId && in_array($type, ['wechat', 'routine']))
            return app('json')->fail('请关联微信' . ($type == 'wechat' ? '公众号' : '小程序') . '!');
        $config = systemConfig(['store_user_min_recharge', 'recharge_switch', 'balance_func_status']);
        if (!$config['recharge_switch'] || !$config['balance_func_status'])
            return app('json')->fail('余额充值功能已关闭');
        if ($rechargeId) {
            if (!intval($rechargeId))
                return app('json')->fail('请选择充值金额!');
            $rule = $groupDataRepository->merGet(intval($rechargeId), 0);
            if (!$rule || !isset($rule['price']) || !isset($rule['give']))
                return app('json')->fail('您选择的充值方式已下架!');
            $give = floatval($rule['give']);
            $price = floatval($rule['price']);
            if ($price <= 0)
                return app('json')->fail('请选择正确的充值金额!');
        } else {
            $price = floatval($price);
            if ($price <= 0)
                return app('json')->fail('请输入正确的充值金额!');
            if ($price < $config['store_user_min_recharge'])
                return app('json')->fail('最低充值' . floatval($config['store_user_min_recharge']));
            $give = 0;
        }
        $recharge = $this->repository->create($this->request->uid(), $price, $give, $type);
        $userRepository = app()->make(WechatUserRepository::class);
        if ($type == 'wechat') {
            $openId = $userRepository->idByOpenId($wechatUserId);
            if (!$openId)
                return app('json')->fail('请关联微信公众号!');
            $data = $this->repository->wxPay($openId, $recharge);
        } else if ($type == 'h5') {
            $data = $this->repository->wxH5Pay($recharge);
        } else {
            $openId = $userRepository->idByRoutineId($wechatUserId);
            if (!$openId)
                return app('json')->fail('请关联微信小程序!');
            $data = $this->repository->jsPay($openId, $recharge);
        }

        return app('json')->success(compact('type', 'data'));
    }
}
