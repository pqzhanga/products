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


namespace app\common\repositories\system\merchant;


use app\common\dao\system\merchant\FinancialRecordDao;
use app\common\repositories\BaseRepository;
use think\facade\Db;

/**
 * Class FinancialRecordRepository
 * @package app\common\repositories\system\merchant
 * @author xaboy
 * @day 2020/8/5
 * @mixin FinancialRecordDao
 */
class FinancialRecordRepository extends BaseRepository
{
    public function __construct(FinancialRecordDao $dao)
    {
        $this->dao = $dao;
    }

    public function getList(array $where, $page, $limit)
    {
        $query = $this->dao->search($where);
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        $sum = [
            'pro'=>0,
            'pay'=>0,
            'todaypay'=>0,
            'exp'=>0,
        ];
 
        $sum['todaypay'] =  Db::table('eb_store_order')->whereBetween('create_time',[date('Y-m-d 00:00:00'),date('Y-m-d H:i:s')])->where('status','>=',0)->where('paid',1)->sum('pay_price');
        $sum['pro'] =  Db::table('eb_store_order')->where('status','>',1)->sum('total_price');
        $sum['pay'] =  Db::table('eb_store_order')->where('status','>',1)->sum('pay_price');
        $sum['exp'] =  Db::table('eb_store_order')->where('status','>',1)->sum('total_postage');

        return compact('count', 'list','sum');
    }
}