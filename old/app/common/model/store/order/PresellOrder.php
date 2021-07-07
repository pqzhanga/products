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


namespace app\common\model\store\order;


use app\common\model\BaseModel;
use app\common\model\user\User;
use app\common\repositories\store\order\PresellOrderRepository;

class PresellOrder extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'presell_order_id';
    }

    public static function tableName(): string
    {
        return 'presell_order';
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    public function order()
    {
        return $this->hasOne(StoreOrder::class, 'order_id', 'order_id');
    }


    public function searchOrderIdAttr($query,$value)
    {
        $query->where('order_id',$value);
    }

    public function getActiveStatusAttr()
    {
        $status = 1;
        $now = time();
        if (strtotime($this->final_start_time) > $now) $status = 0;
        else if (strtotime($this->final_end_time) < $now) {
            if ($this->status && $this->presell_order_id)
                app()->make(PresellOrderRepository::class)->cancel($this->presell_order_id);
            $status = 2;
        }
        return $status;
    }
}
