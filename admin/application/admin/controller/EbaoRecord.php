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
 * 途游宝流水
 * Class Item
 * @package app\admin\controller
 */
class EbaoRecord extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcEbaoRecord';

    /**
     * 流水记录
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
        $auth = $this->app->session->get('user');
        $map = [];
        $phone = request()->get('u_phone');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['u.phone'] = $aes->encrypt($phone);
        }
          $query = $this->_query($this->table)->alias('i')->field('i.*,u.phone,u.name as uname');
        //   $query->join('lc_ebao_product p', 'p.id = i.product_id');
        $query->join('lc_user u','i.uid=u.id','left')->where($map)->equal('p.title#title')->order('i.id desc')->page();
    }
    
    protected function _index_page_filter(&$data)
    {
        $aes = new Aes();
        foreach($data as &$vo) {
            $vo['phone'] = $aes->decrypt($vo['phone']);
        }
    }
    
  
    /**
     * 删除记录
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
