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
 * 会员管理
 * Class Item
 * @package app\admin\controller
 */
class Users extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcUser';

    /**
     * 会员列表
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
        // var_dump('d');die;
        $auth 
        = $this->app->session->get('user');
        $where = "";
        $this->adm = true;
        if($auth['authorize']==1){
            $where = "u.agent = {$auth['id']}";
            $this->adm = false;
        }
        $this->title = '会员列表';
        $query = $this->_query($this->table)->alias('u')->field('u.*,m.name as m_name');
        
        //$temp = $query->join('lc_user_member m','u.member=m.id')->where($where)->equal('u.auth#u_auth,u.clock#u_clock,u.member#u_member')->like('u.name|u.phone#u_phone,u.agent#u_agent,u.ip#u_ip')->dateBetween('u.time#u_time')->order('u.id desc')->select();
        
        //var_dump($temp);
        $map = [];
        $phone = request()->get('u_phone');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['u.phone'] = $aes->encrypt($phone);
            // var_dump($aes->decrypt("wKSHI24ZmYp0zyU1/4phZg=="));
            // var_dump($aes->encrypt("0123258741 "));
        }
        // var_dump($map);
        $is_sf = request()->get('is_sf', '');
        // $query->join('lc_user_member m','u.member=m.id')->where($where)->equal('u.auth#u_auth,u.clock#u_clock,u.member#u_member')->like('u.name|u.phone#u_phone,u.agent#u_agent,u.ip#u_ip')->dateBetween('u.time#u_time')->order('u.id desc')->page();
        $query->join('lc_user_member m','u.member=m.id')->where($map)->where($where)->equal('u.name#u_name,u.auth#u_auth,u.clock#u_clock,u.member#u_member,u.sign_status#u_sign_status,u.grade_id#u_grade_id,u.mid#u_id,u.is_sf#is_sf,u.top#u_top')->dateBetween('u.time#u_time')->order('u.id desc')->page();
        
        
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
        $this->member = Db::name("lc_user_member")->field('id,name')->select();
        $this->grade = Db::name('lc_member_grade')->field('id,title')->select();
        $ip = new \Ip2Region();
        $aes = new Aes();
        foreach($data as &$vo){
            $vo['phone'] = $aes->decrypt($vo['phone']);
            $vo['online'] = $vo['logintime']>(time()-300)?1:0;
            // $vo['top']  = Db::name("lc_user")->where("id = {$vo['top']}")->value('phone');
            // $vo['top'] = $aes->decrypt(Db::name("lc_user")->where("id = {$vo['top']}")->value('phone'));
            // $vo['top'] = 0;
            // $vo['cash_sum']  = Db::name("lc_cash")->where("uid = {$vo['id']} AND status = '1'")->sum('money');
            // $vo['cash_sum'] = 0;
            
            // $vo['recharge_sum']  = Db::name("lc_recharge")->where("uid = {$vo['id']} AND status = '1'")->sum('money');
            // $gr_recharge = Db::name('LcRecharge')->where("uid = {$vo['id']} AND pid = 15  AND status = 1")->sum('money2');//个人充值
            // $gr_recharges = Db::name('LcRecharge')->where("uid = {$vo['id']} AND pid = 6  AND status = 1")->sum('money2');//个人充值
            // $ye_recharge = Db::name('LcRecharge')->where("uid = {$vo['id']} AND pid = 20 AND status = 1 AND type='余额转资产'")->sum('money');//余额转资产
            // $vo['recharge_sum'] =$gr_recharge+$ye_recharge+$gr_recharges;
            $vo['recharge_sum'] = $vo['czmoney'];
       
        //   $vo['recharge_sum'] =sprintf('%.2f', $vo['recharge_sum'] /Db::name('LcPayment')->where(['id'=>15])->value('rate'));
            // $vo['invest_sum']  = Db::name('lc_invest')->where("uid = {$vo['id']}")->sum('money');
            // $vo['wait_invest']  = Db::name('lc_invest_list')->where("uid = {$vo['id']} AND pay1 > 0 AND status = 0")->sum('money1');
            // $vo['wait_money']  = Db::name('lc_invest_list')->where("uid = {$vo['id']} AND money2 > 0 AND status = 0")->sum('money2');
            // $vo['invest_sum'] = 0;
            // $vo['wait_invest'] = 0;
            // $vo['wait_money'] = 0;
            $result = $ip->btreeSearch($vo['ip']);
            $vo['isp'] = isset($result['region']) ? $result['region'] : '';
            $vo['isp'] = str_replace(['内网IP', '0', '|'], '', $vo['isp']);
            $vo['g_name'] = Db::name('lc_member_grade')->find($vo['grade_id'])['title'];
            if ($vo['dj_id']) {
                $djUser = Db::name('lc_user')->find($vo['dj_id']);
                $vo['dj_phone'] = $aes->decrypt($djUser['phone']);
            } else {
                $vo['dj_phone'] = '--';
            }

            // $vo['asset'] = isset($vo['id'])?Db::name('LcRecharge')->where("uid = {$vo['id']} AND status = '1'")->sum('money2'):0;
        }
    }

    /**
     * 表单数据处理
     * @param array $data
     * @auth true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function _form_filter(&$vo)
    {
        $aes = new Aes();
        if ($this->request->isPost()) {
            // $agent = Db::name('system_user')->find($vo['agent']);
            // if (!$agent) {
            //     $this->error('代理ID不存在');
            // }
            
            
            
            if($vo['mwpassword']) $vo['password'] = md5($vo['mwpassword']);
            if($vo['mwpassword2']) $vo['password2'] = md5($vo['mwpassword2']);
            if(isset($vo['id'])){
                $money = Db::name($this->table)->where("id = {$vo['id']}")->value('money');
                 $asset = Db::name($this->table)->where("id = {$vo['id']}")->value('asset');
                //  var_dump($vo['invite']);die;
                  $inid = Db::name($this->table)->where("invite = '{$vo['invite']}'")->value('id');
               
                  if($inid){
                      //不存在才可以修改 否则就是别人用过的邀请码
                      unset($vo['invite']);
                  }
                //   var_dump($vo);die;
                        if($vo['money']<0) $this->error('余额不能为负数');
                          if($vo['asset']<0) $this->error('资产不能为负数');
                           if($vo['ebao']<0) $this->error('途游宝不能为负数');
                           if($vo['point_num']<0) $this->error('积分不能为负数');
                                if($vo['value']<0) $this->error('成长值不能为负数');
                if($money&&$money != $vo['money']){
                    $handle_money = $money-$vo['money'];
                    $type = $handle_money>0?2:1;
                    model('admin/Users')->addFinance($vo['id'],abs($handle_money),$type,'官方充值','Nạp tiền chính thức','System Operation','การทำงานของระบบ','Vận hành hệ thống','システム操作','시스템 운영','Operasi sistem');

                    if($type == 1){
                        // 增加
                        Db::name("LcUser")->where(['id'=> $vo['id']])->setInc('czmoney', abs($handle_money));
                        gradeUpgrade($vo['id']);
                    }
                }
                
             if($asset&&$asset != $vo['asset']){
                    $handle_asset = $asset-$vo['asset'];
                    $type = $handle_asset>0?2:1;
                    model('admin/Users')->addFinance($vo['id'],abs($handle_asset),$type,'官方充值','Nạp tiền chính thức vào tài khoản','System Operation','การทำงานของระบบ','Vận hành hệ thống','システム操作','시스템 운영','Operasi sistem');

                    if($type == 1){
                        // 增加
                        Db::name("LcUser")->where(['id'=> $vo['id']])->setInc('czmoney', abs($handle_asset));
                        gradeUpgrade($vo['id']);
                    }
                }
            }else{
                $vo['time'] = date('Y-m-d H:i:s');
            }
            
            
            
            sysoplog('修改用户信息', '管理员修改用户信息');
            $vo['phone'] = $aes->encrypt($vo['phone']);
        } else {
            if(!isset($vo['auth'])) $vo['auth'] = '0';
            $this->member = Db::name("LcUserMember")->order('id asc')->select();
            // $this->memberGrade = Db::name("LcMemberGrade")->order('id asc')->select();
                    //  $this->member = 8015;
            $this->memberGrade = Db::name("LcMemberGrade")->order('id asc')->select();
            // $vo['asset'] = isset($vo['id'])? Db::name('LcRecharge')->where("uid = {$vo['id']} AND status = '1'")->sum('money2'):0;
            if (isset($vo['phone'])) {
                $vo['phone'] = $aes->decrypt($vo['phone']);
            }
            
        }
    }
    //我的团队
	public function myitem(){
	  
	    $this->title = '我的团队';
	    
        if($this->request->isGet()&&$this->request->param('phone')){
            $list = [];
            $phone = $this->request->param('phone');
            $level = $this->request->param('level');
            $type = $this->request->param('type');
            $wheres = array();
            $aes = new Aes();
            if(empty($this->request->param('flag'))){
                $wheres["id"] = input('userid');
            }else{
                $wheres['phone|name'] =  $aes->encrypt($phone);
            }
             
			  
			    $members = Db::name('LcUser')->where($wheres)->find();
                $memberList = Db::name('LcUser')->field('id, phone, top,czmoney')->select();
        //         var_dump($memberList);die;
             
                $itemList = $this->get_downline_list($memberList, $members['id']);
                // var_dump($itemList);exit;
                
                $idss = [input('userid')];
                foreach ($itemList as $item) {
                    $idss[] = $item['id'];
                }
   
                $ids = [];
                // var_dump($itemList);
                 // var_dump($itemList);
                $all_czmoney=0;
                $all_team=count($itemList);
                foreach($itemList as $v){
                      $all_czmoney+=$v['czmoney'];
                    if($level){
                         if($v['level'] == $level){
                        array_push($ids, $v['id']);
                      }  
                       
                    }else{
                     array_push($ids, $v['id']);
                    }
                   
                }
                $where['id'] = $ids;
                  $where1['uid'] = $ids;
            // echo '<pre>';
            $list = Db::name('LcUser')->where($where)->select();
            // foreach ($list as $value) {
            //     var_dump($value);exit;
            // }
            // var_dump($where);
            // var_dump($list);
            // die;
                $all_cash = Db::name('LcCash')->where($where1)->where('status',1)->sum('money');
       
                $uid = Db::name('LcUser')->where(['phone'=>$phone])->value('id');
                if($uid){
                    $list = Db::name('LcUser')->where($where)->select();
                }
                $rechargeTotal = 0;
                $cashTotal = 0;
                $countCommissionTotal = 0;
            if($list){
                foreach ($list as &$v){
                    $vo['top_phone'] = '';
                    if($v['top']){
                      $vo['top_phone'] = Db::name('LcUser')->where(['id'=>$v['top']])->value('phone');  
                    }
                    $v['phone'] = $aes->decrypt($v['phone']);
                    //$vo['total'] = Db::name('LcRecharge') -> where('id', $v['id']) -> where('status', 1) -> count('money2');
                }
                
                
                for($i = 0; $i < count($list); $i++){
                    //总充值
                    $trecharge = Db::name('LcRecharge') -> where('uid', $list[$i]['id']) -> where('status', 1) -> sum('money2');
                    $list[$i]['recharge'] = number_format($trecharge, 2);
                    //总提现
                    $tcash = Db::name('LcCash') -> where('uid', $list[$i]['id']) -> where('status', 1) -> sum('money');
                    $list[$i]['cash'] = number_format($tcash, 2);
                    
                    //获取总投资
                    
                     $list[$i]['tzTotal'] = Db::name('lc_invest t')->join('lc_item m','t.pid = m.id')
                        ->where('m.index_type', '<>', 7)
                        ->where('t.uid', $list[$i]['id'])->sum('t.money');
                    // $list[$i]['tzTotal'] = $cash = Db::name('Lc_invest') -> where('uid', $list[$i]['id']) -> sum('money');
                    //总收益
                        //获取会员用户组信息
                    $user_member = Db::name('lc_user_member') -> where('id', $list[$i]['member']) -> find();
                    $investList = Db::name('lc_invest') -> where('uid', $list[$i]['id']) -> select();
                    // echo json_encode($investList);die;
                    $invest = 0;
                    foreach ($investList as $investListItem){
                        $item = Db::name('lc_item') -> where('id', $investListItem['pid']) -> find();
                        if(empty($item)){
                            continue;
                        }
                        $invest += $investListItem['money'] * (($item['rate'] / 100) + ($user_member['rate'] / 100));
                    }
                    $list[$i]['invest'] = $invest;
                    
                    //团队总奖励
                    // $countCommission = Db::name('LcFinance')->where('uid', $list[$i]['id'])-> where('reason_type', 'in', '5,7,8') -> sum('money');//"uid = ".$list[$i]['id']." AND reason LIKE '%推荐_%'"
                    $countCommission = Db::name('LcFinance')->where('zh_cn', 'like', '升级为%')->where('uid', $list[$i]['id'])->sum('money');
                    $list[$i]['countCommission'] = $countCommission;
                    $countCommissionTotal += $countCommission;
                    $rechargeTotal += $trecharge;
                    $cashTotal += $tcash;
                }
                
                
                
            }
            //echo json_encode($list);exit;
            $this->assign('list', $list);
            $this->assign('type', $type);
             $this->assign('all_czmoney', $all_czmoney);
            $this->assign('all_team', $all_team);
            $this->assign('all_cash', $all_cash);
            $this->assign('level', $level);
            $totalInvest = Db::name('lc_invest t')->join('lc_item m','t.pid = m.id')
            ->where('m.index_type', '<>', 7)
            ->whereIn('t.uid', $ids)->sum('t.money');
            
            $countCommissionTotal = Db::name('LcFinance')->where('zh_cn', 'like', '升级为%')->whereIn('uid', $idss)->sum('money');
            // $this->assign('yj', number_format($rechargeTotal - $cashTotal, 2));
            $this->assign('yj', $totalInvest);
            $this->assign('countCommission', $countCommissionTotal);
        }
          $this->fetch();
	}
    /*无限极下级
     * 
     *
    */
    public function get_downline_list($user_list, $telephone, $level = 0)
    {
        $arr = array();
        foreach ($user_list as $key => $v) { 
            // if($level<=2){
                 if ($v['top'] == $telephone) {  //inviteid为0的是顶级分类
                $v['level'] = $level + 1;
                $arr[] = $v;
                // var_dump($arr);die;
                $arr = array_merge($arr, $this->get_downline_list($user_list, $v['id'], $level + 1));
            // }
            }
           
        }
        return $arr;
    }
    /**
     * 会员关系网
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function user_relation(){
        $this->title = '会员关系网';
        if($this->request->isGet()&&$this->request->param('phone')){
            $list = [];
            $phone = $this->request->param('phone');
            $type = $this->request->param('type');
            $aes = new Aes();
            $phone = $aes->encrypt($phone);
            if($type == 1){
                $top = Db::name('LcUser')->where(['phone'=>$phone])->value('top');
                if($top){
                    $list = Db::name('LcUser')->where(['id'=>$top])->select();
                }
            }else{
                $uid = Db::name('LcUser')->where(['phone'=>$phone])->value('id');
                if($uid){
                    $list = Db::name('LcUser')->where(['top'=>$uid])->select();
                }
            }
            if($list){
                foreach ($list as &$v){
                    $vo['top_phone'] = '';
                    if($v['top']){
                      $vo['top_phone'] = Db::name('LcUser')->where(['id'=>$v['top']])->value('phone');  
                    }
                    $v['phone'] = $aes->decrypt($v['phone']);
                }
            }
            $this->assign('list', $list);
            $this->assign('type', $type);
        }
        $this->fetch();
    }
    /**
     * 添加用户
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
//        $this->_form($this->table, 'form');
        $this->_formUser($this->table, 'form', '',[],['is_yq'=>0,'is_sf'=>0]);
    }
    
    public function adds()
    {
       
        $this->applyCsrfToken();
//        $this->_form($this->table, 'form');
        $this->_formUser($this->table, 'add', '',[],['is_yq'=>0,'is_sf'=>0]);
    }
    
    public function addto(){
        $form = $this->request->param();
        
        $agent = Db::name('system_user')->find($form['agent']);
        if (!$agent) {
            $this->error('代理ID不存在');
        }
        
        $number = $form['number'];
        $aes = new Aes();
        for($i = 0; $i < $number; $i++){
            $newPhone = $i == 0 ? $form['phone'] : $form['phone'] + $i;
            $invite = $this -> getRandomStr(8, false);
            $data[] = [
                'phone' => $aes->encrypt($newPhone),
                'qdnum' => $form['qdnum'],
                'name' => $form['name'],
                'idcard' => $form['idcard'],
                'password' => md5($form['mwpassword']),
                'password2' => md5($form['mwpassword2']),
                'mwpassword' => $form['mwpassword'],
                'mwpassword2' => $form['mwpassword2'],
                'ebao' => $form['ebao'],
                'point_num' => $form['point_num'],
                'money' => $form['money'],
                'asset' => $form['asset'],
                'invite' => $invite,
                'kj_money' => $form['kj_money'],
                'member' => $form['member'],
                'grade_id' => $form['grade_id'],
                'is_yq' => $form['is_yq'],
                'is_sf' => $form['is_sf'],
                'auth' => $form['auth'],
                'clock' => 1,
                'agent' => $form['agent']
            ];
            echo '账号：' . $newPhone . '------密码：'. $form['mwpassword'] . '------交易密码：' . $form['mwpassword2'] . '------邀请码：' . $invite .'<br>';
        }
        Db::name('lc_user') -> insertAll($data);
        //echo json_encode($data);exit;
        echo "<div style='margin-top: 100px;'><a herf='javascript:;' onClick='javascript:history.back(-1)'>点击返回</a></div>";
    }
    //随机邀请码
    /**
     * 获得随机字符串
     * @param $len             需要的长度
     * @param $special        是否需要特殊符号
     * @return string       返回随机字符串
     */
      public function getRandomStr($len, $special=false){
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );
    
        if($special){
            $chars = array_merge($chars, array(
                "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
                "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
                "}", "<", ">", "~", "+", "=", ",", "."
            ));
        }
    
        $charsLen = count($chars) - 1;
        shuffle($chars);                            //打乱数组顺序
        $str = '';
        for($i=0; $i<$len; $i++){
            $str .= $chars[mt_rand(0, $charsLen)];    //随机取出一位
        }
         $user = Db::name('LcUser')->where("invite", $str)->find();
            if ($user) {
                $this->getRandomStr(8,false);
            } else {
               return $str;
            }
       
    }
    /**
     * 编辑用户
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
        
        if($this -> request -> isPost()){
            $log = [
                'phone' => "用户账号",
                'qdnum' => "签到天数",
                'name' => "姓名",
                'idcard' => "身份证号",
                'mwpassword' => "登录密码",
                'mwpassword2' => "交易密码",
                'ebao' => "途游宝",
                'point_num' => "积分",
                'money' => "余额",
                'asset' => "资产",
                'invite' => "邀请码",
                'kj_money' => "MM币",
                'value' => "成长值",
                'member' => "会员组",
                'grade_id' => "团队等级",
                'is_yq' => "邀请码状态",
                'is_sf' => "身份",
                'auth' => "是否认证",
                'card_front' => "身份证正面",
                'card_back' => "身份证反面",
            ];
            $param = $this -> request -> post();
            //获取用户原数据
            $oldData = Db::name('LcUser') -> where('id', $param['id']) -> find();
            $aes = new Aes();
            $oldData['phone'] = $aes->decrypt($oldData['phone']);
            $logList = [];
            foreach ($param as $key => $item){
                if(array_key_exists($key, $oldData) && $oldData[$key] != $param[$key]){
                    sysCheckLog('修改用户信息', '管理员【'.  Session::get('user.username') . '】修改了用户【' . $param['phone'] . '】：'  . $log[$key] . '为' . $param[$key] . '，原始值为' . $oldData[$key]);
                    //$logList[] = '管理员账号：' .  Session::get('user.username') . ' -> 修改了' . $log[$key];
                }
            }
            
            //var_dump($logList);exit;
            // if($userInfo['phone'] != $param['phone']){
            //     //sysCheckLog('用户模块', $string);
            // }
        }
        $this->_form($this->table, 'form');
    }

    /**
     * 禁用用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['clock' => '0']);
    }
    
    //冻结
    public function freeze()
    {
        if($this -> request -> isPost()){
            $param = $this -> request -> post();
            $param['clock'] = 0;
            Db::name('lc_user')->where('id', $param['id'])->update($param);
            $this->success('操作成功');
        }
        $this->_form($this->table, 'freeze');
    }
    
    //冻结团队
    public function team_freeze()
    {
        if($this -> request -> isPost()){
            $param = $this -> request -> post();
            $uid = $param['id'];
            //获取团队人员
            $memberList = Db::name('LcUser')->field('id, phone, top,czmoney')->select();
            $itemList = $this->get_downline_list($memberList, $uid);
            $userIds[] = $uid;
            foreach ($itemList as $item) {
                $userIds[] = $item['id'];
            }
            Db::name('lc_user')->whereIn('id', $userIds)->update(['is_team' => 1, 'clock' => 0, 'clock_msg' => $param['clock_msg'], 'clock_msg_zh_hk' => $param['clock_msg_zh_hk'], 'clock_msg_en_us' => $param['clock_msg_en_us'],]);
            $this->success('操作成功');
        }
        $this->_form($this->table, 'freeze');
    }

    /**
     * 启用用户
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        $this->applyCsrfToken();
        $this->_save($this->table, ['clock' => '1']);
    }
    
    public function sync()
    {
        $uid = $this->request->post('id');
        $czmoney = Db::name('lc_recharge')->where(['uid' => $uid, 'status' => 1])->sum('money');
        $cash_sum = Db::name('lc_cash')->where(['uid' => $uid, 'status' => 1])->sum('money');
        $invest_sum = Db::name('lc_invest')->where(['uid' => $uid])->sum('money');
        $wait_invest = Db::name('lc_invest_list')->where(['uid' => $uid, 'status' => 0])->sum('money1');
        $wait_money = Db::name('lc_invest_list')->where(['uid' => $uid, 'status' => 0])->sum('money2');
        
        //获取顶级ID
        $user = Db::name('lc_user')->find($uid);
        if ($user['top']) {
            $dj_arr = $this->getTop($user['top']);
            $dj_id = $dj_arr[count($dj_arr)-1];
        } else {
            $dj_id = 0;
        }
        Db::name('lc_user')->where('id', $uid)->update(['czmoney' => $czmoney, 'cash_sum' => $cash_sum, 'invest_sum' => $invest_sum, 'wait_invest' => $wait_invest, 'wait_money' => $wait_money, 'dj_id' => $dj_id]);
        $this->success('操作成功');
    }
    
    public function batchSync()
    {
        $ids = explode(",",  $this->request->param('id'));
        foreach($ids as $v){
            $uid = $v;
            $czmoney = Db::name('lc_recharge')->where(['uid' => $uid, 'status' => 1])->sum('money');
            $cash_sum = Db::name('lc_cash')->where(['uid' => $uid, 'status' => 1])->sum('money');
            $invest_sum = Db::name('lc_invest')->where(['uid' => $uid])->sum('money');
            $wait_invest = Db::name('lc_invest_list')->where(['uid' => $uid, 'status' => 0])->sum('money1');
            $wait_money = Db::name('lc_invest_list')->where(['uid' => $uid, 'status' => 0])->sum('money2');
            //获取顶级ID
            $user = Db::name('lc_user')->find($uid);
            if ($user['top']) {
                $dj_arr = $this->getTop($user['top']);
                $dj_id = $dj_arr[count($dj_arr)-1];
            } else {
                $dj_id = 0;
            }
            Db::name('lc_user')->where('id', $uid)->update(['czmoney' => $czmoney, 'cash_sum' => $cash_sum, 'invest_sum' => $invest_sum, 'wait_invest' => $wait_invest, 'wait_money' => $wait_money, 'dj_id' => $dj_id]);
        }
        $this->success('操作成功');
    }
    
    public function getTop($pid, $list = [], $flag = true)
    {
        static $list = [];
        if ($flag) {
            $list = [];
        }
        $user = Db::name('lc_user')->find($pid);
        if ($user) {
            $list[] = $user['id'];
            $this->getTop($user['top'], $list, false);
        }
        return $list;
    }
    
    
    public function team_forbid()
    {
        $this->applyCsrfToken();
        $uid = $this->request->post('id');
        //获取团队人员
        $memberList = Db::name('LcUser')->field('id, phone, top,czmoney')->select();
        $itemList = $this->get_downline_list($memberList, $uid);
        $userIds[] = $uid;
        foreach ($itemList as $item) {
            $userIds[] = $item['id'];
        }
        Db::name('lc_user')->whereIn('id', $userIds)->update(['is_team' => 1, 'clock' => 0]);
        $this->success('操作成功');
    }

    
    public function team_resume()
    {
        $this->applyCsrfToken();
        $uid = $this->request->post('id');
        //获取团队人员
        $memberList = Db::name('LcUser')->field('id, phone, top,czmoney')->select();
        $itemList = $this->get_downline_list($memberList, $uid);
        $userIds[] = $uid;
        foreach ($itemList as $item) {
            $userIds[] = $item['id'];
        }
        Db::name('lc_user')->whereIn('id', $userIds)->update(['is_team' => 0, 'clock' => 1]);
        $this->success('操作成功');
    }

    /**
     * 删除用户
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
     * 导出Excel
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function exportExcel()
    {
        $filename = '用户列表'.date('YmdHis');
        $header = array('id','邮箱','手机号','姓名','充值总额','下级人数');
        $index = array('id','phone','phone2','name','recharge_sum','invest_count');
        $list = Db::name("lcUser")->field('id,phone,phone2,name')->select();
        
        header("Content-type:application/vnd.ms-excel");  
    	header("Content-Disposition:filename=".$filename.".xls");  
    	$teble_header = implode("\t",$header);
    	$strexport = $teble_header."\r";
    	foreach ($list as $row){  
    	    $row['recharge_sum']  = Db::name("lc_recharge")->where("uid = {$row['id']} AND status = '1'")->sum('money');
    	    $row['invest_count'] =  Db::name('lcUser')->where("top = {$row['id']} OR top2 = {$row['id']} OR top3 = {$row['id']}")->count();
    		foreach($index as $val){
    			$strexport.=$row[$val]."\t";   
    		}
    		$strexport.="\r"; 
     
    	}  
    	$strexport=iconv('UTF-8',"GB2312//IGNORE",$strexport);  
    	exit($strexport);  

    }
    
    public function revision_login(){
        $uid = $this->request->post('id');
        if(empty($uid)){
            $this->error('用户选择错误');
        }
        $Data = Db::name('LcUser') -> where('id', $uid) -> find();
        if(!$Data){
            $this->error('欲操作用户不存在');
        }
        $aes = new Aes();
        $phone = $aes->decrypt($Data['phone']);
        $phone = trim($phone);
        // 再加密回去
        $phone = $aes->encrypt($phone);
        
        if($phone === $Data['phone']){
            $this->success('用户正常，无需修正');
        }
        
        Db::name('LcUser') -> where('id', $uid) -> update(['phone'=>$phone]);
        
        sysCheckLog('执行核心操作', '管理员【'.  Session::get('user.username') . '】点击进行了用户无法登陆修正，' . '将原始数据：' . $Data['phone']. '注入为：'. $phone);
        
        $this->success('修正已注入，请客户重新登录验证');
    }
}
