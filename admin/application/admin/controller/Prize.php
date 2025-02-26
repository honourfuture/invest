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
 * 抽奖管理
 * Class Item
 * @package app\admin\controller
 */
class Prize extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcPrize';

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
        $this->title = '抽奖管理';
        $query = $this->_query($this->table);
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
//
//        foreach ($data as &$vo) {
//            list($vo['pay_type'], $vo['item_class']) = [[], []];
//            foreach ($this->mlist as $class) if ($class['id'] == $vo['class']) $vo['item_class'] = $class;
//        }
    }

    /**
     * 添加商品
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加商品';
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
        $this->title = '编辑项目';
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
            $vo['type'] = isset($vo['type'])?$vo['type']:0;
            $vo['level'] = isset($vo['level'])?$vo['level']:'bronze';
//            $vo['index_type'] = isset($vo['index_type'])?$vo['index_type']:1;
//            $vo['status'] = isset($vo['status'])?$vo['status']:1;
//            if (empty($vo['class']) && $this->request->get('class', '0')) $vo['class'] = $this->request->get('class', '0');
//            $this->class = Db::name("LcItemClass")->order('id asc')->select();
//            $this->class = Data::arr2table($this->class);
        }
//        if (empty($vo['add_time'])) $vo['add_time'] = date("Y-m-d H:i:s");
    }

}
