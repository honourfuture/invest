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
class Certificate extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcCertificate';

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
        $this->title = '身份证列表';
        $map = [];
        $phone = request()->get('u_phone');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['u.phone'] = $aes->encrypt($phone);
        }
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.phone,u.name as u_name');
        $query->join('lc_user u','i.uid=u.id')->like('i.account#i_account,u.username#u_name')->where($map)->order('i.id desc')->page();
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
        $this->mlist = Db::name('LcArticleType')->select();
        $aes = new Aes();
        foreach ($data as &$vo) {
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
        $this->title = '添加文章';
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
        $this->title = '编辑文章';
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
    
    public function pass()
    {
        $this->applyCsrfToken();
        $id = $this->request->post('id');
        
        $info = Db::name('lc_certificate')->where('status',0)->find($id);
        if(!$info){
            $this->success('已经审核过了');
        }
        //修改资料
        Db::name('lc_user')->where('id', $info['uid'])->update([
            'card_front' => $info['card_front'],
            'card_back' => $info['card_back'],
            'name' => $info['name'],
            'idcard' => $info['idcard'],
            'auth' => 1,
            'auth_time' => date('Y-m-d H:i:s', time())
        ]);
        
        $reward = Db::name('LcReward')->where('id', 1)->find()['real_name'];
        
        if ($reward > 0) {
            $user = Db::name('lc_user')->find($info['uid']);
            //记录余额变化
            Db::name('LcFinance')->insert([
                'uid' => $user['id'],
                'money' => $reward,
                'type' => 1,
                'orderid' => 'REALNAME'. date('YmdHis') . rand(1000, 9999) . rand(100, 999),
                'zh_cn' => '实名认证奖励'.$reward,
                'zh_hk' => 'Phần thưởng xác nhận tên thật'.$reward,
                'before' => $user['money'],
                'time' => date('Y-m-d H:i:s', time()),
                'reason_type' => 15
            ]);
            //增加用户余额
            Db::name('lc_user')->where('id', $user['id'])->update(['money' => bcadd($user['money'], $reward, 2)]);
        }
        
        
        // $rsd=Db::name('LcRecharge')->where(['uid'=>$uid,'type'=>'实名奖励'])->find();
        //  if(!$rsd){
                //  $dd = Db::name('LcReward')->where(['id'=>1])->find();
                 
        // $orderid = 'PAY' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
        // $add = array(
        //     'orderid' => $orderid,
        //     'uid' => $uid,
        //     'pid' => 20,
        //     'money' => $dd['real_name'],
        //     'money2' => $dd['real_name'],
        //     'type' => '实名奖励',
        //     'status' => 1,
        //     'time' => date('Y-m-d H:i:s'),
        //     'time2' => '0000-00-00 00:00:00'
        // );
        // setNumber('LcUser', 'asset', $dd['real_name'], 1, "id = $uid");
        //   $re = Db::name('LcRecharge')->insertGetId($add);
        //  }
         
         Db::name('lc_certificate')->where('id', $info['id'])->update(['status' => 1]);
         
            $this->success('操作成功');
    }
    
    public function refuse()
    {
        $this->applyCsrfToken();
        $id = $this->request->post('id');
        $info = Db::name('lc_certificate')->find($id);
        Db::name('lc_certificate')->where('id', $info['id'])->update(['status' => 2]);
        $this->success('操作成功');
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
    }

}
