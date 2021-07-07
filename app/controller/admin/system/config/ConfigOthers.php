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

namespace app\controller\admin\system\config;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\config\ConfigClassifyRepository;
use app\common\repositories\system\config\ConfigRepository as repository;
use app\common\repositories\system\config\ConfigValueRepository;

class ConfigOthers extends BaseController
{

    public $repository;

    public function __construct(App $app,repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function lst()
    {
        $data = [
            'extension_status' => systemConfig('extension_status'),
            'extension_one_rate' => bcmul(systemConfig('extension_one_rate'),100,3),
            'extension_two_rate' => bcmul(systemConfig('extension_two_rate'),100,3),
        ];
        return app('json')->success($data);
    }

    public function update()
    {
        $data = $this->request->params(['extension_status','extension_two_rate','extension_one_rate']);
        if($data['extension_two_rate'] < 0 || $data['extension_one_rate'] < 0) return app('json')->fail('比例不能小于0');
        if(bccomp($data['extension_one_rate'],$data['extension_two_rate'],4) == -1)
            return app('json')->fail('一级比例不能小于二级比例');
        if(bccomp(bcadd($data['extension_one_rate'],$data['extension_two_rate'],3),100,3) == 1)
            return app('json')->fail('比例之和不能超过100%');
        $arr['extension_status'] = $data['extension_status'];
        $arr['extension_one_rate'] = bcdiv($data['extension_one_rate'],100,3);
        $arr['extension_two_rate'] = bcdiv($data['extension_two_rate'],100,3);
        app()->make(ConfigValueRepository::class)->setFormData($arr,0);

        return app('json')->success('修改成功');
    }

    public function getGroupBuying()
    {
        $data = [
            'ficti_status' => systemConfig('ficti_status'),
            'group_buying_rate' => systemConfig('group_buying_rate'),
        ];
        return app('json')->success($data);
    }

    public function setGroupBuying()
    {
        $data['ficti_status'] = $this->request->param('ficti_status') == 1 ? 1: 0;
        $data['group_buying_rate'] = $this->request->param('group_buying_rate');
        if($data['group_buying_rate'] < 0 || $data['group_buying_rate'] >100)
            return app('json')->fail('请填写1～100之间的整数');
        app()->make(ConfigValueRepository::class)->setFormData($data,0);

        return app('json')->success('修改成功');
    }
}
