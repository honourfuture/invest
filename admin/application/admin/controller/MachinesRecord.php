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
 * 矿机记录
 * Class Item
 * @package app\admin\controller
 */
class MachinesRecord extends Controller
{
     /**
      * 绑定数据表
      * @var string
      */
     protected $table = 'LcMachinesList';

     /**
      * 抽奖记录
      * @auth true
      * @menu true
      * @throws \think\Exception
      * @throws \think\db\exception\DataNotFoundException
      * @throws \think\db\exception\ModelNotFoundException
      * @throws \think\exception\DbException
      * @throws \think\exception\PDOException
      */
     public function index()
     {
         $this->title = '挖矿记录';
         $query = $this->_query($this->table)->alias('i')->field('i.*,u.phone,u.name as u_name');
         $query->join('lc_user u','i.uid=u.id')->order('i.id desc')->page();
     }

    // /**
    //  * 删除抽奖记录
    //  * @auth true
    //  * @throws \think\Exception
    //  * @throws \think\exception\PDOException
    //  */
    // public function remove()
    // {
    //     $this->applyCsrfToken();
    //     $this->_delete($this->table);
    // }
}
