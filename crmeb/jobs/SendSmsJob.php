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


namespace crmeb\jobs;


use crmeb\interfaces\JobInterface;
use crmeb\services\YunxinSmsService;
use think\facade\Log;

class SendSmsJob implements JobInterface
{

    public function fire($job, $data)
    {
        try {
            YunxinSmsService::sendMessage($data['tempId'], $data['id']);
        } catch (\Exception $e) {
            Log::info('发送短信失败' . var_export($data, 1) . $e->getMessage());
        }
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
