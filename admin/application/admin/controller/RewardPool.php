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
 * 奖池管理
 * Class Item
 * @package app\admin\controller
 */
class RewardPool extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcRewardPool';

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
        $this->title = '奖池列表';
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
        $this->mlist = Db::name('LcRewardPool')->select();
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
        $this->title = '添加奖池';
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑奖池
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑奖池';
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
        }
        
        if ($this->request->isGet()) {
            
        }
    }
    
    public function _add_form_result(&$id) {
        
        $data = Db::name('lc_reward_pool')->find($id);
        if (!Db::name('lc_reward_pool_period')->where('pool_id', $id)->find()) {
            Db::name('lc_reward_pool_period')->insert([
                'pool_id' => $data['id'],
                'sn' => 10000,
                'start_time' => date('Y-m-d H:i:s', time()),
                'quota' => $data['quota']
            ]);
        }
    }

}
