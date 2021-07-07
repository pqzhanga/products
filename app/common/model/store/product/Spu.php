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


namespace app\common\model\store\product;


use app\common\model\BaseModel;
use app\common\model\store\coupon\StoreCouponProduct;
use app\common\model\store\StoreSeckillActive;
use app\common\model\system\merchant\Merchant;
use think\db\BaseQuery;

class Spu extends BaseModel
{

    /**
     * TODO
     * @return string
     * @author Qinii
     * @day 12/18/20
     */
    public static function tablePk(): string
    {
        return 'spu_id';
    }

    /**
     * TODO
     * @return string
     * @author Qinii
     * @day 12/18/20
     */
    public static function tableName(): string
    {
        return 'store_spu';
    }


    /*
     * -----------------------------------------------------------------------------------------------------------------
     * 属性
     * -----------------------------------------------------------------------------------------------------------------
     */
    public function getMinExtensionAttr($value)
    {
        if($this->product->extension_type){
            return  ($this->product->attrValue()->order('extension_two ASC')->value('extension_two'));
        } else {
            return  bcmul(($this->product->attrValue()->order('price ASC')->value('price')) , systemConfig('extension_one_rate'),2);
        }
    }

    public function getMaxExtensionAttr($value)
    {
        if($this->product->extension_type){
            return  ($this->product->attrValue()->order('extension_two DESC')->value('extension_one'));
        } else {
            return  bcmul(($this->product->attrValue()->order('price DESC')->value('price')) , systemConfig('extension_one_rate'),2);
        }
    }

    public function getStopTimeAttr()
    {
        if($this->product_type == 1){
            $day = date('Y-m-d',time());
            $_day = strtotime($day);
            $end_day = strtotime($this->seckillActive['end_day']);
            if($end_day >= $_day)
                return strtotime($day.$this->seckillActive['end_time'].':00:00');
            if($end_day < strtotime($day))
                return strtotime(date('Y-m-d',$end_day).$this->seckillActive['end_time'].':00:00');
        }
    }

    /*
     * -----------------------------------------------------------------------------------------------------------------
     * 关联表
     * -----------------------------------------------------------------------------------------------------------------
     */
    public function product()
    {
        return $this->hasOne(Product::class,'product_id','product_id');
    }
    public function merchant()
    {
        return $this->hasOne(Merchant::class,'mer_id','mer_id');
    }
    public function issetCoupon()
    {
        return $this->hasOne(StoreCouponProduct::class, 'product_id', 'product_id')->alias('A')
            ->rightJoin('StoreCoupon B', 'A.coupon_id = B.coupon_id')->where(function (BaseQuery $query) {
                $query->where('B.is_limited', 0)->whereOr(function (BaseQuery $query) {
                    $query->where('B.is_limited', 1)->where('B.remain_count', '>', 0);
                });
            })->where(function (BaseQuery $query) {
                $query->where('B.is_timeout', 0)->whereOr(function (BaseQuery $query) {
                    $time = date('Y-m-d H:i:s');
                    $query->where('B.is_timeout', 1)->where('B.start_time', '<', $time)->where('B.end_time', '>', $time);
                });
            })->field('A.product_id,B.*')->where('status', 1)->where('type', 1)->where('send_type', 0)->where('is_del', 0)
            ->order('sort DESC,coupon_id DESC')->hidden(['is_del', 'status']);
    }
    public function merCateId()
    {
        return $this->hasMany(ProductCate::class,'product_id','product_id')->field('product_id,mer_cate_id');
    }
    public function seckillActive()
    {
        return $this->hasOne(StoreSeckillActive::class,'seckill_active_id','activity_id');
    }

    /*
     * -----------------------------------------------------------------------------------------------------------------
     * 搜索器
     * -----------------------------------------------------------------------------------------------------------------
     */
    public function searchMerIdAttr($query,$value)
    {
        $query->where('mer_id',$value);
    }
    public function searchProductIdAttr($query,$value)
    {
        $query->where('product_id',$value);
    }
    public function searchProductTypeAttr($query,$value)
    {
        $query->where('product_type',$value);
    }
    public function searchActivitytIdAttr($query,$value)
    {
        $query->where('activity_id',$value);
    }
    public function searchActivitytIdsAttr($query,$value)
    {
        $query->where('activity_id','in',$value);
    }
    public function searchKeyworkAttr($query,$value)
    {
        $query->whereLike('store_name|keyword',$value);
    }
    public function searchPriceOnAttr($query, $value)
    {
        $query->where('price','>=',$value);
    }
    public function searchPriceOffAttr($query, $value)
    {
        $query->where('price','<=',$value);
    }
    public function searchCateIdAttr($query,$value)
    {
        $query->alias('A')->join('StoreProduct B','A.product_id = B.product_id')->where('B.cate_id',$value)->field('A.*,B.cate_id');
    }
    public function searchMerCateIdAttr($query,$value)
    {
        $query->alias('A')->join('StoreProductCate C','A.product_id = C.product_id')->where('C.mer_cate_id',$value)->field('A.*,C.mer_cate_id');
    }
    public function searchBrandIdAttr($query, $value)
    {
        $query->alias('A')->join('StoreProduct B','A.product_id = B.product_id')->whereIn('B.brand_id',$value)->field('A.*,B.brand_id');
    }
}
