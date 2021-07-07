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


namespace app\common\model\store\product;

use app\common\model\BaseModel;

class ProductAttrValue extends BaseModel
{


    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    public static function tablePk(): string
    {
        return '';
    }


    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    public static function tableName(): string
    {
        return 'store_product_attr_value';
    }

    public function getDetailAttr($value)
    {
        return json_decode($value);
    }

    public function getBcExtensionOneAttr()
    {
        if(!intval(systemConfig('extension_status')))  return 0;
        if($this->extension_one > 0)  return $this->extension_one;
        return floatval(round(bcmul(systemConfig('extension_one_rate'), $this->price, 3),2));
    }

    public function getBcExtensionTwoAttr()
    {
        if(!intval(systemConfig('extension_status')))  return 0;
        if($this->extension_two > 0)  return $this->extension_two;
        return floatval(round(bcmul(systemConfig('extension_two_rate'), $this->price, 3),2));
    }

    public function searchUniqueAttr($query,$value)
    {
        $query->where('unique',$value);
    }

    public function searchSkuAttr($query,$value)
    {
        $query->where('sku',$value);
    }

    public function searchProductIdAttr($query,$value)
    {
        $query->where('product_id',$value);
    }


}
