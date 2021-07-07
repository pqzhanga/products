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


use crmeb\basic\BaseController;
use app\common\repositories\system\config\ConfigClassifyRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use think\App;
use think\facade\Db;

/**
 * Class ConfigValue
 * @package app\controller\admin\system\config
 * @author xaboy
 * @day 2020-03-27
 */
class ConfigValue extends BaseController
{
    /**
     * @var ConfigClassifyRepository
     */
    private $repository;

    /**
     * ConfigValue constructor.
     * @param App $app
     * @param ConfigValueRepository $repository
     */
    public function __construct(App $app, ConfigValueRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * @param string $key
     * @return mixed
     * @author xaboy
     * @day 2020-04-22
     */
    public function save($key)
    {
        $formData = $this->request->post();
        if (!count($formData)) return app('json')->fail('保存失败');

        /** @var ConfigClassifyRepository $make */
        $make = app()->make(ConfigClassifyRepository::class);
        if (!($cid = $make->keyById($key))) return app('json')->fail('保存失败');

        $this->repository->save($cid, $formData, $this->request->merId());
//        if(isset($formData['release_rate']) && $formData['release_rate'] >= 0 && $formData['release_rate'] <= 100 ){
//
//        }
//
//        ["jl_b1_1"]=>
//  int(0)
//  ["jl_b1_2"]=>
//  int(0)
//  ["jl_b1_3"]=>
//  int(0)
//  ["jl_b1_4"]=>
//  int(0)
//  ["jl_b2_1"]=>
//  int(0)
//  ["jl_b2_2"]=>
//  int(0)
//  ["jl_b2_3"]=>
//  int(0)
//  ["jl_b2_4"]=>
//  int(0)
//  ["jl_b3_1"]=>
//  int(0)
//  ["jl_b3_2"]=>
//  int(0)
//  ["jl_b3_3"]=>
//  int(0)
//  ["jl_b3_4"]=>
//  int(0)
//  ["jl2_b1_1"]=>
//  int(0)
//  ["jl2_b1_2"]=>
//  int(0)
//  ["jl2_b1_3"]=>
//  int(0)
//
        if(isset($formData['jl_b1_1'])){
                Db::table('bonus_level3')->where(['id'=>1])->update(['b1'=>$formData['jl_b1_1']]);
        }
        if(isset($formData['jl_b1_2'])){
            Db::table('bonus_level3')->where(['id'=>2])->update(['b1'=>$formData['jl_b1_2']]);
        }
        if(isset($formData['jl_b1_3'])){
            Db::table('bonus_level3')->where(['id'=>3])->update(['b1'=>$formData['jl_b1_3']]);
        }
        if(isset($formData['jl_b1_4'])){
            Db::table('bonus_level3')->where(['id'=>4])->update(['b1'=>$formData['jl_b1_4']]);
        }
//
        if(isset($formData['jl_b2_1'])){
            Db::table('bonus_level3')->where(['id'=>1])->update(['b2'=>$formData['jl_b2_1']]);
        }
        if(isset($formData['jl_b2_2'])){
            Db::table('bonus_level3')->where(['id'=>2])->update(['b2'=>$formData['jl_b2_2']]);
        }
        if(isset($formData['jl_b2_3'])){
            Db::table('bonus_level3')->where(['id'=>3])->update(['b2'=>$formData['jl_b2_3']]);
        }
        if(isset($formData['jl_b2_4'])){
            Db::table('bonus_level3')->where(['id'=>4])->update(['b2'=>$formData['jl_b2_4']]);
        }
//
        if(isset($formData['jl_b3_1'])){
            Db::table('bonus_level3')->where(['id'=>1])->update(['b3'=>$formData['jl_b3_1']]);
        }
        if(isset($formData['jl_b3_2'])){
            Db::table('bonus_level3')->where(['id'=>2])->update(['b3'=>$formData['jl_b3_2']]);
        }
        if(isset($formData['jl_b3_3'])){
            Db::table('bonus_level3')->where(['id'=>3])->update(['b3'=>$formData['jl_b3_3']]);
        }
        if(isset($formData['jl_b3_4'])){
            Db::table('bonus_level3')->where(['id'=>4])->update(['b3'=>$formData['jl_b3_4']]);
        }
//
        if(isset($formData['jl2_b1_1'])){
            Db::table('bonus_level2')->where(['id'=>1])->update(['b1'=>$formData['jl2_b1_1']]);
        }
        if(isset($formData['jl2_b1_2'])){
            Db::table('bonus_level2')->where(['id'=>2])->update(['b1'=>$formData['jl2_b1_2']]);
        }
        if(isset($formData['jl2_b1_3'])){
            Db::table('bonus_level2')->where(['id'=>3])->update(['b1'=>$formData['jl2_b1_3']]);
        }
        return app('json')->success('保存成功');
    }
}
