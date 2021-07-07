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

namespace app\common\repositories\store\product;

use app\common\dao\store\product\ProductAssistSkuDao;
use app\common\repositories\BaseRepository;
use think\facade\Db;

class ProductAssistSkuRepository extends BaseRepository
{
    public function __construct(ProductAssistSkuDao $dao)
    {
        $this->dao = $dao;
    }
}
