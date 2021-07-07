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

namespace app\controller\api\store\merchant;

use app\common\repositories\system\merchant\MerchantCategoryRepository;
use app\validate\api\MerchantIntentionValidate;
use crmeb\services\SwooleTaskService;
use crmeb\services\YunxinSmsService;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantIntentionRepository as repository;
use think\exception\ValidateException;
use think\facade\Queue;

class MerchantIntention extends BaseController
{
    protected $repository;
    protected $userInfo;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    public function create()
    {
        $data = $this->checkParams();
        if ($this->userInfo) $data['uid'] = $this->userInfo->uid;
        $this->repository->create($data);

        SwooleTaskService::admin('notice', [
            'type' => 'intention',
            'title' => '您有新的商户入驻申请',
            'message' => '您有新的商户入驻申请',
        ]);

        return app('json')->success('提交成功');
    }

    public function update($id)
    {
        if (!$this->repository->getWhere(['mer_intention_id' => (int)$id, 'uid' => $this->userInfo->uid, 'is_del' => 0]))
            return app('json')->fail('数据不存在');
        $data = $this->checkParams();
        $this->repository->updateIntention((int)$id, $data);
        return app('json')->success('修改成功');
    }

    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $data = $this->repository->getList(['uid' => $this->userInfo->uid], $page, $limit);
        return app('json')->success($data);
    }

    function detail($id)
    {
        $data = $this->repository->detail((int)$id, $this->userInfo->uid);
        if (!$data) {
            return app('json')->fail('数据不存在');
        }
        if ($data->status == 1) {
            $data['login_url'] = rtrim(systemConfig('site_url'), '/') . '/' . config('admin.merchant_prefix');
        }
        return app('json')->success($data);
    }

    protected function checkParams()
    {
        $data = $this->request->params(['province','city','city_id','district','phone', 'mer_name', 'name', 'code', 'images', 'merchant_category_id','mertype','protype','district_id','province_id']);
        app()->make(MerchantIntentionValidate::class)->check($data);
        $check = (YunxinSmsService::create())->checkSmsCode($data['phone'], $data['code'], 'intention');
        if (!$check) throw new ValidateException('验证码不正确');
        $categ = app()->make(MerchantCategoryRepository::class)->get($data['merchant_category_id']);
        if (!$categ) throw new ValidateException('商户分类不存在');
        unset($data['code']);
        return $data;
    }

    /**
     * 商户分类
     * @Author:Qinii
     * @Date: 2020/9/15
     * @return mixed
     */
    public function cateLst()
    {
        $lst = app()->make(MerchantCategoryRepository::class)->getSelect();
        return app('json')->success($lst);
    }
}

