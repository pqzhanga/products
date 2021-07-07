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


namespace app\common\repositories\store\order;


use app\common\dao\store\order\StoreOrderDao;
use app\common\model\store\order\StoreGroupOrder;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\product\ProductPresellSku;
use app\common\model\user\User;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\store\MerchantTakeRepository;
use app\common\repositories\store\product\ProductAssistSetRepository;
use app\common\repositories\store\product\ProductAssistSkuRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\ProductGroupBuyingRepository;
use app\common\repositories\store\product\ProductGroupSkuRepository;
use app\common\repositories\store\product\ProductPresellSkuRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\shipping\ExpressRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use app\common\repositories\system\attachment\AttachmentRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserAddressRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserMerchantRepository;
use app\common\repositories\user\UserRelationRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\RoutineQrcodeRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\jobs\PayGiveCouponJob;
use crmeb\jobs\SendSmsJob;
use crmeb\jobs\SendTemplateMessageJob;
use crmeb\services\AlipayService;
use crmeb\services\ExpressService;
use crmeb\services\MiniProgramService;
use crmeb\services\QrcodeService;
use crmeb\services\printer\Printer;
use crmeb\services\SwooleTaskService;
use crmeb\services\UploadService;
use crmeb\services\WechatService;
use Exception;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Route;
use think\Model;

use think\facade\Log;

/**
 * Class StoreOrderRepository
 * @package app\common\repositories\store\order
 * @author xaboy
 * @day 2020/6/9
 * @mixin StoreOrderDao
 */
class StoreOrderRepository extends BaseRepository
{
    /**
     * 支付类型
     */
    const PAY_TYPE = ['balance', 'weixin', 'routine', 'h5', 'alipay', 'alipayQr','yunfastpay'];

    /**
     * StoreOrderRepository constructor.
     * @param StoreOrderDao $dao
     */
    public function __construct(StoreOrderDao $dao)
    {
        $this->dao = $dao;
    }

    public function createPresellOrder(User $user, int $pay_type, int $duihuan, array $cartId, int $addressId, array $coupons, array $takes, array $mark, array $receipt_data)
    {
        $uid = $user->uid;
        $couponUserRepository = app()->make(StoreCouponUserRepository::class);
        foreach ($coupons as $merId => $coupon) {
            if (!is_array($coupon)) {
                unset($coupons[$merId]);
                continue;
            }
            $storeCoupon = $coupon['store'] ?? ($coupon['store'] = 0);
            $productCoupon = array_unique($coupon['product'] ?? ($coupon['product'] = []));
            $_coupons = $storeCoupon ? array_merge($productCoupon, [$storeCoupon]) : $productCoupon;
            if (!count($_coupons)) {
                unset($coupons[$merId]);
                continue;
            }
            if (count($couponUserRepository->validIntersection($merId, $uid, $_coupons)) != count($_coupons))
                throw new ValidateException('请选择正确的优惠券');
        }
        $order = $this->cartIdByOrderInfo($uid, $takes, $cartId, $addressId, 1, $coupons);
        if ($order['status'] == 'noDeliver')
            throw new ValidateException('部分商品不支持该区域');
        if (!$order['address']) throw new ValidateException('请选择正确的收货地址');

        $cartInfo = $order['order'][0];
        $address = $order['address'];

        if (in_array($cartInfo['mer_id'], $takes)) {
            if (!$cartInfo['take']['mer_take_status']) {
                throw new ValidateException('该店铺不支持到店自提');
            }
            $cartInfo['order']['postage_price'] = 0;
        }

        $cost = 0;
        $totalNum = 0;
        $giveCouponIds = [];
        $total_extension_one = 0;
        $total_extension_two = 0;
        $useCoupon = [];
        $spreadUid = $user->valid_spread_uid;
        $topUid = $user->valid_top_uid;
        if (systemConfig('extension_status')) {
            foreach ($cartInfo['list'] as $cart) {
                $totalNum += $cart['cart_num'];
                $giveCouponIds = array_merge($giveCouponIds, $cart['product']['give_coupon_ids']);
                $cost = bcadd(bcmul($cart['productPresellAttr']['cost'], $cart['cart_num'], 2), $cost, 2);
                if ($spreadUid && $cart['productPresellAttr']['bc_extension_one'] > 0)
                    $total_extension_one = bcadd($total_extension_one, bcmul($cart['cart_num'], $cart['productPresellAttr']['bc_extension_one'], 2), 2);
                if ($topUid && $cart['productPresellAttr']['bc_extension_two'] > 0)
                    $total_extension_two = bcadd($total_extension_two, bcmul($cart['cart_num'], $cart['productPresellAttr']['bc_extension_two'], 2), 2);
            }
        }

        foreach ($cartInfo['coupon'] as $coupon) {
            if (isset($coupon['checked']) && $coupon['checked']) $useCoupon[] = $coupon['coupon_user_id'];
        }

        $merchantRepository = app()->make(MerchantRepository::class);
        $order = [
            'commission_rate' => (float)$merchantRepository->get($cartInfo['mer_id'])->mer_commission_rate,
            'order_type' => in_array($cartInfo['mer_id'], $takes) == 1 ? 1 : 0,
            'extension_one' => $total_extension_one,
            'extension_two' => $total_extension_two,
            'orderInfo' => $cartInfo['order'],
            'cartInfo' => $cartInfo['list'],
            'order_sn' => $this->getNewOrderId() . 0,
            'uid' => $uid,
            'real_name' => $address['real_name'],
            'user_phone' => $address['phone'],
            'user_address' => $address['province'] . $address['city'] . $address['district'] . ' ' . $address['detail'],
            'cart_id' => implode(',', array_column($cartInfo['list'], 'cart_id')),
            'total_num' => $cartInfo['order']['total_num'],
            'total_price' => $cartInfo['order']['total_price'],
            'total_postage' => $cartInfo['order']['postage_price'],
            'pay_postage' => $cartInfo['order']['postage_price'],
            'pay_price' => $cartInfo['order']['pay_price'],
            'activity_type' => 2,
            'mer_id' => $cartInfo['mer_id'],
            'cost' => $cost,
            'coupon_id' => implode(',', $useCoupon),
            'mark' => $mark[$cartInfo['mer_id']] ?? '',
            'coupon_price' => $cartInfo['order']['coupon_price'] > $cartInfo['order']['total_price'] ? $cartInfo['order']['total_price'] : $cartInfo['order']['coupon_price'],
            'pay_type' => $pay_type,
            'duihuan' => $duihuan
        ];

        $groupOrder = [
            'uid' => $uid,
            'group_order_sn' => $this->getNewOrderId() . '0',
            'total_postage' => $order['total_postage'],
            'total_price' => $order['total_price'],
            'total_num' => $totalNum,
            'real_name' => $address['real_name'],
            'user_phone' => $address['phone'],
            'user_address' => $address['province'] . $address['city'] . $address['district'] . ' ' . $address['detail'],
            'pay_price' => $order['pay_price'],
            'coupon_price' => $order['coupon_price'],
            'pay_postage' => $order['total_postage'],
            'cost' => $cost,
            'pay_type' => $pay_type,
            'give_coupon_ids' => $giveCouponIds
        ];

        $storeGroupOrderRepository = app()->make(StoreGroupOrderRepository::class);
        $storeCartRepository = app()->make(StoreCartRepository::class);
        $attrValueRepository = app()->make(ProductAttrValueRepository::class);
        $productRepository = app()->make(ProductRepository::class);
        $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);
        $productPresellSkuRepository = app()->make(ProductPresellSkuRepository::class);

        $group = Db::transaction(function () use ($receipt_data, $topUid, $spreadUid, $uid, $cartInfo, $useCoupon, $order, $productPresellSkuRepository, $couponUserRepository, $storeOrderProductRepository, $productRepository, $attrValueRepository, $storeCartRepository, $storeGroupOrderRepository, $groupOrder) {
            $cart = $cartInfo['list'][0];
            try {
                $productPresellSkuRepository->descStock($cart['productPresellAttr']['product_presell_id'], $cart['productPresellAttr']['unique'], $cart['cart_num']);
                $attrValueRepository->descStock($cart['productAttr']['product_id'], $cart['productAttr']['unique'], $cart['cart_num']);
                $productRepository->descStock($cart['product']['product_id'], $cart['cart_num']);
            } catch (Exception $e) {
                throw new ValidateException('库存不足');
            }

            //修改购物车状态
            $storeCartRepository->update($cart['cart_id'], [
                'is_pay' => 1
            ]);

            if (count($useCoupon)) {
                //使用优惠券
                $couponUserRepository->updates($useCoupon, [
                    'use_time' => date('Y-m-d H:i:s'),
                    'status' => 1
                ]);
            }
            $groupOrder = $storeGroupOrderRepository->create($groupOrder);
            $order['group_order_id'] = $groupOrder->group_order_id;
            $_order = $this->dao->create($order);
            $orderStatus = [
                'order_id' => $_order->order_id,
                'change_message' => '订单生成',
                'change_type' => 'create'
            ];

            $finalPrice = max(bcsub($cartInfo['order']['final_price'], $cartInfo['order']['coupon_price'], 2), 0);

            $allFinalPrice = $order['order_type'] ? $finalPrice : bcadd($finalPrice, $order['pay_postage'], 2);

            if ($cart['productPresell']['presell_type'] == 1) {
                $productPrice = bcsub($cartInfo['order']['pay_price'], $order['pay_postage'], 2);
            } else {
                $productPrice = bcadd($cartInfo['order']['pay_price'], $finalPrice, 2);
            }

            if (isset($receipt_data[$_order['mer_id']])) {
                app()->make(StoreOrderReceiptRepository::class)->add($receipt_data[$_order['mer_id']], $_order, $productPrice);
            }

            $orderProduct = [
                'order_id' => $_order->order_id,
                'cart_id' => $cart['cart_id'],
                'uid' => $uid,
                'product_id' => $cart['product_id'],
                'product_price' => $productPrice,
                'extension_one' => $spreadUid ? $cart['productPresellAttr']['bc_extension_one'] : 0,
                'extension_two' => $topUid ? $cart['productPresellAttr']['bc_extension_two'] : 0,
                'product_sku' => $cart['productAttr']['unique'],
                'product_num' => $cart['cart_num'],
                'product_type' => $cart['product_type'],
                'activity_id' => $cart['source_id'],
                'refund_num' => $cart['cart_num'],
                'cart_info' => json_encode([
                    'product' => $cart['product'],
                    'productAttr' => $cart['productAttr'],
                    'productPresell' => $cart['productPresell'],
                    'productPresellAttr' => $cart['productPresellAttr'],
                    'product_type' => $cart['product_type'],
                ])
            ];
            if ($cart['productPresell']['presell_type'] == 2) {
                $presellOrderRepository = app()->make(PresellOrderRepository::class);
                $presellOrderRepository->create([
                    'uid' => $uid,
                    'order_id' => $_order->order_id,
                    'mer_id' => $_order->mer_id,
                    'final_start_time' => $cart['productPresell']['final_start_time'],
                    'final_end_time' => $cart['productPresell']['final_end_time'],
                    'pay_price' => $allFinalPrice,
                    'presell_order_sn' => $presellOrderRepository->getNewOrderId()
                ]);
            }
            app()->make(ProductPresellSkuRepository::class)->incCount($cart['source_id'],$cart['productAttr']['unique'],'one_take');
            $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
            $userMerchantRepository = app()->make(UserMerchantRepository::class);
            $userMerchantRepository->getInfo($uid, $order['mer_id']);
            app()->make(MerchantRepository::class)->incSales($order['mer_id'], $order['total_num']);
            $storeOrderStatusRepository->create($orderStatus);
            $storeOrderProductRepository->create($orderProduct);
            return $groupOrder;
        });
        queue::push(SendTemplateMessageJob::class, ['tempCode' => 'ORDER_CREATE', 'id' => $group->group_order_id]);
        return $group;
    }

    /**
     * @param User $user
     * @param int $pay_type
     * @param array $cartId
     * @param int $addressId
     * @param array $coupons
     * @param array $takes
     * @param array $mark
     * @param array $receipt_data
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/11/2
     */
    public function createOrder(User $user, int $pay_type, array $cartId, int $addressId, array $coupons, array $takes, array $mark, array $receipt_data)
    {
        $uid = $user->uid;
        $couponUserRepository = app()->make(StoreCouponUserRepository::class);
        foreach ($coupons as $merId => $coupon) {
            if (!is_array($coupon)) {
                unset($coupons[$merId]);
                continue;
            }
            $storeCoupon = $coupon['store'] ?? ($coupon['store'] = 0);
            $productCoupon = array_unique($coupon['product'] ?? ($coupon['product'] = []));
            $_coupons = $storeCoupon ? array_merge($productCoupon, [$storeCoupon]) : $productCoupon;
            if (!count($_coupons)) {
                unset($coupons[$merId]);
                continue;
            }
            if (count($couponUserRepository->validIntersection($merId, $uid, $_coupons)) != count($_coupons))
                throw new ValidateException('请选择正确的优惠券');
        }
        $order = $this->cartIdByOrderInfo($uid, $takes, $cartId, $addressId, false);
        if ($order['status'] == 'noDeliver')
            throw new ValidateException('部分商品不支持该区域');
        if (!$order['address']) throw new ValidateException('请选择正确的收货地址');
        if ($order['order_type'] == '2') throw new ValidateException('预售商品请单独购买');
        if ($order['order_type']) $coupons = [];
        $orderPrice = 0;
        $orderInfo = $order['order'];
        $address = $order['address'];
        $orderList = [];
        $totalPostage = 0;
        $totalPayPrice = 0;
        $totalPayDuihuan = 0;
        $totalCouponPrice = 0;
        $totalNum = 0;
        $totalCost = 0;
        $allUseCoupon = [];
        $productCouponList = [];
        $storeCouponList = [];
        $spreadUid = $user->valid_spread_uid;
        $topUid = $user->valid_top_uid;
        $giveCouponIds = [];
        $merchantRepository = app()->make(MerchantRepository::class);
        foreach ($orderInfo as $k => $cartInfo) {

            if (in_array($cartInfo['mer_id'], $takes) && !$cartInfo['take']['mer_take_status'])
                throw new ValidateException('该店铺不支持到店自提');

            if (isset($receipt_data[$cartInfo['mer_id']]) && !$cartInfo['openReceipt'])
                throw new ValidateException('该店铺不支持开发票');

            $useCoupon = [];
            if (isset($coupons[$cartInfo['mer_id']])) {
                $coupon = $coupons[$cartInfo['mer_id']];
                if (count($coupon['product'])) {
                    foreach ($cartInfo['coupon'] as $_k => $_coupon) {
                        if (!$_coupon['coupon']['type']) continue;
                        if (in_array($_coupon['coupon_user_id'], $coupon['product'])) {
                            $productId = array_search($_coupon['coupon_user_id'], $coupon['product']);
                            if (!in_array($productId, array_column($_coupon['product'], 'product_id')))
                                throw new ValidateException('请选择正确的优惠券');
                            $useCoupon[] = $_coupon['coupon_user_id'];
                            unset($coupon['product'][$productId]);
                            $productCouponList[$productId] = [
                                'rate' => $cartInfo['order']['product_price'][$productId] > 0 ? bcdiv($_coupon['coupon_price'], $cartInfo['order']['product_price'][$productId], 4) : 1,
                                'coupon_price' => $_coupon['coupon_price'],
                                'price' => $cartInfo['order']['product_price'][$productId]
                            ];
                            $cartInfo['order']['pay_price'] = max(bcsub($cartInfo['order']['pay_price'], $_coupon['coupon_price'], 2), 0);
                            $cartInfo['order']['coupon_price'] = bcadd($cartInfo['order']['coupon_price'], $_coupon['coupon_price'], 2);
                        }
                    }
                    if (count($coupon['product'])) throw new ValidateException('请选择正确的优惠券');
                }
                if ($coupon['store']) {
                    $flag = false;
                    foreach ($cartInfo['coupon'] as $_coupon) {
                        if ($_coupon['coupon']['type']) continue;
                        if ($_coupon['coupon_user_id'] == $coupon['store']) {
                            $flag = true;
                            $useCoupon[] = $_coupon['coupon_user_id'];
                            $storeCouponList[$cartInfo['mer_id']] = [
                                'rate' => $cartInfo['order']['pay_price'] > 0 ? bcdiv($_coupon['coupon_price'], $cartInfo['order']['pay_price'], 4) : 1,
                                'coupon_price' => $_coupon['coupon_price'],
                                'price' => $cartInfo['order']['coupon_price']
                            ];
                            $cartInfo['order']['pay_price'] = max(bcsub($cartInfo['order']['pay_price'], $_coupon['coupon_price'], 2), 0);
                            $cartInfo['order']['coupon_price'] = bcadd($cartInfo['order']['coupon_price'], $_coupon['coupon_price'], 2);
                            break;
                        }
                    }
                    if (!$flag) throw new ValidateException('请选择正确的优惠券');
                }
            }
            $cost = 0;
            $total_extension_one = 0;
            $total_extension_two = 0;
            if (systemConfig('extension_status')) {
                foreach ($cartInfo['list'] as $cart) {
                    $totalNum += $cart['cart_num'];
                    $giveCouponIds = array_merge($giveCouponIds, $cart['product']['give_coupon_ids']);
                    $cost = bcadd(bcmul($cart['productAttr']['cost'], $cart['cart_num'], 2), $cost, 2);
                    if (!$cart['product_type']) {
                        if ($spreadUid && $cart['productAttr']['bc_extension_one'] > 0)
                            $total_extension_one = bcadd($total_extension_one, bcmul($cart['cart_num'], $cart['productAttr']['bc_extension_one'], 2), 2);
                        if ($topUid && $cart['productAttr']['bc_extension_two'] > 0)
                            $total_extension_two = bcadd($total_extension_two, bcmul($cart['cart_num'], $cart['productAttr']['bc_extension_two'], 2), 2);
                    }
                }
            }
            if (in_array($cartInfo['mer_id'], $takes)) {
                $cartInfo['order']['pay_price'] = max(bcsub($cartInfo['order']['pay_price'], $cartInfo['order']['postage_price'], 2), 0);
                $cartInfo['order']['postage_price'] = 0;
            } else {
                $cartInfo['order']['pay_price'] = max($cartInfo['order']['pay_price'], $cartInfo['order']['postage_price']);
            }

            $order_type_n = 0;
            //订单类型 1、普通区 3、促销区  4、兑换区 5、厂供区    6 创始专享区
            switch ( $cartInfo['list'][0]['product']['cate_parid'] ){
                case 181 :
                    $order_type_n = 3;
                    break;
                case 414 :
                    $order_type_n = 6;
                    break;
                case 186 :
                    $order_type_n = 1;
                    break;
                case 182 :
                    $order_type_n = 4;
                    break;
                case 180 :
                    $order_type_n = 5;
                    break;
            }


            //TODO 生成订单

            $_order = [
                'activity_type' => $order['order_type'],
                'commission_rate' => (float)$merchantRepository->get($cartInfo['mer_id'])->mer_commission_rate,
                'order_type' => in_array($cartInfo['mer_id'], $takes) == 1 ? 1 : 0,
                'extension_one' => $total_extension_one,
                'extension_two' => $total_extension_two,
                'orderInfo' => $cartInfo['order'],
                'cartInfo' => $cartInfo['list'],
                'order_sn' => $this->getNewOrderId() . ($k + 1),
                'uid' => $uid,
                'real_name' => $address['real_name'],
                'user_phone' => $address['phone'],
                'user_address' => $address['province'] . $address['city'] . $address['district'] . ' ' . $address['detail'],
                'cart_id' => implode(',', array_column($cartInfo['list'], 'cart_id')),
                'total_num' => $cartInfo['order']['total_num'],
                'total_price' => $cartInfo['order']['total_price'],
                'total_postage' => $cartInfo['order']['postage_price'],
                'pay_postage' => $cartInfo['order']['postage_price'],
                'pay_price' => $cartInfo['order']['pay_price'],
                'mer_id' => $cartInfo['mer_id'],
                'cost' => $cost,
                'coupon_id' => implode(',', $useCoupon),
                'mark' => $mark[$cartInfo['mer_id']] ?? '',
                'coupon_price' => $cartInfo['order']['coupon_price'] > $cartInfo['order']['total_price'] ? $cartInfo['order']['total_price'] : $cartInfo['order']['coupon_price'],
                'pay_type' => $pay_type,

                'ranglibs'=> $cartInfo['list'][0]['product']['rangli'] ,//倍数
                'rangli_coin'=> bcmul( bcdiv($cartInfo['list'][0]['product']['rangli'] , 10 ,2)  ,   $cartInfo['order']['total_price'] ,2),
                'order_type_n'=>$order_type_n ,
                'pay_duihuan'=> $cartInfo['order']['pay_duihuan'],
                'is_gift_bag_n'=>  $cartInfo['list'][0]['product']['is_gift_bag'],

                'sheng'=> $address['province'] ,
                'shi'=>$address['city']  ,
                'xian'=>$address['district'] ,
            ];


            $allUseCoupon = array_merge($allUseCoupon, $useCoupon);
            $orderList[] = $_order;
            $orderInfo[$k] = $cartInfo;
            $orderPrice = bcadd($orderPrice, $_order['total_price'], 2);
            $totalPostage = bcadd($totalPostage, $_order['total_postage'], 2);
            $totalPayPrice = bcadd($totalPayPrice, $_order['pay_price'], 2);
            if(in_array($order_type_n,[4,5]))$totalPayDuihuan =  bcadd($totalPayDuihuan, $_order['pay_duihuan'], 2);
            $totalCouponPrice = bcadd($totalCouponPrice, $_order['coupon_price'], 2);
            $totalCost = bcadd($totalCost, $cost, 2);
        }
        $groupOrder = [
            'uid' => $uid,
            'group_order_sn' => $this->getNewOrderId() . '0',
            'total_postage' => $totalPostage,
            'total_price' => $orderPrice,
            'total_num' => $totalNum,
            'real_name' => $address['real_name'],
            'user_phone' => $address['phone'],
            'user_address' => $address['province'] . $address['city'] . $address['district'] . ' ' . $address['detail'],
            'pay_price' => $totalPayPrice,
            'pay_duihuan' => $totalPayDuihuan,
            'coupon_price' => $totalCouponPrice,
            'pay_postage' => $totalPostage,
            'cost' => $totalCost,
            'pay_type' => $pay_type,
            'give_coupon_ids' => $giveCouponIds
        ];
        $storeGroupOrderRepository = app()->make(StoreGroupOrderRepository::class);
        $storeCartRepository = app()->make(StoreCartRepository::class);
        $attrValueRepository = app()->make(ProductAttrValueRepository::class);
        $productRepository = app()->make(ProductRepository::class);
        $storeOrderProductRepository = app()->make(StoreOrderProductRepository::class);

        $group = Db::transaction(function () use ($topUid, $spreadUid, $uid, $receipt_data, $productCouponList, $storeCouponList, $allUseCoupon, $couponUserRepository, $storeOrderProductRepository, $productRepository, $attrValueRepository, $storeCartRepository, $storeGroupOrderRepository, $groupOrder, $orderList, $orderInfo) {
            $cartIds = [];
            $uniqueList = [];
            //更新库存
            foreach ($orderInfo as $cartInfo) {
                $cartIds = array_merge($cartIds, array_column($cartInfo['list'], 'cart_id'));
                foreach ($cartInfo['list'] as $cart) {
                    if (!isset($uniqueList[$cart['productAttr']['product_id'] . $cart['productAttr']['unique']]))
                        $uniqueList[$cart['productAttr']['product_id'] . $cart['productAttr']['unique']] = true;
                    else
                        throw new ValidateException('购物车商品信息重复');

                    try{
                        if ($cart['product_type'] == '1') {
                            $attrValueRepository->descSkuStock($cart['product']['old_product_id'], $cart['productAttr']['sku'], $cart['cart_num']);
                            $productRepository->descStock($cart['product']['old_product_id'], $cart['cart_num']);
                        } else if ($cart['product_type'] == '3') {
                            app()->make(ProductAssistSkuRepository::class)->descStock($cart['productAssistAttr']['product_assist_id'], $cart['productAssistAttr']['unique'], $cart['cart_num']);
                            $productRepository->descStock($cart['product']['old_product_id'], $cart['cart_num']);
                            $attrValueRepository->descStock($cart['product']['old_product_id'], $cart['productAttr']['unique'], $cart['cart_num']);
                        } else if ($cart['product_type'] == '4') {
                            app()->make(ProductGroupSkuRepository::class)->descStock($cart['activeSku']['product_group_id'], $cart['activeSku']['unique'], $cart['cart_num']);
                            $productRepository->descStock($cart['product']['old_product_id'], $cart['cart_num']);
                            $attrValueRepository->descStock($cart['product']['old_product_id'], $cart['productAttr']['unique'], $cart['cart_num']);
                        } else {
                            $attrValueRepository->descStock($cart['productAttr']['product_id'], $cart['productAttr']['unique'], $cart['cart_num']);
                            $productRepository->descStock($cart['product']['product_id'], $cart['cart_num']);
                        }
                    }catch (Exception $e){
                        throw new ValidateException('库存不足');
                    }
                }
            }
            //修改购物车状态
            $storeCartRepository->updates($cartIds, [
                'is_pay' => 1
            ]);
            //使用优惠券
            if (count($allUseCoupon)) {
                $couponUserRepository->updates($allUseCoupon, [
                    'use_time' => date('Y-m-d H:i:s'),
                    'status' => 1
                ]);
            }
            $groupOrder = $storeGroupOrderRepository->create($groupOrder);
            foreach ($orderList as $k => $order) {
                $orderList[$k]['group_order_id'] = $groupOrder->group_order_id;
            }
            $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
            $orderProduct = [];
            $orderStatus = [];
            $userMerchantRepository = app()->make(UserMerchantRepository::class);
            foreach ($orderList as $order) {
                $cartInfo = $order['cartInfo'];
                $_orderInfo = $order['orderInfo'];
                unset($order['cartInfo'], $order['orderInfo']);
                $_order = $this->dao->create($order);
                if (isset($receipt_data[$_order['mer_id']])) {
                    app()->make(StoreOrderReceiptRepository::class)->add($receipt_data[$_order['mer_id']], $_order);
                }
                foreach ($cartInfo as $k => $cart) {
                    $cartTotalPrice = bcmul($this->cartByPrice($cart), $cart['cart_num'], 2);
                    if (!$cart['product_type'] && $cartTotalPrice > 0 && isset($productCouponList[$cart['product_id']])) {
                        if ($productCouponList[$cart['product_id']]['rate'] >= 1) {
                            $cartTotalPrice = 0;
                        } else {
                            array_pop($_orderInfo['product_cart']);
                            if (!count($_orderInfo['product_cart'])) {
                                $cartTotalPrice = bcsub($cartTotalPrice, $productCouponList[$cart['product_id']]['coupon_price'], 2);
                            } else {
                                $couponPrice = bcmul($cartTotalPrice, $productCouponList[$cart['product_id']]['rate'], 2);
                                $cartTotalPrice = bcsub($cartTotalPrice, $couponPrice, 2);
                                $productCouponList[$cart['product_id']]['coupon_price'] = bcsub($productCouponList[$cart['product_id']]['coupon_price'], $couponPrice, 2);
                            }
                        }
                    }

                    if (!$cart['product_type'] && $cartTotalPrice > 0 && isset($storeCouponList[$order['mer_id']])) {
                        if ($storeCouponList[$order['mer_id']]['rate'] >= 1) {
                            $cartTotalPrice = 0;
                        } else {
                            if (count($cartInfo) == $k + 1) {
                                $cartTotalPrice = bcsub($cartTotalPrice, $storeCouponList[$order['mer_id']]['coupon_price'], 2);
                            } else {
                                $couponPrice = bcmul($cartTotalPrice, $storeCouponList[$order['mer_id']]['rate'], 2);
                                $cartTotalPrice = bcsub($cartTotalPrice, $couponPrice, 2);
                                $storeCouponList[$order['mer_id']]['coupon_price'] = bcsub($storeCouponList[$order['mer_id']]['coupon_price'], $couponPrice, 2);
                            }
                        }
                    }

                    $cartTotalPrice = max($cartTotalPrice, 0);

                    $orderStatus[] = [
                        'order_id' => $_order->order_id,
                        'change_message' => '订单生成',
                        'change_type' => 'create'
                    ];

                    $info = [];
                    if ($cart['product_type'] == '3') {
                        $info = [
                            'productAssistAttr' => $cart['productAssistAttr'],
                            'productAssistSet' => $cart['productAssistSet'],
                        ];
                    } else if ($cart['product_type'] == '4') {
                        $info = [
                            'activeSku' => $cart['activeSku']
                        ];
                    }

                    $orderProduct[] = [
                        'order_id' => $_order->order_id,
                        'cart_id' => $cart['cart_id'],
                        'uid' => $uid,
                        'product_id' => $cart['product_id'],
                        'activity_id' => $cart['source'] >= 3 ? $cart['source_id'] : $cart['product_id'],
                        'product_price' => $cartTotalPrice,
                        'extension_one' => $spreadUid ? $cart['productAttr']['bc_extension_one'] : 0,
                        'extension_two' => $topUid ? $cart['productAttr']['bc_extension_two'] : 0,
                        'product_sku' => $cart['productAttr']['unique'],
                        'product_num' => $cart['cart_num'],
                        'refund_num' => $cart['cart_num'],
                        'product_type' => $cart['product_type'],
                        'cart_info' => json_encode([
                                'product' => $cart['product'],
                                'productAttr' => $cart['productAttr'],
                                'product_type' => $cart['product_type']
                            ] + $info)
                    ];
                }
                $userMerchantRepository->getInfo($uid, $order['mer_id']);
                app()->make(MerchantRepository::class)->incSales($order['mer_id'], $order['total_num']);
            }
            $storeOrderStatusRepository->insertAll($orderStatus);
            $storeOrderProductRepository->insertAll($orderProduct);
            return $groupOrder;
        });
        foreach ($orderInfo as $cartInfo) {
            foreach ($cartInfo['list'] as $cart) {
                if (($cart['productAttr']['stock'] - $cart['cart_num']) < (int)merchantConfig($cartInfo['mer_id'], 'mer_store_stock')) {
                    SwooleTaskService::merchant('notice', [
                        'type' => 'min_stock',
                        'data' => [
                            'title' => '库存不足',
                            'message' => $cart['product']['store_name'] . '(' . $cart['productAttr']['sku'] . ')库存不足',
                            'id' => $cart['product']['product_id']
                        ]
                    ], $cartInfo['mer_id']);
                }
            }
        }
        queue::push(SendTemplateMessageJob::class, ['tempCode' => 'ORDER_CREATE', 'id' => $group->group_order_id]);
        return $group;
    }

    /**
     * @param string $type
     * @param User $user
     * @param StoreGroupOrder $groupOrder
     * @param string $return_url
     * @return mixed
     * @author xaboy
     * @day 2020/10/22
     */
    public function pay(string $type, User $user, StoreGroupOrder $groupOrder, $return_url = '',$is_app = false)
    {
        $method = 'pay' . ucfirst($type);
        if (!method_exists($this, $method))
            throw new ValidateException('不支持该支付方式');
        return $this->{$method}($user, $groupOrder, $return_url,$is_app);
    }

    public  function payyunfastpay_app(User $user, StoreGroupOrder $groupOrder ){

        $typeField = "ORG";
        $secretKey = "L2DYNQ5YR9P532ZTX8WNTBWX";
        $mchtCd = "MCHT965103250";
        $orgCd = "202010209693553";
        // $reqUrl = "http://test.api.route.hangmuxitong.com";
        $reqUrl = "https://api.yunfastpay.com";

        $APPID = 'wx94545fc9bb1c09c1';
//        $APPID = 'wx4c524b1e99602280';


//机构号：202010209693553
//机构密钥：L2DYNQ5YR9P532ZTX8WNTBWX
//商户号：MCHT965103250
//商户名称：光彩万众（广东）数字科技有限公司
//

        //交易码，固定：TRANS1119
        $reqData["trscode"] = "TRANS1119";
        //机构号
        $reqData["orgCd"] = $orgCd;
        //商户编号
        $reqData["mchtCd"] = $mchtCd;

//        $reqData["appid"] = $APPID;

        //外部订单号
        $reqData["outOrderId"] = $groupOrder['group_order_sn']; // 此处仅做示例,故以时间戳为值
        //交易金额, 单位：元
        $reqData["transAmt"] = $groupOrder['pay_price'];//"0.1";
//        $reqData["transAmt"] =  "0.1";
        //产品编号，统一下单：tran
        $reqData["proCd"] = "tran";
        //费率通道，公众号支付：1，生活号支付：1，小程序支付：7，H5支付/APP支付：7
        $reqData["chanelType"] = "7";
        //订单标题，非必填
        $reqData["outOrderTitle"] = "下单";
        //订单描述，非必填
        $reqData["outOrderDesc"] = "购物";
        //浏览器，区分消费者支付方式：支付宝：alipay，微信：wxpay
        $reqData["browser"] = "wxpay";
        //收银台回调地址，用户支付完成后显示的页面。
//        /pages/order_pay_status/index?order_id=37
        $reqData["frontUrl"] =   rtrim( systemConfig('site_url') , '/') .'/pages/users/order_list/index'  ;

        $reqData["notifyUrl"] =  rtrim( systemConfig('site_url') , '/') . Route::buildUrl('fastpayNotify')->build() ;

        //订单有效时间,YYYYMMDDHHMMSS，默认1小时
        // $reqData["expireTime"] = "20180807171001";
        //是否分账，0：不分账，1：分账。为空时默认分帐。
        $reqData["isSplitBill"] = "0";

        Log::write('请求报文：' . json_encode($reqData),'notice');

        $encReqData = $this->encrypt(json_encode($reqData), $secretKey);
        $data = [];
        $data["typeField"] = $typeField;
        $data["keyField"] = $orgCd;
        $data["dataField"] = $encReqData;

        $encRespStr = $this->send_request($reqUrl, json_encode($data));

        $respMsg = json_decode($encRespStr, true);

        $respStr = $this->decrypt($respMsg["dataField"], $secretKey);

        Log::write('三方接口返回数据3-app：' . $respStr ,'notice');

        $respData = json_decode($respStr, true);

        if($respData  && "0000" == $respData["respCode"] && $respData['orderCd']   &&  $respData['sign'] ){
            return app('json')->status('yunfastpay', '请支付！', ['orderCd' => $respData['orderCd'] , 'sign' => $respData['sign'] , 'order_id' => $groupOrder['group_order_id'] ]);
        }
        else {
            return app('json')->fail('网关错误请稍后再试！');
        }

    }

    public function payyunfastpay(User $user, StoreGroupOrder $groupOrder,$return_url,$is_app){
        Log::write('快付订单数据'.$groupOrder,'notice');


        if($is_app){
                return $this->payyunfastpay_app( $user,  $groupOrder);
        }

        $typeField = "ORG";
        $secretKey = "L2DYNQ5YR9P532ZTX8WNTBWX";
        $mchtCd = "MCHT965103250";
        $orgCd = "202010209693553";
        // $reqUrl = "http://test.api.route.hangmuxitong.com";
        $reqUrl = "https://api.yunfastpay.com";


//机构号：202010209693553
//机构密钥：L2DYNQ5YR9P532ZTX8WNTBWX
//商户号：MCHT965103250
//商户名称：光彩万众（广东）数字科技有限公司
//

        //交易码，固定：TRANS1119
        $reqData["trscode"] = "TRANS1119";
        //机构号
        $reqData["orgCd"] = $orgCd;
        //商户编号
        $reqData["mchtCd"] = $mchtCd;
        //外部订单号
        $reqData["outOrderId"] = $groupOrder['group_order_sn']; // 此处仅做示例,故以时间戳为值
        //交易金额, 单位：元
        $reqData["transAmt"] = $groupOrder['pay_price'];//"0.1";
        //产品编号，统一下单：tran
        $reqData["proCd"] = "tran";
        //费率通道，公众号支付：1，生活号支付：1，小程序支付：7，H5支付/APP支付：7
        $reqData["chanelType"] = "7";
        //订单标题，非必填
        $reqData["outOrderTitle"] = "下单";
        //订单描述，非必填
        $reqData["outOrderDesc"] = "购物";
        //浏览器，区分消费者支付方式：支付宝：alipay，微信：wxpay
         $reqData["browser"] = "wxpay";
        //收银台回调地址，用户支付完成后显示的页面。
//        /pages/order_pay_status/index?order_id=37
        $reqData["frontUrl"] =   rtrim( systemConfig('site_url') , '/') .'/pages/users/order_list/index'  ;

        $reqData["notifyUrl"] =  rtrim( systemConfig('site_url') , '/') . Route::buildUrl('fastpayNotify')->build() ;

        //订单有效时间,YYYYMMDDHHMMSS，默认1小时
        // $reqData["expireTime"] = "20180807171001";
        //是否分账，0：不分账，1：分账。为空时默认分帐。
        $reqData["isSplitBill"] = "0";

        Log::write('请求报文：' . json_encode($reqData),'notice');

        $encReqData = $this->encrypt(json_encode($reqData), $secretKey);
        $data = [];
        $data["typeField"] = $typeField;
        $data["keyField"] = $orgCd;
        $data["dataField"] = $encReqData;


        $encRespStr = $this->send_request($reqUrl, json_encode($data));

        $respMsg = json_decode($encRespStr, true);

        $respStr = $this->decrypt($respMsg["dataField"], $secretKey);

        Log::write('三方接口返回数据3：' . $respStr ,'notice');

        $respData = json_decode($respStr, true);

        if($respData  && "0000" == $respData["respCode"]){

            $url = mb_ereg_replace('%23','#',$respData["qrData"]);

            return app('json')->status('yunfastpay', '请支付！', ['url' => $url , 'order_id' => $groupOrder['group_order_id']]);

//                /*交易成功*/
//                Db::transaction(function () use ($user, $groupOrder) {
//                    // $user->now_money = bcsub($user->now_money, $groupOrder['pay_price'], 2);
//                    // $user->save();
//                    $userBillRepository = app()->make(UserBillRepository::class);
//                    $userBillRepository->decBill($user['uid'], 'now_money', 'pay_product', [
//                        'link_id' => $groupOrder['group_order_id'],
//                        'status' => 1,
//                        'title' => '购买商品',
//                        'number' => $groupOrder['pay_price'],
//                        'mark' => '余额支付支付' . floatval($groupOrder['pay_price']) . '元购买商品',
//                        'balance' => $user->now_money
//                    ]);
//                    $this->paySuccess($groupOrder);
//                });
//                return app('json')->status('success', '快付支付成功', ['order_id' => $groupOrder['group_order_id']]);
//            /* 交易正常 */
//            // echo $respData["respMsg"] . "<br/>";
//            if("100" == $respData["transStatus"]){
//                /*交易成功*/
//                Db::transaction(function () use ($user, $groupOrder) {
//                    // $user->now_money = bcsub($user->now_money, $groupOrder['pay_price'], 2);
//                    // $user->save();
//                    $userBillRepository = app()->make(UserBillRepository::class);
//                    $userBillRepository->decBill($user['uid'], 'now_money', 'pay_product', [
//                        'link_id' => $groupOrder['group_order_id'],
//                        'status' => 1,
//                        'title' => '购买商品',
//                        'number' => $groupOrder['pay_price'],
//                        'mark' => '余额支付支付' . floatval($groupOrder['pay_price']) . '元购买商品',
//                        'balance' => $user->now_money
//                    ]);
//                    $this->paySuccess($groupOrder);
//                });
//                return app('json')->status('success', '快付支付成功', ['order_id' => $groupOrder['group_order_id']]);
//
//
//            }else if("102" ==  $respData["transStatus"]){
//                /*交易失败*/
//            } else {
//                /*交易状态未知，请调查询接口获取最终状态*/
//            }
        }
        else {

            return app('json')->fail('网关错误请稍后再试！');

            /* 交易异常 */
            // echo $respData["respMsg"] . "<br/>";
            /*交易状态未知，请调查询接口获取最终状态*/
        }
//        Log::write('结果：' . $respData,'notice');
        // $ret=file_get_contents($url);
        // $context = array(
        //     'http' => array(
        //     'method' => 'POST',
        //     'header' => 'Content-type: application/json; charset= utf-8' .
        //                     '\r\n'.'User-Agent : Jimmy\'s POST Example beta' .
        //                     '\r\n'.'Content-length:' . strlen($data) + 8,
        //     'content' => 'mypost=' . $data)
        // );
        // $stream_context = stream_context_create($context);
        // $res = file_get_contents($url, false, $stream_context);
        // print_r($res);
    }


    public function yunPay($sn,$money,$rate,$sub_mer,$env){
        Log::write('快付-扫码-订单数据$sn='.$sn.'----$money='.$money.'---$env='.$env);

        bcscale(2);

        $typeField = "ORG";
        $secretKey = "L2DYNQ5YR9P532ZTX8WNTBWX";
        $mchtCd = "MCHT965103250";
        $orgCd = "202010209693553";
        // $reqUrl = "http://test.api.route.hangmuxitong.com";
        $reqUrl = "https://api.yunfastpay.com";

//机构号：202010209693553
//机构密钥：L2DYNQ5YR9P532ZTX8WNTBWX
//商户号：MCHT965103250
//商户名称：光彩万众（广东）数字科技有限公司
//

        //交易码，固定：TRANS1119
        $reqData["trscode"] = "TRANS1119";
        //机构号
        $reqData["orgCd"] = $orgCd;
        //商户编号
        $reqData["mchtCd"] = $sub_mer;//$mchtCd;
        //外部订单号
        $reqData["outOrderId"] = $sn; // 此处仅做示例,故以时间戳为值
        //交易金额, 单位：元
        $reqData["transAmt"] = $money;//"0.1";
        //产品编号，统一下单：tran
        $reqData["proCd"] = "tran";
        //费率通道，公众号支付：1，生活号支付：1，小程序支付：7，H5支付/APP支付：7
        $reqData["chanelType"] = "7";
        //订单标题，非必填
        $reqData["outOrderTitle"] = "扫码支付";
        //订单描述，非必填
        $reqData["outOrderDesc"] = "支付";
        //浏览器，区分消费者支付方式：支付宝：alipay，微信：wxpay
        $reqData["browser"] = $env;//"wxpay";
        //收银台回调地址，用户支付完成后显示的页面。
//        /pages/order_pay_status/index?order_id=37
        $reqData["frontUrl"] =   rtrim( systemConfig('site_url') , '/') .'/pages/users/order_list/index'  ;

        $reqData["notifyUrl"] =  rtrim( systemConfig('site_url') , '/') . Route::buildUrl('fastpayNotify')->build() ;

        $reqData["isSplitBill"] = "1";


        //分账列表
        $reqData["itemsList"] = [
            //平台
            [
                "item1" => "1",
        /*
         * 分账角色，服务商:SERVICE_PROVIDER，门店: STORE，员工:STAFF，店主:STORE_OWNER
         * 合作伙伴:PARTNER,总部:HEADQUARTER,品牌方:BRAND,分销商:DISTRIBUTOR,用户:USER,供应商:SUPPLIER
         */
        "item2" => "SERVICE_PROVIDER",
        //分账接收方,接收方商户号。
        "item3" => $mchtCd,//"MCHT100012134",
        //手续费承担方，只能有一方承担,是	0：否，1：是，部分通道不支持,可以先问下业务。
        "item4" =>  "1",
        //分账ID类型，固定02，userId：00，loginName：01，商户id：02，个人微信号：03
        "item5" => "02",
        //分账描述
        "item9" => "平台分佣",
        //分账金额，单位(元)
        "item10" => bcmul(bcmul($rate , $money ),0.01) ,
        //分账者省代码，可空
        "item11" => "",
        //分账者市代码，可空
        "item12" => "",
        //分账者区代码，可空
        "item13" => "",
            ],
        //商户
          [
              "item1" => "1",
        /*
         * 分账角色，服务商:SERVICE_PROVIDER，门店: STORE，员工:STAFF，店主:STORE_OWNER
         * 合作伙伴:PARTNER,总部:HEADQUARTER,品牌方:BRAND,分销商:DISTRIBUTOR,用户:USER,供应商:SUPPLIER
         */
        "item2" => "SERVICE_PROVIDER",
        //分账接收方,接收方商户号。
        "item3" => $sub_mer, //"MCHT100012134",
        //手续费承担方，只能有一方承担,是	0：否，1：是，部分通道不支持,可以先问下业务。
        "item4" =>  "0",
        //分账ID类型，固定02，userId：00，loginName：01，商户id：02，个人微信号：03
        "item5" => "02",
        //分账描述
        "item9" => "付款",
        //分账金额，单位(元)
        "item10" => bcsub($money , bcmul(bcmul($rate , $money ),0.01))  ,
        //分账者省代码，可空
        "item11" => "",
        //分账者市代码，可空
        "item12" => "",
        //分账者区代码，可空
        "item13" => "",
            ]
        ];


        Log::write('请求报文：' . json_encode($reqData),'notice');

        $encReqData = $this->encrypt(json_encode($reqData), $secretKey);
        $data = []; 
        $data["typeField"] = $typeField;
        $data["keyField"] = $orgCd;
        $data["dataField"] = $encReqData;


        $encRespStr = $this->send_request($reqUrl, json_encode($data));

        $respMsg = json_decode($encRespStr, true);

        $respStr = $this->decrypt($respMsg["dataField"], $secretKey);

        Log::write('三方接口返回数据3：' . $respStr ,'notice');

        $respData = json_decode($respStr, true);

        if("0000" == $respData["respCode"]){

            $url = mb_ereg_replace('%23','#',$respData["qrData"]);

            return $url;

        }
        else {

            return  false;

        }
    }




//加密
public function encrypt($input, $key)
{
    Log::write('进入加密：' . $input,'notice');
    return openssl_encrypt($input,'des-ede3', $key, 0);
}

//解密
public function decrypt($encrypted, $key)
{
    return openssl_decrypt(base64_decode($encrypted), 'des-ede3', $key, 1);
}

public function pkcs5_pad ($text, $blocksize)
{
    $pad = $blocksize - (strlen($text) % $blocksize);
    return $text . str_repeat(chr($pad), $pad);
}

public function pkcs5_unpad($text)
{
    $pad = ord($text{strlen($text)-1});
    if ($pad > strlen($text))
    {
    return false;
    }
    if (strspn($text, chr($pad), strlen($text) - $pad) != $pad)
    {
        return false;
    }
    return substr($text, 0, -1 * $pad);
}

public function send_request($url, $params = [], $method = 'POST', $options = [])
{
    Log::write('send_request1');
    $method = strtoupper($method);
    $protocol = substr($url, 0, 5);
    $query_string = is_array($params) ? http_build_query($params) : $params;

    $ch = curl_init();
    $defaults = [];
    if ('GET' == $method)
    {
        $geturl = $query_string ? $url . (stripos($url, "?") !== FALSE ? "&" : "?") . $query_string : $url;
        $defaults[CURLOPT_URL] = $geturl;
    }
    else
    {
        Log::write('send_request2');
        $defaults[CURLOPT_URL] = $url;
        if ($method == 'POST')
        {
            $defaults[CURLOPT_POST] = 1;
        }
        else
        {
            $defaults[CURLOPT_CUSTOMREQUEST] = $method;
        }
        $defaults[CURLOPT_POSTFIELDS] = $query_string;
    }
    Log::write('send_request3');
    $defaults[CURLOPT_HEADER] = FALSE;
    $defaults[CURLOPT_USERAGENT] = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.98 Safari/537.36";
    $defaults[CURLOPT_FOLLOWLOCATION] = TRUE;
    $defaults[CURLOPT_RETURNTRANSFER] = TRUE;
    $defaults[CURLOPT_CONNECTTIMEOUT] = 30;
    $defaults[CURLOPT_TIMEOUT] = 30;

    // disable 100-continue
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Expect:", "Content-Type:application/json"));

    if ('https' == $protocol)
    {
        $defaults[CURLOPT_SSL_VERIFYPEER] = FALSE;
        $defaults[CURLOPT_SSL_VERIFYHOST] = FALSE;
    }

    curl_setopt_array($ch, (array) $options + $defaults);

    $ret = curl_exec($ch);
    $err = curl_error($ch);
    Log::write('send_request4');
    Log::write('-------');
    Log::write($ret);
    Log::write($err);
    Log::write('-------');
    if (FALSE === $ret || !empty($err))
    {
        $errno = curl_errno($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        return [
            'ret'   => FALSE,
            'errno' => $errno,
            'msg'   => $err,
            'info'  => $info,
        ];
    }
    curl_close($ch);
    return $ret;
}





















    /**
     * @param User $user
     * @param StoreGroupOrder $groupOrder
     * @return mixed
     * @author xaboy
     * @day 2020/6/9
     */
    public function payBalance(User $user, StoreGroupOrder $groupOrder)
    {
        if (!systemConfig('yue_pay_status'))
            throw new ValidateException('未开启余额支付');
        if ($user['now_money'] < $groupOrder['pay_price'])
            throw new ValidateException('余额不足' . floatval($groupOrder['pay_price']));
        Db::transaction(function () use ($user, $groupOrder) {
            $user->now_money = bcsub($user->now_money, $groupOrder['pay_price'], 2);
            $user->save();
            $userBillRepository = app()->make(UserBillRepository::class);
            $userBillRepository->decBill($user['uid'], 'now_money', 'pay_product', [
                'link_id' => $groupOrder['group_order_id'],
                'status' => 1,
                'title' => '购买商品',
                'number' => $groupOrder['pay_price'],
                'mark' => '余额支付支付' . floatval($groupOrder['pay_price']) . '元购买商品',
                'balance' => $user->now_money
            ]);
            $this->paySuccess($groupOrder);
        });
        return app('json')->status('success', '余额支付成功', ['order_id' => $groupOrder['group_order_id']]);
    }

    public function changePayType(StoreGroupOrder $groupOrder, int $pay_type)
    {
        Db::transaction(function () use ($groupOrder, $pay_type) {
            $groupOrder->pay_type = $pay_type;
            foreach ($groupOrder->orderList as $order) {
                $order->pay_type = $pay_type;
                $order->save();
            }
            $order->save();
        });
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020/8/3
     */
    public function verifyCode()
    {
        $code = substr(uniqid('', true), 15) . substr(microtime(), 2, 8);
        if ($this->dao->existsWhere(['verify_code' => $code]))
            return $this->verifyCode();
        else
            return $code;
    }

    /**
     * //TODO 支付成功后
     *
     * @param StoreGroupOrder $groupOrder
     * @author xaboy
     * @day 2020/6/9
     */
    public function paySuccess(StoreGroupOrder $groupOrder)
    {
        $groupOrder->append(['user']);
        //修改订单状态
        Db::transaction(function () use ($groupOrder) {
            $time = date('Y-m-d H:i:s');
            $groupOrder->paid = 1;
            $groupOrder->pay_time = $time;
            $orderStatus = [];
            $groupOrder->append(['orderList.orderProduct']);
            $flag = true;
            $finance = [];
            $financialRecordRepository = app()->make(FinancialRecordRepository::class);
            $financeSn = $financialRecordRepository->getSn();
            $userMerchantRepository = app()->make(UserMerchantRepository::class);
            $uid = $groupOrder->uid;
            $cs = false;
            foreach ($groupOrder->orderList as $_k => $order) {

                if($order->orderProduct[0]['cart_info']['product']['cate_parid'] == 414)    $cs = true;


                $order->paid = 1;
                $order->pay_time = $time;
                //todo 等待付尾款
                if ($order->activity_type == 2) {
                    $_make = app()->make(ProductPresellSkuRepository::class);
                    if ($order->orderProduct[0]['cart_info']['productPresell']['presell_type'] == 2) {
                        $order->status = 10;
                    } else {
                        $_make->incCount($order->orderProduct[0]['activity_id'], $order->orderProduct[0]['product_sku'], 'two_pay');
                    }
                    $_make->incCount($order->orderProduct[0]['activity_id'], $order->orderProduct[0]['product_sku'], 'one_pay');
                } else if ($order->activity_type == 4) {
                    $group_buying_id = app()->make(ProductGroupBuyingRepository::class)->create($groupOrder->user, $order->orderProduct[0]['cart_info']['activeSku']['product_group_id'], $order->orderProduct[0]['activity_id'], $order->order_id);
                    $order->orderProduct[0]->activity_id = $group_buying_id;
                    $order->orderProduct[0]->save();
                    $order->status = 9;
                } else if ($order->activity_type == 3) {
                    //更新助力状态
                    app()->make(ProductAssistSetRepository::class)->changStatus($order->orderProduct[0]['activity_id']);
                }
                if ($order->order_type == 1 && $order->status != 10)
                    $order->verify_code = $this->verifyCode();
                $order->save();
                $orderStatus[] = [
                    'order_id' => $order->order_id,
                    'change_message' => '订单支付成功',
                    'change_type' => 'pay_success'
                ];

//                foreach ($order->orderProduct as $product) {
//                    if ($flag && $product['cart_info']['product']['is_gift_bag']) {
//                        app()->make(UserRepository::class)->promoter($order->uid);
//                        $flag = false;
//
//                        //n1///
//                        $ctime=date("Y-m-d H:i:s");
//                        $user_k1 = Db::table('eb_user')->find($groupOrder->uid);
//                        if(!$user_k1['is_promoter']){
//                            $saveDate = [
//                                'uid' => $user_k1['uid'],
//                                'link_id' =>$order->order_id,
//                                'pm'=>1,  //0 = 支出 1 = 获得
//                                'title'=>'购买渠道商品',
//                                'category' => 'brokerage_gongxian',
//                                'number'=> 1000 ,
//                                'balance'=> $user_k1['brokerage_duihuan'],
//                                'mark'=>'购买渠道商品，赠送 1000 贡献值',
//                                'create_time'=>$ctime,
//                                'status'=>1
//                            ];
//                            Db::table('eb_user_bill')->save($saveDate);
//
//                            Db::table('eb_user')->update([
//                                'uid'=>$user_k1['uid'],
//                                'brokerage_gongxian'=>$user_k1['brokerage_gongxian'] + 1000,
//                            ]);
//
//                        }
//
//                    }
//                }

                $finance[] = [
                    'order_id' => $order->order_id,
                    'order_sn' => $order->order_sn,
                    'user_info' => $groupOrder->user->nickname,
                    'user_id' => $uid,
                    'financial_type' => 'order',
                    'financial_pm' => 1,
                    'number' => $order->pay_price,
                    'mer_id' => $order->mer_id,
                    'financial_record_sn' => $financeSn . ($_k + 1)
                ];
                $userMerchantRepository->updatePayTime($uid, $order->mer_id, $order->pay_price);
                SwooleTaskService::merchant('notice', [
                    'type' => 'new_order',
                    'data' => [
                        'title' => '新订单',
                        'message' => '您有一个新的订单',
                        'id' => $order->order_id
                    ]
                ], $order->mer_id);
                //自动打印订单
                $this->autoPrinter($order->order_id, $order->mer_id);
            }
            app()->make(UserRepository::class)->update($groupOrder->uid, [
                'pay_count' => Db::raw('pay_count+' . count($groupOrder->orderList)),
                'pay_price' => Db::raw('pay_price+' . $groupOrder->pay_price),
            ]);
            $financialRecordRepository->insertAll($finance);
            app()->make(StoreOrderStatusRepository::class)->insertAll($orderStatus);
            if (count($groupOrder['give_coupon_ids']) > 0)
                $groupOrder['give_coupon_ids'] = app()->make(StoreCouponRepository::class)->getGiveCoupon($groupOrder['give_coupon_ids'])->column('coupon_id');
            $groupOrder->save();

//            if($cs){
//                $user_p = Db::table('eb_user')->find($uid);
//                if(!$user_p['is_promoter']){
//                    Db::table('eb_user')->where(['uid'=>$user_p['uid']])->update([
//                        'is_promoter'=>1
//                    ]);
//                }
//                $user_p = Db::table('eb_user')->find($user_p['spread_uid']);
//                if($user_p && $user_p['is_promoter']){
//                    $coin = bcmul( $groupOrder->total_price  ,0.3 , 2);
//                     Db::table('eb_user')->where(['uid'=>$user_p['uid']])->update([
//                            'brokerage_price'=>bcadd($user_p['brokerage_price'] , $coin ,2 )
//                     ]);
//                    Db::table('eb_user_bill')->save([
//                        'uid' => $user_p['uid'],
//                        'link_id' => $groupOrder->uid,
//                        'pm'=>1,
//                        'title'=>'奖励股权值',
//                        'category' => 'brokerage_price',
//                        'number'=>$coin,
//                        'balance'=>bcadd($user_p['brokerage_price'] , $coin ,2 ) ,
//                        'mark'=>'创始区购买，股权值增加'.$coin,
//                        'create_time'=>date('Y-m-d H:i:s'),
//                        'status'=>1
//                    ]);
//                }
//            }

        });

        if (count($groupOrder['give_coupon_ids']) > 0) {
            try {
                Queue::push(PayGiveCouponJob::class, ['ids' => $groupOrder['give_coupon_ids'], 'uid' => $groupOrder['uid']]);
            } catch (Exception $e) {
            }
        }

        queue::push(SendTemplateMessageJob::class, [
            'tempCode' => 'ORDER_PAY_SUCCESS',
            'id' => $groupOrder->group_order_id
        ]);
        Queue::push(SendSmsJob::class, [
            'tempId' => 'PAY_SUCCESS_CODE',
            'id' => $groupOrder->group_order_id
        ]);
        Queue::push(SendSmsJob::class, [
            'tempId' => 'ADMIN_PAY_SUCCESS_CODE',
            'id' => $groupOrder->group_order_id
        ]);
    }


    /**
     *  自动打印
     * @Author:Qinii
     * @Date: 2020/10/13
     * @param int $orderId
     * @param int $merId
     */
    public function autoPrinter(int $orderId, int $merId)
    {
        if (merchantConfig($merId, 'printing_auto_status')) {
            try {
                $this->printer($orderId, $merId);
            } catch (Exception $exception) {
            }
        }
    }

    /**
     * @param User $user
     * @param StoreGroupOrder $groupOrder
     * @return ValidateException
     * @author xaboy
     * @day 2020/6/9
     */
    public function payWeixin(User $user, StoreGroupOrder $groupOrder)
    {
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $openId = $wechatUserRepository->idByOpenId($user['wechat_user_id']);
        if (!$openId)
            return new ValidateException('请关联微信公众号!');
        $config = WechatService::create()->jsPay($openId, $groupOrder['group_order_sn'], $groupOrder['pay_price'], 'order', '订单支付');
        return app('json')->status('weixin', ['config' => $config, 'order_id' => $groupOrder['group_order_id']]);
    }

    /**
     * @param User $user
     * @param StoreGroupOrder $groupOrder
     * @return \think\response\Json
     * @author xaboy
     * @day 2020/6/9
     */
    public function payRoutine(User $user, StoreGroupOrder $groupOrder)
    {
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $openId = $wechatUserRepository->idByRoutineId($user['wechat_user_id']);
        if (!$openId)
            new ValidateException('请关联微信小程序!');
        $config = MiniProgramService::create()->jsPay($openId, $groupOrder['group_order_sn'], $groupOrder['pay_price'], 'order', '订单支付');
        return app('json')->status('routine', ['config' => $config, 'order_id' => $groupOrder['group_order_id']]);
    }

    /**
     * @param User $user
     * @param StoreGroupOrder $groupOrder
     * @return mixed
     * @author xaboy
     * @day 2020/6/9
     */
    public function payH5(User $user, StoreGroupOrder $groupOrder)
    {
        $config = WechatService::create()->paymentPrepare(null, $groupOrder['group_order_sn'], $groupOrder['pay_price'], 'order', '订单支付', '', 'MWEB');
        return app('json')->status('h5', ['config' => $config, 'order_id' => $groupOrder['group_order_id']]);
    }

    /**
     * @param User $user
     * @param StoreGroupOrder $groupOrder
     * @param $return_url
     * @return \think\response\Json
     * @author xaboy
     * @day 2020/10/22
     */
    public function payAlipay(User $user, StoreGroupOrder $groupOrder, $return_url)
    {
        $url = AlipayService::create('order')->wapPaymentPrepare($groupOrder['group_order_sn'], $groupOrder['pay_price'], '订单支付', $return_url);
        $pay_key = md5($url);
        Cache::store('file')->set('pay_key' . $pay_key, $url, 3600);
        return app('json')->status('alipay', ['config' => $url, 'pay_key' => $pay_key, 'order_id' => $groupOrder['group_order_id']]);
    }

    /**
     * @param User $user
     * @param StoreGroupOrder $groupOrder
     * @return \think\response\Json
     * @author xaboy
     * @day 2020/10/22
     */
    public function payAlipayQr(User $user, StoreGroupOrder $groupOrder)
    {
        $url = AlipayService::create('order')->qrPaymentPrepare($groupOrder['group_order_sn'], $groupOrder['pay_price'], '订单支付');
        return app('json')->status('alipayQr', ['config' => $url, 'order_id' => $groupOrder['group_order_id']]);
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020/6/9
     */
    public function getNewOrderId()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');
        $orderId = 'wx' . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));
        return $orderId;
    }

    /**
     * @param $uid
     * @param array $takes
     * @param array $cartId
     * @param int|null $addressId
     * @param bool $confirm
     * @param null $useCoupon
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/11/6
     */
    public function cartIdByOrderInfo($uid,array $takes, array $cartId, int $addressId = null, $confirm = true, $useCoupon = null)
    {
        $address = null;
        if ($addressId) {
            $addressRepository = app()->make(UserAddressRepository::class);
            $address = $addressRepository->getWhere(['uid' => $uid, 'address_id' => $addressId]);
            if (!$address['city_id'])
                throw new ValidateException('请选择正确的收货地址');
        }
        $storeCartRepository = app()->make(StoreCartRepository::class);
        $res = $storeCartRepository->checkCartList($storeCartRepository->cartIbByData($cartId, $uid, isset($address) ? $address['city_id'] : null, $confirm === true));
        $merchantInfo = $res['list'];
        $fail = $res['fail'];

        if (count($fail)) {
            if ($fail[0]['is_fail'])
                throw new ValidateException($fail[0]['product']['store_name'] . ' 已失效');
            if (in_array($fail[0]['product_type'], [1, 2, 3]) && !$fail[0]['userPayCount']) {
                throw new ValidateException($fail[0]['product']['store_name'] . ' 已超出购买限制');
            }
            throw new ValidateException($fail[0]['product']['store_name'] . ' 已失效');
        }

        $pay_duihuan = 0;
        $p_num = 0;
        foreach ($merchantInfo as $cart) {
            foreach ($cart['list'] as $item) {
                if ($item['product_type'] > 0 && (count($cart['list']) != 1 || count($merchantInfo) != 1)) {
                    throw new ValidateException('活动商品必须单独购买');
                }
                if($item['product']['duihuan'] > 0){
                    $pay_duihuan = bcadd( $pay_duihuan ,  bcmul($item['product']['duihuan'],$item['cart_num'],2) ,2);
                }
                $p_num++;
            }
        }

        if($p_num > 1)  throw new ValidateException('一次不能购买多件商品！');

        $merchantTakeRepository = app()->make(MerchantTakeRepository::class);
        $order_price = 0;
        $order_total_price = 0;
        $noDeliver = false;
        $order_type = 0;
        $presellType = 0;
        $fn = [];

        foreach ($merchantInfo as $k => $cartInfo) {
            $postageRule = [];
            $total_price = 0;
            $total_num = 0;
            $valid_total_price = 0;
            $postage_price = 0;
            $product_price = [];
            $final_price = 0;
            $down_price = 0;

            //TODO 计算邮费
            foreach ($cartInfo['list'] as $_k => $cart) {

                if ($cart['product_type'] > 0) $order_type = $cart['product_type'];

                if ($cart['product_type'] == 2) {
                    $cart->append(['productPresell', 'productPresellAttr']);
                    $final_price = bcadd($final_price, bcmul($cart['cart_num'], $cart['productPresellAttr']['final_price'], 2), 2);
                    if($cart['productPresell']['presell_type'] == 2)
                        $down_price = bcadd($down_price, bcmul($cart['cart_num'], $cart['productPresellAttr']['down_price'], 2), 2);
                    $presellType = $cart['productPresell']['presell_type'];
                } else if ($cart['product_type'] == 3) {
                    $cart->append(['productAssistAttr']);
                } else if ($cart['product_type'] == 4) {
                    $cart->append(['activeSku']);
                }

                $price = bcmul($cart['cart_num'], $this->cartByPrice($cart), 2);
                $total_price = bcadd($total_price, $price, 2);
                $total_num += $cart['cart_num'];
                $_price = bcmul($cart['cart_num'], $this->cartByCouponPrice($cart), 2);
                if ($_price > 0) {
                    $product_price[$cart['product_id']] = bcadd($product_price[$cart['product_id']] ?? 0, $_price, 2);
                    $valid_total_price = bcadd($valid_total_price, $_price, 2);
                }
                if (!isset($product_cart[$cart['product_id']]))
                    $product_cart[$cart['product_id']] = [$cart['cart_id']];
                else
                    $product_cart[$cart['product_id']][] = $cart['cart_id'];
                if (!$address || !$cart['product']['temp']) {
                    $cartInfo['list'][$_k]['undelivered'] = true;
                    $noDeliver = true;
                    continue;
                }
                $temp1 = $cart['product']['temp'];
                $cart['undelivered'] = (!in_array($cartInfo['mer_id'], $takes)) && $temp1['undelivery'] && isset($temp1['undelives']);
                $free = $temp1['free'][0] ?? null;
                $region = $temp1['region'][0] ?? null;
                $fn[] = function () use ($cartInfo, $_k) {
                    unset($cartInfo['list'][$_k]['product']['temp']);
                };
                $cartInfo['list'][$_k] = $cart;

                if ($cart['undelivered']) {
                    $noDeliver = true;
                    continue;
                }

                if (!isset($postageRule[$cart['product']['temp_id']])) {
                    $postageRule[$cart['product']['temp_id']] = [
                        'free' => null,
                        'region' => null
                    ];
                }
                $number = $this->productByTempNumber($cart);
                $freeRule = $postageRule[$cart['product']['temp_id']]['free'];
                $regionRule = $postageRule[$cart['product']['temp_id']]['region'];
                if ($temp1['appoint'] && $free) {
                    if (!isset($freeRule)) {
                        $freeRule = $free;
                        $freeRule['cart_price'] = 0;
                        $freeRule['cart_number'] = 0;
                    }
                    $freeRule['cart_number'] = bcadd($freeRule['cart_number'], $number, 2);
                    $freeRule['cart_price'] = bcadd($freeRule['cart_price'], $price, 2);
                }

                if ($region) {
                    if (!isset($regionRule)) {
                        $regionRule = $region;
                        $regionRule['cart_price'] = 0;
                        $regionRule['cart_number'] = 0;
                    }
                    $regionRule['cart_number'] = bcadd($regionRule['cart_number'], $number, 2);
                    $regionRule['cart_price'] = bcadd($regionRule['cart_price'], $price, 2);
                }
                $postageRule[$cart['product']['temp_id']]['free'] = $freeRule;
                $postageRule[$cart['product']['temp_id']]['region'] = $regionRule;
            }

            foreach ($postageRule as $item) {
                $freeRule = $item['free'];
                if ($freeRule && $freeRule['cart_number'] >= $freeRule['number'] && $freeRule['cart_price'] >= $freeRule['price'])
                    continue;
                if (!$item['region']) continue;
                $regionRule = $item['region'];
                $postage = $regionRule['first_price'];
                if ($regionRule['first'] > 0 && $regionRule['cart_number'] > $regionRule['first']) {
                    $num = ceil(bcdiv(bcsub($regionRule['cart_number'], $regionRule['first'], 2), $regionRule['continue'], 2));
                    $postage = bcadd($postage, bcmul($num, $regionRule['continue_price'], 2), 2);
                }
                $postage_price = bcadd($postage_price, $postage, 2);
            }
            $coupon_price = 0;
            $use_coupon_product = [];
            $use_store_coupon = 0;
            foreach ($cartInfo['coupon'] as $__k => $coupon) {
                if (!$coupon['coupon']['type']) continue;
                if (!is_null($useCoupon)) {
                    if (isset($useCoupon[$cartInfo['mer_id']])) {
                        $productCoupon = $useCoupon[$cartInfo['mer_id']]['product'] ?? [];
                        if (!count($productCoupon) || !in_array($coupon['coupon_user_id'], $productCoupon))
                            continue;
                    } else {
                        continue;
                    }
                }
                //商品券
                if (count(array_intersect(array_column($coupon['product'], 'product_id'), array_keys($product_price))) == 0) {
                    unset($cartInfo['coupon'][$__k]);
                    continue;
                }
                $flag = false;
                foreach ($coupon['product'] as $_product) {
                    if (isset($product_price[$_product['product_id']]) && $product_price[$_product['product_id']] >= $coupon['use_min_price']) {
                        $flag = true;
                        if ($confirm && !isset($use_coupon_product[$_product['product_id']])) {
                            $coupon_price = bcadd($coupon_price, $coupon['coupon_price'], 2);
                            $use_coupon_product[$_product['product_id']] = $coupon['coupon_user_id'];
                            $cartInfo['coupon'][$__k]['checked'] = true;
                        }
                        break;
                    }
                }
                if (!isset($cartInfo['coupon'][$__k]['checked']))
                    $cartInfo['coupon'][$__k]['checked'] = false;
                if (!$flag) unset($cartInfo['coupon'][$__k]);
            }
            $pay_price = max(bcsub($valid_total_price, $coupon_price, 2), 0);
            $_pay_price = $pay_price;
            foreach ($cartInfo['coupon'] as $__k => $coupon) {
                if ($coupon['coupon']['type']) continue;
                if (!is_null($useCoupon)) {
                    if (isset($useCoupon[$cartInfo['mer_id']])) {
                        $store = $useCoupon[$cartInfo['mer_id']]['store'] ?? '';
                        if (!$store || $coupon['coupon_user_id'] != $store)
                            continue;
                    } else {
                        continue;
                    }
                }

                //店铺券
                if ($valid_total_price >= $coupon['use_min_price']) {
                    if (!$confirm || $pay_price <= 0 || $use_store_coupon) {
                        $cartInfo['coupon'][$__k]['checked'] = false;
                        continue;
                    }
                    $use_store_coupon = $coupon['coupon_user_id'];
                    $coupon_price = bcadd($coupon_price, $coupon['coupon_price'], 2);
                    $_pay_price = bcsub($_pay_price, $coupon['coupon_price'], 2);
                    $cartInfo['coupon'][$__k]['checked'] = true;
                } else {
                    unset($cartInfo['coupon'][$__k]);
                }
            }
            $take = $merchantTakeRepository->get($cartInfo['mer_id']);
            $org_price = bcadd(bcsub($total_price, $valid_total_price, 2), max($_pay_price, 0), 2);
            if($presellType == 2)
                $org_price = max(bcsub($org_price, $final_price, 2), $down_price);
            $coupon_price = min($coupon_price, bcsub($total_price, $down_price, 2));
            if ($order_type != 2 || $presellType != 2) {
                $pay_price = bcadd($postage_price, $org_price, 2);
            } else {
                $pay_price = $org_price;
            }

            foreach ($fn as $callback) {
                $callback();
            }


            $cartInfo['take'] = $take['mer_take_status'] == '1' ? $take : ['mer_take_status' => 0];
            $cartInfo['coupon'] = array_values($cartInfo['coupon']);
            $cartInfo['order'] = compact('pay_duihuan','order_type', 'final_price', 'down_price', 'valid_total_price', 'product_cart', 'product_price', 'postage_price', 'org_price', 'total_price', 'total_num', 'pay_price', 'coupon_price', 'use_coupon_product', 'use_store_coupon');
            $merchantInfo[$k] = $cartInfo;
            // print_r($cartInfo);


            $order_price = bcadd($order_price, $merchantInfo[$k]['order']['pay_price'], 2);
            $order_total_price = bcadd($order_total_price, $total_price, 2);
        }
        return ['order_type' => $order_type, 'order_price' => $order_price, 'total_price' => $order_total_price, 'address' => $address, 'order' => $merchantInfo, 'status' => $address ? ($noDeliver ? 'noDeliver' : 'finish') : 'noAddress'];
    }

    /**
     * @param $cart
     * @return string
     * @author xaboy
     * @day 2020/6/9
     */
    public function productByTempNumber($cart)
    {
        $type = $cart['product']['temp']['type'];
        $cartNum = $cart['cart_num'];
        if (!$type)
            return $cartNum;
        else if ($type == 2) {
            return bcmul($cartNum, $cart['productAttr']['volume'], 2);
        } else {
            return bcmul($cartNum, $cart['productAttr']['weight'], 2);
        }
    }

    public function cartByPrice($cart)
    {
        if ($cart['product_type'] == '2') {
            return $cart['productPresellAttr']['presell_price'];
        } else if ($cart['product_type'] == '3') {
            return $cart['productAssistAttr']['assist_price'];
        } else if ($cart['product_type'] == '4') {
            return $cart['activeSku']['active_price'];
        } else {
            return $cart['productAttr']['price'];
        }
    }

    public function cartByCouponPrice($cart)
    {
        if ($cart['product_type'] == '2') {
            return $cart['productPresellAttr']['final_price'];
        } else if ($cart['product_type'] == '1') {
            return 0;
        } else if ($cart['product_type'] == '3') {
            return 0;
        } else if ($cart['product_type'] == '4') {
            return 0;
        } else {
            return $cart['productAttr']['price'];
        }
    }

    public function cartByFinalPrice($cart)
    {
        if ($cart['product_type'] == '2') {
            return $cart['productPresellAttr']['down_price'];
        } else {
            return 0;
        }
    }


    /**
     * @param int $uid
     * @return array
     * @author xaboy
     * @day 2020/6/10
     */
    public function userOrderNumber(int $uid)
    {
        $noPay = app()->make(StoreGroupOrderRepository::class)->orderNumber($uid);
        $noPostage = $this->dao->search(['uid' => $uid, 'status' => 0, 'paid' => 1])->where('is_del', 0)->count();
        $all = $this->dao->search(['uid' => $uid, 'paid' => 1])->where('is_del', 0)->count();
        $noDeliver = $this->dao->search(['uid' => $uid, 'status' => 1, 'paid' => 1])->where('is_del', 0)->count();
        $noComment = $this->dao->search(['uid' => $uid, 'status' => 2, 'paid' => 1])->where('is_del', 0)->count();
        $done = $this->dao->search(['uid' => $uid, 'status' => 3, 'paid' => 1])->where('is_del', 0)->count();
        $refund = app()->make(StoreRefundOrderRepository::class)->getWhereCount(['uid' => $uid, 'status' => [0, 1, 2]]);
        //$orderPrice = $this->dao->search(['uid' => $uid, 'paid' => 1])->sum('pay_price');
        $orderCount = $this->dao->search(['uid' => $uid, 'paid' => 1])->count();
        return compact('noComment', 'done', 'refund', 'noDeliver', 'noPay', 'noPostage', 'orderCount', 'all');
    }

    /**
     * @param $id
     * @param null $uid
     * @return array|Model|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/10
     */
    public function getDetail($id, $uid = null)
    {
        $where = [];
        $with = ['orderProduct', 'merchant' => function ($query) {
            return $query->field('mer_id,mer_name');
        }];
        if ($uid) {
            $where['uid'] = $uid;
        } else if (!$uid) {
            $with['user'] = function ($query) {
                return $query->field('uid,nickname');
            };
        }
        $order = $this->dao->search($where)->where('order_id', $id)->where('is_del', 0)->with($with)->find();
        if (!$order) {
            return null;
        }
        if ($order->activity_type == 2) {
            if ($order->presellOrder) {
                $order->presellOrder->append(['activeStatus']);
                $order->presell_price = bcadd($order->pay_price, $order->presellOrder->pay_price, 2);
            } else {
                $order->presell_price = $order->pay_price;
            }
        }
        return $order;
    }

    public function codeByDetail($code, $uid = null)
    {
        $where = [];
        if ($uid) $where['uid'] = $uid;
        return $this->dao->search($where)->where('verify_code', $code)->where('is_del', 0)->with(['orderProduct', 'merchant' => function ($query) {
            return $query->field('mer_id,mer_name');
        }])->find();
    }

    /**
     * @param StoreOrder $order
     * @param User $user
     * @author xaboy
     * @day 2020/8/3
     */
    public function computed(StoreOrder $order, User $user)
    {
        $userBillRepository = app()->make(UserBillRepository::class);
        //TODO 添加冻结佣金
        if ($order->extension_one > 0 && $user->spread_uid) {



//            $userBillRepository->incBill($user->spread_uid, 'brokerage', 'order_one', [
////            $userBillRepository->incBill($user->spread_uid, 'brokerage_price', 'order_one', [
//                'link_id' => $order['order_id'],
//                'status' => 0,
//                'title' => '获得推广佣金(股权值)',
//                'number' => $order->extension_one,
//                'mark' => $user['nickname'] . '成功消费' . floatval($order['pay_price']) . '元,奖励推广佣金(股权值)' . floatval($order->extension_one),
//                'balance' => 0
//            ]);
//            $userRepository = app()->make(UserRepository::class);
//            $userRepository->incBrokerage($user->spread_uid, $order->extension_one);
//            app()->make(FinancialRecordRepository::class)->dec([
//                'order_id' => $order->order_id,
//                'order_sn' => $order->order_sn,
//                'user_info' => $userRepository->getUsername($user->spread_uid),
//                'user_id' => $user->spread_uid,
//                'financial_type' => 'brokerage_one',
//                'number' => $order->extension_one,
//            ], $order->mer_id);
        }
        if ($order->extension_two > 0 && $user->top_uid) {
//            $userBillRepository->incBill($user->top_uid, 'brokerage', 'order_two', [
//                'link_id' => $order['order_id'],
//                'status' => 0,
//                'title' => '获得推广佣金(股权值)',
//                'number' => $order->extension_two,
//                'mark' => $user['nickname'] . '成功消费' . floatval($order['pay_price']) . '元,奖励推广佣金(股权值)' . floatval($order->extension_two),
//                'balance' => 0
//            ]);
//            $userRepository = app()->make(UserRepository::class);
//            $userRepository->incBrokerage($user->top_uid, $order->extension_two);
//            app()->make(FinancialRecordRepository::class)->dec([
//                'order_id' => $order->order_id,
//                'order_sn' => $order->order_sn,
//                'user_info' => $userRepository->getUsername($user->top_uid),
//                'user_id' => $user->top_uid,
//                'financial_type' => 'brokerage_two',
//                'number' => $order->extension_two,
//            ], $order->mer_id);
        }

//        if($user->spread_uid && $user->is_promoter){
//            $user_par = Db::table('eb_user')->find($user->spread_uid);
//            $user = Db::table('eb_user')->find($user->uid);
//            if($user_par['is_promoter']){
//                $order_product = Db::table('eb_store_order_product')->find(['order_id'=>$order->order_id]);
//                if($order_product){
//                    $product = Db::table('eb_store_product')->find(['product_id'=>$order_product['product_id']]);
//                    if($product['is_gift_bag']){
//
//                        //n1///
//                        $ctime=date("Y-m-d H:i:s");
//                        $saveDate = [
//                            'uid' => $user_par['uid'],
//                            'link_id' =>$order->order_id,
//                            'pm'=>1,  //0 = 支出 1 = 获得
//                            'title'=>'推广收益',
//                            'category' => 'brokerage_price',
//                            'number'=> 300 ,
//                            'balance'=> $user_par['brokerage_price'] ,
//                            'mark'=>'推广收益，推荐人获得 300 个股权值',
//                            'create_time'=>$ctime,
//                            'status'=>1
//                        ];
//                        Db::table('eb_user_bill')->save($saveDate);
//                        Db::table('eb_user')->update([
//                            'uid'=>$user_par['uid'],
//                            'brokerage_price'=>$user_par['brokerage_price'] + 300,
//                        ]);
//
//                        $saveDate = [
//                            'uid' => $user_par['uid'],
//                            'link_id' =>$order->order_id,
//                            'pm'=>1,  //0 = 支出 1 = 获得
//                            'title'=>'推广收益',
//                            'category' => 'brokerage_gongxian',
//                            'number'=> 50 ,
//                            'balance'=> $user_par['brokerage_gongxian'] ,
//                            'mark'=>'推广收益，推荐人获得 50 个贡献值',
//                            'create_time'=>$ctime,
//                            'status'=>1
//                        ];
//                        Db::table('eb_user_bill')->save($saveDate);
//
//                        Db::table('eb_user')->update([
//                            'uid'=>$user_par['uid'],
//                            'brokerage_gongxian'=>$user_par['brokerage_gongxian'] +50 ,
//                        ]);
//
//
//
//
//
//
//
//
//                    }
//                }
//            }
//        }

    }

    /**
     * @param StoreOrder $order
     * @param User $user
     * @param string $type
     * @author xaboy
     * @day 2020/8/3
     */
    public function takeAfter(StoreOrder $order, User $user)
    {
        Db::transaction(function () use ($user, $order) {
            $this->computed($order, $user);
            //TODO 确认收货
            app()->make(StoreOrderStatusRepository::class)->status($order->order_id, 'take', '已收货');
            Queue::push(SendTemplateMessageJob::class, [
                'tempCode' => 'ORDER_TAKE_SUCCESS',
                'id' => $order->order_id
            ]);
            Queue::push(SendSmsJob::class, [
                'tempId' => 'TAKE_DELIVERY_CODE',
                'id' => $order->order_id
            ]);
            Queue::push(SendSmsJob::class, [
                'tempId' => 'ADMIN_TAKE_DELIVERY_CODE',
                'id' => $order->order_id
            ]);
            $order->save();
        });
    }

    /**
     * @param $id
     * @param User $user
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/17
     */
    public function takeOrder($id, ?User $user = null)
    {
        $order = $this->dao->search(!$user ? [] : ['uid' => $user->uid], null)->where('order_id', $id)->where('is_del', 0)->find();
        if (!$order)
            throw new ValidateException('订单不存在');
        if ($order['status'] != 1 || $order['order_type'])
            throw new ValidateException('订单状态有误');
        if (!$user) $user = $order->user;
        $order->status = 2;//待评价

        Db::transaction(function () use ($order, $user) {
            $this->takeAfter($order, $user);
            $order->save();
        });

        $flag = true;
        foreach ($order->orderProduct as $product) {
            if ($flag && $product['cart_info']['product']['is_gift_bag']) {
                app()->make(UserRepository::class)->promoter($order->uid);
                $flag = false;

                //n1///
                $ctime=date("Y-m-d H:i:s");
                $user_k1 = Db::table('eb_user')->find($order->uid);
                if(!$user_k1['is_promoter']){
                    $saveDate = [
                        'uid' => $user_k1['uid'],
                        'link_id' =>$order->order_id,
                        'pm'=>1,  //0 = 支出 1 = 获得
                        'title'=>'购买渠道商品',
                        'category' => 'brokerage_gongxian',
                        'number'=> 1000 ,
                        'balance'=> $user_k1['brokerage_duihuan'],
                        'mark'=>'购买渠道商品，赠送 1000 贡献值',
                        'create_time'=>$ctime,
                        'status'=>1
                    ];
                    Db::table('eb_user_bill')->save($saveDate);

                    Db::table('eb_user')->update([
                        'uid'=>$user_k1['uid'],
                        'brokerage_gongxian'=>$user_k1['brokerage_gongxian'] + 1000,
                    ]);

                }

            }
        }


            if( $order->order_type_n == 6 ){
                $uid = $order->uid;
                $user_p = Db::table('eb_user')->find($uid);
                //new
                if(!$user_p['is_promoter']){
                    Db::table('eb_user')->where(['uid'=>$user_p['uid']])->update([
                        'is_promoter'=>1
                    ]);
                }

                $user_p = Db::table('eb_user')->find($user_p['spread_uid']);
                if($user_p && $user_p['is_promoter']){
                    $coin = bcmul( $order->total_price  ,0.3 , 2);
                     Db::table('eb_user')->where(['uid'=>$user_p['uid']])->update([
                            'brokerage_price'=>bcadd($user_p['brokerage_price'] , $coin ,2 )
                     ]);
                    Db::table('eb_user_bill')->save([
                        'uid' => $user_p['uid'],
                        'link_id' => $order->order_id,
                        'pm'=>1,
                        'title'=>'奖励股权值',
                        'category' => 'brokerage_price',
                        'number'=>$coin,
                        'balance'=>bcadd($user_p['brokerage_price'] , $coin ,2 ) ,
                        'mark'=>'创始区购买，股权值增加'.$coin,
                        'create_time'=>date('Y-m-d H:i:s'),
                        'status'=>1
                    ]);
                }
            }


//        if(false){
//            try {
//                bcscale(2);
//                $mer = Db::table('eb_merchant')->find($order['mer_id']);
//                $user = Db::table('eb_user')->find($order['uid']);
//                $user_mer = Db::table('eb_user')->find($mer['uid']);
//
//                $user_p1 = Db::table('eb_user')->find($user['spread_uid']);
//                $user_mer_p1 = Db::table('eb_user')->find($user_mer['spread_uid']);
//
//
//                if(!$mer || !$user_mer ) return;
////            $prod_ord = Db::table('eb_store_order_product')->where(['order_id'=>$order['order_id']])->find();//rangli
////            $prod = Db::table('eb_store_product')->find($prod_ord['product_id']);//rangli
////            if(!$prod || $prod['rangli'] <= 0)  return ;
//                if($order['rangli'] <= 0)  return ;
//
//                $total_price =  $order['total_price'];
////			$prod_rate = bcdiv($prod['rangli'] * 10 , 100);
//                $prod_rate = bcdiv($order['rangli']   , 100);
//
//// 买家确认收货后：
////买家获得 =  付款金额 * 比例 * 10   （贡献值）
////卖家获得 = 付款金额 * 比例（贡献值）
//                $gongxian_mer = bcmul($total_price , $prod_rate);
//                $gongxian_user = bcmul($gongxian_mer , 10);
//
//                $res =  Db::table('eb_user')->where(['uid'=>$user_mer['uid']])->update([
//                    'brokerage_gongxian'=>$user_mer['brokerage_gongxian'] + $gongxian_mer
//                ]);
//                if($res){
//                    Db::table('eb_user_bill')->save([
//                        'uid' => $user_mer['uid'],
//                        'link_id' =>$order['order_id'],
//                        'pm'=>1,
//                        'title'=>'支付奖励',
//                        'category' => 'brokerage_gongxian',
//                        'number'=> $gongxian_mer,
//                        'balance'=>$user_mer['brokerage_gongxian'] + $gongxian_mer ,
//                        'mark'=>'支付奖励，贡献值增加'.$gongxian_mer ,
//                        'create_time'=>date('Y-m-d H:i:s'),
//                        'status'=>1
//                    ]);
//                }
//                $res =  Db::table('eb_user')->where(['uid'=>$user['uid']])->update([
//                    'brokerage_gongxian'=>$user['brokerage_gongxian'] + $gongxian_user
//                ]);
//                if($res){
//                    Db::table('eb_user_bill')->save([
//                        'uid' => $user['uid'],
//                        'link_id' =>$order['order_id'],
//                        'pm'=>1,
//                        'title'=>'支付奖励',
//                        'category' => 'brokerage_gongxian',
//                        'number'=> $gongxian_user,
//                        'balance'=>$user['brokerage_gongxian'] + $gongxian_user ,
//                        'mark'=>'支付奖励，贡献值增加'.$gongxian_user ,
//                        'create_time'=>date('Y-m-d H:i:s'),
//                        'status'=>1
//                    ]);
//                }
//
////如果买家或者卖家的上级是推荐人，并且还是渠道商 ：
////买家-上级渠道商 = 付款金额 * 比例 * 10 * 5% （贡献值）
////卖家-上级渠道商 = 付款金额 * 比例 * 10 * 5% （贡献值）
//                $gongxian_mer_p1 = bcmul($gongxian_user , 0.05);
//                $gongxian_user_p1 = $gongxian_mer_p1;
//
//                if($user_p1 && $user_p1['is_promoter']  ){
//                    $res =  Db::table('eb_user')->where(['uid'=>$user_p1['uid']])->update([
//                        'brokerage_gongxian'=>$user_p1['brokerage_gongxian'] + $gongxian_user_p1
//                    ]);
//                    if($res){
//                        Db::table('eb_user_bill')->save([
//                            'uid' => $user_p1['uid'],
//                            'link_id' =>$order['order_id'],
//                            'pm'=>1,
//                            'title'=>'渠道商贡献值奖励',
//                            'category' => 'brokerage_gongxian',
//                            'number'=> $gongxian_user_p1,
//                            'balance'=>$user_p1['brokerage_gongxian'] + $gongxian_user_p1 ,
//                            'mark'=>'渠道商贡献值奖励，贡献值增加'.$gongxian_user_p1 ,
//                            'create_time'=>date('Y-m-d H:i:s'),
//                            'status'=>1
//                        ]);
//                    }
//                }
//                if($user_mer_p1 && $user_mer_p1['is_promoter']  ){
//                    $res =  Db::table('eb_user')->where(['uid'=>$user_mer_p1['uid']])->update([
//                        'brokerage_gongxian'=>$user_mer_p1['brokerage_gongxian'] + $gongxian_mer_p1
//                    ]);
//                    if($res){
//                        Db::table('eb_user_bill')->save([
//                            'uid' => $user_mer_p1['uid'],
//                            'link_id' =>$order['order_id'],
//                            'pm'=>1,
//                            'title'=>'渠道商贡献值奖励',
//                            'category' => 'brokerage_gongxian',
//                            'number'=> $gongxian_mer_p1,
//                            'balance'=>$user_mer_p1['brokerage_gongxian'] + $gongxian_mer_p1 ,
//                            'mark'=>'渠道商贡献值奖励，贡献值增加'.$gongxian_mer_p1 ,
//                            'create_time'=>date('Y-m-d H:i:s'),
//                            'status'=>1
//                        ]);
//                    }
//                }
//
////如果 买家 的所有上级里面，只要是业务主任的：
////业务主任 = 付款金额 * 比例 * 10 * 1.5% （贡献值）
////memberlevel3    团队会员（0非团、1主任、2经理、3总监、4总裁）
////主任 0.015  经理 0.015  总监0.03  总裁 0.02
//
//                $user_ps = $user['retree'];
//                $user_ps  = explode(',',$user_ps);
//                $user_ps = array_reverse(array_unique(array_filter($user_ps)));
//                $ps_arr = [
//                    'p2'=>'',
//                    'p3'=>'',
//                    'p4'=>'',
//                    'p5'=>'',
//                ];
//                foreach($user_ps as $v){
//                    if($v  == $user['uid']) continue;
//                    $ps_tmp = Db::table('eb_user')->find($v);
//                    if($ps_tmp && $ps_tmp['memberlevel3'] > 0 ){
//                        if($ps_arr['p'.$ps_tmp['memberlevel3']] ) continue;
//                        $ps_arr['p'.$ps_tmp['memberlevel3']] =  $ps_tmp;
//                    }
//                }
//                foreach($ps_arr as $vv){
//                    if($vv){
//                        $rate = 0;
//                        switch($vv['memberlevel3']){
//                            case 1:
//                                $rate = 0.015;
//                                break;
//                            case 2:
//                                $rate = 0.015;
//                                break;
//                            case 3:
//                                $rate = 0.03;
//                                break;
//                            case 4:
//                                $rate = 0.02;
//                                break;
//                        }
//                        $vv_gongxian = bcmul($gongxian_user , $rate );
//                        $res =  Db::table('eb_user')->where(['uid'=>$vv['uid']])->update([
//                            'brokerage_gongxian'=>$vv['brokerage_gongxian'] + $vv_gongxian
//                        ]);
//                        if($res){
//                            Db::table('eb_user_bill')->save([
//                                'uid' => $vv['uid'],
//                                'link_id' =>$order['order_id'],
//                                'pm'=>1,
//                                'title'=>'团队贡献值奖励',
//                                'category' => 'brokerage_gongxian',
//                                'number'=> $vv_gongxian,
//                                'balance'=>$vv['brokerage_gongxian'] + $vv_gongxian ,
//                                'mark'=>'团队贡献值奖励，贡献值增加'.$vv_gongxian ,
//                                'create_time'=>date('Y-m-d H:i:s'),
//                                'status'=>1
//                            ]);
//                        }
//                    }
//                }
//
//            } catch (Exception $e) {
//                Log::info('支付奖励发放失败66:' . var_export([$e->getMessage(), $e->getFile() . ':' . $e->getLine()], true));
//            }
//        }

    }


    /**
     *  获取订单列表头部统计数据
     * @Author:Qinii
     * @Date: 2020/9/12
     * @param int|null $merId
     * @param int|null $orderType
     * @return array
     */
    public function OrderTitleNumber(?int $merId, ?int $orderType)
    {
        $where = [];
        $sysDel = $merId ? 0 : null;    //商户删除
        if ($merId) $where['mer_id'] = $merId; //商户订单
        if ($orderType === 0) $where['order_type'] = 0; //普通订单
        if ($orderType === 1) $where['take_order'] = 1; //已核销订单
        //1: 未支付 2: 未发货 3: 待收货 4: 待评价 5: 交易完成 6: 已退款 7: 已删除
        $all = $this->dao->search($where, $sysDel)->where($this->getOrderType(0))->count();
        $statusAll = $all;
        $unpaid = $this->dao->search($where, $sysDel)->where($this->getOrderType(1))->count();
        $unshipped = $this->dao->search($where, $sysDel)->where($this->getOrderType(2))->count();
        $untake = $this->dao->search($where, $sysDel)->where($this->getOrderType(3))->count();
        $unevaluate = $this->dao->search($where, $sysDel)->where($this->getOrderType(4))->count();
        $complete = $this->dao->search($where, $sysDel)->where($this->getOrderType(5))->count();
        $refund = $this->dao->search($where, $sysDel)->where($this->getOrderType(6))->count();
        $del = $this->dao->search($where, $sysDel)->where($this->getOrderType(7))->count();

        return compact('all', 'statusAll', 'unpaid', 'unshipped', 'untake', 'unevaluate', 'complete', 'refund', 'del');
    }

    public function orderType(array $where)
    {
        return [
            [
                'count' => $this->dao->search($where)->count(),
                'title' => '全部',
                'order_type' => -1,
            ],
            [
                'count' => $this->dao->search($where)->where('order_type', 0)->count(),
                'title' => '普通订单',
                'order_type' => 0,
            ],
            [
                'count' => $this->dao->search($where)->where('order_type', 1)->count(),
                'title' => '核销订单',
                'order_type' => 1,
            ],
        ];
    }

    /**
     * @param $status
     * @return mixed
     * @author Qinii
     */
    public function getOrderType($status)
    {
        $param['is_del'] = 0;
        switch ($status) {
            case 1:
                $param['paid'] = 0;
                break;    // 未支付
            case 2:
                $param['paid'] = 1;
                $param['status'] = 0;
                break;  // 待发货
            case 3:
                $param['status'] = 1;
                break;  // 待收货
            case 4:
                $param['status'] = 2;
                break;  // 待评价
            case 5:
                $param['status'] = 3;
                break;  // 交易完成
            case 6:
                $param['status'] = -1;
                break; // 已退款
            case 7:
                $param['is_del'] = 1;
                break;  // 已删除
            default:
                unset($param['is_del']);
                break;         //全部
        }
        return $param;
    }

    /**
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function sendProductForm($id)
    {
        return app()->make(ExpressRepository::class)->sendProductForm($id);
    }

    /**
     * @param int $id
     * @param int|null $merId
     * @return array|Model|null
     * @author Qinii
     */
    public function merDeliveryExists(int $id, ?int $merId)
    {
        $where = ['order_id' => $id, 'is_del' => 0, 'paid' => 1, 'status' => 0];
        if ($merId) $where['mer_id'] = $merId;
        return $this->dao->merFieldExists($where);
    }

    /**
     * TODO
     * @param int $id
     * @param int|null $merId
     * @return bool
     * @author Qinii
     * @day 2020-06-11
     */
    public function merGetDeliveryExists(int $id, ?int $merId)
    {
        $where = ['order_id' => $id, 'is_del' => 0, 'paid' => 1, 'status' => 1];
        if ($merId) $where['mer_id'] = $merId;
        return $this->dao->merFieldExists($where);
    }

    /**
     * @param int $id
     * @param int|null $merId
     * @return array|Model|null
     * @author Qinii
     */
    public function merStatusExists(int $id, ?int $merId)
    {
        $where = ['order_id' => $id, 'is_del' => 0, 'paid' => 0, 'status' => 0];
        if ($merId) $where['mer_id'] = $merId;
        return $this->dao->merFieldExists($where);
    }

    public function userDelExists(int $id, ?int $merId)
    {
        $where = ['order_id' => $id, 'is_del' => 1];
        if ($merId) $where['mer_id'] = $merId;
        return $this->dao->merFieldExists($where);
    }

    /**
     * @param $id
     * @return Form
     * @author Qinii
     */
    public function form($id)
    {
        $data = $this->dao->getWhere([$this->dao->getPk() => $id], 'total_price,pay_price,total_postage,pay_postage');
        $form = Elm::createForm(Route::buildUrl('merchantStoreOrderUpdate', ['id' => $id])->build());
        $form->setRule([
            Elm::number('total_price', '订单总价', $data['total_price'])->required(),
            Elm::number('total_postage', '订单邮费', $data['total_postage'])->required(),
            Elm::number('pay_price', '实际支付金额', $data['pay_price'])->required(),
        ]);
        return $form->setTitle('修改订单');
    }

    /**
     * TODO 修改订单价格
     * @param int $id
     * @param array $data
     * @author Qinii
     * @day 12/15/20
     */
    public function eidt(int $id, array $data)
    {

        /**
         * 1 计算出新的实际支付价格
         *      1.1 计算邮费
         *      1.2 计算商品总价
         * 2 修改订单信息
         * 3 计算总单数据
         * 4 修改总单数据
         * 5 修改订单商品单价
         *
         * pay_price = total_price - coupon_price + pay_postage
         */
        $order = $this->dao->get($id);
        $data['pay_price'] = $this->bcmathPrice($data['total_price'],$order['coupon_price'],$data['pay_postage']);
        if($data['pay_price'] < 0) {
            throw new ValidateException('实际支付金额不能小于0');
        }
        $make = app()->make(StoreGroupOrderRepository::class);
        $orderGroup = $make->dao->getWhere(['group_order_id' => $order['group_order_id']]);

        //总单总价格
        $_group['total_price'] = $this->bcmathPrice($orderGroup['total_price'],$order['total_price'],$data['total_price']);
        //总单实际支付价格
        $_group['pay_price']   = $this->bcmathPrice($orderGroup['pay_price'],$order['pay_price'],$data['pay_price']);
        //总单实际支付邮费
        $_group['pay_postage'] = $this->bcmathPrice($orderGroup['pay_postage'],$order['pay_postage'],$data['pay_postage']);
        Db::transaction(function () use ($id, $data,$orderGroup,$order,$_group) {
            $orderGroup->total_price = $_group['total_price'];
            $orderGroup->pay_price   = $_group['pay_price'];
            $orderGroup->pay_postage = $_group['pay_postage'];
            $orderGroup->group_order_sn = $this->getNewOrderId() . '0';
            $orderGroup->save();

            $this->dao->update($id, $data);
            $this->changOrderProduct($id,$data);

            app()->make(StoreOrderStatusRepository::class)->status($id, 'change', '订单信息修改');
            if($data['pay_price'] != $order['pay_price']) Queue::push(SendSmsJob::class, ['tempId' => 'PRICE_REVISION_CODE', 'id' => $id]);
        });
    }

    /**
     * TODO 改价后重新计算每个商品的单价
     * @param int $orderId
     * @param array $data
     * @author Qinii
     * @day 12/15/20
     */
    public function changOrderProduct(int $orderId,array $data)
    {
        $make = app()->make(StoreOrderProductRepository::class);
        $ret = $make->getSearch(['order_id' => $orderId])->field('order_product_id,product_num,product_price')->select();
        $count = $make->getSearch(['order_id' => $orderId])->sum('product_price');
        $_count = (count($ret->toArray()) - 1);
        $pay_price = $data['total_price'];
        foreach ($ret as $k => $item){
            $_price = 0;
            /**
             *  比例 =  单个商品总价 / 订单原总价；
             *
             *  新的商品总价 = 比例 * 订单修改总价
             *
             *  更新数据库
             */
            if($k == $_count){
                $_price = $pay_price;
            }else{
                $_reta = bcdiv($item->product_price , $count,3);
                $_price = bcmul($_reta,$data['total_price'],2);
            }

            $item->product_price = $_price;
            $item->save();

            $pay_price = $this->bcmathPrice($pay_price,$_price,0);
        }
    }

    /**
     * TODO 计算的重复利用
     * @param $total
     * @param $old
     * @param $new
     * @return int|string
     * @author Qinii
     * @day 12/15/20
     */
    public function bcmathPrice($total,$old,$new)
    {
        $_bcsub = bcsub($total, $old, 2);
        $_count = (bccomp($_bcsub , 0,2) == -1) ? 0 : $_bcsub;
        $count = bcadd($_count, $new, 2);
        return (bccomp($count , 0,2) == -1) ? 0 : $count;
    }

    /**
     * @param $id
     * @param $uid
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/12
     */
    public function refundProduct($id, $uid)
    {
        $order = $this->dao->userOrder($id, $uid);
        if (!$order)
            throw new ValidateException('订单不存在');
        if (!count($order->refundProduct))
            throw new ValidateException('没有可退款商品');
       if(in_array($order->status,[2,3]))
           throw new ValidateException('已收货，不可退款！');

        return $order->refundProduct->toArray();
    }

    public function delivery($id, $data)
    {
        $data['status'] = 1;
        $order = $this->dao->get($id);
        if ($data['delivery_type'] == 1) {
            $exprss = app()->make(ExpressRepository::class)->getWhere(['id' => $data['delivery_name']]);
            if (!$exprss) throw new ValidateException('快递公司不存在');
            $data['delivery_name'] = $exprss['name'];
        }
        if ($data['delivery_type'] == 2 && !preg_match("/^1[3456789]{1}\d{9}$/", $data['delivery_id']))
            throw new ValidateException('手机号格式错误');
        $this->dao->update($id, $data);

        if ($data['delivery_type'] == 1) {
            app()->make(StoreOrderStatusRepository::class)->status($id, 'delivery_0', '订单已配送【快递名称】:' . $data['delivery_name'] . '; 【快递单号】：' . $data['delivery_id']);
            queue::push(SendTemplateMessageJob::class, [
                'tempCode' => 'ORDER_POSTAGE_SUCCESS',
                'id' => $order['order_id'],
            ]);
            Queue::push(SendSmsJob::class, [
                'tempId' => 'DELIVER_GOODS_CODE',
                'id' => $order['order_id']
            ]);
        }

        if ($data['delivery_type'] == 2) {
            app()->make(StoreOrderStatusRepository::class)->status($id, 'delivery_1', '订单已配送【送货人姓名】:' . $data['delivery_name'] . '; 【手机号】：' . $data['delivery_id']);
            queue::push(SendTemplateMessageJob::class, [
                'tempCode' => 'ORDER_DELIVER_SUCCESS',
                'id' => $order['order_id'],
            ]);
            Queue::push(SendSmsJob::class, [
                'tempId' => 'DELIVER_GOODS_CODE',
                'id' => $order['order_id']
            ]);
        }
        if ($data['delivery_type'] == 3) {
            app()->make(StoreOrderStatusRepository::class)->status($id, 'delivery_2', '订单已配送【虚拟发货】');
        }
    }

    public function getOne($id, ?int $merId)
    {
        $where = [$this->getPk() => $id];
        if ($merId) {
            $whre['mer_id'] = $merId;
            $whre['is_system_del'] = 0;
        }
        return $this->dao->getWhere($where, '*', ['user' => function ($query) {
            $query->field('uid,real_name,nickname');
        },'finalOrder']);
    }

    public function getOrderStatus($id, $page, $limit)
    {
        return app()->make(StoreOrderStatusRepository::class)->search($id, $page, $limit);
    }

    public function remarkForm($id)
    {
        $data = $this->dao->get($id);
        $form = Elm::createForm(Route::buildUrl('merchantStoreOrderRemark', ['id' => $id])->build());
        $form->setRule([
            Elm::text('remark', '备注', $data['remark'])->required(),
        ]);
        return $form->setTitle('修改备注');
    }

    public function adminMarkForm($id)
    {
        $data = $this->dao->get($id);
        $form = Elm::createForm(Route::buildUrl('systemMerchantOrderMark', ['id' => $id])->build());
        $form->setRule([
            Elm::text('admin_mark', '备注', $data['admin_mark'])->required(),
        ]);
        return $form->setTitle('修改备注');
    }

    /**
     * TODO 平台每个商户的订单列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2020-06-15
     */
    public function adminMerGetList($where, $page, $limit)
    {
        $where['paid'] = 1;
        $query = $this->dao->search($where, null);
        $count = $query->count();
        $list = $query->with([
            'orderProduct',
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name,is_trader');
            },
            'groupOrder' => function($query){
                $query->field('group_order_id,group_order_sn');
            },
            'finalOrder',
        ])->page($page, $limit)->select();

        return compact('count', 'list');
    }

    public function reconList($where, $page, $limit)
    {
        $ids = app()->make(MerchantReconciliationOrderRepository::class)->getIds($where);
        $query = $this->dao->search([], null)->whereIn('order_id', $ids);
        $count = $query->count();
        $list = $query->with(['orderProduct'])->page($page, $limit)->select()->each(function ($item) {
            //(实付金额 - 一级佣金 - 二级佣金) * 抽成
            $commission_rate = ($item['commission_rate'] / 100);
            //佣金
            $_order_extension = bcadd($item['extension_one'], $item['extension_two'], 3);
            //手续费 =  (实付金额 - 一级佣金 - 二级佣金) * 比例
            $_order_rate = bcmul(bcsub($item['pay_price'], $_order_extension, 3), $commission_rate, 3);
            $item['order_extension'] = round($_order_extension, 2);
            $item['order_rate'] = round($_order_rate, 2);
            return $item;
        });

        return compact('count', 'list');
    }

    /**
     * @param array $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     */
    public function merchantGetList(array $where, $page, $limit)
    {
        $status = $where['status'];
        unset($where['status']);
        $query = $this->dao->search($where)->where($this->getOrderType($status))
            ->with([
                'orderProduct',
                'merchant' => function ($query) {
                    $query->field('mer_id,mer_name');
                },
                'verifyService' => function ($query) {
                    $query->field('service_id,nickname');
                },
                'finalOrder',
                'groupUser.groupBuying'
            ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        $productId = $this->dao->search($where)->where($this->getOrderType($status))->column('order_id');
        $make = app()->make(StoreRefundOrderRepository::class);
        $presellOrderRepository = app()->make(PresellOrderRepository::class);
        $orderRefund = $make->refundPirceByOrder($productId);
        $all = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->count();
        $countPay = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->field('sum(pay_price) as pay_price')->find();
        $countPay2 = $presellOrderRepository->search(['paid' => 1, 'mer_id' => $where['mer_id']])->sum('pay_price');
        $banclPay = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->where('pay_type', 0)->field('sum(pay_price) as pay_price')->find();
        $banclPay2 = $presellOrderRepository->search(['pay_type' => [0], 'paid' => 1, 'mer_id' => $where['mer_id']])->sum('pay_price');
        $wechatpay = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->where('pay_type', 'in', [1, 2, 3])->field('sum(pay_price) as pay_price')->find();
        $wechatpay2 = $presellOrderRepository->search(['pay_type' => [1, 2, 3], 'paid' => 1, 'mer_id' => $where['mer_id']])->sum('pay_price');
        $alipay = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->where('pay_type', 'in', [4, 5])->field('sum(pay_price) as pay_price')->find();
        $alipay2 = $presellOrderRepository->search(['pay_type' => [4, 5], 'paid' => 1, 'mer_id' => $where['mer_id']])->sum('pay_price');

        $stat = [
            [
                'className' => 'el-icon-s-goods',
                'count' => $all,
                'field' => '件',
                'name' => '已支付订单数量'
            ],
            [
                'className' => 'el-icon-s-order',
                'count' => (float)bcadd($countPay['pay_price'] ? $countPay['pay_price'] : 0, $countPay2, 2),
                'field' => '元',
                'name' => '实际支付金额'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => $orderRefund ? $orderRefund : 0,
                'field' => '元',
                'name' => '已退款金额'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => bcadd($wechatpay['pay_price'] ? $wechatpay['pay_price'] : 0, $wechatpay2, 2),
                'field' => '元',
                'name' => '微信支付金额'
            ],
            [
                'className' => 'el-icon-s-finance',
                'count' => bcadd($banclPay['pay_price'] ? $banclPay['pay_price'] : 0, $banclPay2, 2),
                'field' => '元',
                'name' => '余额支付金额'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => bcadd($alipay['pay_price'] ? $alipay['pay_price'] : 0, $alipay2, 2),
                'field' => '元',
                'name' => '支付宝支付金额'
            ],
        ];
        return compact('count', 'list', 'stat');
    }

    /**
     * TODO 平台总的订单列表
     * @param array $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2020-06-15
     */
    public function adminGetList(array $where, $page, $limit)
    {
        $status = $where['status'];
        unset($where['status']);
        $query = $this->dao->search($where, null)->where($this->getOrderType($status))
            ->with([
                'orderProduct',
                'merchant' => function ($query) {
                    return $query->field('mer_id,mer_name,is_trader');
                },
                'verifyService' => function ($query) {
                    return $query->field('service_id,nickname');
                },
                'groupOrder' => function($query){
                    $query->field('group_order_id,group_order_sn');
                },
                'finalOrder',
                'groupUser.groupBuying'
            ]);
        $count = $query->count();
        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        $productId = $this->dao->search($where)->where($this->getOrderType($status))->column('order_id');
        $make = app()->make(StoreRefundOrderRepository::class);
        $presellOrderRepository = app()->make(PresellOrderRepository::class);
        $orderRefund = $make->refundPirceByOrder($productId);
        $all = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->count();
        $countPay = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->field('sum(pay_price) as pay_price')->find();
        $countPay2 = $presellOrderRepository->search(['paid' => 1])->sum('pay_price');
        $banclPay = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->where('pay_type', 0)->field('sum(pay_price) as pay_price')->find();
        $banclPay2 = $presellOrderRepository->search(['pay_type' => [0], 'paid' => 1])->sum('pay_price');
        $wechatpay = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->where('pay_type', 'in', [1,2,3])->field('sum(pay_price) as pay_price')->find();
        $wechatpay2 = $presellOrderRepository->search(['pay_type' => [1, 2, 3], 'paid' => 1])->sum('pay_price');
        $alipay = $this->dao->search($where)->where($this->getOrderType($status))->where('paid', 1)->where('pay_type', 'in',[4,5])->field('sum(pay_price) as pay_price')->find();
        $alipay2 = $presellOrderRepository->search(['pay_type' => [4, 5], 'paid' => 1])->sum('pay_price');

        $stat = [
            [
                'className' => 'el-icon-s-goods',
                'count' => $all,
                'field' => '件',
                'name' => '已支付订单数量'
            ],
            [
                'className' => 'el-icon-s-order',
                'count' => (float)bcadd($countPay['pay_price'] ? $countPay['pay_price'] : 0, $countPay2, 2),
                'field' => '元',
                'name' => '实际支付金额'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => $orderRefund ? $orderRefund : 0,
                'field' => '元',
                'name' => '已退款金额'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => bcadd($wechatpay['pay_price'] ? $wechatpay['pay_price'] : 0, $wechatpay2, 2),
                'field' => '元',
                'name' => '微信支付金额'
            ],
            [
                'className' => 'el-icon-s-finance',
                'count' => bcadd($banclPay['pay_price'] ? $banclPay['pay_price'] : 0, $banclPay2, 2),
                'field' => '元',
                'name' => '余额支付金额'
            ],
            [
                'className' => 'el-icon-s-cooperation',
                'count' => bcadd($alipay['pay_price'] ? $alipay['pay_price'] : 0, $alipay2, 2),
                'field' => '元',
                'name' => '支付宝支付金额'
            ],
        ];
        return compact('count', 'list', 'stat');
    }

    /**
     * @param array $where
     * @param $page
     * @param $limit
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/10
     */
    public function getList(array $where, $page, $limit)
    {
        $query = $this->dao->search($where)->where('is_del', 0);
        $count = $query->count();
        $list = $query->with(['orderProduct', 'presellOrder', 'merchant' => function ($query) {
            return $query->field('mer_id,mer_name');
        }])->page($page, $limit)->order('pay_time DESC')->select();

        foreach ($list as $order) {
            if ($order->activity_type == 2) {
                if ($order->presellOrder) {
                    $order->presellOrder->append(['activeStatus']);
                    $order->presell_price = bcadd($order->pay_price, $order->presellOrder->pay_price, 2);
                } else {
                    $order->presell_price = $order->pay_price;
                }
            }
        }

        return compact('list', 'count');
    }

    public function userList($uid, $page, $limit)
    {
        $query = $this->dao->search([
            'uid' => $uid,
            'paid' => 1
        ]);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }


    public function userMerList($uid, $merId, $page, $limit)
    {
        $query = $this->dao->search([
            'uid' => $uid,
            'mer_id' => $merId,
            'paid' => 1
        ]);
        $count = $query->count();
        $list = $query->with(['presellOrder'])->page($page, $limit)->select();
        foreach ($list as $order) {
            if ($order->activity_type == 2 && $order->status >= 0 && $order->status < 10 && $order->presellOrder) {
                $order->pay_price = bcadd($order->pay_price, $order->presellOrder->pay_price, 2);
            }
        }
        return compact('count', 'list');
    }

    public function express($orderId)
    {
        $product = $this->dao->get($orderId);
        return ExpressService::express($product['delivery_id']);
    }

    public function checkPrinterConfig(int $merId)
    {
        if (!merchantConfig($merId, 'printing_status'))
            throw new ValidateException('打印功能未开启');
        $config = [
            'clientId' => merchantConfig($merId, 'printing_client_id'),
            'apiKey' => merchantConfig($merId, 'printing_api_key'),
            'partner' => merchantConfig($merId, 'develop_id'),
            'terminal' => merchantConfig($merId, 'terminal_number')
        ];
        if (!$config['clientId'] || !$config['apiKey'] || !$config['partner'] || !$config['terminal'])
            throw new ValidateException('打印机配置错误');
        return $config;
    }

    /**
     * TODO 打印机
     * @param int $id
     * @param int $merId
     * @return bool|mixed|string
     * @author Qinii
     * @day 2020-07-30
     */
    public function printer(int $id, int $merId)
    {
        $res = $this->dao->getWhere(['order_id' => $id], '*', ['orderProduct', 'merchant' => function ($query) {
            $query->field('mer_id,mer_name');
        }]);
        foreach ($res['orderProduct'] as $item) {
            $product[] = [
                'store_name' => $item['cart_info']['product']['store_name'] . '【' . $item['cart_info']['productAttr']['sku'] . '】',
                'product_num' => $item['product_num'],
                'price' => $item['product_price'],
                'product_price' => bcmul($item['product_price'], $item['product_num'], 2)
            ];
        }
        $data = [
            'order_sn' => $res['order_sn'],
            'pay_time' => $res['pay_time'],
            'real_name' => $res['real_name'],
            'user_phone' => $res['user_phone'],
            'user_address' => $res['user_address'],
            'total_price' => $res['total_price'],
            'coupon_price' => $res['coupon_price'],
            'pay_price' => $res['pay_price'],
            'total_postage' => $res['total_postage'],
            'pay_postage' => $res['pay_postage'],
            'mark' => $res['mark'],
        ];
        $config = $this->checkPrinterConfig($merId);
        $printer = new Printer('yi_lian_yun', $config);
        return $res = $printer->setPrinterContent([
            'name' => $res['merchant']['mer_name'],
            'orderInfo' => $data,
            'product' => $product
        ])->startPrinter();
    }

    public function verifyOrder($id, $merId, $serviceId)
    {
        $order = $this->dao->getWhere(['verify_code' => $id, 'mer_id' => $merId]);
        if (!$order)
            throw new ValidateException('订单不存在');
        if ($order->status != 0)
            throw new ValidateException('订单状态有误');
        if (!$order->paid)
            throw new ValidateException('订单未支付');
        $order->status = 2;
        $order->verify_time = date('Y-m-d H:i:s');
        $order->verify_service_id = $serviceId;
        Db::transaction(function () use ($order) {
            $this->takeAfter($order, $order->user);
            $order->save();
        });
    }

    public function wxQrcode($orderId, $verify_code)
    {
        $siteUrl = systemConfig('site_url');
        $name = md5('owx' . $orderId . date('Ymd')) . '.jpg';
        $attachmentRepository = app()->make(AttachmentRepository::class);
        $imageInfo = $attachmentRepository->getWhere(['attachment_name' => $name]);

        if (isset($imageInfo['attachment_src']) && strstr($imageInfo['attachment_src'], 'http') !== false && curl_file_exist($imageInfo['attachment_src']) === false) {
            $imageInfo->delete();
            $imageInfo = null;
        }
        if (!$imageInfo) {
//            $codeUrl = set_http_type(rtrim($siteUrl, '/') . '/pages/admin/order_cancellation/index?verify_code=' . $verify_code, request()->isSsl() ? 0 : 1);//二维码链接
            $imageInfo = app()->make(QrcodeService::class)->getQRCodePath($verify_code, $name);
            if (is_string($imageInfo)) throw new ValidateException('二维码生成失败');

            $imageInfo['dir'] = tidy_url($imageInfo['dir'], null, $siteUrl);

            $attachmentRepository->create(systemConfig('upload_type') ?: 1, -2, $orderId, [
                'attachment_category_id' => 0,
                'attachment_name' => $imageInfo['name'],
                'attachment_src' => $imageInfo['dir']
            ]);
            $urlCode = $imageInfo['dir'];
        } else $urlCode = $imageInfo['attachment_src'];
        return $urlCode;
    }

    public function routineQrcode($orderId, $verify_code)
    {
        $name = md5('sort' . $orderId . date('Ymd')) . '.jpg';
        return app()->make(QrcodeService::class)->getRoutineQrcodePath($name, 'pages/admin/order_cancellation/index', 'verify_code=' . $verify_code);
    }

    /**
     * TODO 根据商品ID获取订单数
     * @param int $productId
     * @return int
     * @author Qinii
     * @day 2020-08-05
     */
    public function seckillOrderCounut(int $productId)
    {
        $where = [
            'activity_id' => $productId,
            'product_type' => 1,
            'day' => date('Y-m-d', time())
        ];
        $count = $this->dao->getTattendCount($where,null)->count();
        $count_ = $this->dao->getSeckillRefundCount($where,2);
        $count__ = $this->dao->getSeckillRefundCount($where,1);
        return $count - $count_ - $count__;
    }

    /**
     * TODO 根据商品sku获取订单数
     * @param int $productId
     * @return int
     * @author Qinii
     * @day 2020-08-05
     */
    public function seckillSkuOrderCounut(string $sku)
    {
        $where = [
            'product_sku' => $sku,
            'product_type' => 1,
            'day' => date('Y-m-d', time())
        ];
        $count = $this->dao->getTattendCount($where,null)->count();
        $count_ = $this->dao->getSeckillRefundCount($where,2);
        $count__ = $this->dao->getSeckillRefundCount($where,1);
        return $count - $count_ - $count__;
    }

    /**
     * TODO 秒杀获取个人当天限购
     * @param int $uid
     * @param int $productId
     * @return int
     * @author Qinii
     * @day 2020-08-15
     */
    public function getDayPayCount(int $uid, int $productId)
    {
        $make = app()->make(StoreSeckillActiveRepository::class);
        $active = $make->getWhere(['product_id' => $productId]);
        if ($active['once_pay_count'] == 0) return true;

        $where = [
            'activity_id' => $productId,
            'product_type' => 1,
            'day' => date('Y-m-d', time())
        ];

        $count = $this->dao->getTattendCount($where,$uid)->count();
        return ($active['once_pay_count'] > $count);
    }

    /**
     * TODO 秒杀获取个人总限购
     * @param int $uid
     * @param int $productId
     * @return int
     * @author Qinii
     * @day 2020-08-15
     */
    public function getPayCount(int $uid, int $productId)
    {
        $make = app()->make(StoreSeckillActiveRepository::class);
        $active = $make->getWhere(['product_id' => $productId]);
        if ($active['all_pay_count'] == 0) return true;
        $where = [
            'activity_id' => $productId,
            'product_type' => 1,
            'day' => date('Y-m-d', time())
        ];
        $count = $this->dao->getTattendCount($where,$uid)->count();
        return ($active['all_pay_count'] > $count);
    }

    /**
     *  根据订单id查看是否全部退款
     * @Author:Qinii
     * @Date: 2020/9/11
     * @param int $orderId
     * @return bool
     */
    public function checkRefundStatusById(int $orderId, int $refundId)
    {
        Db::transaction(function () use ($orderId, $refundId) {
            $res = $this->dao->search(['order_id' => $orderId])->with(['orderProduct'])->find();
            $refund = app()->make(StoreRefundOrderRepository::class)->getRefundCount($orderId, $refundId);
            if ($refund) return false;
            foreach ($res['orderProduct'] as $item) {
                if ($item['refund_num'] !== 0) return false;
                $item->is_refund = 3;
                $item->save();
            }
            $res->status = -1;
            $res->save();
            app()->make(StoreOrderStatusRepository::class)->status($orderId, 'refund_all', '订单已全部退款');
        });
    }

    /**
     * @param $id
     * @param $uid
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/9/17
     */
    public function userDel($id, $uid)
    {
        $order = $this->dao->getWhere([['status', 'in', [0, 3, -1, 11]], ['order_id', '=', $id], ['uid', '=', $uid], ['is_del', '=', 0]]);
        if (!$order || ($order->status == 0 && $order->paid == 1))
            throw new ValidateException('订单状态有误');
        $this->delOrder($order, '订单删除');
    }

    public function delOrder($order, $info = '订单删除')
    {
        Db::transaction(function () use ($info, $order) {
            $order->is_del = 1;
            $order->save();
            app()->make(StoreOrderStatusRepository::class)->status($order->order_id, 'delete', $info);
            $productRepository = app()->make(ProductRepository::class);
            foreach ($order->orderProduct as $cart) {
                $productRepository->orderProductIncStock($cart);
            }
        });
    }

    public function merDelete($id)
    {
        Db::transaction(function()use($id){
            $data['is_system_del'] = 1;
            $this->dao->update($id,$data);
            app()->make(StoreOrderReceiptRepository::class)->deleteByOrderId($id);
        });
    }
}
