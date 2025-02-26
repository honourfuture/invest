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
class FigureCollectLog extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcFigureCollectLog';

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
        $this->title = '数字藏品记录列表';
        $map = [];
        $phone = request()->get('r_phone');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['r.phone'] = $aes->encrypt($phone);
        }
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.name,u.image,r.phone');
        $query->join('lc_figure_collect u','i.figure_collect_id=u.id')
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
        $this->mlist = Db::name('LcFigureCollectLog')->select();
        $aes = new Aes();
        foreach($data as &$vo) {
            $vo['phone'] = $aes->decrypt($vo['phone']);
        }
    }
    
    public function pass()
    {
        $this->applyCsrfToken();
        $id = $this->request->post('id');
        $info = Db::name('lc_figure_collect_log')->find($id);
        
        //计算卖出价格
        $sell_price = bcdiv($info['sell_rate']*$info['money']*$info['lock_days'], 100, 2);
        $total_price = bcadd($sell_price, $info['money'], 2);
        
        $userinfo = Db::name('lc_user')->find($info['uid']);
        //增加用户余额
        Db::name('lc_user')->where('id', $info['uid'])->update(['money' => bcadd($userinfo['money'], $total_price, 2)]);
        //资金变动记录
        Db::name('lc_finance')->insert([
            'uid' => $info['uid'],
            'money' => $total_price,
            'type' => 1,
            'zh_cn' => '数字藏品卖出获得收益 '.$total_price,
            'before' => $userinfo['money'],
            'time' => date('Y-m-d H:i:s', time())
        ]);
        Db::name('lc_figure_collect_log')->where('id', $info['id'])->update(['status' => 2, 'sell_time' => time()]);
        $this->success('操作成功');
    }
    
    public function refuse()
    {
        $this->applyCsrfToken();
        $id = $this->request->post('id');
        Db::name('lc_figure_collect_log')->where('id', $id)->update(['status' => 0]);
        $this->success('操作成功');
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
        $this->title = '编辑藏品';
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
        
        if ($this->request->isPost() && $this->request->action() == 'edit') {
            if ($vo['sell_rate'] <= 0 || $vo['sell_rate'] >= 100) {
                $this->error('卖出利率异常');
            }
            
            $info = Db::name('lc_figure_collect_log')->find($vo['id']);
        
            //计算卖出价格
            $sell_price = bcdiv($vo['sell_rate']*$info['money']*$info['lock_days'], 100, 2);
            $total_price = bcadd($sell_price, $info['money'], 2);
            
            $userinfo = Db::name('lc_user')->find($info['uid']);
            //增加用户余额
            Db::name('lc_user')->where('id', $info['uid'])->update(['money' => bcadd($userinfo['money'], $total_price, 2)]);
            //资金变动记录
            Db::name('lc_finance')->insert([
                'uid' => $info['uid'],
                'money' => $total_price,
                'type' => 1,
                'zh_cn' => '数字藏品卖出获得收益 '.$total_price,
                'before' => $userinfo['money'],
                'time' => date('Y-m-d H:i:s', time())
            ]);
            Db::name('lc_figure_collect_log')->where('id', $info['id'])->update(['status' => 2, 'sell_time' => time(), 'sell_rate' => $vo['sell_rate'], 'expect_profit' => $sell_price]);
            $this->success('操作成功');
        }
        
    }

}
