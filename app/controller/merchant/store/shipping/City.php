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

namespace app\controller\merchant\store\shipping;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\shipping\CityRepository as repository;

class City extends BaseController
{
    protected $repository;

    /**
     * City constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @Time: 14:40
     * @return mixed
     */
    public function lst()
    {
        return app('json')->success($this->repository->getFormatList([['is_show', '=', 1],['level','<',2]]));
    }

    public function citys()
    {
        $data  = $this->repository->getFormatList([['is_show', '=', 1],['level','<',3]]);
        foreach ($data as &$v){
            if(isset($v['children']) && $v['children']){
                foreach ($v['children'] as &$vv){
                    if(isset($vv['children']) && $vv['children']){
                        foreach ($vv['children'] as &$vvv){
                            $vvv['value'] = $vvv['city_id'];
                            $vvv['label'] = $vvv['name'];
                        }
                        unset($vvv);
                    }
                    $vv['value'] = $vv['city_id'];
                    $vv['label'] = $vv['name'];
                }
                unset($vv);
            }
            $v['value'] = $v['city_id'];
            $v['label'] = $v['name'];
        }
        unset($v);
        return  $data;
    }

    /**
     * @return mixed
     * @author Qinii
     */
    public function getlist()
    {
        return app('json')->success($this->repository->getFormatList(['is_show' => 1]));
    }



}
