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

use Swoole\Timer;
use think\facade\Log;
use crmeb\interfaces\ListenerInterface;
use app\common\repositories\store\product\ProductGroupBuyingRepository;

class ProductGroupStatusCheckListen implements ListenerInterface
{
    public function handle($event): void
    {
        $make = app()->make(ProductGroupBuyingRepository::class);
        Timer::tick(1000 * 60, function () use ($make) {
            try {
                $make->checkStatus(null);
            } catch (\Exception $e) {
                Log::info('自动检测拼团结束失败' . date('Y-m-d H:i:s', time()));
            }
        });
    }
}
