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


namespace app;

use crmeb\traits\Macro;

class Request extends \think\Request
{
    use Macro;

    protected $cache = [];

    public function __construct()
    {
        parent::__construct();
        $this->filter[] = function ($val) {
            return is_string($val) ? trim($val) : $val;
        };
    }

    public function ip(): string
    {
        return $this->header('remote-host') ?? parent::ip();
    }

    /**
     * @param $db
     * @param $key
     * @return bool
     * @author xaboy
     * @day 2020/10/22
     */
    public function hasCache($db, $key)
    {
        return isset($this->cache[$db][$key]);
    }

    /**
     * @param $db
     * @param $key
     * @return array|mixed|string
     * @author xaboy
     * @day 2020/10/22
     */
    public function getCache($db, $key)
    {
        if (is_array($key)) {
            $data = [];
            foreach ($key as $v) {
                $data[$v] = $this->getCache($db, $v);
            }
            return $data;
        }
        return $this->cache[$db][$key] ?? '';
    }

    /**
     * @param $db
     * @param $key
     * @param null $value
     * @author xaboy
     * @day 2020/10/22
     */
    public function setCache($db, $key, $value = null)
    {
        if (!isset($this->cache[$db])) $this->cache[$db] = [];
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->setCache($db, $k, $v);
            }
            return;
        }
        $this->cache[$db][$key] = $value;
    }

    public function clearCache()
    {
        $this->cache = [];
    }

    public function params(array $names, $filter = '')
    {
        $data = [];
        $flag = false;
        if ($filter === true) {
            $filter = '';
            $flag = true;
        }
        foreach ($names as $name) {
            if (!is_array($name))
                $data[$name] = $this->param($name, '', $filter);
            else
                $data[$name[0]] = $this->param($name[0], $name[1], $filter);
        }

        return $flag ? array_values($data) : $data;
    }

    public function merId()
    {
        return intval($this->hasMacro('merchantId') ? $this->merchantId() : 0);
    }
}
