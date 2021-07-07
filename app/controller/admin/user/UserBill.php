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


namespace app\controller\admin\user;

use app\common\repositories\store\ExcelRepository;
use crmeb\basic\BaseController;
use app\common\repositories\user\UserBillRepository;
use think\App;
use think\facade\Db;
use FormBuilder\Factory\Elm;
use think\facade\Route;

class UserBill extends BaseController
{
    protected $repository;

    public function __construct(App $app, UserBillRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function getList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'date', 'type','uid']);
//        $where['category'] = 'now_money';

        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    public function type()
    {
        return app('json')->success($this->repository->type());
    }


    public function getsyscapitalList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'date', 'type','category','kw','uid','pm']);
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    public function subinfostatus()
    {
        $data = $this->request->params(['stat', 'id','fail_msg']);
        Db::table('eb_mer_sub_info')->where(['id'=>$data['id']])->update([
            'state'=>$data['stat'] == 1?1:2,
            'fail_msg'=>$data['fail_msg']
        ]);
        return app('json')->success('修改成功');
    }

    public function subinfoform($id)
    {
        $res = Db::table('eb_mer_sub_info')->where(['id'=>intval($id)])->find();
        if (!$res)
            return app('json')->fail('数据不存在');

        $form = Elm::createForm(Route::buildUrl('subinfoStatus', ['id' => $id])->build());
        $form->setRule([
            Elm::radio('state', '状态', 1 )->options([
                ['label' => '失败', 'value' => 2],
                ['label' => '成功', 'value' => 1],
            ])->required()->control([
                ['value' =>2 , 'rule' => [
                    Elm::textarea('fail_msg', '拒绝原因')->required()
                ]]
            ]),
        ]);
       $form->setTitle('修改状态');
        return app('json')->success(formToData($form));
    }


    public function subinfoexport()
    {
        $where = $this->request->params(['date','state','keyword','is_split','sn']);
        app()->make(ExcelRepository::class)->create($where, $this->request->adminId(), 'subinfo',$this->request->merId());
        return app('json')->success('开始导出数据');
    }


    public function subinfolist()
    {
        //当日交易金额，总交易金额

        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date','state','keyword','is_split','sn']);
        $qq =  Db::table('eb_mer_sub_info')->alias('sorder')->join('merchant merchant','merchant.mer_id = sorder.mer_id')->field('sorder.*,merchant.mer_name mer_name')
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'sorder.create_time');
            })->when(isset($where['state']) && $where['state'] !== '', function ($query) use ($where) {
                $query->where('sorder.state',$where['state']);
            });

        $count =$qq->count();
        $list =$qq->page($page, $limit)->order('sorder.id desc')->select()->toArray();

        foreach ($list as &$v){
            $v['face_imgs_arr'] = explode(',',$v['face_imgs']);
            $v['user'] =  Db::table('eb_user')->find($v['uid']) ;
//            $v['mer'] = Db::table('eb_merchant')->find($v['mer_id']);
//            $v['pay_time'] =  strtotime($v['pay_time']) > 0? $v['pay_time']:'--';
        }
        unset($v);
        return app('json')->success(['count'=>$count,'list'=>$list]);
    }




    public function getScanlist()
    {
        //当日交易金额，总交易金额
        $sum = [
            'today'=>0,
            'total'=>0
        ];
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date','status','keyword','is_split','sn']);
        $qq =  Db::table('eb_scan_order')->alias('sorder')->join('merchant merchant','merchant.mer_id = sorder.mer_id')->field('sorder.*,merchant.mer_name mer_name')
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date'], 'sorder.pay_time');
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('sorder.status',$where['status']);
        })->when(isset($where['sn']) && $where['sn'] !== '', function ($query) use ($where) {
                $query->whereLike('sorder.order_sn', "%{$where['sn']}%");
        })->when(isset($where['is_split']) && $where['is_split'] !== '', function ($query) use ($where) {
                $query->where('sorder.is_split',$where['is_split']?1:0);
        })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('merchant.mer_name', "%{$where['keyword']}%");
         });

        $count =$qq->count();
        $list =$qq->page($page, $limit)->order('sorder.scan_order_id desc')->select()->toArray();

        $sum['total'] = Db::table('eb_scan_order')->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date'], 'pay_time');
        })->when(isset($where['is_split']) && $where['is_split'] !== '', function ($query) use ($where) {
            $query->where('is_split',$where['is_split']?1:0);
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status',$where['status']);
        })->sum('money');

        foreach ($list as &$v){
            $v['user'] = Db::table('eb_user')->find($v['uid']);
            $v['mer'] = Db::table('eb_merchant')->find($v['mer_id']);
            $v['pay_time'] =  strtotime($v['pay_time']) > 0? $v['pay_time']:'--';
        }
        unset($v);

        $sum['today'] =  Db::table('eb_scan_order')->whereBetween('create_time',[date('Y-m-d 00:00:00'),date('Y-m-d H:i:s')])->where('status', 1)->sum('money');
        return app('json')->success(['count'=>$count,'list'=>$list,'sum'=>$sum]);
    }


    public function getmercoinlist()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date','mer_id']);
        $qq =  Db::table('eb_merchant_bill')->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date'], 'create_time');
        })->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->where(['mer_id'=>$where['mer_id']]);
        });
        $count =$qq->count();
        $list =$qq->page($page, $limit)->order('id desc')->select()->toArray();
        foreach ($list as &$v){
            $v['mer'] = Db::table('eb_merchant')->find($v['mer_id']);
            $v['create_time'] =  strtotime($v['create_time']) > 0? $v['create_time']:'--';
            $v['types'] = $v['type'] == 'add'?'打款':'提现';
        }
        unset($v);
        return app('json')->success(['count'=>$count,'list'=>$list]);
    }



    public function usersign()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date','user_id']);
        $qq =  Db::table('eb_user_sign')->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date'], 'sign_time');
        })->when(isset($where['user_id']) && $where['user_id'] !== '', function ($query) use ($where) {
            $query->where(['uid'=>$where['user_id']]);
        });
        $count =$qq->count();
        $list =$qq->page($page, $limit)->order('id desc')->select()->toArray();
        foreach ($list as &$v){
            $v['user'] = Db::table('eb_user')->find($v['uid']);
        }
        unset($v);
        return app('json')->success(['count'=>$count,'list'=>$list]);
    }


}