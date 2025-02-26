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
 * 期数管理
 * Class Item
 * @package app\admin\controller
 */
class RewardPoolPeriod extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcRewardPoolPeriod';

    /**
     * 期数列表
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
        $this->title = '期数列表';
        $pool_id = $this->request->get('pool_id');
        $query = $this->_query($this->table)->alias('i');
        $query->join('lc_reward_pool p','i.pool_id=p.id')->order('i.id desc')->where('pool_id', $pool_id)->equal('i.sn#sn')->field('i.*,p.name')->page();
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
        $this->mlist = Db::name('LcRewardPoolPeriod')->select();
        foreach ($data as &$vo)
        {
            $vo['cur_quota'] = Db::name('lc_reward_pool_log')->where('period_id', $vo['id'])->sum('score');
            $vo['rate'] = bcdiv($vo['cur_quota']*100, $vo['quota'], 2);
        }
    }

    /**
     * 添加期数
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加期数';
        $this->items =  Db::name('LcItem')->select();
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑期数
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑期数';
        $this->items =  Db::name('LcItem')->select();
        $this->_form($this->table, 'form');
    }

    /**
     * 删除期数
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
        }
        
        if ($this->request->isGet()) {
        }
    }

}
