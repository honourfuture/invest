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
use library\tools\Data;
use think\Db;

/**
 * 项目管理
 * Class Item
 * @package app\admin\controller
 */
class Item extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcItem';

    /**
     * 项目管理
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
        
        $this->title = '商品管理';
        $query = $this->_query($this->table)->equal('class,index_type')->like('zh_cn');
        $query->order('sort asc,id desc')->page();
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
        $this->mlists = Db::name('LcItemClass')->select();
        $this->mlist = Data::arr2table($this->mlists);
        foreach ($data as &$vo) {
            list($vo['pay_type'], $vo['item_class']) = [[], []];
            foreach ($this->mlist as $class) if ($class['id'] == $vo['class']) $vo['item_class'] = $class;
        }
    }

    /**
     * 添加项目
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
       $this->user_member =  Db::name('LcUserMember')->select();
        $this->title = '添加项目';
        $this->pre_items = Db::name('lc_item')->field('id,zh_cn')->select();
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑项目
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
         $this->user_member =  Db::name('LcUserMember')->select();
        $this->title = '编辑项目';
        $this->pre_items = Db::name('lc_item')->field('id,zh_cn')->where('id', '<>', $this->request->get('id'))->select();
        $this->_form($this->table, 'form');
    }

    /**
     * 删除项目
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->_delete($this->table);
    }

    /**
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        if ($this->request->isGet()) {
            // echo'<pre>';
            // var_dump($vo);
            // var_dump($this->request->input());
            // exit;
            $vo['prize'] = isset($vo['prize'])?$vo['prize']:0;
            $vo['index_type'] = isset($vo['index_type'])?$vo['index_type']:1;
            $vo['status'] = isset($vo['status'])?$vo['status']:1;
            $vo['gift_points'] = isset($vo['gift_points'])?$vo['gift_points']:1;
            $vo['cycle_type'] = isset($vo['cycle_type'])?$vo['cycle_type']:1;
            $vo['show_home'] = isset($vo['show_home'])?$vo['show_home']:0;
            $vo['user_member'] = isset($vo['user_member'])?$vo['user_member']:0;
            $vo['tag_one'] = isset($vo['tag_one'])?$vo['tag_one']:1;
            $vo['tag_two'] = isset($vo['tag_two'])?$vo['tag_two']:1;
            $vo['tag_three'] = isset($vo['tag_three'])?$vo['tag_three']:1;
            $vo['hour'] = isset($vo['hour'])?$vo['hour']:0;
            $vo['is_redpack'] = isset($vo['is_redpack'])?$vo['is_redpack']:0;
            $vo['id'] = isset($vo['id'])?$vo['id']:0;
            $vo['is_rec'] = isset($vo['is_rec'])?$vo['is_rec']:0;
            $vo['is_share'] = isset($vo['is_share'])?$vo['is_share']:0;
            $vo['members'] = isset($vo['members']) ? explode(',', $vo['members']): [];
            $vo['pre_item_id'] = isset($vo['pre_item_id']) ? $vo['pre_item_id'] : 0;
            $vo['add_rate'] = isset($vo['add_rate'])?$vo['add_rate']:0;
            $vo['grow_type'] = isset($vo['grow_type'])?$vo['grow_type']:1;
            $vo['score_type'] = isset($vo['score_type'])?$vo['score_type']:1;
            $vo['sell_time'] = isset($vo['sell_time'])?$vo['sell_time']:'';
            $vo['year'] = isset($vo['year'])?$vo['year']:'';
            
            
            if (empty($vo['class']) && $this->request->get('class', '0')) $vo['class'] = $this->request->get('class', '0');
            $this->class = Db::name("LcItemClass")->order('id asc')->select();
            $this->class = Data::arr2table($this->class);
            $vo['able_buy_num'] = isset($vo['able_buy_num']) ? explode(',', $vo['able_buy_num']): [];

            $this->tag = Db::name("LcItemTag")->order('id asc')->select();
        }
        
        
        if ($this->request->isPost()) {
            $vo['hour'] = (isset($vo['hour'])?$vo['hour']:0) * 24;
            $vo['members'] = isset($vo['members']) ? implode(',', $vo['members']) : '';
            $vo['able_buy_num'] = isset($vo['able_buy_num']) ? implode(',', $vo['able_buy_num']) : '';
            if ($vo['percent'] >= 100) {
                $vo['complete_time'] = date('Y-m-d H:i:s', time());
            } else {
                $vo['complete_time'] = null;
            }
            // var_dump($vo['able_buy_num']);exit;
        }
        
        if (empty($vo['add_time']) && $this->request->isGet()) $vo['add_time'] = date("Y-m-d H:i:s");
    }

}
