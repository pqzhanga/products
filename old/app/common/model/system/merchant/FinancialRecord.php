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


namespace app\common\model\system\merchant;


use app\common\model\BaseModel;
use app\common\model\user\User;
use app\common\repositories\system\merchant\MerchantRepository;

class FinancialRecord extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'financial_record_id';
    }

    public static function tableName(): string
    {
        return 'financial_record';
    }

    public function user()
    {
        return $this->hasOne(User::class,'uid','user_id');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class,'mer_id','mer_id');
    }
}
