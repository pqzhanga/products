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

namespace app\controller\merchant\store\product;

use app\common\repositories\store\product\ProductAssistRepository as repository;
use crmeb\basic\BaseController;
use crmeb\jobs\ChangeSpuStatusJob;
use think\App;
use app\validate\merchant\StoreProductAssistValidate;

class ProductAssist extends BaseController
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
        $where = $this->request->params(['product_status','keyword','is_show','type','presell_type']);
        $where['mer_id'] = $this->request->merId();
        return app('json')->success($this->repository->getMerchantList($where,$page,$limit));
    }

    /**
     * TODO 添加
     * @param StoreProductAssistValidate $validate
     * @return mixed
     * @author Qinii
     * @day 2020-10-12
     */
    public function create(StoreProductAssistValidate $validate)
    {
        $data = $this->checkParams($validate);
        $this->repository->create($this->request->merId(),$data);
        return app('json')->success('添加成功');
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
        $data = $this->repository->detail($this->request->merId(),$id);
        return app('json')->success($data);
    }

    /**
     * TODO
     * @param $id
     * @param StoreProductAssistValidate $validate
     * @return mixed
     * @author Qinii
     * @day 2020-10-13
     */
    public function update($id,StoreProductAssistValidate $validate)
    {
        $data = $this->checkParams($validate->isUpdate());
        $this->repository->edit($id,$data);
        return app('json')->success('编辑成功');
    }


    public function delete($id)
    {
        $where = [
            $this->repository->getPk() => $id,
            'mer_id' => $this->request->merId()
        ];
        $this->repository->delete($where);
        return app('json')->success('删除成功');
    }

    public function switchStatus($id)
    {
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        if(!$this->repository->detail($this->request->merId(),$id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, ['is_show' => $status]);
        Queue(ChangeSpuStatusJob::class,['product_type' => 3 ,'id' => $id]);
        return app('json')->success('修改成功');
    }


    public function checkParams(StoreProductAssistValidate $validate)
    {
        $params = [
            "image", "slider_image", "store_name", "store_info", "product_id","is_show","temp_id","attrValue",
            "start_time", "end_time", "assist_user_count", "assist_count", "status","pay_count","product_status"
        ];
        $data = $this->request->params($params);
        $validate->check($data);
        return $data;
    }

}
