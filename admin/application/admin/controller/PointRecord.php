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
class PointRecord extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcPointRecord';

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
        $this->title = '积分记录列表';
        $map = [];
        $phone = request()->get('r_phone');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['r.phone'] = $aes->encrypt($phone);
        }
        $query = $this->_query($this->table)->alias('i')->equal('i.type#i_type')->field('i.*,r.phone,r.name')
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
        $this->mlist = Db::name($this->table)->select();
        $aes = new Aes();
        foreach($data as &$vo) {
            $vo['phone'] = $aes->decrypt($vo['phone']);
        }
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
        
    }

}
