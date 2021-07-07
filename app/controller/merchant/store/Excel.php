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

namespace app\controller\merchant\store;

use app\common\repositories\store\ExcelRepository;
use crmeb\exceptions\UploadException;
use think\App;
use crmeb\basic\BaseController;

class Excel extends BaseController
{

    protected $repository;

    public function __construct(App $app, ExcelRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * TODO
     * @return mixed
     * @author Qinii
     * @day 2020-08-15
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where['type'] = $this->request->param('type','');
        $where['mer_id'] = $this->request->merId();
        $where['admin_id'] = $this->request->adminId();
        return app('json')->success($this->repository->getList($where,$page,$limit));
    }

    /**
     * TODO 下载文件
     * @param $id
     * @return \think\response\File
     * @author Qinii
     * @day 2020-07-30
     */
    public function download()
    {
        try{
            $id = $this->request->param('id');
            $file = $this->repository->getWhere(['excel_id' => $id,'mer_id' => $this->request->merId()]);
            $path = app()->getRootPath().'public'.$file['path'];
            if(!$file || !file_exists($path))return app('json')->fail('文件不存在');
            return download($path,$file['name']);
        }catch (UploadException $e){
            return app('json')->fail('下载失败');
        }
    }
}
