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

use app\common\repositories\BaseRepository;
use app\common\dao\user\MerExtractDao as dao;
use crmeb\jobs\SendTemplateMessageJob;
use crmeb\services\SwooleTaskService;
use think\facade\Db;
use think\facade\Queue;

class MerExtractRepository extends BaseRepository
{

    /**
     * @var dao
     */
    protected $dao;


    /**
     * UserExtractRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }


    /**
     * TODO
     * @param $id
     * @return bool
     * @author Qinii
     * @day 2020-06-16
     */
    public function getWhereCount($id)
    {
        $where['extract_id'] = $id;
        $where['status'] = 0;
        return $this->dao->getWhereCount($where) > 0;
    }

    /**
     * TODO
     * @param array $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2020-06-16
     */
    public function search(array $where, $page, $limit)
    {
        $query = $this->dao->search($where);
        $count = $query->count();
        $list = $query->page($page,$limit)->select();
        return compact('count','list');
    }

    /**
     * @param $uid
     * @return mixed
     * @author xaboy
     * @day 2020/6/22
     */
    public function userTotalExtract($uid)
    {
        return $this->dao->search(['status' => 1, 'uid' => $uid])->sum('extract_price');
    }

    /**
     * TODO
     * @param $user
     * @param $data
     * @author Qinii
     * @day 2020-06-16
     */
    public function create($user,$data)
    {
        $data = Db::transaction(function()use($user,$data){
            $brokerage_price = bcsub($user['brokerage_price'],$data['extract_price'],2);
            $user->brokerage_price = $brokerage_price;
            $user->save();

            $data['status'] = 0;
            $data['uid'] = $user['uid'];
            $data['statbalanceus'] = $brokerage_price;
            return $this->dao->create($data);
        });

        SwooleTaskService::admin('notice', [
            'type' => 'extract',
            'title' => '您有一条新的提现申请',
            'id' => $data->extract_id
        ]);
    }

    public function switchStatus($id,$data)
    {
        Db::transaction(function()use($id,$data){
            $extract = $this->dao->getWhere(['extract_id' => $id]);
            $user = app()->make(UserRepository::class)->get($extract['uid']);
            if($data['status'] == '-1'){

                $brokerage_price = bcadd($user['brokerage_price'] ,$extract['extract_price'],2);
                $user->brokerage_price = $brokerage_price;
                $user->save();


            }
            $make = app()->make(UserBillRepository::class);
            $make->decBill($user['uid'], 'brokerage', '', [
                'link_id' => 1,
                'status' => 1,
                'title' => '提现',
                'number' => $extract['extract_price'],
                'mark' => '提现' . floatval($extract['extract_price']) . '股权值',
            ]);


            $this->dao->update($id,$data);
        });
        Queue::push(SendTemplateMessageJob::class,[
            'tempCode' => 'ORDER_DELIVER_SUCCESS',
            'id' =>$id
        ]);
        

    }
}
