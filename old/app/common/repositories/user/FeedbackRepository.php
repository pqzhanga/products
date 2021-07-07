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


use app\common\dao\user\FeedbackDao;
use app\common\repositories\BaseRepository;

/**
 * Class FeedbackRepository
 * @package app\common\repositories\user
 * @author xaboy
 * @day 2020/5/28
 * @mixin FeedbackDao
 */
class FeedbackRepository extends BaseRepository
{
    /**
     * FeedbackRepository constructor.
     * @param FeedbackDao $dao
     */
    public function __construct(FeedbackDao $dao)
    {
        $this->dao = $dao;
    }

    public function getList(array $where, $page, $limit)
    {
        $query = $this->dao->search($where)->with(['type' => function($query){
            $query->field('feedback_category_id,cate_name');
        }]);
        $count = $query->count();
        $list = $query->page($page, $limit)->withAttr('images',function($val){
            return $val ? json_decode($val, true) : [];
        })->select();
        return compact('count', 'list');
    }

    public function get( $id)
    {
        $data = $this->dao->getWhere([$this->dao->getPk() => $id]);
        $type = app()->make(FeedBackCategoryRepository::class)->getWhere(['feedback_category_id' => $data['type']]);
        $parent = app()->make(FeedBackCategoryRepository::class)->getWhere(['feedback_category_id' => $type['pid']]);
        $data['type'] = $type['cate_name'];
        $data['category'] = $parent['cate_name'];
        return $data;
    }

}
