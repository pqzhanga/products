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


namespace crmeb\listens\pay;


use app\common\repositories\store\order\PresellOrderRepository;
use crmeb\interfaces\ListenerInterface;

class PresellPaySuccessListen implements ListenerInterface
{

    public function handle($data): void
    {
        $orderSn = $data['order_sn'];
        $order = app()->make(PresellOrderRepository::class)->getWhere(['presell_order_sn' => $orderSn]);
        if (!$order || $order->paid == 1) return;
        app()->make(PresellOrderRepository::class)->paySuccess($order);
    }
}