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

namespace app\common\dao\store\product;

use app\common\dao\BaseDao;
use app\common\model\store\product\ProductPresell;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\system\merchant\MerchantRepository;

class ProductPresellDao extends BaseDao
{
    protected function getModel(): string
    {
        return ProductPresell::class;
    }

    public function search(array $where)
    {
        $query = ProductPresell::hasWhere('product',function($query)use($where){
            $query->when(isset($where['product_show']) && $where['product_show'] !== '',function($query)use($where){
                    $query->where('is_del',0)->where('mer_status',1);
                })
                ->when(isset($where['product_type']) && $where['product_type'] !== '',function($query)use($where){
                    $query->where('product_type',2);
                })
                ->where('status',1);
        });
        $query->when(isset($where['product_presell_id']) && $where['product_presell_id'] !== '',function($query)use($where){
                $query->where('product_presell_id',$where['product_presell_id']);
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '',function($query)use($where){
                $query->where('ProductPresell.mer_id',$where['mer_id']);
            })
            ->when(isset($where['action_status']) && $where['action_status'] !== '',function($query)use($where){
                $query->where('ProductPresell.action_status',$where['action_status']);
            })
            ->when(isset($where['keyword']) && $where['keyword'] !== '',function($query)use($where){
                $query->whereLike('ProductPresell.store_name|ProductPresell.product_id',"%{$where['keyword']}%");
            })
            ->when(isset($where['product_status']) && $where['product_status'] !== '',function($query)use($where){
                if($where['product_status'] == -1){
                    $query->where('ProductPresell.product_status','in',[-1,-2]);
                }else{
                    $query->where('ProductPresell.product_status',$where['product_status']);
                }
            })
            ->when(isset($where['type']) && $where['type'] !== '',function($query)use($where){
                switch ($where['type']){
                    case 0: //未开始
                        if(isset($where['api_type'])){
                            $query->where('product_status',1);
                        }
                        $query->where(function($query){
                            $query->where('start_time','> TIME',time())->whereOr(function($query){
                                $query->whereOr('ProductPresell.status','<>',1)->whereOr('ProductPresell.is_show','<>',1);
                            });
                        });
                        break;
                    case 1: //进行中
                        $query->whereTime('start_time','<=',time())->whereTime('end_time','>',time())
                            ->where('product_status',1)->where('ProductPresell.status',1)->where('ProductPresell.is_show',1);
                        break;
                    case 2: //已结束
//                        if(isset($where['api_type'])){
//                            $query->where('ProductPresell.presell_type',2)
//                                ->whereTime('end_time','<=',time())
//                                ->whereTime('final_end_time','>=',time());
//                        }else{
                            $query->where(function($query){
                                $query->where('action_status',-1)->whereOr('end_time','<= TIME',time());
                            });
//                        }
                        break;
                    case 3: //已关闭
                        $query->where(function($query){
                            $query->where(function($query){
                                $query->where('ProductPresell.presell_type',1)->whereTime('end_time','<',time());
                            })->whereOr(function($query){
                                $query->where('ProductPresell.presell_type',2)->whereTime('final_end_time','<',time());
                            });
                        });
                        break;
                }
            })
            ->when(isset($where['presell_type']) && $where['presell_type'] !== '',function($query)use($where){
                $query->where('ProductPresell.presell_type',$where['presell_type']);
            })
            ->when(isset($where['status']) && $where['status'] !== '',function($query)use($where){
                $query->where('ProductPresell.status',$where['status']);
            })
            ->when(isset($where['is_show']) && $where['is_show'] !== '',function($query)use($where){
                $query->where('ProductPresell.is_show',$where['is_show']);
            })
            ->when(isset($where['mer_name']) && $where['mer_name'] !== '',function($query)use($where){
                $make = app()->make(MerchantRepository::class);
                $mer_id = $make->search(['keyword' => $where['mer_name']])->column('mer_id');
                $query->whereIn('ProductPresell.mer_id',$mer_id);
            })
            ->when(isset($where['is_trader']) && $where['is_trader'] !== '',function($query)use($where){
                $make = app()->make(MerchantRepository::class);
                $mer_id = $make->search(['is_trader' => $where['is_trader']])->column('mer_id');
                $query->whereIn('ProductPresell.mer_id',$mer_id);
            });
        $query->where('ProductPresell.is_del',0);
        return $query;
    }

    /**
     * TODO 移动端展示 条件
     * @return array
     * @author Qinii
     * @day 2020-10-19
     */
    public function presellShow()
    {
        return [
            'product_show' => 1,
            //'product_status' => 1,
            'status' => 1,
            'is_show' => 1,
            'api_type' => 1
        ];
    }

    /**
     * TODO
     * @author Qinii
     * @day 1/27/21
     */
    public function valActiveStatus()
    {
        $query = $this->getModel()::getDB()->whereTime('end_time','<=',time())->where('action_status',1);
        $id = $query->column($this->getPk());
        if($id){
            $this->getModel()::getDB()->where($this->getPk(),'in',$id)->update(['action_status' => -1]);
            $where = [
                'product_type' => 2,
                'activity_ids' => $id
            ];
            app()->make(SpuRepository::class)->getSearch($where)->update(['status' => 0]);
        }
    }

}

