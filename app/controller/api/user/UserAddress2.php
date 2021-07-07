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


namespace app\controller\api\user;

use think\App;
use crmeb\basic\BaseController;
use think\facade\Db;

class UserAddress2 extends BaseController
{
    /**
     * @var repository
     */
    protected $repository;
    protected $uid;

    /**
     * UserAddress constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->uid = $this->request->uid();
    }


    public function lst()
    {
        $list  = Db::table('eb_user_address2')->where('uid',$this->uid)->order('is_default desc')->select();
        return app('json')->success(compact('list'));
    }

    public function detail($id)
    {
        $item  = Db::table('eb_user_address2')->where('uid',$this->uid)->order('is_default desc')->where('address_id',$id)->find();
        return app('json')->success($item);
    }
    /**
     * @param validate $validate
     * @return mixed
     * @author Qinii
     */
    public function create()
    {
        $data = $this->request->params(['address_id','real_name','bank_code','bank_address','is_default']);
        $address_id =intval( $data['address_id'] );
        unset($data['address_id']);
        $data['is_default'] =    $data['is_default'] ? 1:0;
        if($address_id){
            $item  = Db::table('eb_user_address2')->where('uid',$this->uid)->order('is_default desc')->where('address_id',$address_id)->find();
            if(!$item)return app('json')->fail('数据无效！');
            Db::table('eb_user_address2')->where('address_id', $address_id ) ->update($data);
        }else{
            $data['uid'] = $this->uid;
            $address_id = Db::table('eb_user_address2')->insertGetId($data);
        }
        if($data['is_default'] ){
            Db::table('eb_user_address2')->where('uid',$this->uid)->where('address_id','<>',$address_id)->update(['is_default'=>0]);
        }

        return app('json')->success('操作成功！');
    }

    /**
     * @param $id
     * @param validate $validate
     * @return mixed
     * @author Qinii
     */
    public function update($id)
    {
        $address_id =intval( $id  );
        $item  = Db::table('eb_user_address2')->where('uid',$this->uid)->order('is_default desc')->where('address_id',$address_id)->find();
        if(!$item)return app('json')->fail('数据无效！');
        Db::table('eb_user_address2')->where('address_id',$address_id)->update(['is_default'=>1]);
        Db::table('eb_user_address2')->where('uid',$this->uid)->where('address_id','<>',$address_id)->update(['is_default'=>0]);
        return app('json')->success('编辑成功');
    }

    /**
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function delete($id)
    {
        $address_id =intval( $id  );
        $item  = Db::table('eb_user_address2')->where('uid',$this->uid)->order('is_default desc')->where('address_id',$address_id)->find();
        if(!$item)return app('json')->fail('数据无效！');
        Db::table('eb_user_address2')->where('address_id',$address_id)->delete();
        return app('json')->success('删除成功');
    }

    public function editDefault($id)
    {
        $address_id =intval( $id  );
        $item  = Db::table('eb_user_address2')->where('uid',$this->uid)->order('is_default desc')->where('address_id',$address_id)->find();
        if(!$item)return app('json')->fail('数据无效！');
        Db::table('eb_user_address2')->where('address_id',$address_id)->update(['is_default'=>1]);
        Db::table('eb_user_address2')->where('uid',$this->uid)->where('address_id','<>',$address_id)->update(['is_default'=>0]);
        return app('json')->success('修改成功');
    }


}
