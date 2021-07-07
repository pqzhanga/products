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

use app\common\repositories\store\order\PresellOrderRepository;
use app\common\repositories\store\product\ProductPresellRepository;
use crmeb\jobs\CheckProductPresellJob;
use crmeb\jobs\SendSmsJob;
use Swoole\Timer;
use think\facade\Log;
use crmeb\interfaces\ListenerInterface;
use think\facade\Queue;

class ProductPresellStatusListen implements ListenerInterface
{
    public function handle($event): void
    {
        Timer::tick(1000 * 300, function () {
            try {
                Queue::push(CheckProductPresellJob::class, []);
            } catch (\Exception $e) {
                Log::info('自动更新失败');
            }
            try {
                $ids = app()->make(PresellOrderRepository::class)->sendSmsIds(date('Y-m-d H'));
                foreach ($ids as $id) {
                    Queue::push(SendSmsJob::class, ['tempId' => 'PAY_PRESELL_CODE', 'id' => (int)$id]);
                }
            } catch (\Exception $e) {
                Log::info('预售短信通知失败');
            }
        });
    }
}
