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

namespace app\controller\admin\user;

use app\common\repositories\store\ExcelRepository;
use crmeb\basic\BaseController;
use think\App;
use app\validate\api\UserExtractValidate as validate;
use app\common\repositories\user\UserExtractRepository as repository;

class UserExtract extends BaseController
{
    /**
     * @var repository
     */
    public $repository;

    /**
     * UserExtract constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app,repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    public function export2()
    {
        $where = $this->request->params(['date']);
        app()->make(ExcelRepository::class)->create($where, $this->request->adminId(), 'scan',$this->request->merId());
        return app('json')->success('开始导出数据');
    }


    public function export()
    {
        $where = $this->request->params(['status','keyword','date','extract_type','use_type']);

        app()->make(ExcelRepository::class)->create($where, $this->request->adminId(), 'ext',$this->request->merId());
        return app('json')->success('开始导出数据');
    }

    /**
     * TODO
     * @return mixed
     * @author Qinii
     * @day 2020-06-16
     */
    public function lst()
    {
        [$page,$limit] = $this->getPage();
        $where = $this->request->params(['status','keyword','date','extract_type','use_type']);
        return app('json')->success($this->repository->search($where,$page,$limit));
    }


    /**
     * TODO
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-16
     */
    public function switchStatus($id)
    {
        $data = $this->request->params(['status','fail_msg','mark']);
        if($data['status'] == '-1' && empty($data['fail_msg']))
            return app('json')->fail('请填写拒绝原因');
        if(!$this->repository->getWhereCount($id))
            return app('json')->fail('数据不存在或状态错误');
        $data['admin_id'] = $this->request->adminId();
        $data['status_time'] = date('Y-m-d H:i:s',time());
        $this->repository->switchStatus($id,$data);
        return app('json')->success('审核成功');
    }
}
