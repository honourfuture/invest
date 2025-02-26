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
use library\service\AdminService;
use think\Db;

/**
 * 系统用户管理
 * Class User
 * @package app\admin\controller
 */
class User extends Controller
{

    /**
     * 指定当前数据表
     * @var string
     */
    public $table = 'SystemUser';
    protected $info = 'LcInfo';



    public function qrcode()
    {
        require  '../extend/GoogleAuthenticator/GoogleAuthenticator.php';
        $auth = $this->app->session->get('user');
        $authenticator = new \GoogleAuthenticator;
        //$secret  = Db::table('system_user')->where('id', $auth['id'])->value('googlecode');
        //if(empty($secret)) {
        $secret = $authenticator->createSecret(32);
        //}
        $qrdata = $authenticator->getQRCodeGoogleUrl('动态口令认证', $secret, 'Kirin Technology');
        require  '../extend/GoogleAuthenticator/phpqrcode.php';
        $qr = new \QRcode;
        ob_start();
        $qr::png($qrdata, false, 'L', 7);
        $image = base64_encode(ob_get_contents());
        ob_end_clean();
        $this->success( ['image' => 'data:image/png;base64,' . $image, 'secret' => $secret]);
    }
    
    public function opensecret()
    {
        $post = $this->request->post();
        if(!isset($post['secret'])) {
            $this->error('数据异常，请刷新页面！');
        }
        $auth = $this->app->session->get('user');
        Db::table('system_user')->where('id', $auth['id'])->update(['googlecode' => $post['secret']]);
        $this->success('开启成功');
    }
    
    public function closesecret()
    {
        $post = $this->request->post();
        $auth = $this->app->session->get('user');
        Db::table('system_user')->where('id', $auth['id'])->update(['googlecode' => null]);
        $this->success('关闭成功');
    }
    

    /**
     * 系统用户管理
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
        //echo request() -> ip();exit;
        $this->title = '系统用户管理';
        $query = $this->_query($this->table)->like('username,name,phone,mail')->equal('status');
        $query->dateBetween('login_at,create_at')->where(['is_deleted' => '0'])->order('id desc')->page();
    }

    /**
     * 添加系统用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add()
    {
        $this->applyCsrfToken();  
  
        $this->_form($this->table, 'form');
    }

    /**
     * 编辑系统用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->applyCsrfToken();
        $this->_form($this->table, 'form');
    }

    /**
     * 修改用户密码
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function pass()
    {
        $this->applyCsrfToken();
        if ($this->request->isGet()) {
            $this->verify = false;
            $this->_form($this->table, 'pass');
        } else {
            $post = $this->request->post();
            if ($post['password'] !== $post['repassword']) {
                $this->error('两次输入的密码不一致！');
            }
            $admin = Db::name($this->table)->find($post['id']);
            if (empty($admin['salt'])) {
                $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $admin['salt'] = substr(str_shuffle(str_repeat($pool, ceil(6 / strlen($pool)))), 0, 6);
            }
            if (Data::save($this->table, ['id' => $post['id'],'salt' => $admin['salt'], 'password' => md5(md5($post['password']).$admin['salt'])], 'id')) {
            // if (Data::save($this->table, ['id' => $post['id'], 'password' => md5($post['password'])], 'id')) {
                $this->success('密码修改成功，下次请使用新密码登录！', '');
            } else {
                $this->error('密码修改失败，请稍候再试！');
            }
        }
    }

    /**
     * 表单数据处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _form_filter(&$data)
    {
        if ($this->request->isPost()) {
            // 用户权限处理
            // var_dump($data);die;
            $data['authorize'] = (isset($data['authorize']) && is_array($data['authorize'])) ? join(',', $data['authorize']) : '';
            // 用户账号重复检查
            if (isset($data['id'])) unset($data['username']);
            elseif (Db::name($this->table)->where(['username' => $data['username'], 'is_deleted' => '0'])->count() > 0) {
                $this->error("账号{$data['username']}已经存在，请使用其它账号！");
            }
        } else {
            $data['authorize'] = explode(',', isset($data['authorize']) ? $data['authorize'] : '');
            $this->authorizes = Db::name('SystemAuth')->where(['status' => '1'])->order('sort desc,id desc')->select();
        }
    }

    /**
     * 禁用系统用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (in_array('10000', explode(',', $this->request->post('id')))) {
            $this->error('系统超级账号禁止操作！');
        }
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '0']);
    }

    /**
     * 启用系统用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['status' => '1']);
    }

    /**
     * 删除系统用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        if (in_array('10000', explode(',', $this->request->post('id')))) {
            $this->error('系统超级账号禁止删除！');
        }
        $this->applyCsrfToken();
        $this->_delete($this->table);
    }
    
     /**
     * 生成代理链接
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function setAgentLink()
    {
        $this->applyCsrfToken();
        $info = Db::name("LcInfo")->find(1);
        $agent_link = $info["domain"]."/#/?agent=".$this->request->post('id');
        $this->_save($this->table, ['agent_link' => $agent_link]);
    }

}
