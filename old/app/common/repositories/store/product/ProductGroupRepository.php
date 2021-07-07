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
use app\common\dao\store\product\ProductGroupDao;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\StoreCategoryRepository;
use think\exception\ValidateException;
use think\facade\Db;

class ProductGroupRepository extends BaseRepository
{
    protected $dao;

    /**
     * ProductGroupRepository constructor.
     * @param ProductGroupDao $dao
     */
    public function __construct(ProductGroupDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * TODO
     * @param $merId
     * @param $data
     * @author Qinii
     * @day 1/8/21
     */
    public function create($merId,$data)
    {
        $product_make = app()->make(ProductRepository::class);

        $product = [
            'store_name' => $data['store_name'],
            'image' => $data['image'],
            'slider_image' => $data['slider_image'],
            'store_info' => $data['store_info'],
            'unit_name' => $data['unit_name'],
            'temp_id' => $data['temp_id'],
            'product_type' => 4,
            'status'    => 1,
            'sort' => $data['sort'],
            'old_product_id'    => $data['product_id'],
        ];

        Db::transaction(function()use($data,$product_make,$product,$merId) {

            $product_id = $product_make->productCopy($data['product_id'], $product, 4);
            $slt = $this->sltNumber($data);

            $result = [
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'status' => 1,
                'is_show' => $data['is_show'] ?? 1,
                'product_id' => $product_id,
                'pay_count' => $data['pay_count'],
                'once_pay_count' => $data['once_pay_count'],
                'mer_id' => $merId,
                'buying_count_num' => $data['buying_count_num'],
                'buying_num' => $slt['buying_num'],
                'ficti_status' => $data['ficti_status'],
                'ficti_num' => $slt['ficti_num'],
                'time' => $data['time']
            ];

            $resultData = $this->dao->create($result);

            $sku_make = app()->make(ProductGroupSkuRepository::class);
            $res = $this->sltSku($data,$resultData->product_group_id,$data['product_id']);
            $sku_make->insertAll($res['sku']);

            $this->dao->update($resultData->product_group_id,['price' => $res['price']]);
            $product_make->update($product_id,['price' => $res['old_price']]);
            $data['mer_id'] = $merId;
            $data['price'] = $res['price'];
            app()->make(SpuRepository::class)->create($data, $product_id, $resultData->product_group_id, 4);
        });
    }

    /**
     * TODO
     * @param int $id
     * @param array $data
     * @author Qinii
     * @day 1/8/21
     */
    public function edit(int $id,array $data)
    {
        $product = [
            'image' => $data['image'],
            'store_name' => $data['store_name'],
            'store_info' => $data['store_info'],
            'slider_image' => implode(',', $data['slider_image']),
            'temp_id' => $data['temp_id'],
            'unit_name' => $data['unit_name'],
            'sort' => $data['sort'],
        ];
        $slt = $this->sltNumber($data);
        $active = [
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => 1,
            'is_show' => $data['is_show'] ?? 1,
            'pay_count' => $data['pay_count'],
            'once_pay_count' => $data['once_pay_count'],
            'buying_count_num' => $data['buying_count_num'],
            'buying_num' => $slt['buying_num'],
            'ficti_status' => $data['ficti_status'],
            'ficti_num' => $slt['ficti_num'],
            'time' => $data['time'],
            'product_status' => 0,
            'action_status' => 0,
        ];

        Db::transaction(function()use($id,$active,$product,$data){
            $product_make = app()->make(ProductRepository::class);
            $sku_make = app()->make(ProductGroupSkuRepository::class);

            $resData = $this->dao->get($id);
            $res = $this->sltSku($data,$id,$resData['product_id']);

            $active['price'] = $res['price'];
            $this->dao->update($id,$active);

            $sku_make->clear($id);
            $sku_make->insertAll($res['sku']);
            $product['price'] = $res['old_price'];
            $product_make->update($resData['product_id'],$product);
            $product_make->createContent($resData['product_id'], ['content' => $data['content']]);
            $data['price'] = $res['price'];
            $data['mer_id'] = $resData['mer_id'];
            app()->make(SpuRepository::class)->update($data,$resData['product_id'],$id,4);
        });

    }

    /**
     * TODO 检测是否每个sku的价格
     * @param array $data
     * @param int $presellId
     * @param int $productId
     * @return array
     * @author Qinii
     * @day 1/8/21
     */
    public function sltSku(array $data,int $ActiveId)
    {
        $make = app()->make(ProductAttrValueRepository::class);
        $sku = [];
        $price = 0;
        $old_price = 0;
        foreach ($data['attrValue'] as $item){
            $skuData = $make->getWhere(['unique' => $item['unique']]);
            if(!$skuData) throw new ValidateException('SKU不存在');
            if(bccomp($item['active_price'],$skuData['price'],2) == 1)
                throw new ValidateException('活动价格不得大于原价');
            if(!$item['active_price'] || $item['active_price'] < 0)
                throw new ValidateException('请正确填写金额');
            $sku[] = [
                'product_group_id' => $ActiveId,
                'product_id' => $data['product_id'],
                'unique' => $item['unique'],
                'stock' => $item['stock'],
                'stock_count' => $item['stock'],
                'active_price' => $item['active_price'],
            ];
            $price = ($price == 0 ) ? $item['active_price'] : (($price > $item['active_price']) ? $item['active_price']:$price) ;
            $old_price = ($old_price == 0 ) ? $item['price'] : (($old_price > $item['price']) ? $item['price']:$old_price) ;
        }
        return compact('sku','price','old_price');
    }

    public function sltNumber($data)
    {
        $ficti_status = systemConfig('ficti_status');
        $buying_num = $data['buying_count_num'];
        $ficti_num = 0;
        if($ficti_status && $data['ficti_status']){
            $ficti_num = (int)round($data['buying_count_num'] * (systemConfig('group_buying_rate') / 100 ));
            if($data['ficti_num'] > $ficti_num)
                throw new ValidateException('最多虚拟人数超出比例范围');
            $ficti_num = $data['ficti_num'];
            $buying_num = $data['buying_count_num'] - $ficti_num;
        }

        return compact('buying_num','ficti_num');
    }

    /**
     * TODO
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 1/9/21
     */
    public function getApiList(array $where,int $page,int $limit)
    {
        $where = array_merge($where,$this->dao->actionShow());
        $where['order'] = 'api';
        $query = $this->dao->search($where)->with([
            'product' => function($query){
                $query->field('product_id,store_name,image,price,sales,unit_name');
            },
            'merchant' => function($query){
                $query->field('mer_id,mer_name,is_trader');
            }
        ]);
        $count = $query->count();
        $list = $query->page($page,$limit)->hidden(['ficti_status','ficti_num','refusal','is_del'])->select()
            ->append(['stock','sales']);
        return compact('count','list');
    }

    public function getMerchantList(array $where,int $page,int $limit)
    {
        $where['order'] = 'sort';
        $query = $this->dao->search($where)->with([
            'product' => function($query){
                $query->field('product_id,store_name,image,price,sales,sort');
            },
        ]);
        $count = $query->count();
        $list = $query->page($page,$limit)->select()->append(['stock','sales','count_take','count_user']);
        return compact('count','list');
    }

    public function getAdminList(array $where,int $page,int $limit)
    {
        $where['order'] = 'star';
        $query = $this->dao->search($where)->with([
            'product' => function($query){
                $query->field('product_id,store_name,image,price,sales,rank');
            },
            'merchant' => function($query){
                $query->field('mer_id,mer_name,is_trader');
            }
        ]);
        $count = $query->count();
        $list = $query->page($page,$limit)->select()->append(['stock','sales','count_take','count_user','star']);
        return compact('count','list');
    }

    /**
     * TODO merchant 编辑时详情
     * @param int $id
     * @return array|\think\Model|null
     * @author Qinii
     * @day 1/8/21
     */
    public function detail(?int $merId,int $id)
    {
        $where[$this->dao->getPk()] = $id;
        $where['is_del'] = 0;
        $data = $this->dao->getWhere($where,'*',[
            'product' => ['attr','oldAttrValue','content'],
            'merchant'=> function($query){
                $query->field('mer_id,mer_avatar,mer_name,is_trader');
            }]
        );
        if(!$data) throw new ValidateException('数据不存在');
        if(!$data['product']) throw new ValidateException('该商品已不存在');
        $sku_make = app()->make(ProductGroupSkuRepository::class);
        foreach ($data['product']['oldAttrValue'] as $key => $item) {
            $sku = explode(',', $item['sku']);
            $item['old_stock'] = $item['stock'];
            $_sku = $sku_make->getWhere([$this->dao->getPk() => $id,'unique' => $item['unique']]);
            if($_sku) $_sku->append(['sales']);
            $item['_sku'] = $_sku;
            if(!$merId && !$item['_sku']) continue;

            foreach ($sku as $k => $v) {
                $item['value' . $k] = $v;
            }
            $data['product']['attrValue'][$key] = $item;
        }
        unset($data['product']['oldAttrValue']);
        foreach ($data['product']['attr'] as $k => $v) {
            $data['product']['attr'][$k] = [
                'value'  => $v['attr_name'],
                'detail' => $v['attr_values']
            ];
        }
        $data->append(['stock','sales','count_take','count_user']);
        return $data;
    }

    /**
     * TODO
     * @param int $id
     * @return array|\think\Model|null
     * @author Qinii
     * @day 1/9/21
     */
    public function apiDetail(int $id)
    {
        $where = $this->dao->actionShow();
        $where[$this->dao->getPk()] = $id;
        $data = $this->dao->search($where)->with([
            'groupBuying.initiator' => function($query){
                $query->where('status',0)->where('is_del',0)
                    ->field('group_buying_id,status,product_group_id,buying_count_num,yet_buying_num,end_time')
                    ->order('create_time ASC');
            }
        ])->hidden(['ficti_status','ficti_num','refusal','is_del'])->find();
        if(!$data) throw new ValidateException('商品已下架');
        $make = app()->make(ProductRepository::class);
        $data['successUser'] = app()->make(ProductGroupUserRepository::class)->successUser($id);
        $data['product'] = $make->apiProductDetail(['product_id' => $data['product_id']],4,$id);
        return $data->append(['sales','stock']);
    }

    public function updateProduct(int $id,array $data)
    {
        $res = $this->dao->get($id);
        app()->make(SpuRepository::class)->changRank($id,$res['product_id'],4,$data);
        unset($data['star']);
        app()->make(ProductRepository::class)->adminUpdate($res['product_id'],$data);
    }


    public function cartCheck(array $data,$userInfo)
    {
        /**
         * 1.是否有团ID
         *     1.1 有团，验证团是否满，状态是否可加入
         * 2.购买数量是否超过限制
         * 3.商品的限购库存
         * 4.原商品的库存
         * 5.限购数是否超出
         */
        if(!$data['is_new']) throw new ValidateException('拼团商品不可加入购物车');
        $res = $this->dao->get($data['product_id']);
        if($data['cart_num'] > $res['once_pay_count']) throw new ValidateException('购买数量超过单次限制');

        if($data['group_buying_id']){
            $group_make = app()->make(ProductGroupRepository::class);
            $where = $group_make->actionShow();
            $where['product_group_id'] = $data['product_id'];
            $product = $group_make->search($where)->find();
            if(!$product) throw new ValidateException('商品已下架');
            $buging_make = app()->make(ProductGroupBuyingRepository::class);
            $group_status = $buging_make->checkGroupStatus($data['group_buying_id']);
            if(!$group_status) throw new ValidateException('不可加入此团');
        }
        $make = app()->make(ProductAttrValueRepository::class);
        $old_sku = $make->getWhere(['unique' => $data['product_attr_unique']]);
        if($old_sku['stock'] < $res['cart_num']) throw new ValidateException('原商品库存不足');

        $sku_make = app()->make(ProductGroupSkuRepository::class);
        $sku = $sku_make->getWhere(['product_group_id' => $data['product_id'],'unique' => $data['product_attr_unique']]);
        if($sku['stock'] < $data['cart_num']) throw new ValidateException('商品限购数量不足');

        if($res['pay_count'] !== 0 ) {
            if($data['cart_num'] > $res['pay_count']) throw new ValidateException('购买数量超过活动限制');
            $order_make = app()->make(StoreOrderRepository::class);
            $where = ['product_id' => $res['product_id'], 'product_type' => 4];
            $count = (int)$order_make->getTattendCount($where, $userInfo->uid)->sum('product_num');
            if(($count + $data['cart_num']) > $res['pay_count']) throw new ValidateException('购买数量超过活动限制');
        }

        $product = $res['product'];
        $cart = null;

        return compact('product','sku','cart');
    }

    public function getCategory()
    {
        $pathArr = $this->dao->category();
        $path = [];
        foreach ($pathArr as $item){
            $path[] = explode('/',$item)[1];
        }
        $path = array_unique($path);
        $cat = app()->make(StoreCategoryRepository::class)->getSearch(['ids' => $path])->field('store_category_id,cate_name')->select();
        return $cat;
    }

    public function updateSort(int $id,?int $merId,array $data)
    {
        $where[$this->dao->getPk()] = $id;
        if($merId) $where['mer_id'] = $merId;
        $ret = $this->dao->getWhere($where);
        if(!$ret) throw new  ValidateException('数据不存在');
        app()->make(ProductRepository::class)->update($ret['product_id'],$data);
        $make = app()->make(SpuRepository::class);
        return $make->updateSort($ret['product_id'],$ret[$this->dao->getPk()],4,$data);
    }

}
