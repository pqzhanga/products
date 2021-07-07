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


namespace app\common\dao\system\merchant;


use app\common\dao\BaseDao;
use app\common\model\system\merchant\FinancialRecord;

class FinancialRecordDao extends BaseDao
{

    protected function getModel(): string
    {
        return FinancialRecord::class;
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020/6/9
     */
    public function getSn()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');
        $orderId = 'jy' . $msectime . mt_rand(10000, max(intval($msec * 10000) + 10000, 98369));
        return $orderId;
    }

    public function inc(array $data, $merId)
    {
        $data['mer_id'] = $merId;
        $data['financial_pm'] = 1;
        $data['financial_record_sn'] = $this->getSn();
        return $this->create($data);
    }

    public function dec(array $data, $merId)
    {
        $data['mer_id'] = $merId;
        $data['financial_pm'] = 0;
        $data['financial_record_sn'] = $this->getSn();
        return $this->create($data);
    }

    public function search(array $where)
    {
        $query = $this->getModel()::getDB();
        $query->alias('fina')->join('user user','user.uid = fina.user_id')->join('merchant merchant','merchant.mer_id = fina.mer_id')->field('fina.*,user.account uaccount,merchant.mer_name mer_name');
        $query->when(isset($where['financial_type']) && $where['financial_type'] !== '', function ($query) use ($where) {
                $query->whereIn('fina.financial_type', $where['financial_type']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('fina.mer_id', $where['mer_id']);
            })
            ->when(isset($where['mer_name']) && $where['mer_name'] !== '', function ($query) use ($where) {
                $query->whereLike('merchant.mer_name', "%{$where['mer_name']}%");
            })
            ->when(isset($where['user_info']) && $where['user_info'] !== '', function ($query) use ($where) {
                $query->where('fina.user_info', $where['user_info']);
            })
            ->when(isset($where['user_id']) && $where['user_id'] !== '', function ($query) use ($where) {
                $query->where('fina.user_id', $where['user_id']);
            })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('fina.order_sn|user.account', "%{$where['keyword']}%");
            })->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'fina.create_time');
            });

        return $query->order('fina.create_time DESC');
    }

}
