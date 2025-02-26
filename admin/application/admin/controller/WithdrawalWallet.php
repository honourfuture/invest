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
 * 提现方式管理
 * Class Item
 * @package app\admin\controller
 */
class WithdrawalWallet extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'lc_withdrawal_wallet';

    /**
     * 提现方式列表
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
        $this->title = '提现方式列表（开发中）';
        $query = $this->_query($this->table);
        $query->order('sort asc,id asc')->page();
    }

    /**
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        if ($this->request->isGet()) {
            $vo['show'] = isset($vo['show'])?$vo['show']:1;
            $vo['type'] = isset($vo['type'])?$vo['type']:1;
        }
        if ($this->request->isPost()) {
                
            $admin = Db::name('SystemUser')->find($this->app->session->get('user.id'));
            if (md5(md5($vo['password']).$admin['salt']) !== $admin['password']) {
                $this->error('登录账号或密码错误，请重新输入!');
            }
            unset($vo['password']);
            
            if ($this->request->action() == 'add') {
                sysoplog('添加提现方式', '管理员添加提现方式');
            } elseif ($this->request->action() == 'edit') {
                sysoplog('修改提现方式', '管理员修改提现方式');
            }
        }
    }
    /**
     * 添加可提现钱包类型
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加可提现钱包类型';
        $this->_form($this->table, 'form');
    }
    /**
     * 编辑可提现钱包
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑可提现钱包';
        $this->_form($this->table, 'form');
    }


    /**
     * 删除提现钱包
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table);
    }
}
