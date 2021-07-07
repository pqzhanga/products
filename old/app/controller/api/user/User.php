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
        return app('json')->success(['list'=>$list]);
    }
    public function signin(){
        $user = $this->request->userInfo();
        if(!$user['sign_time'] || date("Y-m-d") != date('Y-m-d',strtotime($user['sign_time']))  ){
            Db::table('eb_user_sign')->save([
                'uid'=>$user['uid'],
                'sign_time'=>date('Y-m-d'),
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
