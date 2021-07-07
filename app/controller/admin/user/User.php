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
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserGroupRepository;
use app\common\repositories\user\UserLabelRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\WechatNewsRepository;
use app\common\repositories\wechat\WechatUserRepository;
use app\validate\admin\UserNowMoneyValidate;
use app\validate\admin\UserNowGongxianValidate;
use app\validate\admin\UserNowDuihuanValidate;
use app\validate\admin\UserNowShuquanValidate;
use app\validate\admin\UserNowPriceValidate;
use app\validate\admin\UserValidate;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;

/**
 * Class User
 * @package app\controller\admin\user
 * @author xaboy
 * @day 2020-05-07
 */
class User extends BaseController
{
    /**
     * @var UserRepository
     */
    protected $repository;

    /**
     * User constructor.
     * @param App $app
     * @param UserRepository $repository
     */
    public function __construct(App $app, UserRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }



    public function export()
    {
        $where = $this->request->params([
            'label_id',
            'user_type',
            'sex',
            'is_promoter',
            'country',
            'pay_count',
            'user_time_type',
            'user_time',
            'nickname',
            'province',
            'city',
            'group_id',
            'spread_uid'
        ]);
        app()->make(ExcelRepository::class)->create($where, $this->request->adminId(), 'user',$this->request->merId());
        return app('json')->success('开始导出数据');
    }



    /**
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-07
     */
    public function lst()
    {
        /*
         * 昵称，分组，标签，地址，性别，
         */
        $where = $this->request->params([
            'uid',
            'account',
            'label_id',
            'user_type',
            'sex',
            'is_promoter',
            'country',
            'pay_count',
            'user_time_type',
            'user_time',
            'nickname',
            'province',
            'city',
            'group_id',
            'spread_uid',
            'user_type2'
        ]);
        [$page, $limit] = $this->getPage();
        $data =$this->repository->getList($where, $page, $limit);
        $admin_rol = $this->request->adminInfo()->roles[0];
        return app('json')->success(array_merge($data,['admin_rol'=>$admin_rol]) );
    }

    public function getOneLevelList($uid){
        $where = $this->request->params([$uid]);
        return app('json')->success($this->repository->getOneLevelList($where));
    }

    public function spreadList($uid)
    {
        $where = $this->request->params(['level', 'keyword', 'date']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getLevelList($uid, $where, $page, $limit));
    }

    public function spreadOrder($uid)
    {
        $where = $this->request->params(['level', 'keyword', 'date']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->subOrder($uid, $page, $limit, $where));
    }

    public function clearSpread($uid)
    {
        $this->repository->update($uid, ['spread_uid' => 0]);
        return app('json')->success('清除成功');
    }

    /**
     * @param $id
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-09
     */
    public function updateForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->userForm($id)));
    }



    public function changePsForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->userPsForm($id)));
    }

    public function changePs($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $spread_uid = $this->request->post('spread_uid/d');
        if(!$spread_uid || $spread_uid == $id )  return app('json')->fail('会员ID错误！');
//        $spread = Db::table('eb_user')->where(['uid'=>$spread_uid,'is_promoter'=>1])->find();
//        if(!$spread) return app('json')->fail('ID错误或不是推广员！');
        $spread = Db::table('eb_user')->where(['uid'=>$spread_uid])->find();
        if(!$spread) return app('json')->fail('ID错误！');
        $pis = array_filter(explode(',',$spread['retree']));
       if(in_array($id,$pis)) return app('json')->fail('该会员ID是其下级！');
       if(!$pis) $pis = [$spread_uid];
        $pis = array_merge($pis,[$id]);
        $this->repository->update($id, ['spread_uid'=>$spread_uid,'layer'=>count($pis),'retree'=> implode(',',$pis).',' ]);
        $this->refreshUserTree($id,  implode(',',$pis).','   );
        return app('json')->success('修改成功');
    }

    private function refreshUserTree($uid,$retree_p,$level = 0){
        if($level > 100) return;
        $ch_users = Db::table('eb_user')->where(['spread_uid'=>$uid])->select();
        foreach ($ch_users as $vv){
            $retree = $vv['retree'];
//            if($retree && strpos($retree , ",{$vv['uid']}," ) !== false){
//               $vv['retree'] = rtrim($retree_p,',') . substr($retree,strpos($retree,  ",{$vv['uid']},"  ));
//            } else{
                $vv['retree'] =   rtrim($retree_p,',') . ",{$vv['uid']}," ;
//            }
            $vv['layer'] = count(array_unique(array_filter(explode(',', $vv['retree'] ))));
            Db::table('eb_user')->where(['uid'=>$vv['uid']])->update($vv);
            $this->refreshUserTree($vv['uid'],$vv['retree'],$level+1);
        }
    }

    public function delUser($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->delete($id);
        $this->refreshUserTree($id, '' );
        return app('json')->success('删除成功');
    }


    /**
     * @param $id
     * @param UserValidate $validate
     * @param UserLabelRepository $labelRepository
     * @param UserGroupRepository $groupRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-09
     */
    public function update($id, UserValidate $validate, UserLabelRepository $labelRepository, UserGroupRepository $groupRepository)
    {
        $data = $this->request->params(['real_name', 'phone', 'birthday', 'card_id', 'addres', 'mark', 'group_id', ['label_id', []], 'is_promoter', 'memberlevel2','memberlevel3', 'agentLevel','city_id','sw_gq_ye','sw_gq_tx']);

        $validate->check($data);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        if ($data['group_id'] && !$groupRepository->exists($data['group_id']))
            return app('json')->fail('分组不存在');
        $label_id = (array)$data['label_id'];
        foreach ($label_id as $k => $value) {
            $label_id[$k] = (int)$value;
            if (!$labelRepository->exists((int)$value))
                return app('json')->fail('标签不存在');
        }
        $data['label_id'] = implode(',', $label_id);
        if ($data['is_promoter'])
            $data['promoter_time'] = date('Y-m-d H:i:s');
        if(!$data['birthday']) unset($data['birthday']);

        $data['city_id'] = $data['city_id']?$data['city_id'][2]:0;

        $this->repository->update($id, $data);


        /////n1/// 同步用户数据
//        $uid = $id;
//        $user_b = Db::table('eb_user')->find($uid);
//        if( $user_b['agentLevel'] > 0){
//                $bonus_agent = [
//                    'uid'=> $uid  ,
//                    'agentLevel'=> $user_b['agentLevel'],
//                    'createtime'=> date('Y-m-d H:i:s'),
//                    'agentSheng'=>  '',
//                    'agentShi'=>  '',
//                    'agentXian'=>  '',
//                    'shengid'=>  '',
//                    'shiid'=>  '',
//                    'xianid'=> '',
//                ];
//                if($user_b['city_id']){
//                    $xian = Db::table('eb_system_city')->where(['city_id'=>$user_b['city_id']])->find();
//                    $bonus_agent['agentXian'] = $xian['name'];
//                    $bonus_agent['xianid'] = $xian['city_id'];
//                    $Shi = Db::table('eb_system_city')->where(['city_id'=>$xian['parent_id']])->find( );
//                    $bonus_agent['agentShi'] = $Shi['name'];
//                    $bonus_agent['shiid'] = $Shi['city_id'];
//                    $Sheng = Db::table('eb_system_city')->where(['city_id'=>$Shi['parent_id']])->find( );
//                    $bonus_agent['agentSheng'] = $Sheng['name'];
//                    $bonus_agent['shengid'] = $Sheng['city_id'];
//                }
//                $bonus_agent_o = Db::table('bonus_agent')->where(['uid'=>$uid])->find();
//                if(!$bonus_agent_o){
//                    Db::table('bonus_agent')->insert($bonus_agent);
//                }else{
//                    $bonus_agent['id'] = $bonus_agent_o['id'];
//                    Db::table('bonus_agent')->update($bonus_agent);
//                }
//        }

        return app('json')->success('编辑成功');
    }


    /**
     * @param $id
     * @param UserLabelRepository $labelRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-08
     */
    public function changeLabel($id, UserLabelRepository $labelRepository)
    {
        $label_id = (array)$this->request->param('label_id', []);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        foreach ($label_id as $k => $value) {
            $label_id[$k] = (int)$value;
            if (!$labelRepository->exists((int)$value))
                return app('json')->fail('标签不存在');
        }
        $label_id = implode(',', $label_id);
        $this->repository->update($id, compact('label_id'));
        return app('json')->success('修改成功');
    }

    /**
     * @param UserLabelRepository $labelRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-08
     */
    public function batchChangeLabel(UserLabelRepository $labelRepository)
    {
        $label_id = (array)$this->request->param('label_id', []);
        $ids = (array)$this->request->param('ids', []);
        if (!count($ids))
            return app('json')->fail('数据不存在');
        foreach ($label_id as $k => $value) {
            $label_id[$k] = (int)$value;
            if (!$labelRepository->exists((int)$value))
                return app('json')->fail('标签不存在');
        }
        $this->repository->batchChangeLabelId($ids, $label_id);
        return app('json')->success('修改成功');
    }


    /**
     * @param $id
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-08
     */
    public function changeLabelForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeLabelForm($id)));
    }

    public function changePwdForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changePwdForm($id)));
    }

    public function changePwd($id){
        $data = $this->request->params(['pwd']);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->changePwd($id, $data['pwd']);

        return app('json')->success('修改成功');
    }


    /**
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-08
     */
    public function batchChangeLabelForm()
    {
        $ids = $this->request->param('ids', '');
        $ids = array_filter(explode(',', $ids));
        if (!count($ids))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeLabelForm($ids)));
    }


    /**
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-08
     */
    public function batchChangeGroupForm()
    {
        $ids = $this->request->param('ids', '');
        $ids = array_filter(explode(',', $ids));
        if (!count($ids))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeGroupForm($ids)));
    }

    /**
     * @param $id
     * @param UserGroupRepository $groupRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-07
     */
    public function changeGroup($id, UserGroupRepository $groupRepository)
    {
        $group_id = (int)$this->request->param('group_id', 0);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        if ($group_id && !$groupRepository->exists($group_id))
            return app('json')->fail('分组不存在');
        $this->repository->update($id, compact('group_id'));
        return app('json')->success('修改成功');
    }

    /**
     * @param UserGroupRepository $groupRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-07
     */
    public function batchChangeGroup(UserGroupRepository $groupRepository)
    {
        $group_id = (int)$this->request->param('group_id', 0);
        $ids = (array)$this->request->param('ids', []);
        if (!count($ids))
            return app('json')->fail('数据不存在');
        if ($group_id && !$groupRepository->exists($group_id))
            return app('json')->fail('分组不存在');
        $this->repository->batchChangeGroupId($ids, $group_id);
        return app('json')->success('修改成功');
    }

    /**
     * @param $id
     * @return mixed
     * @throws FormBuilderException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-07
     */
    public function changeGroupForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeGroupForm($id)));
    }

    /**
     * @param $id
     * @return mixed
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020-05-07
     */
    public function changeNowMoneyForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeNowMoneyForm($id)));
    }

    /**
     * @param $id
     * @param UserNowMoneyValidate $validate
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-07
     */
    public function changeNowMoney($id, UserNowMoneyValidate $validate)
    {
        $data = $this->request->params(['now_money', 'type','mark']);
        $validate->check($data);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->changeNowMoney($id, $this->request->adminId(), $data['type'], $data['now_money'], $data['mark']);

        return app('json')->success('修改成功');
    }


    public function changeNowGongXianForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeNowGongxianForm($id)));
    }
    public function changeNowGongxian($id, UserNowGongxianValidate $validate)
    {
        $data = $this->request->params(['brokerage_gongxian', 'type','mark']);
        $validate->check($data);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->changeNowGongxian($id, $this->request->adminId(), $data['type'], $data['brokerage_gongxian'], $data['mark']);

        return app('json')->success('修改成功');
    }

    public function changeNowDuihuanForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeNowDuihuanForm($id)));
    }
    public function changeNowDuihuan($id, UserNowDuihuanValidate $validate)
    {
        $data = $this->request->params(['brokerage_duihuan', 'type','mark']);
        $validate->check($data);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->changeNowDuihuan($id, $this->request->adminId(), $data['type'], $data['brokerage_duihuan'], $data['mark']);

        return app('json')->success('修改成功');
    }

    public function changeNowShuquanForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeNowShuquanForm($id)));
    }
    public function changeNowShuquan($id, UserNowShuquanValidate $validate)
    {
        $data = $this->request->params(['brokerage_shuquan', 'type','mark']);
        $validate->check($data);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->changeNowShuquan($id, $this->request->adminId(), $data['type'], $data['brokerage_shuquan'], $data['mark']);

        return app('json')->success('修改成功');
    }
    public function changeNowPriceForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeNowPriceForm($id)));
    }
    public function changeNowPrice($id, UserNowPriceValidate $validate)
    {
        $data = $this->request->params(['brokerage_price', 'type','mark']);
        $validate->check($data);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->changeNowPrice($id, $this->request->adminId(), $data['type'], $data['brokerage_price'], $data['mark']);

        return app('json')->success('修改成功');
    }

    public function cashUserVerifyForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->cashUserVerifyForm($id)));
    }
    public function cashUserVerify($id, UserNowPriceValidate $validate)
    {
        $data = $this->request->params(['certifyState']);
        // $validate->check($data);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');

        $this->repository->cashUserVerify($id, $this->request->adminId(), $data['certifyState']);

        return app('json')->success('审核成功');
    }

    public function cash_user_verify_bk($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');

        $this->repository->get($id)->data([
                'card_id'=>'',
                'idcardFront'=>'',
                'idcardBack'=>'',
                'certifyState'=>0
        ])->save();
 
        return app('json')->success('操作成功');
    }








    /**
     * @param WechatNewsRepository $wechatNewsRepository
     * @param WechatUserRepository $wechatUserRepository
     * @return mixed
     * @author xaboy
     * @day 2020-05-11
     */
    public function sendNews(WechatNewsRepository $wechatNewsRepository, WechatUserRepository $wechatUserRepository)
    {
        $ids = array_filter(array_unique(explode(',', $this->request->param('ids'))));
        $news_id = (int)$this->request->param('news_id', 0);
        if (!$news_id)
            return app('json')->fail('请选择图文消息');
        if (!$wechatNewsRepository->exists($news_id))
            return app('json')->fail('数据不存在');
        if (!count($ids))
            return app('json')->fail('请选择微信用户');
        $wechatUserRepository->sendNews($news_id, $ids);
        return app('json')->success('发送成功');
    }

    public function promoterList()
    {
        $where = $this->request->params(['keyword','date', 'user_type', 'status', 'sex', 'group_id','is_promoter','memberlevel2','memberlevel3','agentLevel','spread_uid','uid']);
        // $where['is_promoter'] = 1;
        if($where['spread_uid'] || $where['uid'])$where['is_promoter'] = '';
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    public function detail($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->userOrderDetail($id));
    }

    public function order($id, StoreOrderRepository $repository)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->userList($id, $page, $limit));
    }

    public function coupon($id, StoreCouponUserRepository $repository)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->userList(['uid' => $id], $page, $limit));
    }

    public function bill($id, UserBillRepository $repository)
    {
        $data = $this->request->params(['category']);
        $data['category'] =  $data['category']? $data['category'] : 'now_money';
        if (!$this->repository->exists(intval($id)))
            return app('json')->fail('数据不存在');
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->userList($data, $id, $page, $limit,'create_time desc,bill_id desc'));
    }
}
