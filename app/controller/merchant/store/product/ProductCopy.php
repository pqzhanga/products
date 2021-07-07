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

namespace app\controller\merchant\store\product;

use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\validate\merchant\StoreProductValidate as validate;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductCopyRepository as repository;

class ProductCopy extends BaseController
{
    /**
     * @var repository
     */
    protected $repository;

    /**
     * ProductCopy constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app ,repository $repository)
    {
        $this->repository = $repository;
        parent::__construct($app);
    }

    /**
     * TODO 列表
     * @return mixed
     * @author Qinii
     * @day 2020-08-14
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where['mer_id'] = $this->request->merId();
        return app('json')->success($this->repository->getList($where,$page, $limit));
    }

    /**
     * TODO
     * @return mixed
     * @author Qinii
     * @day 2020-08-07
     */
    public function count()
    {
        $count = $this->request->merchant()->copy_product_num;
        return app('json')->success(['count' => $count]);
    }



    /**
     * TODO 复制商品
     * @return mixed
     * @author Qinii
     * @day 2020-08-06
     */
    public function get()
    {
        if(!systemConfig('copy_product_status')) return app('json')->fail('复制商品功能未开启');
        $num = app()->make(MerchantRepository::class)->getCopyNum($this->request->merId());
        if($num <= 0) return app('json')->fail('复制商品次数已用完');
        $data = $this->request->params(['type','id','shopid','url']);
        $res = $this->repository->copyProduct($data,$this->request->merId());
        return app('json')->success($res);
    }

    public function save(validate $validate,ProductRepository $productRepository)
    {
        $merchant = $this->request->merchant();
        $data = $this->checkParams($validate);
        $data['mer_id'] = $this->request->merId();
        $productRepository->check($data,$this->request->merId());
        $data['status'] = $this->request->merchant()->is_audit ? 0 : 1;
        $data['mer_status'] = ($merchant['is_del'] || !$merchant['mer_state'] || !$merchant['status']) ? 0 : 1;
        $this->repository->create($data,0);
        return app('json')->success('添加成功');
    }

    public function checkParams(validate $validate)
    {
        $params = [
            "image", "slider_image", "store_name", "store_info", "keyword", "bar_code", "brand_id",
            "cate_id", "mer_cate_id", "unit_name", "sort" , "is_show", "is_good",'is_gift_bag',
            "video_link", "temp_id", "content", "spec_type","extension_type", "attr", "attrValue",['give_coupon_ids',[]]
        ];
        $data = $this->request->params($params);
        $validate->check($data);
        return $data;
    }

}
