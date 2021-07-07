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


namespace app\common\repositories\system\merchant;


use app\common\dao\system\merchant\MerchantDao;
use app\common\model\store\product\ProductReply;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductCopyRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\store\shipping\ShippingTemplateRepository;
use app\common\repositories\store\StoreCategoryRepository;
use app\common\repositories\system\attachment\AttachmentRepository;
use app\common\repositories\user\UserRelationRepository;
use app\common\repositories\user\UserVisitRepository;
use app\common\repositories\wechat\RoutineQrcodeRepository;
use crmeb\services\QrcodeService;
use crmeb\services\UploadService;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;
use think\Model;
use app\common\repositories\store\shipping\CityRepository   ;

/**
 * Class MerchantRepository
 * @package app\common\repositories\system\merchant
 * @mixin MerchantDao
 * @author xaboy
 * @day 2020-04-16
 */
class MerchantRepository extends BaseRepository
{
    /**
     * MerchantRepository constructor.
     * @param MerchantDao $dao
     */
    public function __construct(MerchantDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @param array $where
     * @param $page
     * @param $limit
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-16
     */
    public function lst(array $where, $page, $limit)
    {
        $query = $this->dao->search($where);
        if($where['uaccount']){
            $query->rightjoin('User user', 'user.uid = mer.uid and user.account like '."'%{$where['uaccount']}%'") ;
        }
        $count = $query->count($this->dao->getPk());
         $query->page($page, $limit)->setOption('field', []);
        $list = $query->with([ 'admin' => function ($query) {
            $query->field('mer_id,account');
        },'user' => function ($query) use($where){
            $query->field('account,uid');
        }  ])->append(['qr'])->field('mer.money,mer.sort,mer.sub_mer,mer.sub_name,mer.crate, mer.mer_id, mer.mer_name, mer.uid, mer.real_name, mer.mer_phone, mer.mer_address, mer.mark, mer.status, mer.create_time,mer.is_best,mer.is_trader,mer.protype,mer.mertype')->select();
        return compact('count', 'list');
    }

    public function count()
    {
        $valid = $this->dao->search(['status' => 1])->count();
        $invalid = $this->dao->search(['status' => 0])->count();
        return compact('valid', 'invalid');
    }

    /**
     * @param int|null $id
     * @param array $formData
     * @return Form
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020-04-16
     */
    public function form(?int $id = null, array $formData = [])
    {
        $form = Elm::createForm(is_null($id) ? Route::buildUrl('systemMerchantCreate')->build() : Route::buildUrl('systemMerchantUpdate', ['id' => $id])->build());

        /** @var MerchantCategoryRepository $make */
        $make = app()->make(MerchantCategoryRepository::class);

        $config = systemConfig(['broadcast_room_type', 'broadcast_goods_type']);

        $rule = [
            Elm::input('mer_name', '商户名称')->required(),
            Elm::select('category_id', '商户分类')->options(function () use ($make) {
                $data = $make->allOptions();
                $options = [];
                foreach ($data as $value => $label) {
                    $options[] = compact('value', 'label');
                }
                return $options;
            })->requiredNum(),
            Elm::input('mer_account', '商户账号')->required()->disabled(!is_null($id))->required(!is_null($id)),
            Elm::password('mer_password', '登录密码')->required()->disabled(!is_null($id))->required(!is_null($id)),
            Elm::input('real_name', '商户姓名'),
            Elm::input('mer_phone', '商户手机号')->col(12)->required(),
//            Elm::number('commission_rate', '手续费(%)')->col(12),
            Elm::input('mer_keyword', '商户关键字')->col(12),
            Elm::cityArea('district_id', '选择区域' )->style(['width'=>'100%'])->placeholder('选择区域')->options(function ( )   {
                $repository = app()->make(CityRepository::class);
                $list =  $repository->getFormatList(['is_show' => 1]);
                $list = json_encode($list);
                $list = mb_ereg_replace('name','label',$list);
                $list = mb_ereg_replace('city_id','value',$list);
                return json_decode($list,true);
            }) ,
            Elm::input('mer_address', '详细地址')->placeholder('请输入详细地址(不包含省市区)'),
            Elm::textarea('mark', '备注'),
            Elm::number('sort', '排序', 0),
            $id ? Elm::hidden('status', 1) : Elm::switches('status', '是否开启', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->activeText('开启')->col(12),
//            Elm::switches('is_bro_room', '直播间审核', $config['broadcast_room_type'] == 1 ? 0 : 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->activeText('开启')->col(12),
            Elm::switches('is_audit', '产品审核', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->activeText('开启')->col(12),
//            Elm::switches('is_bro_goods', '直播间/商品审核', $config['broadcast_goods_type'] == 1 ? 0 : 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->activeText('开启')->col(12),
            Elm::switches('is_best', '是否推荐')->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->activeText('开启')->col(12),
            Elm::switches('is_trader', '是否自营')->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->activeText('开启')->col(12),

            Elm::input('sub_name', '快付名称'),
            Elm::input('sub_mer', '快付账户'),
            Elm::input('crate', '让利比例%'),

        ];

        $form->setRule($rule);
        return $form->setTitle(is_null($id) ? '添加商户' : '编辑商户')->formData($formData);
    }

    /**
     * @param array $formData
     * @return Form
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020/6/25
     */
    public function merchantForm(array $formData = [])
    {
        $form = Elm::createForm(Route::buildUrl('merchantUpdate')->build());
        $rule = [
            Elm::textarea('mer_info', '店铺简介')->required(),
            Elm::input('service_phone', '服务电话')->required(),
            Elm::frameImage('mer_banner', '店铺Banner(710*200px)', '/' . config('admin.merchant_prefix') . '/setting/uploadPicture?field=mer_banner&type=1')->modal(['modal' => false])->width('896px')->height('480px')->props(['footer' => false]),
            Elm::frameImage('mer_avatar', '店铺头像(120*120px)', '/' . config('admin.merchant_prefix') . '/setting/uploadPicture?field=mer_avatar&type=1')->modal(['modal' => false])->width('896px')->height('480px')->props(['footer' => false]),
            Elm::switches('mer_state', '是否开启', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->activeText('开启')->col(12),
        ];
        $form->setRule($rule);
        return $form->setTitle('编辑店铺信息')->formData($formData);
    }

    /**
     * @param $id
     * @return Form
     * @throws FormBuilderException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-16
     */
    public function updateForm($id)
    {
        $data = $this->dao->get($id)->toArray();
        /** @var MerchantAdminRepository $make */
        $make = app()->make(MerchantAdminRepository::class);
        $data['mer_account'] = $make->merIdByAccount($id);
        $data['mer_password'] = '***********';
        return $this->form($id, $data);
    }

    /**
     * @param array $data
     * @author xaboy
     * @day 2020-04-17
     */
    public function createMerchant(array $data)
    {
        if ($this->fieldExists('mer_name', $data['mer_name']))
            throw new ValidateException('商户名已存在:'.$data['mer_name']);
        if ($data['mer_phone'] && isPhone($data['mer_phone']))
            throw new ValidateException('请输入正确的手机号:'.$data['mer_phone']);
        $merchantCategoryRepository = app()->make(MerchantCategoryRepository::class);
        $adminRepository = app()->make(MerchantAdminRepository::class);

        if (!$data['category_id'] || !$merchantCategoryRepository->exists($data['category_id']))
            throw new ValidateException('商户分类不存在 id:'.$data['category_id']);
        if ($adminRepository->fieldExists('account', $data['mer_account']))
            throw new ValidateException('账号已存在:'.$data['mer_account']);

        /** @var MerchantAdminRepository $make */
        $make = app()->make(MerchantAdminRepository::class);
        return Db::transaction(function () use ($data, $make) {
            $account = $data['mer_account'];
            $password = $data['mer_password'];
            unset($data['mer_account'], $data['mer_password']);
            $merchant = $this->dao->create($data);
            $make->createMerchantAccount($merchant, $account, $password);
            app()->make(ShippingTemplateRepository::class)->createDefault($merchant->mer_id);
            app()->make(ProductCopyRepository::class)->defaulCopyNum($merchant->mer_id);
            return $merchant;
        });
    }


    /**
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     */
    public function getList($where, $page, $limit, $userInfo)
    {
        $where['protype'] = $where['protype'] == 2?2:1;
        $where['status'] = 1;
        $where['mer_state'] = 1;
        $where['is_del'] = 0;
        if (isset($where['location'])) {
            $data = @explode(',', (string)$where['location']);
            if (2 != count(array_filter($data ?: []))) {
                unset($where['location']);
            } else {
                $where['location'] = [
                    'lat' => (float)$data[0],
                    'long' => (float)$data[1],
                ];
            }
        }

        if ($userInfo && $where['keyword'] !== '') app()->make(UserVisitRepository::class)->searchMerchant($userInfo['uid'], $where['keyword']);
        $query = $this->dao->search($where)->with(['showProduct']);
        $count = $query->count($this->dao->getPk());
        $status = systemConfig('mer_location');
        $list = $query->page($page, $limit)->setOption('field', [])->field('care_count,is_trader,mer_id,mer_banner,mini_banner,mer_name, mark,mer_avatar,product_score,service_score,postage_score,sales,status,is_best,create_time,long,lat,protype,mer_address')->select()->each(function ($item) use ($status, $where) {
            $data = $item->showProduct->toArray();
            unset($item->showProduct);
            $recommend = array_slice($data, 0, 3);
            if ($status && $item['lat'] && $item['long'] && isset($where['location']['lat'], $where['location']['long'])) {
                $distance = getDistance($where['location']['lat'], $where['location']['long'], $item['lat'], $item['long']);
                if ($distance < 0.9) {
                    $distance = max(bcmul($distance, 1000, 0), 1) . 'm';
                } else {
                    $distance .= 'km';
                }
                $item['distance'] = $distance;
            }
            return $item['recommend'] = $recommend;
        });
        return compact('count', 'list');
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param int $id
     * @return array|Model|null
     */
    public function merExists(int $id)
    {
        return ($this->dao->get($id));
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param $id
     * @param $userInfo
     * @return array|Model|null
     */
    public function detail($id, $userInfo)
    {
        $merchant = $this->dao->apiGetOne($id)->hidden(["real_name", "mer_phone", "reg_admin_id", "sort", "is_del", "is_audit", "is_best", "mer_state", "bank", "bank_number", "bank_name", 'update_time']);
        $merchant['care'] = false;
        if ($userInfo)
            $merchant['care'] = $this->getCareByUser($id, $userInfo->uid);
        return $merchant;
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param int $merId
     * @param int $userId
     * @return bool
     */
    public function getCareByUser(int $merId, int $userId)
    {
        if (app()->make(UserRelationRepository::class)->getWhere(['type' => 10, 'type_id' => $merId, 'uid' => $userId]))
            return true;
        return false;
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param $merId
     * @param $where
     * @param $page
     * @param $limit
     * @param $userInfo
     * @return mixed
     */
    public function productList($merId, $where, $page, $limit, $userInfo)
    {
        return app()->make(ProductRepository::class)->getApiSearch($merId, $where, $page, $limit, $userInfo);
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/30
     * @param int $id
     * @return mixed
     */
    public function categoryList(int $id)
    {
        return app()->make(StoreCategoryRepository::class)->getApiFormatList($id, 1);
    }

    public function wxQrcode($merId)
    {
        $siteUrl = systemConfig('site_url');
        $name = md5('mwx' . $merId . date('Ymd')) . '.jpg';
        $attachmentRepository = app()->make(AttachmentRepository::class);
        $imageInfo = $attachmentRepository->getWhere(['attachment_name' => $name]);

        if (isset($imageInfo['attachment_src']) && strstr($imageInfo['attachment_src'], 'http') !== false && curl_file_exist($imageInfo['attachment_src']) === false) {
            $imageInfo->delete();
            $imageInfo = null;
        }
        if (!$imageInfo) {
            $codeUrl = rtrim($siteUrl, '/') . '/pages/store/home/index?id=' . $merId;//二维码链接
            $imageInfo = app()->make(QrcodeService::class)->getQRCodePath($codeUrl, $name);
            if (is_string($imageInfo)) throw new ValidateException('二维码生成失败');

            $imageInfo['dir'] = tidy_url($imageInfo['dir'], null, $siteUrl);

            $attachmentRepository->create(systemConfig('upload_type') ?: 1, -2, $merId, [
                'attachment_category_id' => 0,
                'attachment_name' => $imageInfo['name'],
                'attachment_src' => $imageInfo['dir']
            ]);
            $urlCode = $imageInfo['dir'];
        } else $urlCode = $imageInfo['attachment_src'];
        return $urlCode;
    }

    public function routineQrcode($merId)
    {
        $name = md5('smrt' . $merId . date('Ymd')) . '.jpg';
        return tidy_url(app()->make(QrcodeService::class)->getRoutineQrcodePath($name, 'pages/store/home/index', 'id=' . $merId), 0);
    }

    public function copyForm(int $id)
    {
        $form = Elm::createForm(Route::buildUrl('systemMerchantChangeCopy', ['id' => $id])->build());
        $form->setRule([
            Elm::input('copy_num', '复制次数', $this->dao->getCopyNum($id))->disabled(true)->readonly(true),
            Elm::radio('type', '修改类型', 1)
                ->setOptions([
                    ['value' => 1, 'label' => '增加'],
                    ['value' => 2, 'label' => '减少'],
                ]),
            Elm::number('num', '修改数量', 0)->required()
        ]);
        return $form->setTitle('修改复制商品次数');
    }

    public function delete($id)
    {
        Db::transaction(function () use ($id) {
            $this->dao->update($id, ['is_del' => 1]);
            app()->make(MerchantAdminRepository::class)->deleteMer($id);
        });
    }
}
