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
 * 文章管理
 * Class Item
 * @package app\admin\controller
 */
class BlindBuyLog extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcBlindBuyLog';

    /**
     * 文章列表
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
        $this->title = '盲盒购买记录';
        $map = [];
        $phone = request()->get('r_phone');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['r.phone'] = $aes->encrypt($phone);
        }
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.name,r.phone');
        $query->join('lc_blind u','i.blind_id=u.id')
        ->join('lc_user r', 'i.uid = r.id')->where($map)->order('i.id desc')->page();
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
        $this->mlist = Db::name('LcBlindBuyLog')->select();
        $aes = new Aes();
        foreach($data as &$vo) {
            $vo['phone'] = $aes->decrypt($vo['phone']);
        }
    }

    /**
     * 添加文章
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->title = '添加盲盒';
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑文章
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑盲盒';
        $this->_form($this->table, 'form');
    }

    /**
     * 删除文章
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
        // if ($this->request->isGet()) {
        //     $this->class = Db::name("LcArticleType")->order('id asc')->select();
        //     if(!isset($vo['show'])) $vo['show'] = '1';
        // }
        // if (empty($vo['time'])) $vo['time'] = date("Y-m-d H:i:s");
        if ($this->request->isPost()) {
            $vo['create_time'] = time();
        }
    }

}
