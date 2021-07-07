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
use app\common\model\BaseModel;
use app\common\model\user\UserBill;

/**
 * Class UserBillDao
 * @package app\common\dao\user
 * @author xaboy
 * @day 2020/6/22
 */
class UserBillDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return UserBill::class;
    }

    /**
     * @param array $where
     * @param $data
     * @return int
     * @throws \think\db\exception\DbException
     * @author xaboy
     * @day 2020/6/22
     */
    public function updateBill(array $where, $data)
    {
        return UserBill::getDB()->where($where)->limit(1)->update($data);
    }

    /**
     * @param $time
     * @return \think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author xaboy
     * @day 2020/6/22
     */
    public function getTimeoutBrokerageBill($time)
    {
        return UserBill::getDB()->where('create_time', '<=', $time)->where('category', 'brokerage')
            ->whereIn('type', ['order_one', 'order_two'])->with('user')->where('status', 0)->select();
    }

    /**
     * @param $uid
     * @return float
     * @author xaboy
     * @day 2020/6/22
     */
    public function lockBrokerage($uid)
    {
        return UserBill::getDB()->where('category', 'brokerage')
            ->whereIn('type', ['order_one', 'order_two'])->where('uid', $uid)->where('status', 0)->sum('number');
    }

    /**
     * @param $uid
     * @return float
     * @author xaboy
     * @day 2020/6/22
     */
    public function totalBrokerage($uid)
    {
        return UserBill::getDB()->where('category', 'brokerage')
            ->whereIn('type', ['order_one', 'order_two'])->where('uid', $uid)->sum('number');
    }

    /**
     * @param $uid
     * @return float
     * @author xaboy
     * @day 2020/6/22
     */
    public function yesterdayBrokerage($uid)
    {
        return getModelTime(UserBill::getDB()->where('category', 'brokerage')
            ->whereIn('type', ['order_one', 'order_two'])->where('uid', $uid), 'yesterday')->sum('number');
    }

    /**
     * @param array $where
     * @return \think\db\BaseQuery
     * @author xaboy
     * @day 2020/6/22
     */
    public function search(array $where)
    {
        return UserBill::getDB()->when(isset($where['now_money']) && in_array($where['now_money'], [0, 1, 2]), function ($query) use ($where) {
            if ($where['now_money'] == 0)
                $query->where('category', 'now_money')->whereIn('type', ['pay_product', 'recharge', 'sys_inc_money', 'sys_dec_money', 'brokerage', 'presell']);
            else if ($where['now_money'] == 1)
                $query->where('category', 'now_money')->whereIn('type', ['pay_product', 'sys_dec_money', 'presell']);
            else if ($where['now_money'] == 2)
                $query->where('category', 'now_money')->whereIn('type', ['recharge', 'sys_inc_money', 'brokerage']);
        })->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('uid', $where['uid']);
        })->when(isset($where['pm']) && $where['pm'] !== '', function ($query) use ($where) {
            $query->where('pm', $where['pm']);
        })->when(isset($where['category']) && $where['category'] !== '', function ($query) use ($where) {
            $query->where('category', $where['category']);
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status', $where['status']);
        });
    }

    public function userNowMoneyIncTotal($uid)
    {
        return $this->search(['uid' => $uid, 'now_money' => 2])->sum('number');
    }

    public function searchJoin(array $where)
    {
        return UserBill::getDB()->alias('a')->leftJoin('User b', 'a.uid = b.uid')
            ->field('a.bill_id,a.pm,a.title,a.number,a.balance,a.mark,a.create_time,a.status,b.nickname,a.uid')
            ->when(isset($where['category']) && $where['category'] !== '', function ($query) use ($where) {
                $query->where('a.category', $where['category']);
            })
            ->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
                $query->where('a.type', $where['type']);
            })->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'a.create_time');
            })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->where('a.uid|b.nickname','like', "%{$where['keyword']}%");
            })->when(isset($where['kw']) && $where['kw'] !== '', function ($query) use ($where) {
                $query->where('a.mark|a.title|b.nickname','like', "%{$where['kw']}%");
            })->when(isset($where['pm']) && $where['pm'] !== '' && $where['pm'] != -1 , function ($query) use ($where) {
                $query->where('a.pm',  intval($where['pm']) );
            })->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('a.uid' , $where['uid'] );
            });
    }

    public function refundBrokerage($order_id, $uid)
    {
        return UserBill::getDB()->where('link_id', $order_id)->where('uid', $uid)
            ->where('category', 'brokerage')->whereIn('type', ['refund_two', 'refund_one'])->sum('number');
    }

    public function type()
    {
        return UserBill::getDB()->group('type')->column('title,type');
    }

}