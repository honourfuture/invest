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
use think\facade\Session;

/**
 * 银行卡管理
 * Class Item
 * @package app\admin\controller
 */
class Bank extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcBank';

    /**
     * 银行卡列表
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
        $this->title = '银行卡列表';
        $u_phone = "not null";
        if(!empty($_GET['u_phone'])){
            $aes = new Aes();
            $u_phone =  $aes->encrypt($_GET['u_phone']);
        }
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.phone,u.name as u_name');
        $query->join('lc_user u','i.uid=u.id')->like('i.account#i_account,u.name#u_name')
        ->where('u.phone', $u_phone)
        ->order('i.id desc')->page();
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
        foreach ($data as &$vo)
        {
            $vo['phone'] = $aes->decrypt($vo['phone']);
        }
    }
    
    /**
     * 添加银行卡
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->_form($this->table, 'form');
    }
    
    /**
     * 编辑银行卡
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑银行卡';
        $this->_formBank($this->table, 'form');
    }
    
    /**
     * 表单数据处理
     * @param array $vo
     * @throws \ReflectionException
     */
    protected function _form_filter(&$vo){
        $aes = new Aes();
        if ($this->request->isPost()&&$vo['phone']) {
            $admin = Db::name('SystemUser')->find($this->app->session->get('user.id'));
            
            if (md5(md5($vo['password']).$admin['salt']) !== $admin['password']) {
                $this->error('登录账号或密码错误，请重新输入!');
            }
            unset($vo['password']);
            $uid = Db::name("LcUser")->where(['phone'=>$aes->encrypt($vo['phone'])])->value('id');
            if(!$uid) $this->error("暂无此用户");
            $vo['uid'] = $uid;
            
            $log = [
                'account' => "钱包地址",
                'area' => "钱包地址/银行卡号",
                'wid' => '银行卡类型',
                'name' => '姓名',
                'bank_type' => '开户行'
            ];
            $param = $this -> request -> post();
            if ($param['wid'] == '10') {
                $vo['bank'] = '银行卡';
                $vo['type'] = 4;
            } elseif ($param['wid'] == '11') {
                $vo['bank'] = 'USDT(TRC-20)';
                $vo['type'] = 1;
            }
            if (isset($param['id'])) {
                //获取用户原数据
                $oldData = Db::name('LcBank') -> where('id', $param['id']) -> find();
                $logList = [];
                // var_dump($oldData);
                // var_dump($param);
                foreach ($param as $key => $item){
                    if(array_key_exists($key, $oldData) && $oldData[$key] != $param[$key]){
                        // var_dump($item);exit;
                        sysCheckLog('修改用户钱包信息', '管理员【'.  Session::get('user.username') . '】修改了用户【' . $vo['phone'] . '】：'  . $log[$key] . '为' . $param[$key] . '，原始值为' . $oldData[$key]);
                        //$logList[] = '管理员账号：' .  Session::get('user.username') . ' -> 修改了' . $log[$key];
                    }
                }
            }
            //获取用户原数据
            // $oldData = Db::name('LcBank') -> where('id', $param['id']) -> find();
            // $logList = [];
            // foreach ($param as $key => $item){
            //     if(array_key_exists($key, $oldData) && $oldData[$key] != $param[$key]){
            //         sysCheckLog('修改用户钱包信息', '管理员【'.  Session::get('user.username') . '】修改了用户【' . $vo['phone'] . '】：'  . $log[$key] . '为' . $param[$key] . '，原始值为' . $oldData[$key]);
            //         //$logList[] = '管理员账号：' .  Session::get('user.username') . ' -> 修改了' . $log[$key];
            //     }
            // }
            
            // sysoplog('修改用户钱包信息', '管理员修改用户钱包信息');
        }
        if($this->request->isGet()&&$vo){
            $vo['phone'] = Db::name("LcUser")->where(['id'=>$vo['uid']])->value('phone');
            $vo['phone'] = $aes->decrypt($vo['phone']);
        }
            if(!isset($vo['wid'])) $vo['wid'] = '10';
    }

    /**
     * 删除银行卡
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
