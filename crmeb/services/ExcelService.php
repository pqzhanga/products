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

namespace crmeb\services;

use app\common\model\user\UserExtract;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\ExcelRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository;
use app\common\repositories\system\merchant\FinancialRecordRepository;
use app\common\repositories\user\UserExtractRepository;
use app\common\dao\user\UserExtractDao;
use app\common\repositories\user\UserRepository;
use think\Exception;
use think\facade\Db;

class ExcelService
{

    public function getAll($data)
    {
        $this->{$data['type']}($data['where'],$data['excel_id']);
    }

    public function export($header, $title_arr, $export = [],$path, $filename = '', $id,$suffix = 'xlsx')
    {
        $title = isset($title_arr[0]) && !empty($title_arr[0]) ? $title_arr[0] : '导出数据';
        $name = isset($title_arr[1]) && !empty($title_arr[1]) ? $title_arr[1] : '导出数据';
        $info = isset($title_arr[2]) && !empty($title_arr[2]) ? $title_arr[2] : date('Y-m-d H:i:s', time());

        try{
            $_path = SpreadsheetExcelService::instance()
                ->createOrActive()
                ->setExcelHeader($header)
                ->setExcelTile($title, $name, $info)
                ->setExcelContent($export)
                ->excelSave($filename, $suffix, $path);

            app()->make(ExcelRepository::class)->update($id,[
                'name' => $filename.'.'.$suffix,
                'status' => 1,
                'path' => '/'.$_path
            ]);

        }catch (Exception $exception){
            app()->make(ExcelRepository::class)->update($id,[
                'name' => $filename.'.'.$suffix,
                'status' => 2,
                'message' => $exception->getMessage()
            ]);
        }
    }

    /**
     * TODO 导出订单
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 2020-08-10
     */
    public function order(array $where,int $id)
    {
        $make = app()->make(StoreOrderRepository::class);

        $del = $where['mer_id'] > 0 ? 0 : null;

        $status = $where['status'];
        unset($where['status']);
        $query = $make->search($where,$del)->where($make->getOrderType($status))->order('order_id ASC');
        $list = $query->with([
            'orderProduct',
            'merchant' => function ($query) {return $query->field('mer_id,mer_name');},
            'user.spread'
            ])->select()->each(function($item){
                $item['refund_price'] = app()->make(StoreRefundOrderRepository::class)->refundPirceByOrder([$item['order_id']]);
            return $item;
            });
        $export = $this->orderList($list->toArray());
        $header =    [
            '序号','订单编号','订单类型','推广人','用户信息','商品名称','商品规格','单商品总数','商品价格(元)','优惠','实付邮费(元)','实付金额(元)','已退款金额(元)', '收货人','收货人电话','收货地址','物流单号','下单时间','支付方式','支付状态','商家备注'
        ];
        $title = ['订单列表','订单信息','生成时间:' . date('Y-m-d H:i:s',time())];
        $filename = '订单列表_'.date('YmdHis');

        return $this->export($header,$title,$export,'order',$filename,$id,'xlsx');
    }

    /**
     * TODO 整理订单信息
     * @param array $data
     * @return array
     * @author Qinii
     * @day 2020-08-10
     */
    public function orderList(array $data)
    {
        $result = [];
        if(empty($data)) return $result;
        $i = 1;
        foreach ($data as $item){
          //  halt($item);
            foreach ($item['orderProduct'] as $key => $value){
                $result[] = [
                    $i,
                    $item['order_sn'],
                    $item['order_type'] ? '核销订单':'普通订单',
                    $item['user']['spread']['nickname'],
                    $item['user']['nickname'],
                    $value['cart_info']['product']['store_name'],
                    $value['cart_info']['productAttr']['sku'],
                    $value['product_num'],
                    $value['cart_info']['product']['price'],
                    ($key == 0 ) ? $item['coupon_price'] : 0,
                    ($key == 0 ) ? $item['pay_postage'] : 0,
                    ($key == 0 ) ? $item['pay_price'] : 0,
                    ($key == 0 ) ? $item['refund_price'] : 0,
                    $item['real_name'],
                    $item['user_phone'],
                    $item['user_address'],
                    $item['delivery_id'],
                    $item['create_time'],
                    $item['pay_type'] ? '微信': '余额',
                    $item['paid'] ? '已支付':'未支付',
                    $item['remark']
                ];
                $i++;
            }
        }
        return $result;
    }

    /**
     * TODO 流水记录导出
     * @param array $where
     * @param int $id
     * @author Qinii
     * @day 2020-08-10
     */
    public function financial(array $where,int $id)
    {
        $_key = [
            'mer_accoubts' => '财务对账',
            'sys_accoubts' => '财务对账',
            'refund_order' => '退款订单',
            'brokerage_one' => '一级分佣',
            'brokerage_two' => '二级分佣',
            'refund_brokerage_one' => '返还一级分佣',
            'refund_brokerage_two' => '返还二级分佣',
            'order' => '订单支付',
        ];
        $make = app()->make(FinancialRecordRepository::class);
        $query = $make->search($where)->with(['merchant']);
        $list = $query->select()->toArray();

        $header = [
            '序号','商户ID','商户名称','流水ID','交易流水单号','订单ID','订单号','用户名','用户ID','交易类型','收入/支出','金额','创建时间'
            ];

        $_export = [];
        foreach ($list as $k => $v){
            $_export[]=[
                $k,
                $v['merchant']['mer_id'],
                $v['merchant']['mer_name'],
                $v['financial_record_id'],
                $v['financial_record_sn'],
                $v['order_id'],
                $v['order_sn'],
                $v['uaccount'],
                $v['user_id'],
                $_key[$v['financial_type']],
                $v['financial_pm'] ? '收入' : '支出',
                ($v['financial_pm'] ? '+ ' : '- ') . $v['number'],
                $v['create_time'],
            ];
        }

        $title = ['流水列表','流水信息','生成时间:' . date('Y-m-d H:i:s',time())];
        $filename = '流水列表_'.date('YmdHis');

        return $this->export($header,$title,$_export,'financial',$filename,$id,'xlsx');
    }

    //会员信息
    public function user(array $where,int $id)
    {
//        $_key = [
//            'uid' => 'ID',
//            'nickname' => '昵称',
//            'is_promoter' => '是否分销员',
//            'brokerage_gongxian' => '贡献值',
//            'brokerage_shuquan' => '数权值',
//            'brokerage_duihuan' => '兑换值',
//            'brokerage_price' => '股权值',
//            'spreadnickname' => '推荐人',
//            'sign_time' => '签到时间',
//            'account' => '用户账户',
//            'now_money' => '余额',
//        ];
        $make = app()->make(UserRepository::class);
        $query = $make->search($where)->with(['spread']);
        $list = $query->select()->toArray();

        $header = [    '序号',  'ID','昵称','是否分销员','贡献值','数权值','兑换值','股权值','推荐人','签到时间','用户账户','余额' ];

        $_export = [];
        foreach ($list as $k => $v){
            $_export[]=[
                $k,
                $v['uid'],
                $v['nickname'],
                $v['is_promoter']?'分销员':'普通用户',
                $v['brokerage_gongxian'],
                $v['brokerage_shuquan'],
                $v['brokerage_duihuan'],
                $v['brokerage_price'],
                $v['spread']?$v['spread']['nickname'].'/'.$v['spread']['spread_uid']:'--',
                $v['sign_time'],
                $v['account'],
                $v['now_money'],
            ];
        }

        $title = ['用户列表','','生成时间:' . date('Y-m-d H:i:s',time())];
        $filename = '用户列表_'.date('YmdHis');

        return $this->export($header,$title,$_export,'user',$filename,$id,'xlsx');
    }
//提现
    public function ext(array $where,int $id)
    {
        $make = app()->make(UserExtractDao::class);
        $query = $make->search($where)->with('user');
        $list = $query->select()->toArray();

        $header = [    '序号', '提现来源','会员账号','会员实名','审核状态','提现总额','基金','手续费','应支付金额','持卡人','银行名称','账号' ,'提现方式','提现时间','拒绝原因'  ];

        $_export = [];
        foreach ($list as $k => $v){
            $_export[]=[
                $k+1,
                $v['use_type'] == 'mer'? '商户货款提现': '股权值提现',
                $v['user']?$v['user']['account']:'--',
                $v['user']?$v['user']['real_name']:'--',
                $v['status']==0?'审核中':($v['status']==1?'已通过':'已拒绝'),
                $v['extract_price'],
                $v['base'],
                $v['sp'],
                $v['pay'],
                $v['real_name'],
                $v['bank_address'],
                ' '.$v['bank_code'],
                $v['extract_type'] == 0 ? '银行卡':($v['extract_type'] ==1?'微信':  ( $v['extract_type'] == 2 ? '支付宝':'已退款')),
                date('Y/m/d H:i',strtotime($v['create_time'])),
                $v['fail_msg'],
            ];
        }

        $title = ['提现列表','','生成时间:' . date('Y-m-d H:i:s',time())];
        $filename = '提现列表_'.date('YmdHis');

        return $this->export($header,$title,$_export,'ext',$filename,$id,'xlsx');
    }
//线下订单
    public function scan(array $where,int $id)
    {

        $qq =  Db::table('eb_scan_order')->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date'], 'pay_time');
        });
        $list =$qq->order('scan_order_id desc')->select()->toArray();
        foreach ($list as &$v){
            $v['user'] = Db::table('eb_user')->find($v['uid']);
            $v['mer'] = Db::table('eb_merchant')->find($v['mer_id']);
            $v['pay_time'] =  strtotime($v['pay_time']) > 0? $v['pay_time']:'--';
        }
        unset($v);
        $header = [    '序号',  '订单号','会员ID','会员名称','商户ID','商户','金额','支付时间' ];

        $_export = [];
        foreach ($list as $k => $v){
            $_export[]=[
                $k,
                $v['order_sn'],
                $v['uid'],
                $v['user']['account'],
                $v['mer_id'],
                $v['mer']['mer_name'],
                $v['money'],
                $v['pay_time'],
            ];
        }

        $title = ['线下订单列表','','生成时间:' . date('Y-m-d H:i:s',time())];
        $filename = '线下订单列表_'.date('YmdHis');

        return $this->export($header,$title,$_export,'scan',$filename,$id,'xlsx');
    }
    //申请
    public function subinfo(array $where,int $id)
    {

        $qq =  Db::table('eb_mer_sub_info')->alias('sorder')->join('merchant merchant','merchant.mer_id = sorder.mer_id')->field('sorder.*,merchant.mer_name mer_name')
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'sorder.create_time');
            })->when(isset($where['state']) && $where['state'] !== '', function ($query) use ($where) {
                $query->where('sorder.state',$where['state']);
            });
        $list =$qq->order('sorder.id desc')->select()->toArray();
        foreach ($list as &$v){
            $v['face_imgs_arr'] = implode("\n",explode(',',$v['face_imgs']));
            $v['user'] =  Db::table('eb_user')->find($v['uid']) ;
//            $v['user'] = Db::table('eb_user')->find($v['uid']);
//            $v['mer'] = Db::table('eb_merchant')->find($v['mer_id']);
//            $v['pay_time'] =  strtotime($v['pay_time']) > 0? $v['pay_time']:'--';
        }
        unset($v);
        $header = [    '序号',  '系统商户名称','用户账户','联系电话','商户名','商户类型','所在地区','注册地址','注册日期',
        '营业执照注册号','营业执照','开户许可证','法人姓名','法人身份证号码',
    '法人身份证有效期','法人或商户负责人身份证正面','法人或商户负责人身份证反面','商户简称','门店照片','结算方式',
    '开户支行','结算人身份证正面','结算人身份证反面','结算账号','结算银行卡正面','结算银行卡反面','非法人授权函'];

        $_export = [];
        foreach ($list as $k => $v){
            $_export[]=[
                $k,
                $v['mer_name'],
                $v['user']['account'],
                $v['phone'],
                $v['name'],
                $v['type'],
                $v['zone'],
                $v['reg_addr'],
                $v['reg_time'],
                $v['reg_license'],
                $v['reg_license_img'],
                $v['reg_sw_img'],
//                $v['reg_jg_img'],
                $v['fa_name'],
                $v['fa_idno'],
                $v['fa_time'],
                $v['fa_idno_img_fr'],
                $v['fa_idno_img_bk'],
                $v['desp'],
                $v['face_imgs_arr'],
                $v['sub_type'],
                $v['bank_addr'],
                $v['js_idno_img_fr'],
                $v['js_idno_img_bk'],
                $v['bank_no'],
                $v['bank_img_fr'],
                $v['bank_img_bk'],
                $v['auth_book_img'],
            ];
        }

        $title = ['商户资料列表','','生成时间:' . date('Y-m-d H:i:s',time())];
        $filename = '商户资料列表_'.date('YmdHis');

        return $this->export($header,$title,$_export,'scan',$filename,$id,'xlsx');
    }
}
