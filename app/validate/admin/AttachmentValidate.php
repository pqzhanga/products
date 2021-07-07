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


namespace app\validate\admin;


use think\Validate;

class AttachmentValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'attachment_category_id|选择分类' => 'require|integer',
        'attachment_name|附件名称' => 'require|max:255',
        'attachment_src|分类目录' => 'require|max:255',
    ];
}