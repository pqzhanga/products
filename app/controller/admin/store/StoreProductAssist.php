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

namespace app\controller\admin\store;

use app\common\repositories\store\product\ProductAssistRepository as repository;
use app\validate\merchant\StoreProductAdminValidate as validate;
use crmeb\basic\BaseController;
use crmeb\jobs\ChangeSpuStatusJob;
use think\App;

class StoreProductAssist extends BaseController
{
    protected  $repository ;

    /**
     * Product constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app ,repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * TODO 列表
     * @return mixed
     * @author Qinii
     * @day 2020-10-12
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['product_status','keyword','status','type','mer_id','is_trader']);
        $data = $this->repository->getAdminList($where,$page,$limit);
        return app('json')->success($data);
    }

    /**
     * TODO 详情
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-10-12
     */
    public function detail($id)
    {
        $data = $this->repository->detail(null,$id);
        return app('json')->success($data);
    }

    public function switchStatus($id)
    {
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        if(!$this->repository->get($id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, ['status' => $status]);
        queue(ChangeSpuStatusJob::class,['id' => $id,'product_type' => 3]);
        return app('json')->success('修改成功');
    }


    /**
     * TODO 获取商品
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-11-02
     */
    public function get($id)
    {
        if(!$this->repository->getWhereCount([$this->repository->getPk() => $id]))
            return  app('json')->fail('数据不存在');
        $data = $this->repository->get($id);
        return app('json')->success($data);
    }

    /**
     * TODO 编辑商品
     * @param $id
     * @param validate $validate
     * @return mixed
     * @author Qinii
     * @day 2020-11-02
     */
    public function update($id,validate $validate)
    {
        $data = $this->checkParams($validate);
        if(!$this->repository->getWhereCount([$this->repository->getPk() => $id]))
            return  app('json')->fail('数据不存在');
        $this->repository->updateProduct($id,$data);
        return app('json')->success('编辑成功');

    }

    public function checkParams(validate $validate)
    {
        $data = $this->request->params(['is_hot','is_best','is_benefit','is_new','store_name','keyword','content','rank']);
        $validate->check($data);
        return $data;
    }

    public function productStatus()
    {
        $id = $this->request->param('id');
        if(!$this->repository->getWhereCount([$this->repository->getPk() => $id]))
            return  app('json')->fail('数据不存在');
        $data = $this->request->params(['status','refusal']);
        if($data['status'] == -1 && empty($data['refusal']))
            return app('json')->fail('请填写拒绝理由');
        if(!is_array($id)) $id = [$id];
        $this->repository->updates($id,['product_status' => $data['status'],'refusal' => $data['refusal']]);
        foreach ($id as $item){
            queue(ChangeSpuStatusJob::class,['id' => $item,'product_type' => 3]);
        }
        return app('json')->success('操作成功');
    }
}
