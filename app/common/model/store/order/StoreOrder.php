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
use app\common\model\store\product\ProductGroupUser;
use app\common\model\store\service\StoreService;
use app\common\model\system\merchant\Merchant;
use app\common\model\user\User;
use app\common\repositories\store\MerchantTakeRepository;

class StoreOrder extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'order_id';
    }

    public static function tableName(): string
    {
        return 'store_order';
    }

    public function orderProduct()
    {
        return $this->hasMany(StoreOrderProduct::class, 'order_id', 'order_id');
    }

    public function refundProduct()
    {
        return $this->orderProduct()->where('refund_num', '>', 0);
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    public function groupOrder()
    {
        return $this->hasOne(StoreGroupOrder::class, 'group_order_id', 'group_order_id');
    }

    public function verifyService()
    {
        return $this->hasOne(StoreService::class, 'service_id', 'verify_service_id');
    }

    public function getTakeAttr()
    {
        return app()->make(MerchantTakeRepository::class)->get($this->mer_id);
    }

    public function searchDataAttr($query, $value)
    {
        return getModelTime($query, $value);
    }

    public function presellOrder()
    {
        return $this->hasOne(PresellOrder::class, 'order_id', 'order_id');
    }

    public function finalOrder()
    {
        return $this->hasOne(PresellOrder::class,'order_id','order_id');
    }

    public function groupUser()
    {
        return $this->hasOne(ProductGroupUser::class,'order_id','order_id');
    }
}
