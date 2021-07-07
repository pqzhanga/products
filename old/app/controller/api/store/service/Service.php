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


namespace app\controller\api\store\service;


use crmeb\basic\BaseController;
use app\common\repositories\store\service\StoreServiceLogRepository;
use app\common\repositories\store\service\StoreServiceRepository;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class Service
 * @package app\controller\api\store\service
 * @author xaboy
 * @day 2020/5/29
 */
class Service extends BaseController
{
    /**
     * @var StoreServiceRepository
     */
    protected $repository;

    /**
     * Service constructor.
     * @param App $app
     * @param StoreServiceRepository $repository
     */
    public function __construct(App $app, StoreServiceRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * @param $id
     * @param StoreServiceLogRepository $repository
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/15
     */
    public function chatHistory($id, StoreServiceLogRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->userList($id, $this->request->uid(), $page, $limit));
    }

    /**
     * @param StoreServiceLogRepository $repository
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/16
     */
    public function getList(StoreServiceLogRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->userMerchantList($this->request->uid(), $page, $limit));
    }

    /**
     * @param StoreServiceLogRepository $repository
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/16
     */
    public function serviceUserList(StoreServiceLogRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->serviceUserList($this->request->uid(), $page, $limit));
    }

    /**
     * @param $merId
     * @param $id
     * @param StoreServiceLogRepository $repository
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/15
     */
    public function serviceHistory($merId, $id, StoreServiceLogRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->merList($merId, $id, $this->request->uid(), $page, $limit));
    }
}