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


namespace app\common\dao\store\order;


use app\common\dao\BaseDao;
use app\common\model\store\order\StoreRefundProduct;

class StoreRefundProductDao extends BaseDao
{

    protected function getModel(): string
    {
        return StoreRefundProduct::class;
    }

    public function search(array $where)
    {
        $query = $this->getModel()::getDB()
            ->when(isset($where['order_id']) && $where['order_id'] !== '',function($query)use($where){
                $query->where('order_id',$where['order_id']);
            });

        return $query->order('create_time');
    }
}