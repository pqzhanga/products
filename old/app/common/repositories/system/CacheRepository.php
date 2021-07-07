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


namespace app\common\repositories\system;


use app\common\dao\system\CacheDao;
use app\common\repositories\BaseRepository;
use think\db\exception\DbException;

/**
 * Class CacheRepository
 * @package app\common\repositories\system
 * @author xaboy
 * @day 2020-04-24
 * @mixin CacheDao
 */
class CacheRepository extends BaseRepository
{
    /**
     * CacheRepository constructor.
     * @param CacheDao $dao
     */
    public function __construct(CacheDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @param string $key
     * @param $result
     * @param int $expire_time
     * @throws DbException
     * @author xaboy
     * @day 2020-04-24
     */
    public function save(string $key, $result, int $expire_time = 0)
    {
        if (!$this->dao->fieldExists('key', $key)) {
            $this->dao->create(compact('key', 'result', 'expire_time'));
        } else {
            $this->dao->keyUpdate($key, compact('result', 'expire_time'));
        }
    }
}