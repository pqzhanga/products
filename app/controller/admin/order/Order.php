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


namespace app\controller\admin\order;

use crmeb\basic\BaseController;
use app\common\repositories\store\ExcelRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\store\order\StoreOrderRepository as repository;
use think\App;
use app\common\repositories\store\order\StoreRefundOrderRepository;
use think\facade\Db;
use think\facade\Log;


class Order extends BaseController
{
    protected $repository;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    public function orderrefund($id){
/*ids: "801"
mark: ""
num: 1
refund_message: "收货地址填错了"
refund_price: "0.10"
refund_type: "1"
type: "1"*/
        try{
       $order_rf =  app()->make(repository::class)->getDetail($id)->toArray();

        $data =  [
            "mark"=> "订单无效，系统取消",
            "refund_message"=> "订单无效",
            "refund_price"=> $order_rf['pay_price'],
            "refund_type"=> "1",
        ];
        $type =1;
        $num = $order_rf['total_num'] ;
        $uid = $order_rf['uid'];

        $order =   app()->make(repository::class)->userOrder($id, $uid);
        if (!$order) return app('json')->fail('订单状态错误');
        if ($order->status < 0) return app('json')->fail('订单已退款');
        if ($order->status == 10) return app('json')->fail('订单不支持退款');

        $order_product_id = Db::table('eb_store_order_product')->where(['order_id'=>$order->order_id])->limit(1)->column('order_product_id')[0];
        $is_error = false;

        try{
                $refund =  app()->make(StoreRefundOrderRepository::class)->refund($order,   $order_product_id  , $num, $uid, $data,true);
            } catch (\Exception $e) {
                Log::error('强制退款错误-2'.var_export(['$order'=>$order,   '$order_product_id'=>$order_product_id  , '$num'=>$num, '$uid'=>$uid,'$data'=> $data,'line' => $e->getFile() . ':' . $e->getLine(), 'message' => $e->getMessage()],true));
           $rf_list =  Db::table('eb_store_refund_order')->where(['order_id'=>$order->order_id,'status'=>0])->select();
           if($rf_list){
               foreach ($rf_list as $vvva){
                   app()->make(StoreRefundOrderRepository::class)->agree($vvva['refund_order_id'],['status'=>1],0);
               }
           }
           $is_error = true;
        }

           if(!$is_error) app()->make(StoreRefundOrderRepository::class)->agree($refund->refund_order_id,['status'=>1],0);

        } catch (\Exception $e) {
            Log::error('强制退款错误'.var_export(['$order'=>$order,   '$order_product_id'=>$order_product_id  , '$num'=>$num, '$uid'=>$uid,'$data'=> $data,'line' => $e->getFile() . ':' . $e->getLine(), 'message' => $e->getMessage()],true));
            return app('json')->fail('操作失败：'.$e->getMessage());
//                 return app('json')->fail('', ['line' => $e->getLine(), 'message' => $e->getMessage()]);
        }

        return app('json')->success('操作成功');
    }

    public function lst($id)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date','order_sn','order_type','keywords','username','activity_type']);
        $where['reconciliation_type'] = $this->request->param('status', 1);
        $where['mer_id'] = $id;
        return app('json')->success($this->repository->adminMerGetList($where, $page, $limit));
    }

    public function markForm($id)
    {
        if (!$this->repository->getWhereCount([$this->repository->getPk() => $id]))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->adminMarkForm($id)));
    }

    public function mark($id)
    {
        if (!$this->repository->getWhereCount([$this->repository->getPk() => $id]))
            return app('json')->fail('数据不存在');
        $data = $this->request->params(['admin_mark']);
        $this->repository->update($id, $data);
        return app('json')->success('备注成功');
    }

    /**
     * TODO
     * @return mixed
     * @author Qinii
     * @day 2020-06-25
     */
    public function getAllList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['type', 'date', 'mer_id','keywords','status','username','order_sn','is_trader','activity_type','goodsname','zone_id','mer_name']);
//
        return app('json')->success(array_merge($this->repository->adminGetList($where, $page, $limit)  , [
            'adminId'=>$this->request->adminId()
        ])  );
    }

    /**
     * TODO 自提订单列表
     * @return mixed
     * @author Qinii
     * @day 2020-08-17
     */
    public function getTakeList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date','order_sn','keywords','username','is_trader']);
        $where['take_order'] = 1;
        $where['status'] = '';
        $where['verify_date'] = $where['date'];
        unset($where['date']);
        return app('json')->success($this->repository->adminGetList($where, $page, $limit));
    }

    /**
     * TODO
     * @return mixed
     * @author Qinii
     * @day 2020-08-17
     */
    public function chart()
    {
        return app('json')->success($this->repository->OrderTitleNumber(null,null));
    }

    /**
     * TODO 自提订单头部统计
     * @return mixed
     * @author Qinii
     * @day 2020-08-17
     */
    public function takeChart()
    {
        return app('json')->success($this->repository->OrderTitleNumber(null,1));
    }

    /**
     * TODO 订单类型
     * @return mixed
     * @author Qinii
     * @day 2020-08-15
     */
    public function orderType()
    {
        return app('json')->success($this->repository->orderType([]));
    }

    public function detail($id)
    {
        $data = $this->repository->getOne($id, null);
        if (!$data)
            return app('json')->fail('数据不存在');
        return app('json')->success($data);
    }

    /**
     * TODO 快递查询
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-25
     */
    public function express($id)
    {
        if (!$this->repository->getWhereCount(['order_id' => $id, 'delivery_type' => 1]))
            return app('json')->fail('订单信息或状态错误');
        return app('json')->success($this->repository->express($id));
    }

    public function reList($id)
    {
        [$page, $limit] = $this->getPage();
        $where = ['reconciliation_id' => $id, 'type' => 0];
        return app('json')->success($this->repository->reconList($where, $page, $limit));
    }

    /**
     * TODO 导出文件
     * @author Qinii
     * @day 2020-07-30
     */
    public function excel()
    {
        $where = $this->request->params(['type', 'date', 'mer_id','keywords','status','username','order_sn','take_order']);
        if($where['take_order']){
            $where['verify_date'] = $where['date'];
            unset($where['date']);
        }
        app()->make(ExcelRepository::class)->create($where, $this->request->adminId(), 'order',$this->request->merId());
        return app('json')->success('开始导出数据');

    }
}
