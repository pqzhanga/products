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


namespace app\webscoket\handler;


use app\common\repositories\system\admin\AdminRepository;
use crmeb\services\JwtTokenService;
use Swoole\Server;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use Throwable;

/**
 * Class Handler
 * @package app\webscoket
 * @author xaboy
 * @day 2020-04-29
 */
class AdminHandler
{
    /**
     * @var Server
     */
    protected $server;

    /**
     * AdminHandler constructor.
     * @param Server $server
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * @param array $data
     * @return mixed
     * @author xaboy
     * @day 2020-05-06
     */
    public function test(array $data)
    {
        return app('json')->success($data);
    }

    /**
     * @param array $data
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-06
     */
    public function login(array $data)
    {
        $token = $data['token'] ?? '';
        if (!$token) return app('json')->fail('token 无效');

        /**
         * @var AdminRepository $repository
         */
        $repository = app()->make(AdminRepository::class);
        $service = new JwtTokenService();
        try {
            $payload = $service->parseToken($token);
        } catch (Throwable $e) {//Token 过期
            return app('json')->fail('token 已过期');
        }
        if ('admin' != $payload->jti[1])
            return app('json')->fail('无效的 token');

        $admin = $repository->get($payload->jti[0]);
        if (!$admin)
            return app('json')->fail('账号不存在');
        if (!$admin['status'])
            return app('json')->fail('账号已被禁用');

        return app('json')->success(['uid' => $admin->admin_id, 'data' => $admin->toArray()]);
    }

}