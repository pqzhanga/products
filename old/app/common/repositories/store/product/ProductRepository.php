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

use app\common\model\user\User;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use app\common\repositories\store\StoreSeckillTimeRepository;
use app\common\repositories\user\UserRelationRepository;
use app\common\repositories\user\UserVisitRepository;
use crmeb\services\QrcodeService;
use crmeb\services\SwooleTaskService;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductDao as dao;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\store\shipping\ShippingTemplateRepository;
use app\common\repositories\store\StoreBrandRepository;
use think\facade\Route;
use crmeb\jobs\ChangeSpuStatusJob;

/**
 * Class ProductRepository
 * @package app\common\repositories\store\product
 * @author xaboy
 * @mixin dao
 */
class ProductRepository extends BaseRepository
{

    protected $dao;
    protected $filed = 'product_id,mer_id,cate_parid,gongxian,rangli,duihuan,brand_id,unit_name,mer_status,rate,reply_count,store_info,cate_id,image,slider_image,store_name,keyword,sort,rank,is_show,sales,price,extension_type,refusal,cost,ot_price,stock,is_gift_bag,care_count,status,is_used,create_time,product_type,old_product_id';

    /**
     * ProductRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $id
     * @param int $merId
     * @return mixed
     */
    public function CatExists(int $id)
    {
        return (app()->make(StoreCategoryRepository::class))->merExists(0, $id);
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/20
     * @param $ids
     * @param int $merId
     * @return bool
     */
    public function merCatExists($ids, int $merId)
    {
        if (!is_array($ids ?? '')) return true;
        foreach ($ids as $id) {
            if (!(app()->make(StoreCategoryRepository::class))->merExists($merId, $id))
                return false;
        }
        return true;
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $merId
     * @param int $id
     * @return mixed
     */
    public function merShippingExists(int $merId, int $id)
    {
        $make = app()->make(ShippingTemplateRepository::class);
        return $make->merExists($merId, $id);
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $id
     * @return mixed
     */
    public function merBrandExists(int $id)
    {
        $make = app()->make(StoreBrandRepository::class);
        return $make->meExists($id);
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $merId
     * @param int $id
     * @return bool
     */
    public function merExists(?int $merId, int $id)
    {
        return $this->dao->merFieldExists($merId, $this->getPk(), $id);
    }

    public function merDeleteExists(int $merId, int $id)
    {
        return $this->dao->getDeleteExists($merId, $id);
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param int $merId
     * @param int $id
     * @return bool
     */
    public function apiExists(?int $merId, int $id)
    {
        return $this->dao->apiFieldExists($merId, $this->getPk(), $id);
    }

    /**
     * @param int $merId
     * @param int $tempId
     * @return bool
     * @author Qinii
     */
    public function merTempExists(int $merId, int $tempId)
    {
        return $this->dao->merFieldExists($merId, 'temp_id', $tempId);
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param array $data
     */
    public function create(array $data, int $productType = 0)
    {
        return Db::transaction(function () use ($data, $productType) {
            $activity_id = 0;
            $product = $this->setProduct($data);
            $result = $this->dao->create($product);
            $settleParams = $this->setAttrValue($data['attrValue'], $result->product_id, $productType, 0);
            $settleParams['cate'] = $this->setMerCate($data['mer_cate_id'], $result->product_id, $data['mer_id']);
            $settleParams['attr'] = $this->setAttr($data['attr'], $result->product_id);
            $this->save($result->product_id, $settleParams, $data['content']);
            if ($productType == 1) { //秒杀商品
                $dat = $this->setSeckillProduct($data);
                $dat['product_id'] = $result->product_id;
                $seckill = app()->make(StoreSeckillActiveRepository::class)->create($dat);
                $activity_id = $seckill->seckill_active_id;
            }
            if(in_array($productType,[0,1])){
                $product['price'] = $settleParams['data']['price'];
                app()->make(SpuRepository::class)->create($product,$result->product_id,$activity_id,$productType);
            }
            return $result->product_id;
        });
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $id
     * @param array $data
     */
    public function edit(int $id, array $data, int $merId, int $productType)
    {
        Db::transaction(function () use ($id, $data, $merId, $productType) {
            $product = $this->setProduct($data);
            $settleParams = $this->setAttrValue($data['attrValue'], $id, $productType, 1);
            $settleParams['cate'] = $this->setMerCate($data['mer_cate_id'], $id, $merId);
            $settleParams['attr'] = $this->setAttr($data['attr'], $id);
            $this->save($id, $settleParams, $data['content'], $product);
            if ($productType == 1) {//秒杀商品
                $dat = $this->setSeckillProduct($data);
                app()->make(StoreSeckillActiveRepository::class)->updateByProduct($id, $dat);
            }
            $product['price'] = $settleParams['data']['price'];
            $product['mer_id'] = $merId;
            app()->make(SpuRepository::class)->update($product, $id, 0, $productType);
            queue(ChangeSpuStatusJob::class, ['id' => $id, 'product_type' => $productType]);
        });
    }


    /**
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param $id
     */
    public function destory($id)
    {
        (app()->make(ProductAttrRepository::class))->clearAttr($id);
        (app()->make(ProductAttrValueRepository::class))->clearAttr($id);
        (app()->make(ProductContentRepository::class))->clearAttr($id);
        (app()->make(ProductCateRepository::class))->clearAttr($id);
        $this->dao->delete($id, true);
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/20
     * @param $id
     * @param $spec_type
     * @param $settleParams
     * @param $content
     * @return int
     */
    public function save($id, $settleParams, $content, $data = [])
    {
        (app()->make(ProductAttrRepository::class))->clearAttr($id);
        (app()->make(ProductAttrValueRepository::class))->clearAttr($id);
        (app()->make(ProductContentRepository::class))->clearAttr($id);
        (app()->make(ProductCateRepository::class))->clearAttr($id);

        if (isset($settleParams['cate']))
            (app()->make(ProductCateRepository::class)->insert($settleParams['cate']));
        if (isset($settleParams['attr']))
            (app()->make(ProductAttrRepository::class))->insert($settleParams['attr']);
        if (isset($settleParams['attrValue'])) {
            $arr = array_chunk($settleParams['attrValue'], 30);
            foreach ($arr as $item) {
                (app()->make(ProductAttrValueRepository::class))->insert($item);
            }
        }
        $this->dao->createContent($id, ['content' => $content]);
        if(isset($settleParams['data'])){
            $data['price'] = $settleParams['data']['price'];
            $data['ot_price'] = $settleParams['data']['ot_price'];
            $data['cost'] = $settleParams['data']['cost'];
            $data['stock'] = $settleParams['data']['stock'];
        }
        return $this->dao->update($id, $data);
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/18
     * @param int $id
     * @param array $data
     * @return int
     */
    public function adminUpdate(int $id, array $data)
    {
        Db::transaction(function () use ($id, $data) {
            app()->make(ProductContentRepository::class)->clearAttr($id);
            $this->dao->createContent($id, ['content' => $data['content']]);
            unset($data['content']);
            $res = $this->dao->getWhere(['product_id'=>$id],'*',['seckillActive']);
            $activity_id = $res['seckillActive']['seckill_active_id'] ?? 0;
            app()->make(SpuRepository::class)->changRank($activity_id,$id,$res['product_type'],$data);
            return $this->dao->update($id, $data);

        });
    }

    /**
     *  格式化秒杀商品活动时间
     * @Author:Qinii
     * @Date: 2020/9/15
     * @param array $data
     * @return array
     */
    public function setSeckillProduct(array $data)
    {
        $dat = [
            'start_day' => $data['start_day'],
            'end_day' => $data['end_day'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => 1,
            'once_pay_count' => $data['once_pay_count'],
            'all_pay_count' => $data['all_pay_count'],
        ];
        if (isset($data['mer_id'])) $dat['mer_id'] = $data['mer_id'];
        return $dat;
    }

    /**
     *  格式商品主体信息
     * @Author:Qinii
     * @Date: 2020/9/15
     * @param array $data
     * @return array
     */
    public function setProduct(array $data)
    {
        $give_coupon_ids = '';
        if (isset($data['give_coupon_ids']) && !empty($data['give_coupon_ids'])) {
            $gcoupon_ids = array_unique($data['give_coupon_ids']);
            $give_coupon_ids = implode(',', $gcoupon_ids);
        }
        $result = [
            'store_name' => $data['store_name'],
            'image' => $data['image'],
            'slider_image' => implode(',', $data['slider_image']),
            'store_info' => $data['store_info'],
            'keyword' => $data['keyword'],
            'brand_id' => $data['brand_id'],
            'cate_id' => $data['cate_id'],
            'cate_parid' => $data['cate_parid'],
            'gongxian' => isset($data['attrValue'][0]['price'])?bcmul($data['attrValue'][0]['price'], $data['rangli'] , 2):0,
            'duihuan' => $data['duihuan'],
            'rangli' => $data['rangli'],
            'unit_name' => $data['unit_name'],
            'sort' => $data['sort'],
            'is_show' => $data['is_show'] ?? 0,
            'is_good' => $data['is_good'],
            'video_link' => $data['video_link'],
            'temp_id' => $data['temp_id'],
            'extension_type' => $data['extension_type'],
            'spec_type' => $data['spec_type'],
            'status' => $data['status'],
            'give_coupon_ids' => $give_coupon_ids,
            'mer_status' => $data['mer_status'],
        ];
        if (isset($data['is_gift_bag'])) $result['is_gift_bag'] = $data['is_gift_bag'];
        if (isset($data['mer_id'])) $result['mer_id'] = $data['mer_id'];
        if (isset($data['old_product_id'])) $result['old_product_id'] = $data['old_product_id'];
        if (isset($data['product_type'])) $result['product_type'] = $data['product_type'];
        return $result;
    }

    /**
     *  格式商品商户分类
     * @Author:Qinii
     * @Date: 2020/9/15
     * @param array $data
     * @param int $productId
     * @param int $merId
     * @return array
     */
    public function setMerCate(array $data, int $productId, int $merId)
    {
        $result = [];
        foreach ($data as $value) {
            $result[] = [
                'product_id' => $productId,
                'mer_cate_id' => $value,
                'mer_id' => $merId,
            ];
        }
        return $result;
    }

    /**
     *  格式商品规格
     * @Author:Qinii
     * @Date: 2020/9/15
     * @param array $data
     * @param int $productId
     * @return array
     */
    public function setAttr(array $data, int $productId)
    {
        $result = [];
        foreach ($data as $value) {
            $result[] = [
                'type' => 0,
                'product_id' => $productId,
                "attr_name" => $value['value'] ?? $value['attr_name'],
                'attr_values' => implode('-!-', $value['detail']),
            ];
        }
        return $result;
    }

    /**
     *  格式商品SKU
     * @Author:Qinii
     * @Date: 2020/9/15
     * @param array $data
     * @param int $productId
     * @return mixed
     */
    public function setAttrValue(array $data, int $productId, int $productType, int $isUpdate = 0)
    {
        $extension_status = systemConfig('extension_status');
        if ($isUpdate) {
            $product = app()->make(ProductAttrValueRepository::class)->search(['product_id' => $productId])->select()->toArray();
            $oldSku = $this->detailAttrValue($product, null);
        }
        $pric = $stock = $ot_price = $cost = 0;
        foreach ($data as $value) {
            $sku = '';
            if (isset($value['detail']) && is_array($value['detail'])) {
                $sku = implode(',', $value['detail']);
            }
            $unique = $this->setUnique($productId, $sku, $productType);
            $result['attrValue'][] = [

                'detail' => json_encode($value['detail'] ?? ''),
                "bar_code" => $value["bar_code"] ?? '',
                "image" => $value["image"],
                "cost" => $value['cost'] ? (($value['cost'] < 0) ? 0 : $value['cost']) : 0,
                "price" => $value['price'] ? (($value['price'] < 0) ? 0 : $value['price']) : 0,
                "volume" => $value['volume'] ? (($value['volume'] < 0) ? 0 : $value['volume']) : 0,
                "weight" => $value['weight'] ? (($value['weight'] < 0) ? 0 : $value['weight']) : 0,
                "stock" => $value['stock'] ? (($value['stock'] < 0) ? 0 : $value['stock']) : 0,
                "ot_price" => $value['ot_price'] ? (($value['ot_price'] < 0) ? 0 : $value['ot_price']) : 0,
                "extension_one" => $extension_status ? ($value['extension_one'] ?? 0) : 0,
                "extension_two" => $extension_status ? ($value['extension_two'] ?? 0) : 0,
                "product_id" => $productId,
                "type" => 0,
                "sku" => $sku,
                "unique" => $unique,
                'sales' => $isUpdate ? ($oldSku[$sku]['sales'] ?? 0) : 0,
            ];

            if (!$pric) $pric = $value['price'];
            if (!$ot_price) $ot_price = $value['ot_price'];
            if (!$cost) $cost = $value['cost'];
            $ot_price = ($ot_price > $value['ot_price']) ? $value['ot_price'] : $ot_price;
            $pric = ($pric > $value['price']) ? $value['price'] : $pric;
            $cost = ($cost > $value['cost']) ? $value['cost'] : $cost;

            $stock = $stock + intval($value['stock']);
        }
        $result['data'] = ['price' => $pric, 'stock' => $stock, 'ot_price' => $ot_price, 'cost' => $cost];
        return $result;
    }


    /**
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $id
     * @param string $sku
     * @param int $type
     * @return string
     */
    public function setUnique(int $id, $sku, int $type)
    {
        return $unique = substr(md5($sku . $id), 12, 11) . $type;
//        $has = (app()->make(ProductAttrValueRepository::class))->merUniqueExists(null, $unique);
//        return $has ? false : $unique;
    }


    /**
     * TODO 后台管理需要的商品详情
     * @param int $id
     * @param int|null $activeId
     * @return array|\think\Model|null
     * @author Qinii
     * @day 2020-11-24
     */
    public function getAdminOneProduct(int $id,?int $activeId)
    {
        $with = ['attr', 'attrValue','oldAttrValue', 'content', 'merCateId.category', 'storeCategory', 'brand', 'temp', 'seckillActive'];
        $data = $this->dao->getWhere(['product_id' => $id], '*', $with);
        $make_order = app()->make(StoreCouponRepository::class);
        $where = [['coupon_id', 'in', $data['give_coupon_ids']]];
        $data['coupon'] = $make_order->selectWhere($where, 'coupon_id,title')->toArray();
        $data['star'] = app()->make(SpuRepository::class)->getSearch(['activity_id' => $activeId,'product_type' => $data['product_type'],'product_id' => $id])->value('star');
        if($data['product_type'] == 1) $data->append(['seckill_status']);
        if($data['product_type'] == 2) $make = app()->make(ProductPresellSkuRepository::class);
        if($data['product_type'] == 3) $make = app()->make(ProductAssistSkuRepository::class);
        if($data['product_type'] == 4) $make = app()->make(ProductGroupSkuRepository::class);

        $mer_cat = [];
        if (isset($data['merCateId'])) {
            foreach ($data['merCateId'] as $i) {
                $mer_cat[] = $i['mer_cate_id'];
            }
        }
        $data['mer_cate_id'] = $mer_cat;

        foreach ($data['attr'] as $k => $v) {
            $data['attr'][$k] = [
                'value' => $v['attr_name'],
                'detail' => $v['attr_values']
            ];
        }
        $attrValue = (in_array($data['product_type'],[3,4])) ?  $data['oldAttrValue'] : $data['attrValue'];
        unset($data['oldAttrValue'],$data['attrValue']);
        $arr = [];
        if(in_array($data['product_type'],[1,3])) $value_make = app()->make(ProductAttrValueRepository::class);
        foreach ($attrValue as $key => $item) {
            if($data['product_type'] == 1){
                $value = $value_make->getSearch(['sku' => $item['sku'],'product_id' => $data['old_product_id']])->find();
                $old_stock = $value['stock'];
            }
            if($data['product_type'] == 2){
                $item['presellSku'] = $make->getSearch(['product_presell_id' => $activeId,'unique' => $item['unique']])->find();
                if(is_null($item['presellSku'])) continue;
            }
            if($data['product_type'] == 3){
                $item['assistSku'] = $make->getSearch(['product_assist_id' => $activeId,'unique' => $item['unique']])->find();
                if(is_null($item['assistSku']))
                    continue;
            }
            if($data['product_type'] == 4){
                $item['_sku'] = $make->getSearch(['product_group_id' => $activeId,'unique' => $item['unique']])->find();
                if(is_null($item['_sku']))
                    continue;
            }
            $sku = explode(',', $item['sku']);
            $item['old_stock'] = $old_stock ?? $item['stock'];
            foreach ($sku as $k => $v) {
                $item['value' . $k] = $v;
            }
            $arr[] = $item;
        }
        $data['attrValue'] = $arr;
        $content = $data['content']['content'];
        unset($data['content']);
        $data['content'] = $content;
        return $data;
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/18
     * @param $type
     * @param int|null $merId
     * @return array
     */
    public function switchType($type, ?int $merId = 0, $productType = 0)
    {
        $stock = 0;
        if ($merId) $stock = merchantConfig($merId, 'mer_store_stock');
        switch ($type) {
            case 1:
                $where = ['is_show' => 1, 'status' => 1,];
                break;
            case 2:
                $where = ['is_show' => 0, 'status' => 1];
                break;
            case 3:
                $where = ['is_show' => 1, 'stock' => 0, 'status' => 1];
                break;
            case 4:
                $where = ['stock' => $stock ? $stock : 0, 'status' => 1];
                break;
            case 5:
                $where = ['soft' => true];
                break;
            case 6:
                $where = ['status' => 0];
                break;
            case 7:
                $where = ['status' => -1];
                break;
            default:
//                $where = ['is_show' => 1, 'status' => 1];
                break;
        }
        if ($productType == 0) {
            $where['product_type'] = $productType;
            if (!$merId) $where['is_gift_bag'] = 0;
        }
        if ($productType == 1) {
            $where['product_type'] = $productType;
        }
        if ($productType == 10) {
            $where['is_gift_bag'] = 1;
        }
        return $where;
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/18
     * @param int|null $merId
     * @return array
     */
    public function getFilter(?int $merId, $name = '商品', $productType = 0)
    {
        $result = [];
        $result[] = [
            'type' => 1,
            'name' => '出售中' . $name,
            'count' => $this->dao->search($merId, $this->switchType(1, $merId, $productType))->count()
        ];
        $result[] = [
            'type' => 2,
            'name' => '仓库中' . $name,
            'count' => $this->dao->search($merId, $this->switchType(2, $merId, $productType))->count()
        ];
        if ($merId) {
            $result[] = [
                'type' => 3,
                'name' => '已售罄' . $name,
                'count' => $this->dao->search($merId, $this->switchType(3, $merId, $productType))->count()
            ];
            $result[] = [
                'type' => 4,
                'name' => '警戒库存',
                'count' => $this->dao->search($merId, $this->switchType(4, $merId, $productType))->count()
            ];
            $result[] = [
                'type' => 5,
                'name' => '回收站' . $name,
                'count' => $this->dao->search($merId, $this->switchType(5, $merId, $productType))->count()
            ];
        }
        $result[] = [
            'type' => 6,
            'name' => '待审核' . $name,
            'count' => $this->dao->search($merId, $this->switchType(6, $merId, $productType))->count()
        ];
        $result[] = [
            'type' => 7,
            'name' => '审核未通过' . $name,
            'count' => $this->dao->search($merId, $this->switchType(7, $merId, $productType))->count()
        ];
        return $result;
    }

    /**
     * TODO 商户商品列表
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $merId
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getList(?int $merId, array $where, int $page, int $limit)
    {
        $query = $this->dao->search($merId, $where)->with(['merCateId.category', 'storeCategory', 'brand']);
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])->field($this->filed)->select();
        $list->append(['max_extension', 'min_extension']);
        return compact('count', 'list');
    }

    /**
     * TODO 商户秒杀商品列表
     * @param int|null $merId
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2020-08-04
     */
    public function getSeckillList(?int $merId, array $where, int $page, int $limit)
    {
        $make = app()->make(StoreOrderRepository::class);
        $query = $this->dao->search($merId, $where)->with(['merCateId.category', 'storeCategory', 'brand','attrValue ','seckillActive']);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)->setOption('field', [])->field($this->filed)->select()
            ->each(function ($item) use ($make,$where) {
                $result = $this->getSeckillAttrValue($item['attrValue'],$item['old_product_id']);
                $item['stock'] = $result['stock'];
                return $item;
            });
        $list->append(['seckill_status']);
        return compact('count', 'list');
    }

    /**
     * TODO 平台商品列表
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param int $merId
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getAdminList(?int $merId, array $where, int $page, int $limit)
    {
        $query = $this->dao->search($merId, $where)->with(['merCateId.category', 'storeCategory', 'brand', 'merchant']);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)->select();
        $list->append(['max_extension', 'min_extension']);
        return compact('count', 'list');
    }

    /**
     * TODO 平台秒杀商品列表
     * @param int|null $merId
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2020-08-04
     */
    public function getAdminSeckillList(?int $merId, array $where, int $page, int $limit)
    {
        $make = app()->make(StoreOrderRepository::class);
        $query = $this->dao->search($merId, $where)->with(['merCateId.category', 'storeCategory', 'brand', 'merchant', 'seckillActive','attrValue']);
        $count = $query->count();
        $list = $query->page($page, $limit)
            ->select()
            ->each(function ($item) use ($make,$where) {
                $result = $this->getSeckillAttrValue($item['attrValue'],$item['old_product_id']);
                $item['stock'] = $result['stock'];
                $item['sales'] = app()->make(StoreOrderRepository::class)->seckillOrderCounut($item['product_id']);
                return $item;
            });
        $list->append(['seckill_status']);
        return compact('count', 'list');
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/28
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param $userInfo
     * @return array
     */
    public function getApiSearch($merId, array $where, int $page, int $limit, $userInfo)
    {
        $where = array_merge($where, $this->dao->productShow());
        //搜索记录
        if ($userInfo && isset($where['keyword']) && !empty($where['keyword']))
            app()->make(UserVisitRepository::class)->searchProduct($userInfo['uid'], $where['keyword']);
        $query = $this->dao->search($merId, $where)->with(['merchant', 'issetCoupon']);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)->setOption('field', [])->field($this->filed)->select();
        $append[] = 'max_extension';
        if ($this->getUserIsPromoter($userInfo)) $list->append($append);
        return compact('count', 'list');
    }

    /**
     * TODO 秒杀列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2020-08-04
     */
    public function getApiSeckill(array $where, int $page, int $limit)
    {
        $field = 'Product.product_id,Product.mer_id,is_new,keyword,brand_id,image,product_type,store_name,sort,rate,reply_count,sales,price,cost,ot_price,stock,extension_type,care_count,unit_name,create_time';

        $make = app()->make(StoreOrderRepository::class);
        $res = app()->make(StoreSeckillTimeRepository::class)->getBginTime($where);
        $count = 0;
        $list = [];

        if ($res) {
            $where = [
                'start_time' => $res['start_time'],
                'end_time' => $res['end_time'],
                'day' => date('Y-m-d', time())
            ];
            $query = $this->dao->seckillSearch($where)->with(['seckillActive']);
            $count = $query->count();
            $list = $query->page($page, $limit)->setOption('field', [])->field($field)->select()
                ->each(function ($item) use ($make) {
                    $item['sales'] = $make->seckillOrderCounut($item['product_id']);
                    $item['stop']  = $item->end_time;
                    return $item;
                });
        }
        return compact('count', 'list');
    }

    /**
     * TODO 平台礼包列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2020-06-01
     */
    public function getBagList(array $where, int $page, int $limit)
    {
        $query = $this->dao->search(null, $where)->with(['merCateId.category', 'storeCategory', 'brand', 'merchant' => function ($query) {
            $query->field('mer_id,mer_avatar,mer_name,product_score,service_score,postage_score,status,care_count,is_trader');
        }]);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)->setOption('field', [])->field($this->filed)->select();

        return compact('count', 'list');
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/28
     * @param array $where
     * @return mixed
     */
    public function getBrandByCategory(array $where)
    {
        $mer_id = $where['mer_id'] ? $where['mer_id'] : null;
        $query = $this->dao->search($mer_id, $where);
        return $query->group('brand_id')->column('brand_id');
    }

    /**
     * api 获取商品详情
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param $id
     * @param $userInfo
     */
    public function detail(int $id, $userInfo)
    {
        $where = [
            'is_show' => 1,
            'status' => 1,
            'is_used' => 1,
            'mer_status' => 1,
            'product_id' => $id
        ];
        return $this->apiProductDetail($where,0,null,$userInfo);
    }

    /**
     * TODO api秒杀商品详情
     * @param int $id
     * @author Qinii
     * @day 2020-08-05
     */
    public function seckillDetail(int $id)
    {
        $where = $this->seckillShow();
        $where['product_id'] = $id;
        return $this->apiProductDetail($where, 1,null);
    }


    public function apiProductDetail(array $where,int $productType, ?int $activityId,$userInfo = null)
    {
        $field = 'is_show,cate_parid,gongxian,duihuan,rangli,product_id,mer_id,image,slider_image,store_name,store_info,unit_name,price,cost,ot_price,stock,sales,video_link,product_type,extension_type,old_product_id';
        $with = [
            'attr',
            'content',
            'attrValue',
            'oldAttrValue',
            'merchant.recommend',
            'seckillActive' => function($query){
                $query->field('start_day,end_day,start_time,end_time,product_id');
            },
        ];
        $append = ['topReply'];
        $res = $this->dao->getWhere($where, $field, $with);
        if ($res){
            switch ($res['product_type']) {
                case 1:
                    $_where = $this->dao->productShow();
                    $_where['product_id'] = $res['old_product_id'];
                    $oldProduct = $this->dao->getWhere($_where);
                    $result = $this->getSeckillAttrValue($res['attrValue'],$res['old_product_id']);
                    $res['attrValue'] = $result['item'];

                    $res['stock'] = $result['stock'];
                    $res['stop'] = strtotime(date('Y-m-d', time()) . $res['seckillActive']['end_time'] . ':00:00');
                    $res['sales'] = app()->make(StoreOrderRepository::class)->seckillOrderCounut($where['product_id']);
                    $res['quota'] = $this->seckillStock($where['product_id']);
                    $res['old_status'] = $oldProduct ? 1 : 0;
                    $append[] = 'seckill_status';
                    break;
                default:
                    break;
            }
            if ($userInfo) {
                $relation_make = app()->make(UserRelationRepository::class);
                $isRelation = $relation_make->getUserRelation(['type_id' => $res['product_id'], 'type' => 1], $userInfo['uid']);
                if ($this->getUserIsPromoter($userInfo)){
                    $append[] = 'max_extension';
                    $append[] = 'min_extension';
                }
            }

            $attr = $this->detailAttr($res['attr']);
            $attrValue = (in_array($res['product_type'],[3,4])) ?  $res['oldAttrValue'] : $res['attrValue'];
            $sku  = $this->detailAttrValue($attrValue, $userInfo,$productType,$activityId);

            $res['isRelation'] = $isRelation ?? false;
            $res['replayData'] = app()->make(ProductReplyRepository::class)->getReplyRate($res['product_id']);
            unset($res['attr'],$res['attrValue'],$res['oldAttrValue'],$res['seckillActive']);
            $res['attr'] = $attr;
            $res['sku'] = $sku;
            $res->append($append);
        }
        return $res;
    }

    /**
     * TODO 单商品属性
     * @param $data
     * @return array
     * @author Qinii
     * @day 2020-08-05
     */
    public function detailAttr($data)
    {
        $attr = [];
        foreach ($data as $key => $item) {
            $attr[$key] = $item;
            $arr = [];
            foreach ($item['attr_values'] as $i => $value) {
                $arr[] = [
                    'attr' => $value,
                    'check' => false
                ];
            }
            $attr[$key]['attr_value'] = $arr;
        }
        return $attr;
    }

    /**
     * TODO 获取秒杀商品的库存数
     * @param array $data
     * @param int $oldProductId
     * @return array
     * @author Qinii
     * @day 2020-11-12
     */
    public function getSeckillAttrValue($data,$oldProductId)
    {
        /**
         *  秒杀商品限购数量
         *  原商品库存 > 限购数
         *      销量 = 订单总数 - 退款退货 - （未发货且仅退款）
         *      限购数 = 限购数 - 销量
         *  原商品库存 < 限购数
         *      限购数 = 原商品库存
         */
        $make = app()->make(ProductAttrValueRepository::class);
        $order_make = app()->make(StoreOrderRepository::class);
        $stock = 0;
        $item = [];
        foreach ($data as $k => $value) {
            $where = [
                'sku' => $value['sku'],
                'product_id' => $oldProductId
            ];
            //愿商品库存信息
            $attr = $make->getWhere($where);
            $value['stock'] = ($attr['stock'] < $value['stock']) ? $attr['stock'] : $value['stock'] - $order_make->seckillSkuOrderCounut($value['unique']);
            $stock = $stock + $value['stock'];
            $item[] = $value;
        }
        return compact('item','stock');
    }
    /**
     * TODO 单商品sku
     * @param $data
     * @param $userInfo
     * @return array
     * @author Qinii
     * @day 2020-08-05
     */
    public function detailAttrValue($data, $userInfo, $productType = 0,$artiveId = null)
    {
        $sku = [];
        $make_presll = app()->make(ProductPresellSkuRepository::class);
        $make_assist = app()->make(ProductAssistSkuRepository::class);
        $make_group = app()->make(ProductGroupSkuRepository::class);
        foreach ($data as $k => $value) {
            $_value = [
                'sku'    => $value['sku'],
                'price'  => $value['price'],
                'stock'  => $value['stock'],
                'image'  => $value['image'],
                'weight' => $value['weight'],
                'volume' => $value['volume'],
                'sales'  => $value['sales'],
                'unique' => $value['unique'],
                'bar_code' => $value['bar_code'],
            ];
//            //秒杀
//            if ($productType == 1 && $flag) {
//                $_value['stock'] = $value['stock'] - $make->seckillSkuOrderCounut($value['unique']); //获取sku的销量
//            }
            //预售
            if($productType == 2){
                $_sku = $make_presll->getSearch(['product_presell_id' => $artiveId,'unique' => $value['unique']])->find();
                if(!$_sku) continue;
                $_value['price'] = $_sku['presell_price'];
                $_value['stock'] = $_sku['stock'];
                $_value['down_price'] = $_sku['down_price'];
            }
            //助力
            if($productType == 3){
                $_sku = $make_assist->getSearch(['product_assist_id' => $artiveId,'unique' => $value['unique']])->find();
                if(!$_sku) continue;
                $_value['price'] = $_sku['assist_price'];
                $_value['stock'] = $_sku['stock'];
            }
            //拼团
            if($productType == 4){
                $_sku = $make_group->getSearch(['product_group_id' => $artiveId,'unique' => $value['unique']])->find();
                if(!$_sku) continue;
                $_value['price'] = $_sku['active_price'];
                $_value['stock'] = $_sku['stock'];
            }
            //推广员
            if ($this->getUserIsPromoter($userInfo)) {
                $_value['extension_one'] = $value->bc_extension_one;
                $_value['extension_two'] = $value->bc_extension_two;
            }
            $sku[$value['sku']] = $_value;
        }
        return $sku;
    }


    /**
     * TODO 秒杀商品库存检测
     * @param int $productId
     * @return bool|int
     * @author Qinii
     * @day 2020-08-05
     */
    public function seckillStock(int $productId)
    {
        $product = $this->dao->getWhere(['product_id' => $productId], '*', ['attrValue']);
        $count = app()->make(StoreOrderRepository::class)->seckillOrderCounut($productId);
        if ($product['stock'] > $count) {
            $make = app()->make(ProductAttrValueRepository::class);
            foreach ($product['attrValue'] as $item) {
                $attr = [
                    ['sku', '=', $item['sku']],
                    ['product_id', '=', $product['old_product_id']],
                    ['stock', '>', 0],
                ];
                if ($make->getWhereCount($attr)) return true;
            }
        }
        return false;
    }


    /**
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param $userInfo
     * @return bool
     */
    public function getUserIsPromoter($userInfo)
    {
        return (isset($userInfo['is_promoter']) && $userInfo['is_promoter'] && systemConfig('extension_status')) ? true : false;
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param $userInfo
     * @param int|null $merId
     * @param $page
     * @param $limit
     * @return array
     */
    public function recommend($userInfo, ?int $merId, $page, $limit)
    {
        $where = ['order' => 'sales'];
        if (!is_null($userInfo)) {
            $cate_ids = app()->make(UserVisitRepository::class)->getRecommend($userInfo['uid']);
            if ($cate_ids) $where = ['cate_ids' => $cate_ids];
        }
        $where = array_merge($where, $this->switchType(1, $merId, 0), $this->dao->productShow());
        $query = $this->dao->search($merId, $where);
        $count = $query->count($this->dao->getPk());
        $list = $query->page($page, $limit)->setOption('field', [])->with(['issetCoupon', 'merchant'])->field('mer_id,product_id,cate_id,image,store_name,sort,sales,price,create_time')->select();

        return compact('count', 'list');
    }

    /**
     * 检测是否有效
     * @Author:Qinii
     * @Date: 2020/6/1
     * @param $id
     * @return mixed
     */
    public function getOne($id)
    {
        $data = ($this->dao->getWhere([$this->dao->getPk() => $id]));
        if (!is_null($data) && $data->check()) return $data;
        return false;
    }

    public function switchStatus(array $id, $data)
    {
        $status = $data['status'] ?? 0;
        $is_show = $data['is_show'] ?? 0;
        $extension_status = systemConfig('extension_status');
        $make = app()->make(ProductAttrValueRepository::class);
        foreach ($id as $i){
            $product = $this->getwhere(['product_id' => $i]);
            if ($is_show == 1){
                if($product['product_type'] == 2) throw new ValidateException('ID:'.$i.' 商品正在参与预售活动');
                if($product['product_type'] == 3) throw new ValidateException('ID:'.$i.' 商品正在参与助力活动');
            }
            if($extension_status && ($status == 1 || $is_show == 1) && $product->extension_type == 1){
                if (!$make->checkExtensionById($i)) throw new ValidateException('设置佣金不能低于系统比例');
            }
            queue(ChangeSpuStatusJob::class,['id' => $i,'product_type' => $product['product_type']]);
        }

        $this->dao->updates($id, $data);
        $res = $this->dao->get($id[0]);

        if (isset($data['status'])) {
            SwooleTaskService::merchant('notice', [
                'type' => 'product',
                'data' => [
                    'title' => '新审核结果',
                    'message' => '您有一条新的商品审核',
                    'id' => ''
                ]
            ], $res['mer_id']);
        }
    }

    /**
     * TODO 执行检测所有线上商品
     * @author Qinii
     * @day 2020-06-24
     */
    public function checkProductByExtension()
    {
        if (systemConfig('extension_status')) {
            $where = ['status' => 1, 'is_show' => 1, 'product_type' => 0];
            $make = app()->make(ProductAttrValueRepository::class);
            $query = $this->dao->search(null, $where)->where('extension_type', 1);
            $query->chunk(100, function ($prduct) use ($make) {
                foreach ($prduct as $item) {
                    if (!$make->checkExtensionById($item['product_id'])) {
                        $item->status = -2;
                        $item->refusal = '因佣金比例调整，商品佣金低于系统比例';
                        $item->save();
                    }
                }
            });
        }
    }

    public function wxQrCode(int $productId, int $productType, User $user)
    {
        $name = md5('pwx' . $productId . $productType. $user->uid . $user['is_promoter'] . date('Ymd')) . '.jpg';
        $make = app()->make(QrcodeService::class);
        $link = '';
        switch ($productType) {
            case 0: //普通商品
                $link = '/pages/goods_details/index?id=' . $productId . '&spid=' . $user['uid'];
                break;
            case 1: //秒杀商品
                $link = '/pages/activity/goods_seckill_details/index?id=' . $productId . '&spid=' . $user['uid'];
                break;
            case 2: //预售商品
                $link = '/pages/activity/presell_details/index?id=' . $productId . '&spid=' . $user['uid'];
                break;
            case 4: //拼团商品
                $link = '/pages/activity/combination_details/index?id='. $productId . '&spid=' . $user['uid'];
                break;
            case 40: //拼团商品2
                $link = '/pages/activity/combination_status/index?id='. $productId . '&spid=' . $user['uid'];
                break;
        }

        return $make->getWechatQrcodePath($name, $link);
    }

    public function routineQrCode(int $productId, int $productType, User $user)
    {
        //小程序
        $name = md5('sprt' . $productId . $productType . $user->uid . $user['is_promoter'] . date('Ymd')) . '.jpg';
        $make = app()->make(QrcodeService::class);
        $params = 'id=' . $productId . '&spid=' . $user['uid'];
        $link = '';
        switch ($productType) {
            case 0: //普通商品
                $link = 'pages/goods_details/index';
                break;
            case 1: //秒杀商品
                $link = 'pages/activity/goods_seckill_details/index';
                break;
            case 2: //预售商品
                $link = 'pages/activity/presell_details/index';
                break;
            case 4: //拼团商品
                $link = '/pages/activity/combination_details/index';
                break;
            case 40: //拼团商品2
                $link = '/pages/activity/combination_status/index';
                break;
        }

        return $make->getRoutineQrcodePath($name, $link, $params);
    }

    /**
     * TODO 礼包是否超过数量限制
     * @param $merId
     * @return bool
     * @author Qinii
     * @day 2020-06-25
     */
    public function checkMerchantBagNumber($merId)
    {
        $where = ['is_gift_bag' => 1];
        $promoter_bag_number = systemConfig('max_bag_number');
        $count = $this->dao->search($merId, $where)->count();
        if (is_null($promoter_bag_number) || ($promoter_bag_number > $count)) return true;
        return false;
    }

    public function orderProductIncStock($cart, $productNum = null)
    {
        $productNum = $productNum ?? $cart['product_num'];
        Db::transaction(function () use ($cart, $productNum) {
            $productAttrValueRepository = app()->make(ProductAttrValueRepository::class);
            if ($cart['product_type'] == '1') {
                $oldId = $cart['cart_info']['product']['old_product_id'];
                $productAttrValueRepository->incSkuStock($oldId, $cart['cart_info']['productAttr']['sku'], $productNum);
                $this->dao->incStock($oldId, $productNum);
            } else if ($cart['product_type'] == '2') {
                $presellSku = app()->make(ProductPresellSkuRepository::class);
                $presellSku->incStock($cart['cart_info']['productPresellAttr']['product_presell_id'], $cart['cart_info']['productPresellAttr']['unique'], $productNum);
                $productAttrValueRepository->incStock($cart['product_id'], $cart['cart_info']['productAttr']['unique'], $productNum);
                $this->dao->incStock($cart['product_id'], $productNum);
            } else if ($cart['product_type'] == '3') {
                app()->make(ProductAssistSkuRepository::class)->incStock($cart['cart_info']['productAssistAttr']['product_assist_id'], $cart['cart_info']['productAssistAttr']['unique'], $productNum);
                $productAttrValueRepository->incStock($cart['cart_info']['product']['old_product_id'], $cart['cart_info']['productAttr']['unique'], $productNum);
                $this->dao->incStock($cart['cart_info']['product']['old_product_id'], $productNum);
            } else if ($cart['product_type'] == '4') {
                app()->make(ProductGroupSkuRepository::class)->incStock($cart['cart_info']['activeSku']['product_group_id'], $cart['cart_info']['activeSku']['unique'], $productNum);
                $this->dao->incStock($cart['cart_info']['product']['old_product_id'], $productNum);
                $productAttrValueRepository->incStock($cart['cart_info']['product']['old_product_id'], $cart['cart_info']['productAttr']['unique'], $productNum);
            } else {
                if (isset($cart['cart_info']['product']['old_product_id']) && $cart['cart_info']['product']['old_product_id'] > 0) {
                    $oldId = $cart['cart_info']['product']['old_product_id'];
                    $productAttrValueRepository->incSkuStock($oldId, $cart['cart_info']['productAttr']['sku'], $productNum);
                    $this->dao->incStock($oldId, $productNum);
                    return;
                }
                $productAttrValueRepository->incStock($cart['product_id'], $cart['cart_info']['productAttr']['unique'], $productNum);
                $this->dao->incStock($cart['product_id'], $productNum);
            }
        });
    }

    /**
     * TODO 商品验证
     * @param $data
     * @author Qinii
     * @day 2020-08-01
     */
    public function check($data, $merId)
    {
        if (!$this->merBrandExists($data['brand_id']))
            throw new ValidateException('品牌不存在');
        if (!$this->CatExists($data['cate_id']))
            throw new ValidateException('平台分类不存在');
        if (isset($data['mer_cate_id']) && !$this->merCatExists($data['mer_cate_id'], $merId))
            throw new ValidateException('不存在的商户分类');
        if (!$this->merShippingExists($merId, $data['temp_id']))
            throw new ValidateException('运费模板不存在');

    }

    public function fictiForm(int $id)
    {
        $form = Elm::createForm(Route::buildUrl('systemStoreProductAddFicti', ['id' => $id])->build());
        $res = $this->dao->getWhere(['product_id' => $id],'ficti,sales');
        $form->setRule([
            Elm::input('number','现有虚拟销量',$res['ficti'])->readonly(true),
            Elm::radio('type', '修改类型', 1)
                ->setOptions([
                    ['value' => 1, 'label' => '增加'],
                    ['value' => 2, 'label' => '减少'],
                ]),
            Elm::number('ficti', '修改虚拟销量数', 0),
        ]);
        return $form->setTitle('修改虚拟销量数');
    }

    /**
     * TODO 普通商品加入购物车检测
     * @param int $prodcutId
     * @param string $unique
     * @param int $cartNum
     * @author Qinii
     * @day 2020-10-20
     */
    public function cartCheck(array $data,$userInfo)
    {
        $where = $this->dao->productShow();
        $where['product_id'] = $data['product_id'];
        unset($where['is_gift_bag']);
        $product = $this->dao->search(null,$where)->find();
        if(!$product) throw new ValidateException('商品已下架');

        $value_make = app()->make(ProductAttrValueRepository::class);
        $sku = $value_make->getOptionByUnique($data['product_attr_unique']);

        if(!$sku) throw new ValidateException('SKU不存在');
        if ($sku['stock'] < $data['cart_num']) throw new ValidateException('库存不足');
        $cart = null;
        //分销礼包
        if($product['is_gift_bag']){
            if(!systemConfig('extension_status'))throw new ValidateException('分销功能未开启');
            if(!$data['is_new']) throw new ValidateException('礼包商品不可加入购物车');
            if($data['cart_num'] !== 1) throw new ValidateException('礼包商品只能购买一个');
            // if($userInfo->is_promoter) throw new ValidateException('您已经是分销员了');
            $cart = null;
        }else{
            if(!$data['is_new']){
                $cart_make = app()->make(StoreCartRepository::class);
                $_cart = $cart_make->getCartByProductSku($data['product_attr_unique'],$userInfo->uid);
                if ($sku['stock'] < ($_cart['cart_num'] + $data['cart_num'])) throw new ValidateException('库存不足');
                if($_cart){
                    $cart = [
                        'cart_id' => $_cart['cart_id'],
                        'cart_num' => $_cart['cart_num'],
                    ];
                }
            }
        }
        return compact('product','sku','cart');
    }

    /**
     * TODO 秒杀商品加入购物车检测
     * @param array $data
     * @param int $userInfo
     * @return array
     * @author Qinii
     * @day 2020-10-21
     */
    public function cartSeckillCheck(array $data,$userInfo)
    {
        if($data['is_new'] !== 1) throw new ValidateException('秒杀商品不能加入购物车');
        if($data['cart_num'] !== 1) throw new ValidateException('秒杀商品只能购买一个');

        $where = $this->dao->seckillShow();
        $where['product_id'] = $data['product_id'];
        $product = $this->dao->search(null,$where)->find();
        if(!$product) throw new ValidateException('商品已下架');
        if($product->end_time < time()) throw new ValidateException('秒杀活动已结束');

        $order_make = app()->make(StoreOrderRepository::class);
        $count = $order_make->seckillOrderCounut($data['product_id']);

        $value_make = app()->make(ProductAttrValueRepository::class);
        $sku = $value_make->getOptionByUnique($data['product_attr_unique']);

        if($sku['stock'] <= $count)  throw new ValidateException('限购数量不足');
        $_sku = $value_make->getWhere(['sku' => $sku['sku'], 'product_id' => $product['old_product_id']]);
        if(!$_sku) throw new ValidateException('原商品SKU不存在');
        if($_sku['stock'] <= 0) throw new ValidateException('原库存不足');

        if(!$order_make->getDayPayCount($userInfo->uid,$data['product_id']))
            throw new ValidateException('您的本次活动购买数量上限');
        if(!$order_make->getPayCount($userInfo->uid,$data['product_id']))
            throw new ValidateException('您的该商品购买数量上限');
        $cart = null;
        return compact('product','sku','cart');
    }

    /**
     * TODO 复制一条商品
     * @param int $productId
     * @param array $data
     * @return mixed
     * @author Qinii
     * @day 2020-11-19
     */
    public function productCopy(int $productId,array $data,$productType = 0)
    {
        $product = $this->getAdminOneProduct($productId,null);
        $product = $product->toArray();
        if($data){
            foreach ($data as $k => $v){
                $product[$k] = $v;
            }
        }
        return $this->create($product,$productType);
    }

    public function existsProduct(int $id,$productType)
    {
        switch ($productType){
            case 2:
                $make = app()->make(ProductPresellRepository::class);
                break;
            case 4:
                $make = app()->make(ProductGroupRepository::class);
                break;
            case 40:
                $make = app()->make(ProductGroupBuyingRepository::class);
                break;
            default:
                $make = $this->dao;
        }
        $where = [
            $make->getPk() => $id,
            'is_del' => 0
        ];
        return $make->getWhereCount($where);
    }
}
