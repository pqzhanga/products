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

// 事件定义文件
return [
    'bind' => [
    ],

    'listen' => [
        'AppInit' => [],
        'HttpRun' => [],
        'HttpEnd' => [],
        'LogLevel' => [],
        'LogWrite' => [],
        'swoole.task' => [\crmeb\listens\SwooleTaskListen::class],
        'swoole.init' => [\crmeb\listens\InitSwooleLockListen::class],
        'swoole.workerStart' => [\app\webscoket\SwooleWorkerStart::class],
        'swoole.start' => env('INSTALLED', false) ? [
            \crmeb\listens\AuthTakeOrderListen::class,
            \crmeb\listens\AutoCancelGroupOrderListen::class,
            \crmeb\listens\AuthCancelPresellOrderListen::class,
            \crmeb\listens\AutoUnLockBrokerageListen::class,
            \crmeb\listens\AutoSendPayOrderSmsListen::class,
            \crmeb\listens\SyncSmsResultCodeListen::class,
            \crmeb\listens\SyncBroadcastStatusListen::class,
            \crmeb\listens\ExcelFileDelListen::class,
            \crmeb\listens\RefundOrderAgreeListen::class,
            \crmeb\listens\SeckillTImeCheckListen::class,
            \crmeb\listens\AutoOrderReplyListen::class,
            \crmeb\listens\ProductPresellStatusListen::class,
            \crmeb\listens\ProductGroupStatusCheckListen::class,
        ] : [],
        'pay_success_user_recharge' => [\crmeb\listens\pay\UserRechargeSuccessListen::class],
        'pay_success_order' => [\crmeb\listens\pay\OrderPaySuccessListen::class],
        'pay_success_presell' => [\crmeb\listens\pay\PresellPaySuccessListen::class]
    ],

    'subscribe' => [
    ],
];
