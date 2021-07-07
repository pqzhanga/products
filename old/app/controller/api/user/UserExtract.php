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
//        if($user['brokerage_price'] < (systemConfig('user_extract_min')))
//            return app('json')->fail('可提现金额不足');
        if($data['extract_price'] < (systemConfig('user_extract_min')))
            return app('json')->fail('提现金额不得小于最低额度');
        if($data['use_type'] == 'mer'){
            if (isset($user->service->customer) && $user->service->customer == 1) {
                $mer = Db::table('eb_merchant')->where(['mer_id'=>$user->service->mer_id])->find();
                if(!$mer)  return app('json')->fail('操作失败！');
            } else {
                return app('json')->fail('操作失败！');
            }
            if($mer['money'] < $data['extract_price'])
                return app('json')->fail('提现金额不足');
        }else{
            if($user['brokerage_price'] < $data['extract_price'])
                return app('json')->fail('提现金额不足');
        }
        $last = Db::table('eb_user_extract')->where(['uid'=>$user['uid']])->where('status','<>','-1')->order('extract_id desc')->find();
        if($last && strtotime($last['create_time']) > time() - 86400*3)   return app('json')->fail('三天内只能提现一次！');

        $this->repository->create($user,$data,$mer);

        SwooleTaskService::admin('notice', [
            'type' => 'extract',
            'title' => '您有新的提现请求',
            'message' => '您有新的提现请求',
        ]);


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
