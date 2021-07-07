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

use app\controller\admin\store\StoreCategory;
//use app\common\repositories\store\StoreCategoryRepository ;
//use app\common\repositories\store\shipping\ShippingTemplateRepository ;
use app\controller\merchant\store\shipping\ShippingTemplate;
use app\controller\merchant\store\product\Product;


use crmeb\basic\BaseController;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\user\UserVisitRepository;
use crmeb\services\YunxinSmsService;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\facade\Cache;

class User extends BaseController
{
    public $repository;

    public function __construct(App $app, UserRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function msg_log(){
        $user = $this->request->userInfo();
        if(!$user['uid']) return ;
        if(!$user->service->mer_id){
            return app('json')->fail('无权限！');
        }
        $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
        if(!$mer)     return app('json')->fail('无权限2！');

        [$page, $limit] = $this->getPage();
        $query = Db::table('eb_system_notice_log')->where(['mer_id'=>$user->service->mer_id,'is_del'=>0]) ;
        $count = $query->count();

        $list  = $query->order('notice_log_id desc')->page($page, $limit)->select()->toArray();
        foreach ($list as &$v){
            $notice = Db::table('eb_system_notice')->find($v['notice_id']);
            $v['title'] = $notice['notice_title'] ;
            $v['content'] = $notice['notice_content'] ;
            $v['create_time'] = $notice['create_time'] ;
        }
        unset($v);

        return app('json')->success(compact('list','count'));
    }


    public function goods_edit(){
        $user = $this->request->userInfo();
        if(!$user['uid']) return ;
        if(!$user->service->mer_id){
            return app('json')->fail('无权限！');
        }
        $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
        if(!$mer)     return app('json')->fail('无权限2！');
        return   app()->make(Product::class)->update2($this->request->param('product_id'),$mer);
    }


    public function switch_status(){
        $user = $this->request->userInfo();
        if(!$user['uid']) return ;
        if(!$user->service->mer_id){
            return app('json')->fail('无权限！');
        }
        $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
        if(!$mer)     return app('json')->fail('无权限2！');
        return  app()->make(Product::class)->switchStatus2($this->request->param('id'),$user->service->mer_id,$this->request->param('status'));
    }

    public function goods_list(){
        $user = $this->request->userInfo();
        if(!$user['uid']) return ;
        if(!$user->service->mer_id){
            return app('json')->fail('无权限！');
        }
        $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
        if(!$mer)     return app('json')->fail('无权限2！');

        return  app()->make(Product::class)->lst2($mer['mer_id'],$this->request->param('product_id'));

    }


    public function goods_add(){
        $user = $this->request->userInfo();
        if(!$user['uid']) return ;
        if(!$user->service->mer_id){
            return app('json')->fail('无权限！');
        }
        $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
        if(!$mer)     return app('json')->fail('无权限2！');
      $res = app()->make(Product::class)->create2($mer);
      if($res)        return app('json')->success('操作成功');
        return app('json')->fail('操作失败');
    }

    public function goods_add_list()
    {
        $user = $this->request->userInfo();
        if(!$user['uid']) return ;
        if(!$user->service->mer_id){
            return app('json')->fail('无权限！');
        }
        $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
        if(!$mer)     return app('json')->fail('无权限2！');


        $list1 = app()->make(StoreCategory::class)->getTreeList2($mer['mer_id']);
        $list2 = app()->make(StoreCategory::class)->BrandList2();
        $list3 = app()->make(ShippingTemplate::class)->getList2($mer['mer_id']);


        $n_list1 = [];
        foreach ($list1 as $vv){
            $n_list1[] = [
             'id'=>$vv['value'],
             'name'=>$vv['label']
         ];
            if(isset($vv['children']) && $vv['children']){
                foreach ($vv['children'] as  $vvv){
                    $n_list1[] = [
                        'id'=>$vvv['value'],
                        'name'=>$vv['label'].'-'.$vvv['label']
                    ];
                    if(isset($vvv['children']) && $vvv['children']){
                        foreach ($vvv['children'] as $vvvv){
                            $n_list1[] = [
                                'id'=>$vvvv['value'],
                                'name'=>$vv['label'].'-'.$vvv['label'] .'-' .$vvvv['label']
                            ];
                        }
                    }
                }
            }
        }
        $list1 = $n_list1;

        $n_list2 = [];
        foreach ($list2 as  $vvvv){
            $n_list2[] =   [
                'id'=>$vvvv['brand_id'],
                'name'=>$vvvv['brand_name']
            ];
        }
        $list2 = $n_list2;
        $n_list3 = [];
        foreach ($list3 as  $vvvv){
            $n_list3[]= [
                'id'=>$vvvv['shipping_template_id'],
                'name'=>$vvvv['name']
            ];
        }
        unset($vvvv);
        $list3 = $n_list3;

        return app('json')->success(compact('list1','list2','list3'));
    }

    public function reg_sub_list()
    {
        $user = $this->request->userInfo();
        if(!$user['uid']) return ;
        if(!$user->service->mer_id){
            return app('json')->fail('无权限！');
        }
        $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
        if(!$mer)     return app('json')->fail('无权限2！');


        [$page, $limit] = $this->getPage();
        $query = Db::table('eb_mer_sub_info')->where([
            'mer_id' =>$mer['mer_id'],
        ]) ;
        $count = $query->count();

        $list  = $query->order('id desc')->page($page, $limit)->select()->toArray();
        foreach ($list as &$v){

        }
        unset($v);

        return app('json')->success(compact('list','count'));
    }
    public function reg_sub()
    {
        $user = $this->request->userInfo();
        $data = $this->request->param();
        if(!$user->service->mer_id){
            return app('json')->fail('无权限！');
        }
        $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
        if(!$mer)     return app('json')->fail('无权限2！');

        $data['mer_id'] = $user->service->mer_id;
        $data['state'] = 0;
        $data['uid'] =$user->uid;


        if(!$data['phone'] || !$data['name'] || !$data['zone'] || !$data['reg_addr'] || !$data['reg_time'] ||  !$data['desp'] ||  !$data['face_imgs']  ||  !$data['bank_addr']    )  return app('json')->fail('请完善内容！');

//个人 企业 show1  0   1   type
//公户收款 法人私户  非法人私户 show2  0   1   2  sub_type
        if($data['type'] == '个人'){
            if(!$data['bank_no'] || !$data['bank_img_fr'] || !$data['bank_img_bk']  )  return app('json')->fail('请完善内容(1)！');
        }else{
            if(!$data['sub_type']  || !$data['reg_license']  ||  !$data['reg_license_img']   )  return app('json')->fail('请完善内容(1.1)！');

            if($data['sub_type'] == '公户收款'){
                if(!$data['reg_sw_img']   )  return app('json')->fail('请完善内容(1.2)！');
            }
            if($data['sub_type'] == '法人私户'){
                if(!$data['bank_no'] || !$data['bank_img_fr'] || !$data['bank_img_bk']  )  return app('json')->fail('请完善内容(2)！');
            }
            if($data['sub_type'] == '非法人私户'){
                if(!$data['js_idno_img_fr'] || !$data['js_idno_img_bk'] )  return app('json')->fail('请完善内容(6)！');
                if(!$data['bank_no'] || !$data['bank_img_fr'] || !$data['bank_img_bk']  )  return app('json')->fail('请完善内容(3)！');
                if( !$data['fa_name'] || !$data['fa_idno'] || !$data['fa_time'] || !$data['fa_idno_img_fr'] ||  !$data['fa_idno_img_bk']   )  return app('json')->fail('请完善内容(4)！');
                if( !$data['auth_book_img']     )  return app('json')->fail('请完善内容(5)！');
            }
        }

        if( Db::table('eb_mer_sub_info')->where(['state'=>0,'mer_id'=>$user->service->mer_id])->find()){
            return app('json')->fail('您还有待审核的申请！');
        }

         Db::table('eb_mer_sub_info')->insert($data);

        return app('json')->success('已提交');
    }


    public function editnor( )
    {
        $user = $this->request->userInfo();
        if(!$user['uid']) return ;
        $data = $this->request->params(['nickname', 'avatar']);
        if($data['nickname']){
            $user->nickname = $data['nickname'];
        }
        if($data['avatar']){
            $user->avatar = $data['avatar'];
        }
        if($user->save()){
            return app('json')->success('已更新！');
        }else{
            return app('json')->fail('未修改！');
        }


    }



    public function scanbill( )
    {
        $user = $this->request->userInfo();
        if(!$user['uid']) return ;
        [$page, $limit] = $this->getPage();
        $query = Db::table('eb_scan_order')->where([
            'uid' =>$user['uid'] ,
        ]) ;
        $count = $query->count();

        $list  = $query->order('scan_order_id desc')->field('mer_id,status,create_time,money,order_sn')->page($page, $limit)->select()->toArray();
        foreach ($list as &$v){
            $mer = Db::table('eb_merchant')->find($v['mer_id']);
            $v['mer_name'] = $mer?$mer['mer_name'] : '--' ;
            $v['status_str'] = $v['status']? '成功' : '失败' ;
        }

        return app('json')->success(compact('list','count'));
    }


    /**
     * @return mixed
     * @author xaboy
     * @day 2020/6/22
     */
    public function spread_image()
    {
        $type = $this->request->param('type');
        $res = $type == 'routine'
            ? $this->repository->routineSpreadImage($this->request->userInfo())
            : $this->repository->wxSpreadImage($this->request->userInfo());
        return app('json')->success($res);
    }

    public function spread_image_v2()
    {
        $type = $this->request->param('type');
        $user = $this->request->userInfo();
        $siteName = systemConfig('site_name');
        $qrcode = $type == 'routine'
            ? $this->repository->mpQrcode($user)
            : $this->repository->wxQrcode($user);
        $poster = systemGroupData('spread_banner');
        $nickname = $user['nickname'];
        $mark = '邀请您加入' . $siteName;
        return app('json')->success(compact('qrcode', 'poster', 'nickname', 'mark'));
    }

    public function spread_image_v3()
    {
        $user = $this->request->userInfo();
        $siteName = systemConfig('site_name');
        $poster = systemGroupData('spread_banner');
        $nickname = $user['nickname'];
        $mark = '邀请您加入' . $siteName;
        $qrcode = $this->repository->wxQrcode($user);
        $qrcode = str_replace(systemConfig('site_url'),  '' , $qrcode);
        foreach ($poster as &$v){
            $v['pic'] = str_replace(systemConfig('site_url'), public_path() , $v['pic']);
            $v['pic'] =  $this->getcode($qrcode,$v['pic'],$nickname,$mark);
        }
        unset($v);

        return app('json')->success(compact('qrcode', 'poster', 'nickname', 'mark'));
    }

    private function  getcode($path,$bg,$nickname,$mark){
        $siteUrl = systemConfig('site_url');
        $outfile = \think\facade\Config::get('qrcode.cache_dir');
        if (!is_dir('./public/' . $outfile))
            mkdir('./public/' . $outfile, 0777, true);
        $name = 'sp'.md5($path.'5').'.jpg';
        $filepath = public_path().$outfile.'/'.$name;
        $fileurl = rtrim($siteUrl, '/') . '/' . $outfile . '/' . $name;
        if(file_exists( $filepath ) ) return $fileurl  ;
        $config = array(
            'image'=>array(
                array(
                    'url'=>public_path().$path ,     //二维码资源
                    'stream'=>0,
                    'left'=>76 + 1  ,
                    'top'=>517 + 17,
                    'right'=>0,
                    'bottom'=>0,
                    'width'=>87,
                    'height'=>87,
                    'opacity'=>100
                )
            ),
            'text'=>array(
                array(
                    'text'=>$nickname,
                    'left'=>76 + 10  + 82 + 5,
                    'top'=>517 + 20+ 27,
                    'right'=>0,
                    'bottom'=>0,
                    'fontSize'=>12,
                    'fontColor'=>'0,0,0', //字体颜色
                    'fontPath'=>public_path().'file/msyh.ttc'
                ),
                array(
                    'text'=>$mark,
                    'left'=>76 + 10  + 82 + 5,
                    'top'=>517 + 20 + 22 + 47,
                    'right'=>0,
                    'bottom'=>0,
                    'fontSize'=>12,
                    'fontColor'=>'0,0,0', //字体颜色
                    'fontPath'=>public_path().'file/msyh.ttc'
                )
            ),
            'background'=>$bg,         //背景图
        );

        createPoster($config,$filepath);
        return   $fileurl;
    }


    // public function spread_info()
    // {
    //     $user = $this->request->userInfo();
    //     $user->append(['one_level_count', 'lock_brokerage', 'two_level_count', 'spread_total', 'yesterday_brokerage', 'total_extract', 'total_brokerage', 'total_brokerage_price','selfyeji','allyeji','memberlevel3','memberlevel2']);
    //     $data = [
    //         'total_brokerage_price' => $user->total_brokerage_price,
    //         'lock_brokerage' => $user->lock_brokerage,
    //         'one_level_count' => $user->one_level_count,
    //         'two_level_count' => $user->two_level_count,
    //         'spread_total' => $user->spread_total,
    //         'yesterday_brokerage' => $user->yesterday_brokerage,
    //         'total_extract' => $user->total_extract,
    //         'total_brokerage' => $user->total_brokerage,
    //         'brokerage_price' => $user->brokerage_price,
    //         'brokerage_gongxian' => $user->brokerage_gongxian,
    //         'brokerage_shuquan' => $user->brokerage_shuquan,
    //         'brokerage_duihuan' => $user->brokerage_duihuan,
    //         'now_money' => $user->now_money,
    //         'broken_day' => (int)systemConfig('lock_brokerage_timer'),
    //         'user_extract_min' => (int)systemConfig('user_extract_min'),
    //         'selfyeji'=>$user->selfyeji, /*个人业绩*/
    //         'allyeji'=>$user->allyeji, /*团队业绩*/
    //         'memberlevel3'=>$user->memberlevel3, /*直推人数*/
    //         'memberlevel2'=>$user->memberlevel2 /*团队人数*/

    //     ];
    //     return app('json')->success($data);
    // }
    public function signinlist2()
    {
        $user = $this->request->userInfo();
        $list = Db::table('eb_user_sign')->where(['uid'=>$user['uid']])->order('id desc')->group('sign_time')->column('sign_time') ;
        foreach ($list as &$v){
            $v = date('Y-m-d',strtotime($v));
        }
        unset($v);
        return app('json')->success(['list'=>$list,'issigned'=>strtotime($user['sign_time']) >= strtotime(date('Y-m-d 00:00:00')) ? true : false ]);
    }


    public function signin(){
        $user = $this->request->userInfo();

        if(time() <strtotime(date('Y-m-d 01:00:00')) ){
            return app('json')->success([
                'msg' => '系统维护中,请稍后'
            ]);
        }

        $sign = Db::table('bonus_log')->where(['content'=>'done=1'])->order('id desc')->find();
        if(!$sign   || strtotime($sign['time']) < strtotime(date('Y-m-d 00:00:00'))){
            return app('json')->success([
                'msg' => '系统维护中,请稍后......'
            ]);
        }


        if(!$user['sign_time'] || date("Y-m-d") != date('Y-m-d',strtotime($user['sign_time']))  ){
            Db::table('eb_user_sign')->save([
                'uid'=>$user['uid'],
                'sign_time'=>date('Y-m-d H:i:s'),
            ]);

            Db::name('user')->save(['uid' => $user['uid'], 'sign_time' => date("Y-m-d H:i:s",time())]);
            $data = [
                'msg' => '签到成功！'
            ];
            return app('json')->success($data);
        } else {
            $data = [
                'msg' => '您已签到！'
            ];
            return app('json')->success($data);
        }
    }

    /**
     * @param UserBillRepository $billRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/22
     */
    public function bill(UserBillRepository $billRepository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($billRepository->userList([
            'now_money' => $this->request->param('type', 0),
            'status' => 1,
        ], $this->request->uid(), $page, $limit));
    }


    /**
     * @param UserBillRepository $billRepository
     * @return mixed
     * @author xaboy
     * @day 2020/6/22
     */
    public function brokerage_list(UserBillRepository $billRepository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($billRepository->userList([
            'category' => 'brokerage_price',
        ], $this->request->uid(), $page, $limit));
    }

    public function brokerage_shuquan_list(UserBillRepository $billRepository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($billRepository->userList([
            'category' => 'brokerage_shuquan',
        ], $this->request->uid(), $page, $limit));
    }
    public function brokerage_duihuan_list(UserBillRepository $billRepository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($billRepository->userList([
            'category' => 'brokerage_duihuan',
        ], $this->request->uid(), $page, $limit));
    }
    public function brokerage_gongxian_list(UserBillRepository $billRepository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($billRepository->userList([
            'category' => 'brokerage_gongxian',
        ], $this->request->uid(), $page, $limit));
    }

    /**
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/22
     */
    public function spread_order()
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->subOrder($this->request->uid(), $page, $limit));
    }

    /**
     * TODO
     * @return mixed
     * @author Qinii
     * @day 2020-06-18
     */
    public function binding()
    {
        $data = $this->request->params(['phone', 'sms_code']);
        if (!$data['sms_code'] || !(YunxinSmsService::create())->checkSmsCode($data['phone'], $data['sms_code'],'binding')) return app('json')->fail('验证码不正确');
        $user = $this->repository->accountByUser($data['phone']);
        if ($user) {
            $data = ['phone' => $data['phone']];
        } else {
            $data = ['account' => $data['phone'], 'phone' => $data['phone']];
        }
        $this->repository->update($this->request->uid(), $data);
        return app('json')->success('绑定成功');
    }

    /**
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/22
     */
    public function spread_list()
    {
        [$level, $sort, $nickname] = $this->request->params(['level', 'sort', 'keyword'], true);
        $uid = $this->request->uid();
        [$page, $limit] = $this->getPage();
        return app('json')->success($level == 2
            ? $this->repository->getTwoLevelList($uid, $nickname, $sort, $page, $limit)
            : $this->repository->getOneLevelList($uid, $nickname, $sort, $page, $limit));
    }


    public function spread_list2()
    {
        [$level, $sort, $nickname] = $this->request->params(['level', 'sort', 'keyword'], true);
        $uid = $this->request->uid();
        [$page, $limit] = $this->getPage();
        return app('json')->success( $this->repository->getOneLevelList2($uid, $nickname, $sort, $page, $limit) );
    }


    /**
     * @return mixed
     * @author xaboy
     * @day 2020/6/22
     */
    public function spread_top()
    {
        [$page, $limit] = $this->getPage();
        $type = $this->request->param('type', 0);
        return app('json')->success($type == 1
            ? $this->repository->spreadMonthTop($page, $limit)
            : $this->repository->spreadWeekTop($page, $limit));
    }

    //new
    public function spread_info()
    {
        $user = $this->request->userInfo();
        $user->append(['one_level_count', 'lock_brokerage', 'two_level_count', 'spread_total', 'yesterday_brokerage', 'total_extract', 'total_brokerage', 'total_brokerage_price','selfyeji','allyeji','memberlevel3','memberlevel2','certifyState']);
        $data = [
            'total_brokerage_price' => $user->total_brokerage_price,
            'lock_brokerage' => $user->lock_brokerage,
            'one_level_count' => $user->one_level_count,
            'two_level_count' => $user->two_level_count,
            'yesterday_brokerage' => $user->yesterday_brokerage,
            'total_extract' => $user->total_extract,
            'total_brokerage' => $user->total_brokerage,
            'brokerage_price' => $user->brokerage_price,
            'brokerage_gongxian' => $user->brokerage_gongxian,
            'brokerage_shuquan' => $user->brokerage_shuquan,
            'brokerage_duihuan' => $user->brokerage_duihuan,
            'certifyState' => $user->certifyState,
            'now_money' => $user->now_money,
            'broken_day' => (int)systemConfig('lock_brokerage_timer'),
            'user_extract_min' => (int)systemConfig('user_extract_min'),
            'selfyeji'=>$user->selfyeji, /*个人业绩*/
            'allyeji'=>$user->allyeji, /*团队业绩*/
            'memberlevel3'=>$user->memberlevel3, /*直推人数*/
            'memberlevel2'=>$user->memberlevel2 /*团队人数*/
        ];

        $user = $this->request->userInfo();
        if (isset($user->service->customer) && $user->service->customer == 1) {
             $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
            $data['merPrice'] = $mer?$mer['money']+0:'0.00';
        } else {
            $data['merPrice'] = 0;
        }

        $data['cash_info'] = systemConfig('cash_info');
        $data['cash_info2'] = systemConfig('cash_info2');

        $sql = "select count(1) num from eb_user where  concat(',',retree) like '%,{$user->uid},%'  and uid <> {$user->uid} ";
        $data['spread_total'] = Db::table('eb_user')->query($sql);
        $data['spread_total'] = $data['spread_total'][0]['num'];

        $data['lastdata'] = [
            'bank_code'=>'',
            'bank_address'=>'',
            'real_name'=>'',
        ];
        $address_id = $this->request->param('address_id');
        if(!$address_id){

            $item = Db::table('eb_user_address2')->where('uid',$user->uid)->where('is_default',1)->find();
            if($item){
                $data['lastdata'] = [
                    'bank_code'=>$item['bank_code'],
                    'bank_address'=>$item['bank_address'],
                    'real_name'=>$item['real_name'],
                ];
            }else{
                $lastdata = Db::table('eb_user_extract')->where(['uid'=>$user->uid ])->order('extract_id desc')->find();
                if($lastdata){
                    $data['lastdata'] = [
                        'bank_code'=>$lastdata['bank_code'],
                        'bank_address'=>$lastdata['bank_address'],
                        'real_name'=>$lastdata['real_name'],
                    ];
                }
            }

        }else{
            $address_id = intval($address_id);
            $item  = Db::table('eb_user_address2')->where('uid',$user->uid)->order('is_default desc')->where('address_id',$address_id)->find();
            $data['lastdata'] = [
                'bank_code'=>$item['bank_code'],
                'bank_address'=>$item['bank_address'],
                'real_name'=>$item['real_name'],
            ];
        }




        $data['pinfo'] = [
            'uid'=>'',
            'account'=>'',
            'phone'=>'',
        ];
        if($user->spread_uid > 0){
            $puser = Db::table('eb_user')->where(['uid'=>$user->spread_uid ])->find();
            if($puser){
                $data['pinfo'] = [
                    'uid'=>$puser['uid'],
                    'account'=>$puser['account'],
                    'phone'=>$puser['phone'],
                ];
            }
        }






        return app('json')->success($data);
    }

    /**
     * @return mixed
     * @author xaboy
     * @day 2020/6/22
     */
    public function brokerage_top()
    {
        [$page, $limit] = $this->getPage();
        $type = $this->request->param('type', 'week');
        $uid = $this->request->uid();
        return app('json')->success($type == 'month'
            ? $this->repository->brokerageMonthTop($uid, $page, $limit)
            : $this->repository->brokerageWeekTop($uid, $page, $limit));
    }

    public function history(UserVisitRepository $repository)
    {
        $uid = $this->request->uid();
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->getHistory($uid, $page, $limit));
    }

    public function deleteHistory($id, UserVisitRepository $repository)
    {
        $uid = $this->request->uid();

        if (!$repository->getWhereCount(['user_visit_id' => $id, 'uid' => $uid]))
            return app('json')->fail('数据不存在');
        $repository->delete($id);
        return app('json')->success('删除成功');
    }

    public function deleteHistoryBatch(UserVisitRepository $repository)
    {
        $uid = $this->request->uid();
        $data = $this->request->param('ids');
        if(!empty($data) && is_array($data)){
            foreach ($data as $id){
                if (!$repository->getWhereCount(['user_visit_id' => $id, 'uid' => $uid]))
                    return app('json')->fail('数据不存在');
            }
            $repository->batchDelete($data,null);
        }
        if($data == 1)
            $repository->batchDelete(null,$uid);

        return app('json')->success('删除成功');
    }

    public function account()
    {
        $user = $this->request->userInfo();
        if (!$user->phone) return app('json')->fail('请绑定手机号');
        return app('json')->success($this->repository->selfUserList($user->phone));
    }

    public function switchUser()
    {
        $uid = (int)$this->request->param('uid');
        if (!$uid) return app('json')->fail('用户不存在');
        $userInfo = $this->request->userInfo();
        if (!$userInfo->phone) return app('json')->fail('请绑定手机号');
        $user = $this->repository->switchUser($userInfo, $uid);
        $tokenInfo = $this->repository->createToken($user);
        $this->repository->loginAfter($user);
        return app('json')->success($this->repository->returnToken($user, $tokenInfo));
    }

}
