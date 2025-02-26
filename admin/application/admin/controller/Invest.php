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
 * 已投项目管理
 * Class Item
 * @package app\admin\controller
 */
class Invest extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcInvest';

    /**
     * 已投项目管理
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
       
        $this->title = '已投项目管理';
        $map = [];
        $phone = request()->get('u_phone');
        $is_sf = request()->get('is_sf', 0);
        $i_time = request()->get('i_time', '');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['u.phone'] = $aes->encrypt($phone);
        }
        $map['u.is_sf'] = $is_sf;
        $cond = '';
        if (!empty($i_time)) {
            $time_arr = explode(' - ', $i_time);
            $start_time = strtotime($time_arr[0]);
            $end_time = strtotime($time_arr[1])+86400;
            $cond = "UNIX_TIMESTAMP(i.time) > $start_time AND UNIX_TIMESTAMP(i.time) < $end_time";
        }
        $this->profit = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')
            ->join('lc_invest i', 'l.iid = i.id')
            ->where($map)->where('l.status', 1)->where($cond)->sum('money1');
        $this->un_profit = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')
            ->join('lc_invest i', 'l.iid = i.id')
            ->where($map)->where('l.status', 0)->where($cond)->sum('money1');
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.phone,u.name');
        $query->join('lc_user u','i.uid=u.id')->where($map)->like('i.zh_cn#i_zh_cn')->equal('u.is_sf#is_sf')->dateBetween('i.time#i_time')->order('i.id desc')->page();
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
        // $this->profit = sprintf("%.2f",Db::name('LcInvestList')->where("status = 1 AND pay1 > 0")->sum('pay1'));
        // $this->un_profit = sprintf("%.2f",Db::name('LcInvestList')->where("status = 0 AND pay1 > 0")->sum('pay1'));
        
        $aes = new Aes();
        foreach($data as &$vo) {
            $vo['phone'] = $aes->decrypt($vo['phone']);
        }
        
    }
    
    
    

    /**
     * 启用用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function open()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['repair_sign' => '1']);
    }
    
    

    /**
     * 启用用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function close()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['repair_sign' => '0']);
    }
}
