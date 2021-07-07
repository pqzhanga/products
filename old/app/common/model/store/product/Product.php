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

use app\common\dao\store\StoreSeckillActiveDao;
use app\common\model\BaseModel;
use app\common\model\store\coupon\StoreCouponProduct;
use app\common\model\store\shipping\ShippingTemplate;
use app\common\model\store\StoreBrand;
use app\common\model\store\StoreCategory;
use app\common\model\store\StoreSeckillActive;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\store\StoreCategoryRepository;
use crmeb\jobs\ChangeSpuStatusJob;
use crmeb\services\VicWordService;
use Lizhichao\Word\VicWord;
use think\db\BaseQuery;
use think\facade\Db;
use think\model\concern\SoftDelete;

class Product extends BaseModel
{
    use SoftDelete;

    protected $deleteTime = 'is_del';
    protected $defaultSoftDelete = 0;
    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    public static function tablePk(): string
    {
        return 'product_id';
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    public static function tableName(): string
    {
        return 'store_product';
    }

    /*
     * -----------------------------------------------------------------------------------------------------------------
     * 属性
     * -----------------------------------------------------------------------------------------------------------------
    */
    public function getSliderImageAttr($value)
    {
        return $value ? explode(',',$value) : [];
    }
    public function getGiveCouponIdsAttr($value)
    {
        return $value ? explode(',',$value) : [];
    }
    public function getMaxExtensionAttr($value)
    {
        if($this->extension_type){
            return  ($this->attrValue()->order('extension_two DESC')->value('extension_one'));
        } else {
            return  bcmul(($this->attrValue()->order('price DESC')->value('price')) , systemConfig('extension_one_rate'),2);
        }
    }
    public function getMinExtensionAttr($value)
    {
        if($this->extension_type){
            return  ($this->attrValue()->order('extension_two ASC')->value('extension_two'));
        } else {
            return  bcmul(($this->attrValue()->order('price ASC')->value('price')) , systemConfig('extension_one_rate'),2);
        }
    }
    public function check()
    {
        if(!$this || !$this->is_show || !$this->is_used || !$this->status || $this->is_del || !$this->mer_status) return false;
        return true;
    }

    /**
     * TODO 秒杀商品结束时间
     * @return false|int
     * @author Qinii
     * @day 2020-08-15
     */
    public function getEndTimeAttr()
    {
        if($this->product_type !== 1) return true;
        $day = date('Y-m-d',time());
        $_day = strtotime($day);
        $end_day = strtotime($this->seckillActive['end_day']);
        if($end_day >= $_day)
            return strtotime($day.$this->seckillActive['end_time'].':00:00');
        if($end_day < strtotime($day))
            return strtotime(date('Y-m-d',$end_day).$this->seckillActive['end_time'].':00:00');
    }

    /**
     * TODO 秒杀商品状态
     * @return array|int
     * @author Qinii
     * @day 2020-08-19
     */
    public function getSeckillStatusAttr()
    {
        if($this->product_type !== 1) return true;
        $day = strtotime(date('Y-m-d',time()));
        $_h = date('H',time());
        $start_day = strtotime($this->seckillActive['start_day']);
        $end_day = strtotime($this->seckillActive['end_day']);
        if(!$this->seckillActive) return '';
        if($this->seckillActive['status'] !== -1){
            //还未开始
            if($start_day > time() || $this->is_show !== 1)return 0;
            //已结束
            if($end_day < $day) return -1;
            //开始 - 结束
            if($start_day <= $day && $day <= $end_day){
                //未开始
                if($this->seckillActive['start_time'] > $_h) return 0;
                //已结束
                if($this->seckillActive['end_time'] <= $_h) return -1;
                //进行中
                if($this->seckillActive['start_time'] <= $_h && $this->seckillActive['end_time'] > $_h) return 1;
            }
        }
        //已结束
        return -1;

    }

    public function getImageAttr($value)
    {
        if (is_int(strpos($value, 'http'))){
            return $value;
        }else{
            return systemConfig('site_url') .$value;
        }
    }

    public function getTopReplyAttr()
    {
        $res = ProductReply::where('product_id',$this->product_id)->where('is_del',0)->with(['orderProduct'])->field('reply_id,uid,nickname,merchant_reply_content,avatar,order_product_id,product_id,product_score,service_score,postage_score,comment,pics,rate,create_time')
            ->order('create_time DESC')->limit(1)->find();
        if(!$res) return null;
        if ($res['orderProduct'])
            $res['sku'] = $res['orderProduct']['cart_info']['productAttr']['sku'];
        unset($res['orderProduct']);
        return $res;
    }

    /*
     * -----------------------------------------------------------------------------------------------------------------
     *  关联模型
     * -----------------------------------------------------------------------------------------------------------------
    */
    public function merCateId()
    {
        return $this->hasMany(ProductCate::class,'product_id','product_id')->field('product_id,mer_cate_id');
    }
    public function attr()
    {
        return $this->hasMany(ProductAttr::class,'product_id','product_id');
    }
    public function attrValue()
    {
        return $this->hasMany(ProductAttrValue::class,'product_id','product_id');
    }
    public function oldAttrValue()
    {
        return $this->hasMany(ProductAttrValue::class,'product_id','old_product_id');
    }
    public function content()
    {
        return $this->hasOne(ProductContent::class,'product_id','product_id');
    }
    protected function temp()
    {
        return $this->hasOne(ShippingTemplate::class,'shipping_template_id','temp_id');
    }
    public function storeCategory()
    {
        return $this->hasOne(StoreCategory::class,'store_category_id','cate_id')->field('store_category_id,cate_name');
    }
    public function merchant()
    {
        return $this->hasOne(Merchant::class,'mer_id','mer_id')->field('is_trader,mer_id,mer_name,mer_avatar,product_score,service_score,postage_score,service_phone,care_count');
    }
    public function reply()
    {
        return $this->hasMany(ProductReply::class,'product_id','product_id')->order('create_time DESC');
    }
    public function brand()
    {
        return $this->hasOne(StoreBrand::class,'brand_id','brand_id')->field('brand_id,brand_name');
    }
    public function seckillActive()
    {
        return $this->hasOne(StoreSeckillActive::class,'product_id','product_id');
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
    public function assist()
    {
        return $this->hasOne(ProductAssist::class,'product_id','product_id');
    }
    public function productGroup()
    {
        return $this->hasOne(ProductGroup::class,'product_id','product_id');
    }

    /*
     * -----------------------------------------------------------------------------------------------------------------
     * 搜索器
     * -----------------------------------------------------------------------------------------------------------------
     */
    public function searchMerCateIdAttr($query, $value)
    {
        $cate_ids = (StoreCategory::where('path','like','%/'.$value.'/%'))->column('store_category_id');
        $cate_ids[] = intval($value);
        $product_id = ProductCate::whereIn('mer_cate_id',$cate_ids)->column('product_id');
        $query->whereIn('product_id',$product_id);
    }
    public function searchKeywordAttr($query, $value)
    {
        if (!$value) return;
        if (is_numeric($value)) {
            $query->whereLike("store_name|keyword|bar_code|product_id", "%{$value}%");
        } else {
            $word = app()->make(VicWordService::class)->getWord($value);
            $query->where(function ($query) use ($word, $value) {
                foreach ($word as $item) {
                    $query->whereOr('store_name|keyword', 'LIKE', "%$item%");
                }
                $query->order(Db::raw('REPLACE(store_name,\'' . $value . '\',\'\')'));
            });
        }
    }
    public function searchStatusAttr($query, $value)
    {
        if($value === -1){
            $query->where('Product.status', 'in',[-1,-2]);
        }else {
            $query->where('Product.status',$value);
        }
    }
    public function searchCateIdAttr($query, $value)
    {
        $query->where('cate_id',$value);
    }
    public function searchCateIdsAttr($query, $value)
    {
        $query->whereIn('cate_id',$value);
    }
    public function searchIsShowAttr($query, $value)
    {
        $query->where('is_show',$value);
    }
    public function searchPidAttr($query, $value)
    {
        $cateId = app()->make(StoreCategoryRepository::class)->allChildren(intval($value));
        $query->whereIn('cate_id', $cateId);
    }
    public function searchStockAttr($query, $value)
    {
        $value ? $query->where('stock','<=', $value) :  $query->where('stock', $value);
    }
    public function searchIsNewAttr($query, $value)
    {
        $query->where('is_new',$value);
    }
    public function searchPriceAttr($query, $value)
    {
        if(empty($value[0]) && !empty($value[1]))
            $query->where('price','<',$value[1]);
        if(!empty($value[0]) && empty($value[1]))
            $query->where('price','>',$value[0]);
        if(!empty($value[0]) && !empty($value[1]))
            $query->whereBetween('price',[$value[0],$value[1]]);
    }
    public function searchBrandIdAttr($query, $value)
    {
        $query->whereIn('brand_id',$value);
    }
    public function searchIsGiftBagAttr($query, $value)
    {
        $query->where('is_gift_bag',$value);
    }
    public function searchIsGoodAttr($query, $value)
    {
        $query->where('is_good',$value);
    }
    public function searchIsUsedAttr($query, $value)
    {
        $query->where('is_used',$value);
    }
    public function searchProductTypeAttr($query, $value)
    {
        $query->where('product_type',$value);
    }
    public function searchSeckillStatusAttr($query, $value)
    {
        $product_id = (new StoreSeckillActiveDao())->getStatus($value)->column('product_id');
        $query->whereIn('product_id',$product_id);
    }
    public function searchStoreNameAttr($query, $value)
    {
        $query->where('store_name','like','%'.$value.'%');
    }
    public function searchMerStatusAttr($query, $value)
    {
        $query->where('mer_status',$value);
    }
    public function searchProductIdAttr($query, $value)
    {
        $query->where('product_id',$value);
    }
    public function searchPriceOnAttr($query, $value)
    {
        $query->where('price','>=',$value);
    }
    public function searchPriceOffAttr($query, $value)
    {
        $query->where('price','<=',$value);
    }
}
