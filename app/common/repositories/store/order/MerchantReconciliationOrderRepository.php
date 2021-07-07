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

namespace app\common\repositories\store\order;

use app\common\repositories\BaseRepository;
use app\common\dao\store\order\MerchantReconciliationOrderDao as dao;

class MerchantReconciliationOrderRepository extends BaseRepository
{
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    public function getIds($where)
    {
        return $this->dao->search($where)->column('order_id');
    }
}
