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


use app\common\dao\store\order\PresellOrderDao;
use app\common\model\store\order\PresellOrder;
use app\common\model\user\User;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductPresellSkuRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserMerchantRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\AlipayService;
use crmeb\services\MiniProgramService;
use crmeb\services\WechatService;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;

/**
 * Class PresellOrderRepository
 * @package app\common\repositories\store\order
 * @author xaboy
 * @day 2020/10/27
 * @mixin PresellOrderDao
 */
class PresellOrderRepository extends BaseRepository
{
    public function __construct(PresellOrderDao $dao)
    {
        $this->dao = $dao;
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
        $orderId = 'ps' . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));
        return $orderId;
    }

    public function createOrder($uid, $orderId, $price, $final_start_time, $final_end_time)
    {
        return $this->dao->create([
            'uid' => $uid,
            'order_id' => $orderId,
            'final_start_time' => $final_start_time,
            'final_end_time' => $final_end_time,
            'pay_price' => $price,
            'presell_order_sn' => $this->getNewOrderId()
        ]);
    }

    public function pay($type, User $user, PresellOrder $order, $return_url = '')
    {
        $method = 'pay' . ucfirst($type);
        if (!method_exists($this, $method))
            throw new ValidateException('不支持该支付方式');
        return $this->{$method}($user, $order, $return_url);
    }

    /**
     * @param User $user
     * @param PresellOrder $order
     * @return mixed
     * @author xaboy
     * @day 2020/6/9
     */
    public function payBalance(User $user, PresellOrder $order)
    {
        if (!systemConfig('yue_pay_status'))
            throw new ValidateException('未开启余额支付');
        if ($user['now_money'] < $order['pay_price'])
            throw new ValidateException('余额不足' . floatval($order['pay_price']));
        Db::transaction(function () use ($user, $order) {
            $user->now_money = bcsub($user->now_money, $order['pay_price'], 2);
            $user->save();
            $userBillRepository = app()->make(UserBillRepository::class);
            $userBillRepository->decBill($user['uid'], 'now_money', 'presell', [
                'link_id' => $order['presell_order_id'],
                'status' => 1,
                'title' => '支付预售尾款',
                'number' => $order['pay_price'],
                'mark' => '余额支付支付' . floatval($order['pay_price']) . '元购买商品',
                'balance' => $user->now_money
            ]);
            $this->paySuccess($order);
        });
        return app('json')->status('success', '余额支付成功', ['order_id' => $order['presell_order_id']]);
    }

    /**
     * @param User $user
     * @param PresellOrder $groupOrder
     * @return \think\response\Json
     * @author xaboy
     * @day 2020/10/28
     */
    public function payWeixin(User $user, PresellOrder $groupOrder)
    {
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $openId = $wechatUserRepository->idByOpenId($user['wechat_user_id']);
        if (!$openId)
            new ValidateException('请关联微信公众号!');
        $config = WechatService::create()->jsPay($openId, $groupOrder['presell_order_sn'], $groupOrder['pay_price'], 'presell', '尾款支付');
        return app('json')->status('weixin', ['config' => $config, 'order_id' => $groupOrder['presell_order_id']]);
    }

    /**
     * @param User $user
     * @param PresellOrder $groupOrder
     * @return \think\response\Json
     * @author xaboy
     * @day 2020/6/9
     */
    public function payRoutine(User $user, PresellOrder $groupOrder)
    {
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $openId = $wechatUserRepository->idByRoutineId($user['wechat_user_id']);
        if (!$openId)
            new ValidateException('请关联微信小程序!');
        $config = MiniProgramService::create()->jsPay($openId, $groupOrder['presell_order_sn'], $groupOrder['pay_price'], 'presell', '尾款支付');
        return app('json')->status('routine', ['config' => $config, 'order_id' => $groupOrder['presell_order_id']]);
    }

    /**
     * @param User $user
     * @param PresellOrder $groupOrder
     * @return mixed
     * @author xaboy
     * @day 2020/6/9
     */
    public function payH5(User $user, PresellOrder $groupOrder)
    {
        $config = WechatService::create()->paymentPrepare(null, $groupOrder['presell_order_sn'], $groupOrder['pay_price'], 'presell', '尾款支付', '', 'MWEB');
        return app('json')->status('h5', ['config' => $config, 'order_id' => $groupOrder['presell_order_id']]);
    }

    /**
     * @param User $user
     * @param PresellOrder $groupOrder
     * @param $return_url
     * @return \think\response\Json
     * @author xaboy
     * @day 2020/10/22
     */
    public function payAlipay(User $user, PresellOrder $groupOrder, $return_url)
    {
        $url = AlipayService::create('presell')->wapPaymentPrepare($groupOrder['presell_order_sn'], $groupOrder['pay_price'], '尾款支付', $return_url);
        $pay_key = md5($url);
        Cache::store('file')->set('pay_key' . $pay_key, $url, 3600);
        return app('json')->status('alipay', ['config' => $url, 'pay_key' => $pay_key, 'order_id' => $groupOrder['presell_order_id']]);
    }

    /**
     * @param User $user
     * @param PresellOrder $groupOrder
     * @return \think\response\Json
     * @author xaboy
     * @day 2020/10/22
     */
    public function payAlipayQr(User $user, PresellOrder $groupOrder)
    {
        $url = AlipayService::create('presell')->qrPaymentPrepare($groupOrder['presell_order_sn'], $groupOrder['pay_price'], '尾款支付');
        return app('json')->status('alipayQr', ['config' => $url, 'order_id' => $groupOrder['presell_order_id']]);
    }

    public function paySuccess(PresellOrder $order)
    {
        Db::transaction(function () use ($order) {
            $time = date('Y-m-d H:i:s');
            $order->paid = 1;
            $order->pay_time = $time;
            $order->order->status = 0;
            if ($order->order->order_type == 1) {
                $order->order->verify_code = app()->make(StoreOrderRepository::class)->verifyCode();
            }
            $order->order->save();
            $order->save();
            $orderStatus = [
                'order_id' => $order->order_id,
                'change_message' => '订单尾款支付成功',
                'change_type' => 'presell'
            ];

            $finance = [
                'order_id' => $order->order_id,
                'order_sn' => $order->presell_order_sn,
                'user_info' => $order->user->nickname,
                'user_id' => $order->uid,
                'financial_type' => 'presell',
                'financial_pm' => 1,
                'number' => $order->pay_price,
                'mer_id' => $order->mer_id,
                'financial_record_sn' => app()->make(FinancialRecordRepository::class)->getSn() . '0'
            ];
            app()->make(UserRepository::class)->update($order->uid, [
                'pay_price' => Db::raw('pay_price+' . $order->pay_price),
            ]);
            app()->make(ProductPresellSkuRepository::class)->incCount($order->order->orderProduct[0]['activity_id'], $order->order->orderProduct[0]['product_sku'], 'two_pay');
            app()->make(UserMerchantRepository::class)->updatePayTime($order->uid, $order->mer_id, $order->pay_price, false);
            app()->make(FinancialRecordRepository::class)->create($finance);
            app()->make(StoreOrderStatusRepository::class)->create($orderStatus);
        });
    }

    public function cancel($id)
    {
        $order = $this->dao->getWhere(['presell_order_id' => $id, 'paid' => 0]);
        if (!$order) return;

        $orderStatus = [
            'order_id' => $order->order_id,
            'change_message' => '预售订单超时支付自动关闭',
            'change_type' => 'presell_close'
        ];

        $productRepository = app()->make(ProductRepository::class);
        Db::transaction(function () use ($productRepository, $order, $orderStatus) {
            app()->make(StoreOrderStatusRepository::class)->create($orderStatus);
            $order->order->status = 11;
            $order->status = 0;
            $order->save();
            $order->order->save();
            foreach ($order->order->orderProduct as $cart) {
                $productRepository->orderProductIncStock($cart);
            }
        });
    }
}
