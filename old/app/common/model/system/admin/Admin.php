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


namespace app\common\model\system\admin;

use app\common\model\BaseModel;
use app\common\model\system\auth\Role;

class Admin extends BaseModel
{
    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'admin_id';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'system_admin';
    }

    /**
     * @param $value
     * @return array
     * @author xaboy
     * @day 2020-03-30
     */
    public function getRolesAttr($value)
    {
        return array_map('intval', explode(',', $value));
    }

    /**
     * @param $value
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public function setRolesAttr($value)
    {
        return implode(',', $value);
    }

    /**
     * @param bool $isArray
     * @return array|string
     * @author xaboy
     * @day 2020-04-09
     */
    public function roleNames($isArray = false)
    {
        $roleNames = Role::getDB()->whereIn('role_id', $this->roles)->column('role_name');
        return $isArray ? $roleNames : implode(',', $roleNames);
    }

    public function searchRealNameAttr($query,$value)
    {
        $query->whereLike('real_name',"%{$value}%");
    }
}
