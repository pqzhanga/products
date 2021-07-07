<?php


namespace crmeb\listens\pay;

use app\common\repositories\store\order\StoreGroupOrderRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\interfaces\ListenerInterface;
use think\facade\Log;
use think\facade\Request;
use crmeb\services\HttpService;
class OrderScPaySuccessListen implements ListenerInterface
{
    public function handle($data): void
    {
        $orderSn = $data['order_sn'];
        $url=Request::domain().':8080'.'/SzysServer/sendingMsg?oid='.$orderSn;
        $s_data=HttpService::getRequest($url);
        Log::info('语音播报：'. $s_data);
    }
}