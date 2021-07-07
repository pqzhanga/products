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


namespace app\controller\api\store\product;

use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\system\groupData\GroupDataRepository;
use app\common\repositories\user\UserMerchantRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductRepository as repository;

class StoreProduct extends BaseController
{
    /**
     * @var repository
     */
    protected $repository;
    protected $userInfo;

    /**
     * StoreProduct constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/28
     * @return mixede
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'cate_id', 'order', 'price_on', 'price_off', 'brand_id', 'pid']);
        $data = $this->repository->getApiSearch(null, $where, $page, $limit, $this->userInfo);
        //$data = app()->make(SpuRepositor::class)->getApiSearch($where,$page, $limit, $this->userInfo);
        return app('json')->success($data);
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param $id
     * @return mixed
     */
    public function detail($id)
    {
        $data = $this->repository->detail($id, $this->userInfo);
        if (!$data) return app('json')->fail('商品已下架');

        if ($this->request->isLogin()) {
            app()->make(UserMerchantRepository::class)->updateLastTime($this->request->uid(), $data->mer_id);
        }

        return app('json')->success($data);
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/30
     * @return mixed
     */
    public function recommendList()
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->recommend($this->userInfo, null, $page, $limit));
    }

    public function qrcode($id)
    {
        $param = $this->request->params(['type',['product_type',0]]);
        if (!$product = $this->repository->existsProduct($id, $param['product_type']))
            return app('json')->fail('商品不存在');
        if ($param['type'] == 'routine'){
            $url = $this->repository->routineQrCode($id,$param['product_type'],$this->request->userInfo());
        }else{
            $url = $this->repository->wxQrCode($id,$param['product_type'],$this->request->userInfo());
        }
        if (!$url) return app('json')->fail('二维码生成失败');
        return app('json')->success(compact('url'));
    }

    public function getBagList()
    {
        if(!systemConfig('extension_status'))  return app('json')->fail('活动未开启');
        [$page, $limit] = $this->getPage();
        $where = $this->repository->bagShow();
        return app('json')->success($this->repository->getBagList($where, $page, $limit));
    }

    public function getBagrecomm()
    {
        $where = $this->repository->bagShow();
        $where['is_best'] = 1;
        return app('json')->success($this->repository->selectWhere($where)->append(['merchant']));
    }

    public function getBagExplain()
    {
        if(!systemConfig('extension_status'))  return app('json')->fail('活动未开启');
        $data = [
            'explain' => systemConfig('promoter_explain'),
            'data' => app()->make(GroupDataRepository::class)->groupData('promoter_config', 0),
        ];
        return app('json')->success($data);
    }

    public function hot($type)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getApiSearch(null, ['hot_type' => $type, 'is_gift_bag' => 0, 'is_used' => 1], $page, $limit, $this->userInfo));
    }

}
