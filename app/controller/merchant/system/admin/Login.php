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


namespace app\controller\merchant\system\admin;


use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantAdminRepository;
use app\validate\admin\LoginValidate;
use crmeb\services\SwooleTaskService;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

class Login extends BaseController
{
    protected $repository;

    public function __construct(App $app, MerchantAdminRepository $repository)
    {

        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * @param LoginValidate $validate
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-10
     */
    public function login(LoginValidate $validate)
    {
        $data = $this->request->params(['account', 'password', 'code', 'key']);
        $validate->check($data);
        $this->repository->checkCode($data['key'], $data['code']);
        $adminInfo = $this->repository->login($data['account'], $data['password']);
        $tokenInfo = $this->repository->createToken($adminInfo);
        $admin = $adminInfo->toArray();
        unset($admin['pwd']);
        $data = [
            'token' => $tokenInfo['token'],
            'exp' => $tokenInfo['out'],
            'admin' => $admin
        ];

        return app('json')->success($data);
    }

    /**
     * @return mixed
     * @author xaboy
     * @day 2020-04-10
     */
    public function logout()
    {
        if ($this->request->isLogin())
            $this->repository->clearToken($this->request->token());
        return app('json')->success('退出登录');
    }

    /**
     * @return mixed
     * @author xaboy
     * @day 2020-04-09
     */
    public function getCaptcha()
    {
        $codeBuilder = new CaptchaBuilder(null, new PhraseBuilder(4));
        $key = $this->repository->createLoginKey($codeBuilder->getPhrase());
        $captcha = $codeBuilder->build()->inline();
        return app('json')->success(compact('key', 'captcha'));
    }
}
