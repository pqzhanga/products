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


namespace crmeb\listens;


use app\common\repositories\user\UserBillRepository;
use crmeb\interfaces\ListenerInterface;
use Swoole\Timer;
use think\facade\Db;

class AutoUnLockBrokerageListen implements ListenerInterface
{

    public function handle($event): void
    {
        //TODO 自动解冻佣金
        $userBill = app()->make(UserBillRepository::class);
        Timer::tick(1000 * 60 * 20, function () use ($userBill) {
            request()->clearCache();
            $timer = ((int)systemConfig('lock_brokerage_timer'));
            $time = date('Y-m-d H:i:s', $timer ? strtotime("- $timer day") : time());
            $bills = $userBill->getTimeoutBrokerageBill($time);
            Db::transaction(function () use ($userBill, $bills) {
                foreach ($bills as $bill) {
                    if ($bill->number > 0) {
                        $refund = $userBill->refundBrokerage($bill->link_id, $bill->uid);
                        $bill->user->brokerage_price = bcadd($bill->user->brokerage_price, bcsub($bill->number, $refund, 2), 2);
                        $bill->user->save();
                    }
                    $bill->status = 1;
                    $bill->balance = $bill->user->brokerage_price;
                    $bill->save();
                }
            });
        });
    }
}