<?php
namespace app\command;

use app\common\model\user\User;
use app\common\repositories\store\order\MerchantReconciliationRepository;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class calcuMerMoney extends Command
{
    protected function configure()
    {
        $this->setName('calcuMerMoney')
        	->setDescription('calcuMerMoney');
    }

    protected function execute(Input $input, Output $output)
    {
          Db::table('eb_store_order')->where('reconciliation_id','>',0)->where('status','>',1)->group('mer_id')->field('mer_id')->select()->each(function ($item){

                app()->make(MerchantReconciliationRepository::class)->create2($item['mer_id']);


          });



        Log::info('结算完成！');
    }

}