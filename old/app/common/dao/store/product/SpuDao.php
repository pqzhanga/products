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
namespace app\common\dao\store\product;

use app\common\dao\BaseDao;
use app\common\model\store\product\Spu;
use app\common\model\store\StoreCategory;
use crmeb\services\VicWordService;

class SpuDao extends  BaseDao
{
    public function getModel(): string
    {
        return Spu::class;
    }

    public function search($where)
    {
        $order = 'P.sort DESC';
        if(isset($where['order'])){
            if(in_array($where['order'], ['is_new', 'price_asc', 'price_desc', 'rate', 'sales'])){
                if ($where['order'] == 'price_asc') {
                    $order = 'S.price ASC';
                } else if ($where['order'] == 'price_desc') {
                    $order = 'S.price DESC';
                } else {
                    $order = 'P.'.$where['order'] . ' DESC';
                }
            }else{
               $order = 'S.'.$where['order'].' DESC';
            }
        }
        $query = Spu::getDB()->alias('S')->join('StoreProduct P','S.product_id = P.product_id');
        $query->when(isset($where['is_del']) && $where['is_del'] !== '',function($query)use($where){
                $query->where('P.is_del',$where['is_del']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '',function($query)use($where){
                $query->where('P.mer_id',$where['mer_id']);
            })
            ->when(isset($where['keyword']) && $where['keyword'] !== '',function($query)use($where){
                if (is_numeric($where['keyword'])) {
                    $query->whereLike("S.store_name|S.keyword|S.bar_code|S.product_id", "%{$where['keyword']}%");
                } else {
                    $word = app()->make(VicWordService::class)->getWord($where['keyword']);
                    $query->where(function ($query) use ($word) {
                        foreach ($word as $item) {
                            $query->whereOr('S.store_name|S.keyword', 'LIKE', "%$item%");
                        }
                    });
                }
                //$query->whereLike('S.keyword|S.store_name',"%{$where['keyword']}%");
            })
            ->when(isset($where['cate_id']) && $where['cate_id'] !== '',function($query)use($where){
                $query->where('P.cate_id',$where['cate_id']);
            })
            ->when(isset($where['price_on']) && $where['price_on'] !== '',function($query)use($where){
                $query->where('P.price','>=',$where['price_on']);
            })
            ->when(isset($where['price_off']) && $where['price_off'] !== '',function($query)use($where){
                $query->where('P.price','<=',$where['price_off']);
            })
            ->when(isset($where['brand_id']) && $where['brand_id'] !== '',function($query)use($where){
                $query->where('P.brand_id',$where['brand_id']);
            })
            ->when(isset($where['is_gift_bag']) && $where['is_gift_bag'] !== '',function($query)use($where){
                $query->where('P.is_gift_bag',$where['is_gift_bag']);
            })
            ->when(isset($where['product_type']) && $where['product_type'] !== '',function($query)use($where){
                $query->where('S.product_type',$where['product_type']);
            })
            ->when(isset($where['action']) && $where['action'] !== '',function($query)use($where){
                $query->where('S.product_type','>',0);
            })
            ->when(isset($where['mer_cate_id']) && $where['mer_cate_id'] !== '',function($query)use($where){
                $ids = (StoreCategory::where('path','like','%/'.$where['mer_cate_id'].'/%'))->column('store_category_id');
                $ids[] = intval($where['mer_cate_id']);
                $query->join('StoreProductCate C','S.product_id = C.product_id')->where('mer_cate_id','in',$ids);
            })
            ->when(isset($where['mer_status']) && $where['mer_status'] !== '',function($query)use($where){
                $query->where('mer_status',$where['mer_status']);
            })
            ->when(isset($where['spu_status']) && $where['spu_status'] !== '',function($query)use($where){
                $query->where('S.status',$where['spu_status']);
            })
            ->when(isset($where['pid']) && $where['pid'] !== '', function ($query) use ($where) {
                $query->join('StoreCategory CT','P.cate_id = CT.store_category_id')->where('CT.pid',$where['pid']);
            })
            ->when(isset($where['hot_type']) && $where['hot_type'] !== '', function ($query) use ($where) {
                if ($where['hot_type'] == 'new') $query->where('P.is_new', 1);
                else if ($where['hot_type'] == 'hot') $query->where('P.is_hot', 1);
                else if ($where['hot_type'] == 'best') $query->where('P.is_best', 1);
                else if ($where['hot_type'] == 'good') $query->where('P.is_benefit', 1);
            });

        return $query->order($order);
    }

    public function findOrCreateAll(array $where)
    {
        foreach ($where as $item) {
            $item['activity_id'] = $item['activity_id'] ?? 0;
            $data = $this->getModel()::getDB()->where('product_id', $item['product_id'])
                ->where('product_type', $item['product_type'])
                ->where('activity_id', $item['activity_id'])
                ->find();
            if (!$data) $this->create($item);
        }
    }

    public function delProduct($id, $isDel = 1)
    {
        $this->getModel()::getDb()->where('product_id', $id)->update(['is_del' => $isDel]);
    }


    public function getActivecategory($type)
    {
        $query = Spu::getDB()->alias('S')->join('StoreProduct P','S.product_id = P.product_id')
            ->join('StoreCategory C','C.store_category_id = P.cate_id');

        $query->where('S.status',1)->where('S.product_type',$type);
        return $query->group('S.product_id')->column('C.path');
    }
}
