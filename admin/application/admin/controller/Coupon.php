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
 * 代金券管理
 * Class Item
 * @package app\admin\controller
 */
class Coupon extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcCoupon';

    /**
     * 代金券列表
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
        $this->title = '代金券列表';
        $query = $this->_query($this->table)->like('name');
        $query->order('id desc')->equal('name#name')->page();
    }

    /**
     * 数据列表处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function _index_page_filter(&$data)
    {
        $this->mlist = Db::name('LcCoupon')->select();
    }

    /**
     * 添加代金券
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加代金券';
         $this->user_member =  Db::name('LcUserMember')->select();
        $this->items =  Db::name('LcItem')->select();
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑代金券
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑代金券';
         $this->user_member =  Db::name('LcUserMember')->select();
        $this->items =  Db::name('LcItem')->select();
        $this->_form($this->table, 'form');
    }

    /**
     * 删除代金券
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        $this->_delete($this->table);
    }
    
    
    /**
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        if ($this->request->isPost()) {
            $vo['createtime'] = date('Y-m-d H:i:s',time());
            $vo['item_ids'] = isset($vo['members']) ? implode(',', $vo['item_ids']) : '';
            $vo['members'] = isset($vo['members']) ? implode(',', $vo['members']) : '';
        }
        
        if ($this->request->isGet()) {
            $vo['item_ids'] = isset($vo['item_ids']) ? explode(',', $vo['item_ids']): [];
            $vo['status'] = isset($vo['status'])?$vo['status']:1;
            $vo['is_pay'] = isset($vo['is_pay'])?$vo['is_pay']:0;
            $vo['members'] = isset($vo['members']) ? explode(',', $vo['members']): [];
        }
    }

}
