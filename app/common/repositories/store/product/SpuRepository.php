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
namespace app\common\repositories\store\product;

use app\common\repositories\BaseRepository;
use app\common\dao\store\product\SpuDao;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use app\common\repositories\user\UserVisitRepository;

class SpuRepository extends BaseRepository
{
    public $dao;
    public $merchantFiled = 'mer_id,mer_name,mer_avatar,is_trader,mer_info,mer_keyword';
    public $productFiled  = 'S.product_id,S.store_name,S.image,activity_id,S.keyword,S.price,S.mer_id,spu_id,S.status,store_info,brand_id,cate_id,unit_name,S.rank,P.sort,sales,S.product_type,rate,reply_count,extension_type,gongxian,duihuan,cate_parid';
    public function __construct(SpuDao $dao)
    {
        $this->dao = $dao;
    }

    public function create(array $param,int $productId,int $activityId,$productType = 0)
    {
        $data = $this->setparam($param,$productId,$activityId,$productType);
        return $this->dao->create($data);
    }

    public function update(array $param,int $productId,int $activityId,$productType = 0)
    {
        switch ($productType){
            case 1:
                $make = app()->make(StoreSeckillActiveRepository::class);
                $activityId = $make ->getSearch(['product_id' => $productId])->value('seckill_active_id');
                break;
        }
        $where = [
            'product_id' => $productId,
            'activity_id' => $activityId,
            'product_type' => $productType,
        ];
        $ret = $this->dao->getSearch($where)->find();
        if(!$ret) {
            return $this->create($param, $productId, $activityId, $productType);
        }else{
            $data = $this->setparam($param,$productId,$activityId,$productType);
            return $this->dao->update($ret->spu_id,$data);
        }
    }

    public function setparam(array $param,$productId,$activityId,$productType)
    {
        $data = [
            'product_id' => $productId,
            'product_type' => $productType ?? 0,
            'activity_id' => $activityId,
            'store_name' => $param['store_name'],
            'keyword' => $param['keyword'] ?? '',
            'image' => $param['image'],
            'price' => $param['price'],
            'status' => 0,
            'rank'  => $param['rank'] ?? 0,
            'temp_id' => $param['temp_id'],
            'sort' => $param['sort'] ?? 0,
        ];
        if(isset($param['mer_id'])) $data['mer_id'] = $param['mer_id'];
        return $data;
    }

    /**
     * TODO 修改排序
     * @param $productId
     * @param $activityId
     * @param $productType
     * @param $data
     * @author Qinii
     * @day 1/19/21
     */
    public function updateSort($productId,$activityId,$productType,$data)
    {
        $where = [
            'product_id' => $productId,
            'activity_id' => $activityId,
            'product_type' => $productType,
        ];
        $ret = $this->dao->getSearch($where)->find();
        if($ret) $this->dao->update($ret['spu_id'],$data);
    }
    /**
     * TODO 移动端列表
     * @param $where
     * @param $page
     * @param $limit
     * @param $userInfo
     * @return array
     * @author Qinii
     * @day 12/18/20
     */
    public function getApiSearch($where, $page, $limit, $userInfo)
    {
        if ($userInfo && isset($where['keyword']) && !empty($where['keyword']))
            app()->make(UserVisitRepository::class)->searchProduct($userInfo['uid'], $where['keyword']);

        $where['spu_status'] = 1;
        $where['mer_status'] = 1;
        $query = $this->dao->search($where);

        $query->with([
            'merchant' => function($query){
                $query->field($this->merchantFiled);
            },
            'issetCoupon',
        ]);
        $count = $query->count();
        $list = $query->page($page,$limit)->setOption('field', [])->field($this->productFiled)->select();
        $append[] = 'stop_time';
        if(app()->make(ProductRepository::class)->getUserIsPromoter($userInfo))
            $append[] = 'max_extension';
        $list->append($append);
        return compact('count','list');
    }


    /**
     * TODO 修改状态
     * @param array $data
     * @author Qinii
     * @day 12/18/20
     */
    public function changeStatus(int $id,int $productType)
    {
        $make = app()->make(ProductRepository::class);
        $where = [];
        $status = 1;
        try {
            switch ($productType) {
                case 0:
                    $where = [
                        'activity_id' => 0,
                        'product_id' => $id,
                        'product_type' => $productType,
                    ];
                    break;
                case 1:
                    $_make = app()->make(StoreSeckillActiveRepository::class);
                    $res = $_make->getSearch(['product_id' => $id])->find();
                    $endday = strtotime($res['end_day']);
                    if ($res['status'] == -1 || $endday < time()) $status = 0;
                    $where = [
                        'activity_id' => $res['seckill_active_id'],
                        'product_id' => $id,
                        'product_type' => $productType,
                    ];
                    break;
                case 2:
                    $_make = app()->make(ProductPresellRepository::class);
                    $res = $_make->getWhere([$_make->getPk() => $id]);
                    if ($res['status'] !== 1 || $res['action_status'] !== 1 || $res['product_status'] !== 1 || $res['is_del'] !== 0 || $res['is_show'] !== 1)
                        $status = 0;
                    $where = [
                        'activity_id' => 20,
                        'product_id' => $res['product_id'],
                        'product_type' => $productType,
                    ];
                    break;
                case 3:
                    $_make = app()->make(ProductAssistRepository::class);
                    $res = $_make->getWhere([$_make->getPk() => $id]);
                    if ($res['status'] !== 1 || $res['is_show'] !== 1 || $res['action_status'] !== 1 || $res['product_status'] !== 1 || $res['is_del'] !== 0)
                        $status = 0;
                    $where = [
                        'activity_id' => $id,
                        'product_id' => $res['product_id'],
                        'product_type' => $productType,
                    ];
                    break;
                case 4:
                    $_make = app()->make(ProductGroupRepository::class);
                    $res = $_make->getWhere([$_make->getPk() => $id]);
                    if ($res['status'] !== 1 || $res['is_show'] !== 1 || $res['action_status'] !== 1 || $res['product_status'] !== 1 || $res['is_del'] !== 0)
                        $status = 0;
                    $where = [
                        'activity_id' => $id,
                        'product_id' => $res['product_id'],
                        'product_type' => $productType,
                    ];
                    break;
                default:
                    break;
            }
            $ret = $make->getSearch(['product_id' => $where['product_id']])->find();
            if (!$ret || $ret['status'] !== 1 || $ret['mer_status'] !== 1 || $ret['is_del']) $status = 0;
            if (in_array($productType, [0, 1]) && ($ret['is_show'] !== 1 || $ret['is_used'] !== 1)) $status = 0;
            $result = $this->dao->getSearch($where)->find();
            if (!$result) $result = $this->create($ret->toArray(), $where['product_id'], $where['activity_id'], $productType);
            $this->dao->update($result['spu_id'], ['status' => $status]);
        }catch (\Exception $exception){
            halt($exception->getMessage());
        }
    }

    /**
     * TODO 平台编辑商品同步修改
     * @param int $id
     * @param int $productId
     * @param int $productType
     * @param array $data
     * @author Qinii
     * @day 12/18/20
     */
    public function changRank(int $id,int $productId,int $productType,array  $data)
    {
        $where = [
            'product_id' => $productId,
            'product_type' => $productType,
            'activity_id' => $id,
        ];
        $res = $this->dao->getWhere($where);
        if($res){
            $res->store_name = $data['store_name'];
            $res->rank = $data['rank'];
            $res->star = $data['star'] ?? 1;
            $res->save();
        }
    }

    /**
     * TODO 同步各类商品到spu表
     * @param array|null $productType
     * @author Qinii
     * @day 12/25/20
     */
    public function updateSpu(?array $productType)
    {
        if(!$productType) $productType = [0,1,2,3,4];
        $_product_make = app()->make(ProductRepository::class);
        $data = [];
        foreach ($productType as $value){
           $ret = $_product_make->activitSearch($value);
           $data = array_merge($data,$ret);
        }
        $this->dao->findOrCreateAll($data);
    }

    /**
     * TODO 获取活动商品的一级分类
     * @param $type
     * @return mixed
     * @author Qinii +0
     * @day 1/12/21
     */
    public function getActiveCaategory($type)
    {
        $pathArr = $this->dao->getActivecategory($type);
        $path = [];
        foreach ($pathArr as $item){
            $path[] = explode('/',$item)[1];
        }
        $path = array_unique($path);
        $cat = app()->make(StoreCategoryRepository::class)->getSearch(['ids' => $path])->field('store_category_id,cate_name')->select();
        return $cat;
    }
}
