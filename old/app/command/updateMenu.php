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

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace app\command;

use Swoole\Coroutine\MySQL\Exception;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\event\RouteLoaded;
use think\facade\Route;
use app\common\repositories\system\auth\MenuRepository;

class updateMenu extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('menu')
            ->setDescription('the menu command');
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/15
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始执行');
        $output->writeln('---------------->');
        $count = $this->routeList();
        $output->writeln('<----------------');
        $output->writeln('执行成功，合计'.$count.'条');
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/15
     * @param string|null $dir
     * @return mixed
     */
    public function routeList(string $dir = null)
    {
        $this->app->route->setTestMode(true);
        $this->app->route->clear();
        $path = $this->app->getRootPath() . 'route' . DIRECTORY_SEPARATOR;
        include $path . 'admin.php';
        include $path . 'merchant.php';
//        $files = is_dir($path) ? scandir($path) : [];
//        foreach ($files as $file) {
//            if (strpos($file, '.php')) {
//                include $path . $file;
//            }
//        }
        //触发路由载入完成事件
        $this->app->event->trigger(RouteLoaded::class);
        $routeList = $this->app->route->getRuleList();
        $rows = [];
        $name = [];
        foreach ($routeList as $k => $item) {
            if (!(strpos($item['name'], '/') !== false) && !(strpos($item['name'], '@') !== false) && is_string($item['route'])) {
                $rule = Route::getRule($item['rule']);
                $group = ($rule[$item['route']])->getParent();
                $groupRoute = $this->getGroupTree($group);
                $groupName = !empty($group->getName()) ? $group->getName() : 'route';
                if (in_array($item['name'], $name))
                    throw new Exception( "< " . $item['name'] . ' >路由名重复');
                $name[] = $item['name'];
                $rows[$groupRoute][$groupName][] = $item['name'];
            }
        }
        return app()->make(MenuRepository::class)->commandCreate($rows);
    }

    /**
     * @Author:Qinii
     * @Date: 2020/5/15
     * @param $group
     * @return mixed
     */
    protected function getGroupTree($group)
    {
        $name = $group->getName();
        if (in_array($name, ['sys','mer'])) {
            return $name;
        } else {
            return $this->getGroupTree($group->getParent());
        }
    }
}
