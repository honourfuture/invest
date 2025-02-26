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
 * 支付方式管理
 * Class Item
 * @package app\admin\controller
 */
class Payment extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcPayment';

    /**
     * 支付方式列表
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
        $this->title = '支付方式列表';
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
        }
        if ($this->request->isPost()) {
            
            $admin = Db::name('SystemUser')->find($this->app->session->get('user.id'));
            if (md5(md5($vo['password']).$admin['salt']) !== $admin['password']) {
                $this->error('登录账号或密码错误，请重新输入!');
            }
            unset($vo['password']);
            if ($this->request->action() == 'add_crypto' || $this->request->action() == 'add_bank') {
                sysoplog('添加支付方式', '管理员添加支付方式');
            } elseif ($this->request->action() == 'edit_crypto' || $this->request->action() == 'edit_bank') {
                $info = Db::name('lc_payment')->find($vo['id']);
                $msg = '';
                if ($this->request->action() == 'edit_crypto') { //加密货币
                    $msg = '';
                    if ($info['crypto'] != $vo['crypto']) {
                        $msg = '加密货币名称由 '.$info['crypto'].' 修改为'.' '.$vo['crypto'].' ';
                    }
                    if ($info['crypto_link'] != $vo['crypto_link']) {
                       $msg = '加密货币地址由 '.$info['crypto_link'].' 修改为'.' '.$vo['crypto_link'].' ';
                    }
                    if ($info['rate'] != $vo['rate']) {
                       $msg = '汇率（货币->美USDT）由 '.$info['rate'].' 修改为'.' '.$vo['rate'].' ';
                    }
                    if ($info['logo'] != $vo['logo']) {
                       $msg = '支付方式LOGO由 '.$info['logo'].' 修改为'.' '.$vo['logo'].' ';
                    }
                    if ($info['logo'] != $vo['logo']) {
                       $msg = '加密货币二维码由 '.$info['crypto_qrcode'].' 修改为'.' '.$vo['crypto_qrcode'];
                    }
                    sysoplog('修改加密货币', $msg);
                } elseif($this->request->action() == 'edit_bank') { //银行卡
                    $msg = '';
                    if ($info['rate'] != $vo['rate']) {
                       $msg = '汇率（货币->美USDT）由 '.$info['rate'].' 修改为'.' '.$vo['rate'].' ';
                    }
                    if ($info['bank'] != $vo['bank']) {
                       $msg = '银行卡名称由 '.$info['bank'].' 修改为'.' '.$vo['bank'].' ';
                    }
                    if ($info['bank_name'] != $vo['bank_name']) {
                       $msg = '持卡人姓名由 '.$info['bank_name'].' 修改为'.' '.$vo['bank_name'].' ';
                    }
                    if ($info['bank_account'] != $vo['bank_account']) {
                       $msg = '卡号由 '.$info['bank_account'].' 修改为'.' '.$vo['bank_account'].' ';
                    }
                    if ($info['logo'] != $vo['logo']) {
                       $msg = '支付方式LOGO由 '.$info['logo'].' 修改为'.' '.$vo['logo'];
                    }
                    sysoplog('修改银行卡', $msg);
                }
                
            }
            
        }
    }
    /**
     * 添加加密货币
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add_crypto()
    {
        $this->title = '添加加密货币';
        $this->_form($this->table, 'form_crypto');
    }
    /**
     * 编辑加密货币
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_crypto()
    {
        $this->title = '编辑加密货币';
        $this->_form($this->table, 'form_crypto');
    }
    /**
     * 添加支付宝
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add_alipay()
    {
        $this->title = '添加支付宝';
        $this->_form($this->table, 'form_alipay');
    }
    /**
     * 编辑支付宝
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_alipay()
    {
        $this->title = '编辑支付宝';
        $this->_form($this->table, 'form_alipay');
    }
    /**
     * 添加微信扫码
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add_wx()
    {
        $this->title = '添加微信扫码';
        $this->_form($this->table, 'form_wx');
    }
    /**
     * 编辑微信扫码
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_wx()
    {
        $this->title = '编辑微信扫码';
        $this->_form($this->table, 'form_wx');
    }
    /**
     * 添加银行卡
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add_bank()
    {
        $this->title = '添加银行卡';
        $this->_form($this->table, 'form_bank');
    }
    /**
     * 编辑银行卡
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_bank()
    {
        $this->title = '编辑银行卡';
        $this->_form($this->table, 'form_bank');
    }


    /**
     * 删除支付方式
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
