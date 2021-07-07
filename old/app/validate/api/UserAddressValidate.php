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


namespace app\validate\api;

use think\Validate;

class UserAddressValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'real_name|收货人姓名' => 'require',
        'phone|收货人电话' => 'require|alphaDash',
        'province|收货人所在省' => 'require',
        'city|收货人所在市' => 'require',
        'district|收货人所在区' => 'require',
        'detail|收货人详细地址' => 'require',
    ];
}
