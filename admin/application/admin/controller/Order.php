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
 * 订单管理
 * Class Item
 * @package app\admin\controller
 */
class Order extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcOrder';

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
        $this->title = '订单管理';
        $map = [];
        $phone = request()->get('r_phone');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['u.phone'] = $aes->encrypt($phone);
        }
          $query = $this->_query($this->table)->alias('i')->field('i.*,u.phone,u.name as uname');
        $query->join('lc_user u','i.uid=u.id')->where($map)->equal('i.goods_name#goods_name')->order('i.id desc')->page();
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
        $aes = new Aes();
        foreach ($data as &$vo) {
            $vo['phone'] = $aes->decrypt($vo['phone']);
        }
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
            $vo['prize'] = isset($vo['prize'])?$vo['prize']:0;
            $vo['index_type'] = isset($vo['index_type'])?$vo['index_type']:1;
            $vo['status'] = isset($vo['status'])?$vo['status']:1;
            if (empty($vo['class']) && $this->request->get('class', '0')) $vo['class'] = $this->request->get('class', '0');
            $this->class = Db::name("LcItemClass")->order('id asc')->select();
            $this->class = Data::arr2table($this->class);
        }
        if (empty($vo['add_time'])) $vo['add_time'] = date("Y-m-d H:i:s");
    }



    /**
     * 修改用户密码
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function ship()
    {
        $this->applyCsrfToken();
        if ($this->request->isGet()) {
            $this->verify = false;
            $this->_form($this->table, 'ship');
        } else {
            $post = $this->request->post();

            if (Data::save($this->table, ['id' => $post['id'], 'shipment_number' => $post['shipment_number'], 'status' => '3'], 'id')) {
                $this->success('发货成功', '');
            } else {
                $this->error('修改失败！');
            }
        }
    }
      public function jujie(){
        $post = $this->request->post();
        if ($post){
       // 开始返回积分
      
        // 创建积分返回明细
        
        $order = Db::name($this->table)->where(['id' =>  $post['id']])->find();
        $pointRecord = array(
            'uid' => $order['uid'],
            'num' => $order["point_num"],
            'type' => 1,
            'zh_cn' => '拒绝兑换',
            'zh_hk' => '拒绝兑换',
            'en_us' => '拒绝兑换',
            'th_th' => '拒绝兑换',
            'vi_vn' => '拒绝兑换',
            'ja_jp' => '拒绝兑换',
            'ko_kr' => '拒绝兑换',
            'ms_my' => '拒绝兑换',
            'time' => date('Y-m-d H:i:s'),
            'before' =>  Db::name("LcUser")->where(['id' => $order['uid']])->value('point_num')
        );
          setNumber('LcUser', 'point_num', $order["point_num"], 1, "id = ". $order['uid']);
        $int = Db::name('LcPointRecord')->insert($pointRecord);
            if (Data::save($this->table, ['id' => $post['id'], 'status' => '5'], 'id')) {
                $this->success('拒绝成功', '');
            } else {
                $this->error('修改失败！');
            }
        }
    }
}
