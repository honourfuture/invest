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
 * 流水记录
 * Class Item
 * @package app\admin\controller
 */
class Finance extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcFinance';

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
        $where = "";
        if($auth['authorize']==1){
            $where = "u.agent = {$auth['id']}";
        }
        $this->title = '流水记录';
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.phone,u.name');
        $map = [];
        $phone = request()->get('u_phone');
        $i_name = request()->get('i_name', '');
        $i_reason = request()->get('i_reason', '');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['u.phone'] = $aes->encrypt($phone);
        }
        // if (!empty($i_name)) {
        //     $map['i.zh_cn'] = ['like', '%'.$i_name.'%'];
        // }
        // $query->join('lc_user u','i.uid=u.id')->equal('i.type#i_type')->equal('u.is_sf#is_sf')->where($where)->like('i.reason#i_reason,u.phone#u_phone')->dateBetween('i.time#i_time')->valueBetween('i.money')->order('i.id desc')->page();
        if ($i_name != '') {
            $map['i.zh_cn'] = ['like', '%'.$i_name.'%'];
        }
        $pn = "";
        if ($i_reason != '') {
            // $map['i.zh_cn'] = ['like', '%'.$i_reason.'%'];
            $pn = " i.zh_cn like '%".$i_reason."%'";
        }
        $i_time = request()->get('i_time', '');
        $cond = '';
        if (!empty($i_time)) {
            $time_arr = explode(' - ', $i_time);
            $start_time = strtotime($time_arr[0]);
            $end_time = strtotime($time_arr[1])+86400;
            $cond = "UNIX_TIMESTAMP(i.time) > $start_time AND UNIX_TIMESTAMP(i.time) < $end_time";
        }
        $i_type = request()->get('i_type', 0);
        if ($i_type) {
            $map['i.type'] = $i_type;
        }
        $trade_type = request()->get('trade_type', 0);
        if ($trade_type) {
            $map['i.trade_type'] = $trade_type;
        }
        $is_sf = request()->get('is_sf', 0);
        $map['u.is_sf'] = $is_sf;
        $this->total_income = Db::name('lc_finance i')->join('lc_user u', 'i.uid = u.id')->where($pn)->where($cond)->where($map)->where('i.type', 1)->sum('i.money');
        $this->total_expend = Db::name('lc_finance i')->join('lc_user u', 'i.uid = u.id')->where($pn)->where($cond)->where($map)->where('i.type', 2)->sum('i.money');
    //   var_dump($map);exit;
        
        $query->join('lc_user u','i.uid=u.id')->equal('i.trade_type#trade_type')->equal('i.type#i_type')->where($map)->equal('u.is_sf#is_sf')->where($where)->like('i.zh_cn#i_name,i.zh_cn#i_reason')->dateBetween('i.time#i_time')->valueBetween('i.money')->order('i.id desc')->page();
        // $query->join('lc_user u','i.uid=u.id')->equal('i.type#i_type,i.trade_type#trade_type,u.is_sf#is_sf')->where($map)->where($where)->like('i.zh_cn#i_reason,u.phone#u_phone')->dateBetween('i.time#i_time')->valueBetween('i.money')->order('i.id desc')->page();
    }
    
    protected function _index_page_filter(&$data)
    {
        
        $aes = new Aes();
        foreach($data as &$vo){
            $vo['phone'] = $aes->decrypt($vo['phone']);
        }
        //总收入
        // $this->total_income = Db::name('lc_finance')->where('type', 1)->sum('money');
        //总支出
        // $this->total_expend = Db::name('lc_finance')->where('type', 2)->sum('money');
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
