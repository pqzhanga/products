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

namespace app\common\model\user;

use app\common\model\BaseModel;

class Feedback extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'feedback_id';
    }

    public static function tableName(): string
    {
        return 'feedback';
    }

    public function getImagesAttr($val)
    {
        return $val ? json_decode($val, true) : [];
    }

    public function setImagesAttr($val)
    {
        return json_encode($val ?: []);
    }

    public function user()
    {
        return $this->hasOne(User::class,'uid','uid');
    }

    public function type()
    {
        return $this->hasOne(FeedBackCategory::class,'feedback_category_id','type');
    }
}
