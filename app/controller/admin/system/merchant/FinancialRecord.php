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


namespace app\controller\admin\system\merchant;


use app\common\repositories\store\ExcelRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use crmeb\basic\BaseController;
use think\App;

class FinancialRecord extends BaseController
{
    protected $repository;

    public function __construct(App $app, FinancialRecordRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'date', 'mer_id','mer_name']);
        $merId = $this->request->merId();
        if ($merId) {
            $where['mer_id'] = $merId;
            $where['financial_type'] = ['order', 'mer_accoubts', 'brokerage_one', 'brokerage_two', 'refund_brokerage_one', 'refund_brokerage_two', 'refund_order'];
        } else {
            $where['financial_type'] = ['order', 'sys_accoubts', 'brokerage_one', 'brokerage_two', 'refund_brokerage_one', 'refund_brokerage_two', 'refund_order'];
        }
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    public function export()
    {
        $where = $this->request->params(['keyword', 'date', 'mer_id','mer_name']);
        $merId = $this->request->merId();
        if ($merId) {
            $where['mer_id'] = $merId;
            $where['financial_type'] = ['order', 'mer_accoubts', 'brokerage_one', 'brokerage_two', 'refund_brokerage_one', 'refund_brokerage_two', 'refund_order'];
        } else {
            $where['financial_type'] = ['order', 'sys_accoubts', 'brokerage_one', 'brokerage_two', 'refund_brokerage_one', 'refund_brokerage_two', 'refund_order'];
        }
        app()->make(ExcelRepository::class)->create($where, $this->request->adminId(), 'financial',$merId);
        return app('json')->success('开始导出数据');
    }
}
