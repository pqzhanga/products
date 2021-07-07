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

use app\common\repositories\user\UserVisitRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\SpuRepository;

class StoreSpu extends BaseController
{
    protected $userInfo;
    protected $repository;

    public function __construct(App $app, SpuRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }

    /**
     * TODO 商品搜索列表
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            'keyword', 'cate_id', 'order', 'price_on', 'price_off', 'brand_id', 'pid','mer_cate_id','product_type','action'
        ]);
        $where['is_gift_bag'] = 0;
        $where['order'] = $where['order'] ? $where['order'] : 'rank';
        $data = $this->repository->getApiSearch($where, $page, $limit, $this->userInfo);
        return app('json')->success($data);
    }

    /**
     * TODO 商户的商品搜索列表
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function merProductLst($id)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            'keyword', 'cate_id','order', 'price_on', 'price_off', 'brand_id', 'pid','mer_cate_id','product_type','action'
        ]);
        $where['mer_id'] = $id;
        $where['is_gift_bag'] = 0;
        $where['order'] = $where['order'] ? $where['order'] : 'sort';
        $data = $this->repository->getApiSearch($where, $page, $limit, $this->userInfo);
        return app('json')->success($data);
    }

    /**
     * TODO 推荐列表
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function recommend()
    {
        [$page, $limit] = $this->getPage();
        $where = ['order' => 'sales'];
        if (!is_null( $this->userInfo)) {
            $cate_ids = app()->make(UserVisitRepository::class)->getRecommend($this->userInfo->uid);
            if ($cate_ids) $where = ['cate_ids' => $cate_ids];
        }
        $where['is_gift_bag'] = 0;
        $where['is_tj'] = 1;
        $data = $this->repository->getApiSearch($where, $page, $limit, $this->userInfo);
        return app('json')->success($data);
    }

    /**
     * TODO 热门列表
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function hot($type)
    {
        [$page, $limit] = $this->getPage();
        $where['hot_type'] = $type;
        $where['is_gift_bag'] = 0;
        $data = $this->repository->getApiSearch($where, $page, $limit,null);
        return app('json')->success($data);
    }

    /**
     * TODO 礼包列表
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function bag()
    {
        [$page, $limit] = $this->getPage();
        $where['is_gift_bag'] = 1;
        $where['order'] = 'rank';
        $data = $this->repository->getApiSearch($where, $page, $limit,null);
        return app('json')->success($data);
    }

    /**
     * TODO 礼包推荐列表
     * @return mixed
     * @author Qinii
     * @day 12/24/20
     */
    public function bagRecommend()
    {
        [$page, $limit] = $this->getPage();
        $where['is_gift_bag'] = 1;
        $where['best'] = 'is_best';
        $data = $this->repository->getApiSearch($where, $page, $limit,null);
        return app('json')->success($data);
    }

    /**
     * TODO 活动分类
     * @param $type
     * @return \think\response\Json
     * @author Qinii
     * @day 1/12/21
     */
    public function activeCategory($type)
    {
        $data = $this->repository->getActiveCaategory($type);
        return app('json')->success($data);
    }
}
