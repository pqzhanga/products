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


use app\common\repositories\store\broadcast\BroadcastGoodsRepository;
use app\common\repositories\store\broadcast\BroadcastRoomRepository;
use crmeb\interfaces\ListenerInterface;
use Swoole\Timer;
use think\facade\Log;

class SyncBroadcastStatusListen implements ListenerInterface
{

    public function handle($event): void
    {
        $broadcastRoomRepository = app()->make(BroadcastRoomRepository::class);
        $broadcastGoodsRepository = app()->make(BroadcastGoodsRepository::class);
        Timer::tick(1000 * 60 * 5, function () use ($broadcastGoodsRepository) {
            try {
                $broadcastGoodsRepository->syncGoodStatus();
            } catch (\Exception $e) {
                Log::error('同步直播商品:' . $e->getMessage());
            }
        });

        Timer::tick(1000 * 60 * 5, function () use ($broadcastRoomRepository) {
            try {
                $broadcastRoomRepository->syncRoomStatus();
            } catch (\Exception $e) {
                Log::error('同步直播间:' . $e->getMessage());
            }
        });
    }
}