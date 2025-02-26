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
 * 抽奖记录管理
 * Class Item
 * @package app\admin\controller
 */
class RedpackPrizeLog extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcRedpackPrizeLog';

    /**
     * 抽奖记录列表
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
        $this->title = '抽奖记录列表';
        $map = [];
        $phone = request()->get('u_phone');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['u.phone'] = $aes->encrypt($phone);
        }
        $type = request()->get('i_type');
        if ($type != '-1') {
            $map['i.type'] = $type;
        }
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.phone,u.name as u_name');
        $query->join('lc_user u','i.uid=u.id')->where($map)->order('id desc')->page();
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
        $this->mlist = Db::name('LcRedpackPrizeLog')->select();
        $aes = new Aes();
        foreach($data as &$vo) {
            $vo['phone'] = $aes->decrypt($vo['phone']);
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
