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

namespace app\common\dao\user;

use app\common\dao\BaseDao;
use app\common\model\user\MerExtract  as model;

class MerExtractDao extends  BaseDao
{
    protected function getModel(): string
    {
        return model::class;
    }

    public function search(array $where)
    {
        if(isset($where['wechat']) && $where['wechat'] != '') {
            $query = model::hasWhere('user',function ($query)use($where){
                $query->where('nickname',"%{$where['wechat']}%");
            });
        }else{
            $query = model::alias('UserExtract');
        }
       $query->when(isset($where['uid']) && $where['uid'] != '',function($query)use($where){
            $query->where('uid',$where['uid']);
        })->when(isset($where['extract_type']) && $where['extract_type'] != '',function($query)use($where){
            $query->where('extract_type',$where['extract_type']);
        })->when(isset($where['keyword']) && $where['keyword'] != '',function($query)use($where){
           $query->whereLike('UserExtract.real_name|UserExtract.uid|bank_code|alipay_code|wechat',"%{$where['keyword']}%");
        })->when(isset($where['status']) && $where['status'] != '',function($query)use($where){
            $query->where('UserExtract.status',$where['status']);
        })->when(isset($where['real_name']) && $where['real_name'] != '',function($query)use($where){
            $query->where('UserExtract.real_name','%'.$where['real_name'].'%');
        })->when(isset($where['date']) && $where['date'] != '',function($query)use($where){
            getModelTime($query, $where['date']);
        })->order('UserExtract.create_time DESC');

        return $query;
    }
}
