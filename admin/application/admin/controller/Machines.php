<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2024  TG@YLFC666 [   ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\admin\controller;

use library\Controller;
use think\Db;

/**
 * 网站配置
 * Class Item
 * @package app\admin\controller
 */
class Machines extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $machines_table = 'LcMachines';

    /**
     * 奖励设置
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function machines()
    {
        $this->title = '矿机设置';
        //获取产品信息
        $machinesInfo = Db::name('lc_machines') -> where('id', 1) -> find();
        $this -> assign('machinesInfo', $machinesInfo);
        $this->_form($this->machines_table, 'machines' );
    }
}
