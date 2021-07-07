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


namespace app\controller\api;


use crmeb\basic\BaseController;
use crmeb\services\WechatService;

class Wechat extends BaseController
{
    public function jsConfig()
    {
        return app('json')->success(WechatService::create()->jsSdk($this->request->param('url')?:$this->request->host()));
    }
}