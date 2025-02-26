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
 * 还款详情
 * Class Item
 * @package app\admin\controller
 */
class InvestList extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcInvestList';

    /**
     * 还款详情
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
        if (!empty($phone)) {
            $aes = new Aes();
            $map['u.phone'] = $aes->encrypt($phone);
        }
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.phone,u.name');
        $query->join('lc_user u','i.uid=u.id')->where($map)->equal('i.iid#i_iid')->like('i.zh_cn#i_zh_cn')->dateBetween('i.time1#i_time1')->order('i.id desc')->page();
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
        $this->refund = sprintf("%.2f",Db::name('LcInvestList')->where("status = 1 AND pay1 > 0")->sum('pay1'));
        $aes = new Aes();
        foreach($data as &$vo){
            if($vo['status'] == '0') $vo['time2'] = '未返款';
            $vo['phone'] = $aes->decrypt($vo['phone']);
            
            
            //获取项目信息
            $investInfo = Db::name('lc_invest') -> where('id', $vo['iid']) -> find();
            $itemInfo = Db::name('lc_item') -> where('id', $investInfo['pid']) -> find();
            //收益期数
            // $periods = $this ->q($itemInfo['cycle_type'], $investInfo['hour']);
            $periods = 1;
            if ($vo['money2'] > 0) {
                $vo['pay1'] = round($vo['money1']/$periods+$vo['money2'], 3);
                $vo['pay2'] = round($vo['money1']/$periods+$vo['money2'], 3);
                
            } else {
                $vo['pay1'] = round($vo['pay1']/$periods, 3);
                $vo['pay2'] = round($vo['pay2']/$periods, 3);
            }
        }
    }
    
    public function q($type, $hour){
        $q = 0;
        switch ($type) {
            case 1:
                $q = $hour;
                break;
            case 2:
                $q = ($hour / 24) < 1 ? 1 : ($hour / 24);
                break;
            case 3:
                $q = ($hour / 24 /7 ) < 1 ? 1 : ($hour / 24 /7);
                break;
            case 4:
                $q = ($hour / 24 / 30) < 1 ? 1 : ($hour / 24 / 30);
                break;
            case 5:
                $q = 1;
                break;
            default:
                $q = 0;
                break;
        }
        return $q;
    }
}
