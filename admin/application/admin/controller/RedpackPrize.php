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
 * 红包雨奖品管理
 * Class Item
 * @package app\admin\controller
 */
class RedpackPrize extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcRedpackPrize';

    /**
     * 奖池列表
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
        $this->title = '奖品列表';
        $query = $this->_query($this->table)->alias('r')->like('name');
        $query->join('lc_coupon c','r.coupon_id=c.id', 'left');
        $query->field('r.*,c.name as coupon_name')->order('id desc')->equal('name#name')->page();
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
        $this->mlist = Db::name('LcRedpackPrize')->select();
        foreach ($data as &$item) {
            $able_member = Db::name('lc_user_member')->whereIn('id', explode(',', $item['members']))->column('name');
            $item['able_member'] = implode(',', $able_member);
        }
    }

    /**
     * 添加奖品
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加奖品';
        $this->user_member =  Db::name('LcUserMember')->select();
        $this->coupon =  Db::name('LcCoupon')->where('status', 1)->select();
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑奖品
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑奖品';
        $this->user_member =  Db::name('LcUserMember')->select();
        $this->coupon =  Db::name('LcCoupon')->where('status', 1)->select();
        $this->_form($this->table, 'form');
    }

    /**
     * 删除奖品
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
            $vo['members'] = isset($vo['members']) ? implode(',', $vo['members']) : '';
        }
        
        if ($this->request->isGet()) {
            $vo['type'] = isset($vo['type'])?$vo['type']:0;
            $vo['coupon_id'] = isset($vo['coupon_id'])?$vo['coupon_id']:0;
            $vo['members'] = isset($vo['members']) ? explode(',', $vo['members']): [];
            $vo['status'] = isset($vo['status'])?$vo['status']:1;
        }
    }

}
