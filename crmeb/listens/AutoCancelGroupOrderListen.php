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


use app\common\repositories\store\order\StoreGroupOrderRepository;
use crmeb\interfaces\ListenerInterface;
use Swoole\Timer;
use think\facade\Log;

class AutoCancelGroupOrderListen implements ListenerInterface
{

    public function handle($event): void
    {
        $storeGroupOrderRepository = app()->make(StoreGroupOrderRepository::class);
        Timer::tick(60000, function () use ($storeGroupOrderRepository) {
            request()->clearCache();
            $timer = ((int)systemConfig('auto_close_order_timer')) ?: 15;
            $time = date('Y-m-d H:i:s', strtotime("- $timer minutes"));
            $groupOrderIds = $storeGroupOrderRepository->getTimeOutIds($time);
            foreach ($groupOrderIds as $id) {
                try {
                    $storeGroupOrderRepository->cancel($id);
                } catch (\Exception $e) {
                    Log::info('自动关闭订单失败' . var_export($id, 1));
                }
            }
        });
    }
}