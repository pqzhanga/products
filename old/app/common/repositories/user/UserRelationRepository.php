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


namespace app\common\repositories\user;

use app\common\dao\user\UserRelationDao as dao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use think\facade\Db;

/**
 * Class UserRelationRepository
 * @package app\common\repositories\user
 * @mixin dao
 */
class UserRelationRepository extends BaseRepository
{

    protected $dao;

    /**
     * UserRelationRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @param $params
     * @return bool
     * @author Qinii
     */
    public function fieldExists($params)
    {
        switch ($params['type']) {
            case 1: //商品
                return app()->make(ProductRepository::class)->apiExists(0, $params['type_id']);
                break;
            case 10: //商铺
                return app()->make(MerchantRepository::class)->apiGetOne($params['type_id']);
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * @param array $params
     * @param int $uid
     * @return bool
     * @author Qinii
     */
    public function getUserRelation(array $params, int $uid)
    {
        return ($this->dao->apiFieldExists('type_id', $params['type_id'], $params['type'], $uid)->count()) > 0;
    }

    /**
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array|bool
     * @author Qinii
     */
    public function search(array $where, int $page, int $limit)
    {
        $query = $this->dao->search($where);
        switch ($where['type']) {
            case 1: //商品
                $query->with(['product' => function ($query) {
                    $query->field('product_id,image,store_name,price,is_show,status,care_count');
                }]);
                break;
            case 10: //商铺
                $query->with(['merchant' => function ($query) {
                    $query->field('mer_id,mer_avatar,mer_name,status,care_count,is_trader');
                }]);;
                break;
            default:
                break;
        }
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }


    /**
     * @param int $uid
     * @param array $data
     * @author Qinii
     */
    public function batchCreate(int $uid, array $data)
    {
        Db::transaction(function () use ($data, $uid) {
            foreach ($data['type_id'] as $item) {
                $param = ['type' => $data['type'], 'type_id' => $item, 'uid' => $uid];
                if (!$this->getUserRelation($param, $uid)) {
                    if ($this->fieldExists($param)) {
                        $this->create($param);
                    }
                }
            }
        });
    }

    /**
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     * @author Qinii
     */
    public function create(array $data)
    {
        if ($data['type'] == 10) {
            app()->make(MerchantRepository::class)->incCareCount($data['type_id']);
            app()->make(UserMerchantRepository::class)->getInfo($data['uid'], $data['type_id']);
        }
        if ($data['type'] == 1)
            app()->make(ProductRepository::class)->incCareCount($data['type_id']);
        return $this->dao->create($data);
    }

    /**
     * @param array $data
     * @author Qinii
     */
    public function destory(array $data)
    {
        if ($data['type'] == 10)
            app()->make(MerchantRepository::class)->decCareCount($data['type_id']);
        if ($data['type'] == 1)
            app()->make(ProductRepository::class)->decCareCount($data['type_id']);
        $this->dao->destory($data);
    }

    /**
     * @param $uid
     * @param array $merIds
     * @author xaboy
     * @day 2020/10/20
     */
    public function payer($uid, array $merIds)
    {
        $isset = $this->dao->intersectionPayer($uid, $merIds);
        $merIds = array_diff($merIds, $isset);
        if (!count($merIds)) return;
        $data = [];
        foreach ($merIds as $merId) {
            $data[] = [
                'type_id' => $merId,
                'type' => 12,
                'uid' => $uid
            ];
        }
        $this->dao->insertAll($data);
    }
}
