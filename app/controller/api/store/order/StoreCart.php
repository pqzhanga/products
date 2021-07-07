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

namespace app\controller\api\store\order;

use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\product\ProductAssistRepository;
use app\common\repositories\store\product\ProductAssistSetRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\ProductGroupRepository;
use app\common\repositories\store\product\ProductPresellRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use MongoDB\BSON\MaxKey;
use think\App;
use crmeb\basic\BaseController;
use app\validate\api\StoreCartValidate as validate;
use app\common\repositories\store\order\StoreCartRepository as repository;
use think\exception\ValidateException;

class StoreCart extends BaseController
{
    /**
     * @var repository
     */
    protected $repository;

    /**
     * StoreBrand constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * @Author:Qinii
     * @Date: 2020/5/28
     * @return mixed
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList($this->request->uid()));
    }

    /**
     * @param validate $validate
     * @return mixed
     * @author Qinii
     */
    public function create(validate $validate)
    {
        $data = $this->checkParams($validate);
        if(!in_array($data['product_type'],[0,1,2,3,4])) return app('json')->fail('商品类型错误');
        switch ($data['product_type'])
        {
            case 0:  //普通商品
                $result = app()->make(ProductRepository::class)->cartCheck($data,$this->request->userInfo());

                [$source, $sourceId, $pid] = explode(':', $this->request->param('source', '0'), 3) + ['', '', ''];
                $data['source'] = (in_array($source, [0, 1]) && $pid == $data['product_id']) ? $source : 0;
                if ($data['source'] > 0) $data['source_id'] = intval($sourceId);

                break;
            case 1:  //秒杀商品
                $result = app()->make(ProductRepository::class)->cartSeckillCheck($data,$this->request->userInfo());
                break;
            case 2:  //预售商品
                $result = app()->make(ProductPresellRepository::class)->cartCheck($data,$this->request->userInfo());
                $data['source'] = $data['product_type'];
                $data['source_id'] = $data['product_id'];
                $data['product_id'] = $result['product']['product_id'];
                break;
            case 3: //助力商品
                $result = app()->make(ProductAssistSetRepository::class)->cartCheck($data,$this->request->userInfo());
                $data['source'] = $data['product_type'];
                $data['source_id'] = $data['product_id'];
                $data['product_id'] = $result['product']['product_id'];
                break;
            case 4: //助力商品
                $result = app()->make(ProductGroupRepository::class)->cartCheck($data,$this->request->userInfo());
                $data['source'] = $data['product_type'];
                $data['source_id'] = $data['group_buying_id'];
                $data['product_id'] = $result['product']['product_id'];
                break;
        }
        unset($data['group_buying_id']);
        if ($cart = $result['cart']) {
            //更新购物车
            $cart_id = $cart['cart_id'];
            $cart_num = ['cart_num' => ($cart['cart_num'] + $data['cart_num'])];
            $this->repository->update($cart_id,$cart_num);
        } else {
            //添加购物车
            $data['uid'] = $this->request->uid();
            $data['mer_id'] = $result['product']['mer_id'];
            $cart = $this->repository->create($data);
        }
        return app('json')->success(['cart_id' => $cart['cart_id']]);
    }


    /**
     * @param $id
     * @return mixed
     * @throws \think\db\exception\DbException
     * @author Qinii
     */
    public function change($id)
    {
        $where = $this->request->params(['cart_num']);
        if( intval($where['cart_num']) < 0 )
            return app('json')->fail('数量必须大于0');
        if(!$cart = $this->repository->getOne($id,$this->request->uid()))
            return app('json')->fail('购物车信息不存在');
        if(!$res= app()->make(ProductAttrValueRepository::class)->getOptionByUnique($cart['product_attr_unique']))
            return app('json')->fail('SKU不存在');
        if($res['stock'] < $where['cart_num'])
            return app('json')->fail('库存不足');
        $this->repository->update($id,$where);
        return app('json')->success('修改成功');

    }

    /**
     * @return mixed
     * @author Qinii
     */
    public function batchDelete()
    {
        $ids = $this->request->param('cart_id');
        if(!count($ids))return app('json')->fail('参数错误');
        $this->repository->batchDelete($ids,$this->request->uid());
        return app('json')->success('删除成功');
    }


    /**
     * @return mixed
     * @author Qinii
     */
    public function cartCount()
    {
        return app('json')->success($this->repository->getCartCount($this->request->uid()));
    }

    /**
     * @param $data
     * @return mixed
     * @author Qinii
     * @day 2020-06-11
     */
    public function check($data)
    {
        $product = app()->make(ProductRepository::class)->get($data['product_id']);
        if(!$product)
           throw new ValidateException('商品不存在');
        if( $data['cart_num'] < 0 )
            throw new ValidateException('数量必须大于0');
        if(!$res= app()->make(ProductAttrValueRepository::class)->getOptionByUnique($data['product_attr_unique']))
            throw new ValidateException('SKU不存在');
        if($res['product_id'] != $data['product_id'])
            throw new ValidateException('数据不一致');
        if($res['stock'] < $data['cart_num'])
            throw new ValidateException('库存不足');
        $data['is_new'] = 1;
        $data['uid'] = $this->request->uid();
        $data['mer_id'] = $product['mer_id'];
        return $data;
    }


    /**
     * @param validate $validate
     * @return mixed
     * @author Qinii
     * @day 2020-06-11
     */
    public function again(validate $validate)
    {
        $param = $this->request->param('data',[]);
        foreach ($param as $data){
            $validate->check($data);
            $item[] = $this->check($data);
        }

        foreach ($item as $it){
            $it__id = $this->repository->create($it);
            $ids[] = $it__id['cart_id'];
        }
        return app('json')->success(['cart_id' => $ids]);
    }


    /**
     * @param validate $validate
     * @return array
     * @author Qinii
     * @day 2020-06-11
     */
    public function checkParams(validate $validate)
    {
        $data = $this->request->params(['product_id','product_attr_unique','cart_num','is_new',['product_type',0],['group_buying_id',0],'cate_parid']);
            $validate->check($data);
        return $data;
    }
}
