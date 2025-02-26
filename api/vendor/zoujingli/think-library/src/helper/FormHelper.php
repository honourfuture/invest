<?php

// +----------------------------------------------------------------------
// | Library for ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2020  天美网络 [   ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 仓库地址 ：https://gitee.com/zoujingli/ThinkLibrary
// | github 仓库地址 ：https://github.com/zoujingli/ThinkLibrary
// +----------------------------------------------------------------------

namespace library\helper;

use library\Helper;
use think\Db;
use think\db\Query;

/**
 * 表单视图管理器
 * Class FormHelper
 * @package library\helper
 */
class FormHelper extends Helper
{
    /**
     * 表单额外更新条件
     * @var array
     */
    protected $where;

    /**
     * 数据对象主键名称
     * @var string
     */
    protected $field;

    /**
     * 数据对象主键值
     * @var string
     */
    protected $value;

    /**
     * 模板数据
     * @var array
     */
    protected $data;

    /**
     * 模板名称
     * @var string
     */
    protected $template;


    public function init($dbQuery, $template = '', $field = '', $where = [], $data = [])
    {
        $this->query = $this->buildQuery($dbQuery);
        list($this->template, $this->where, $this->data) = [$template, $where, $data];
        $this->field = empty($field) ? ($this->query->getPk() ? $this->query->getPk() : 'id') : $field;;
        $this->value = input($this->field, isset($data[$this->field]) ? $data[$this->field] : null);
        // GET请求, 获取数据并显示表单页面
        if ($this->app->request->isGet()) {
            if ($this->value !== null) {
                $where = [$this->field => $this->value];
                $data = (array)$this->query->where($where)->where($this->where)->find();
            }
            $data = array_merge($data, $this->data);
            if (false !== $this->controller->callback('_form_filter', $data)) {
                return $this->controller->fetch($this->template, ['vo' => $data]);
            } else {
                return $data;
            }
        }
        // POST请求, 数据自动存库处理
        if ($this->app->request->isPost()) {

//   $abc = $this->app->request->post();
            $data = array_merge($this->app->request->post(), $this->data);
            
// var_dump($abc);die();
             if(!empty($data['id'])){
                  $id = $data['id'];
            $dd =Db::name('LcUser')->where("id",$id)->find(); 
             }
          
            $string = $this->app->session->get('user.username').'修改用户信息';
            $post = $this->app->request->post();
//            var_dump($_POST['money']);
//            var_dump($dd['money']);
//            exit;
//            if($post['money']!=$dd['money']){
//                $string+='【资产从'.$dd['money'].'修改成'.$data['money'].'】';
//            }

//            if($post['money']!=$dd['money']){
//                $string+='【资产从'.$dd['money'].'修改成'.$data['money'].'】';
//            }
// var_dump($this->field);die;
//            sysCheckLog('用户模块', $string);
            if (false !== $this->controller->callback('_form_filter', $data, $this->where)) {
                $result = data_save($this->query, $data, $this->field, $this->where);
                if (false !== $this->controller->callback('_form_result', $result, $data)) {
                    if ($result !== false) {
                        $this->controller->success(lang('think_library_form_success'), '');
                    } else {
                        $this->controller->error(lang('think_library_form_error'));
                    }
                }
                return $result;
            }
        }
    }

    /**
     * 逻辑器初始化
     * @param string|Query $dbQuery
     * @param string $template 模板名称
     * @param string $field 指定数据主键
     * @param array $where 额外更新条件
     * @param array $data 表单扩展数据
     * @return array|mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function initBank($dbQuery, $template = '', $field = '', $where = [], $data = [])
    {
        $this->query = $this->buildQuery($dbQuery);
        list($this->template, $this->where, $this->data) = [$template, $where, $data];
        $this->field = empty($field) ? ($this->query->getPk() ? $this->query->getPk() : 'id') : $field;;
        $this->value = input($this->field, isset($data[$this->field]) ? $data[$this->field] : null);
        // GET请求, 获取数据并显示表单页面
        if ($this->app->request->isGet()) {
            if ($this->value !== null) {
                $where = [$this->field => $this->value];
                $data = (array)$this->query->where($where)->where($this->where)->find();
            }
            $data = array_merge($data, $this->data);
            if (false !== $this->controller->callback('_form_filter', $data)) {
                return $this->controller->fetch($this->template, ['vo' => $data]);
            } else {
                return $data;
            }
        }
        // POST请求, 数据自动存库处理
        if ($this->app->request->isPost()) {

            $post = $this->app->request->post();
            $data = array_merge($this->app->request->post(), $this->data);
            $post = $this->app->request->post();
            $id = $data['id'];
            $dd =Db::name('LcUser')->where("phone",$post['phone'])->find();
            $user =Db::name('LcBank')->where(["uid"=>$dd['id'],'type'=>4])->find();

            $string = $this->app->session->get('user.username').'修改用户'.$post['phone'];

            if($user['account']!=$post['account']){
                $old = $post['account']?$post['account']:'无';
                $string .='的银行卡，从'.$old.'到'.$user['account'];
            }
            sysCheckLog('修改银行卡', $string);


            if (false !== $this->controller->callback('_form_filter', $data, $this->where)) {
                $result = data_save($this->query, $data, $this->field, $this->where);
                if (false !== $this->controller->callback('_form_result', $result, $data)) {
                    if ($result !== false) {
                        $this->controller->success(lang('think_library_form_success'), '');
                    } else {
                        $this->controller->error(lang('think_library_form_error'));
                    }
                }
                return $result;
            }
        }
    }



    public function initUser($dbQuery, $template = '', $field = '', $where = [], $data = [])
    {
        $this->query = $this->buildQuery($dbQuery);
        list($this->template, $this->where, $this->data) = [$template, $where, $data];
        $this->field = empty($field) ? ($this->query->getPk() ? $this->query->getPk() : 'id') : $field;;
        $this->value = input($this->field, isset($data[$this->field]) ? $data[$this->field] : null);
        // GET请求, 获取数据并显示表单页面
        if ($this->app->request->isGet()) {
            if ($this->value !== null) {
                $where = [$this->field => $this->value];
                $data = (array)$this->query->where($where)->where($this->where)->find();
            }
            $data = array_merge($data, $this->data);
            if (false !== $this->controller->callback('_form_filter', $data)) {
                return $this->controller->fetch($this->template, ['vo' => $data]);
            } else {
                return $data;
            }
        }
        // POST请求, 数据自动存库处理
        if ($this->app->request->isPost()) {
            $data = array_merge($this->app->request->post(), $this->data);
//            sysCheckLog('用户模块', '添加用户信息');
            if (false !== $this->controller->callback('_form_filter', $data, $this->where)) {

                $mobile = $data['phone'];
                $dd =Db::name('LcUser')->where("phone",$mobile)->find();
                if(!empty($dd)){
                    $this->controller->error("账号（手机号）已存在，请重新输入");
                }
                $result = data_save($this->query, $data, $this->field, $this->where);

                $invite = $this->getInviteCode($result);
                Db::name('LcUser')->where("id",$result)->update(['invite'=>$invite]);
                if (false !== $this->controller->callback('_form_result', $result, $data)) {
                    if ($result !== false) {
                        $this->controller->success(lang('think_library_form_success'), '');
                    } else {
                        $this->controller->error(lang('think_library_form_error'));
                    }
                }
                return $result;
            }
        }
    }

    private function getInviteCode($id)
    {
        $items = [
            "0", "1", "2", "3", "4", "5", "6", "7", "8", "9",
            "a", "b", "c", "d",
            "e", "f", "g",
            "h", "i", "g", "k",
            "l", "m", "n",
            "o", "p", "q",
            "r", "s", "t",
            "u", "v", "w",
            "x", "y", "z"
        ];
        $arr = [];
        $len = 7;
        $num = count($items);
        for($i=0; $i<$len; ++$i) {
            $arr[] = $items[floor($id/pow($num, $len-$i-1))];
            $id = $id % pow($num, $len-$i-1);
        }
        $invite = implode('', $arr);
        $user = Db::name('LcUser')->where("invite",$invite)->find();
        if($user){
            $this->getInviteCode($id) ;
        } else {
            return  $invite;
        }
    }
}