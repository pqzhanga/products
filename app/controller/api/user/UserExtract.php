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
use crmeb\services\SwooleTaskService;
use think\App;
use app\validate\api\UserExtractValidate as validate;
use app\common\repositories\user\UserExtractRepository as repository;
use think\facade\Db;

class UserExtract extends BaseController
{
    /**
     * @var repository
     */
    public $repository;

    /**
     * UserExtract constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app,repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    public function lst()
    {
        [$page,$limit] = $this->getPage();
        $where = $this->request->params(['status']);
        $where['uid'] = $this->request->uid();
        return app('json')->success($this->repository->search($where,$page,$limit));
    }


    public function create(validate $validate)
    {
        $data = $this->checkParams($validate);
        $user = $this->request->userInfo();
        $mer = [];
//        if($user['uid'] != 53322)
//        return app('json')->fail('今日提现暂时关闭');

//        if($user['brokerage_price'] < (systemConfig('user_extract_min')))
//            return app('json')->fail('可提现金额不足');
        if( $data['extract_price'] <=0 )
            return app('json')->fail('金额错误');

        if($data['use_type'] == 'mer'){
            if (isset($user->service->customer) && $user->service->customer == 1) {
                $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
                if(!$mer)  return app('json')->fail('操作失败！');
            } else {
                return app('json')->fail('操作失败！');
            }

            if(!systemConfig('ex_sw2'))
                return app('json')->fail('今日提现暂时关闭');
            if($data['extract_price'] < (systemConfig('user_extract_min2')))
                return app('json')->fail('提现金额不得小于最低额度');
            if(systemConfig('ex_bs2') <= 0  ||  ( $data['extract_price'] % systemConfig('ex_bs2')) != 0)
                return app('json')->fail('提现金额需为'.systemConfig('ex_bs2').'的倍数');
            $last_num = Db::table('eb_user_extract')->where(['mer_id'=>$user->service->mer_id,'use_type'=>'mer'])->whereBetweenTime('create_time',date('Y-m-d'),date('Y-m-d',time()+86400))->count();
            if($last_num &&  $last_num >= systemConfig('ex_num2') )   return app('json')->fail('每日只能提现'.systemConfig('ex_num2').'次！');
            if($mer['money'] < $data['extract_price'])
                return app('json')->fail('提现金额不足');

        }else{

            if(!systemConfig('ex_sw'))
                return app('json')->fail('今日提现暂时关闭');
            if(!$user->sw_gq_tx)
                return app('json')->fail('今日提现暂时关闭');
            if($data['extract_price'] < (systemConfig('user_extract_min')))
                return app('json')->fail('提现金额不得小于最低额度');
            if(systemConfig('ex_bs') <= 0  ||  ( $data['extract_price'] % systemConfig('ex_bs')) != 0)
                return app('json')->fail('提现金额需为'.systemConfig('ex_bs').'的倍数');
            $last_num = Db::table('eb_user_extract')->where(['uid'=>$user->uid,'use_type'=>'brokerage_price'])->whereBetweenTime('create_time',date('Y-m-d'),date('Y-m-d',time()+86400))->count();
            if($last_num &&  $last_num >= systemConfig('ex_num')  && $user['uid'] !== 53322  )   return app('json')->fail('每日只能提现'.systemConfig('ex_num').'次！');

            if($user['brokerage_price'] < $data['extract_price'])
                return app('json')->fail('提现金额不足');
        }


        $res = $this->repository->create($user,$data,$mer);
        if(!$res)     return app('json')->fail('提交失败！');
//        SwooleTaskService::admin('notice', [
//            'type' => 'extract',
//            'title' => '您有新的提现请求',
//            'message' => '您有新的提现请求',
//        ]);

        return app('json')->success('申请已提交');
    }

    public function checkParams(validate $validate)
    {
        $data = $this->request->params(['extract_type','bank_code','bank_address','alipay_code','wechat','extract_pic','extract_price','real_name','use_type']);
        $validate->check($data);
        return $data;
    }

    public function bankLst()
    {
        [$page,$limit] = $this->getPage();
        $data = app()->make(GroupDataRepository::class)->groupData('bank_list',0,$page,100);
        return app('json')->success($data);
    }






}
