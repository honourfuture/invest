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
 * 充值管理
 * Class Item
 * @package app\admin\controller
 */
class Recharge extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcRecharge';

    /**
     * 充值记录
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
        $this->adm = true;
        $this->recharge_sum = Db::name('LcRecharge')->where("status = 1")->sum('money');
        if($this->request->param('u_agent')){
            $this->recharge_sum = Db::name('LcRecharge r, lc_user u')->where("r.status = 1 AND r.uid = u.id AND u.agent = {$this->request->param('u_agent')}")->sum('r.money');
        }

        if($auth['authorize']==1){
            $where = "u.agent = {$auth['id']} AND i.status=1";
            $this->adm = false;
            $this->recharge_sum = Db::name('LcRecharge r , lc_user u')->where("status = 1 AND r.uid = u.id AND u.agent = {$auth['id']}")->sum('r.money');
        }
        $map = [];
        $phone = request()->get('u_phone');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['u.name|u.phone'] = $aes->encrypt($phone);
        }
        $i_time = request()->get('i_time', '');
        $cond = '';
        if (!empty($i_time)) {
            $time_arr = explode(' - ', $i_time);
            $start_time = strtotime($time_arr[0]);
            $end_time = strtotime($time_arr[1])+86400;
            $cond = "UNIX_TIMESTAMP(i.time) > $start_time AND UNIX_TIMESTAMP(i.time) < $end_time";
        }
        $u_agent = $this->request->get('u_agent', '');
        $i_type = $this->request->get('i_type', '');
        $i_status = $this->request->get('i_status', '');
        $is_sf = request()->get('is_sf', 0);
        if (!empty($u_agent)) {
            $map['u.agent'] = $u_agent;
        }
        if (!empty($i_type)) {
            $map['i.type'] = $i_type;
        }
        if ($i_status != '') {
            $map['i.status'] = $i_status;
        }
        $map['u.is_sf'] = $is_sf;
        $this->recharge_sum = Db::name('lc_recharge i')->join('lc_user u', 'i.uid = u.id')->where($map)->where($cond)->sum('i.money');

        $this->title = '充值记录';
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.phone,u.name') -> where('i.status', '<>', 3);
        $query->join('lc_user u','i.uid=u.id')->where($map)->where($where)->equal('i.status#i_status')->equal('u.is_sf#is_sf')->like('i.orderid#i_orderid,i.type#i_type,u.agent#u_agent')->dateBetween('i.time#i_time')->order('i.id desc')->page();
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
        $this->type = Db::name($this->table)->field('type')->group('type')->select();
        $this->rejected = sprintf("%.2f",Db::name($this->table)->where("status = 2")->sum('money'));
        $this->finished = sprintf("%.2f",Db::name($this->table)->where("status = 1")->sum('money'));
        $this->reviewed = sprintf("%.2f",Db::name($this->table)->where("status = 0")->sum('money'));
        $this->cny=0;
        $this->usdt=0;
        // echo '<pre>';
        // var_dump($data);die;
        $aes = new Aes();
        foreach($data as &$vo){
            $payment = Db::name("lc_payment")->find($vo['pid']);
            $this->cny+=$vo['money'];
            $this->usdt+=$vo['money2'];
            if($payment){
                // $num = 2;
                if($payment['type']==1){
                    // if($payment['rate']>10) $num = 4;
                    // if($payment['rate']>1000) $num = 6;
                    // if($payment['rate']>10000) $num = 8;

                    $vo['money2'] = "(≈".floatval($vo['money2'])." ".$payment['mark'].")";
                }else{
                    $vo['money2'] = "(≈".$payment['mark'].floatval($vo['money2']).")";
                }

            }
            if(stripos($vo['image'],'http')===false){
                $vo['image'] = '/upload/'.$vo['image'];
            }
            $vo['phone'] = $aes->decrypt($vo['phone']);
        }
    }



    public function batchAgree(){
        $this->applyCsrfToken();
        $ids = explode(",",  $this->request->param('id'));
        foreach($ids as $v){
            $id = $v;
            $recharge = Db::name($this->table)->find($id);
            if($recharge&&$recharge['status'] == 0||$recharge['status'] == 3){
                $money = $recharge['money'];
                // $money2 = $recharge['money2'];
                $money2 = $recharge['money2'];
                $uid = $recharge['uid'];
                $type = $recharge['type'];
                $type_zh_hk = $recharge['type_zh_hk'];
                $type_en_us = $recharge['type_en_us'];

                $LcTips152 = Db::name('LcTips')->where(['id' => '152']);
                $LcTips153 = Db::name('LcTips')->where(['id' => '153']);
                
                if ($recharge['pid'] == 21) {
                    $money = $money2;
                }
                addFinance($uid, $money,1,
                    $type .$LcTips152->value("zh_cn"),
                    $type_zh_hk .$LcTips152->value("zh_hk"),
                    $type_en_us .$LcTips152->value("en_us"),
                    $type .$LcTips152->value("th_th"),
                    $type .$LcTips152->value("vi_vn"),
                    $type .$LcTips152->value("ja_jp"),
                    $type .$LcTips152->value("ko_kr"),
                    $type .$LcTips152->value("ms_my"),
                    "","",1
                );
                setNumber('LcUser', 'asset', $money, 1, "id = $uid");
                //成长值
                // setNumber('LcUser','value', $money2, 1, "id = $uid");

                // 增加累计充值金额
                Db::name("LcUser")->where("id = {$uid}")->setInc("czmoney", $recharge['money']);

                //设置会员等级
                $user = Db::name("LcUser")->find($uid);

                // setUserMember($uid,$user['value']);



                //gradeUpgrade($uid);

                //上级奖励（一、二、三级）
                $top=$user['top'];
                $top2=$user['top2'];
                $top3=$user['top3'];
                //个人充值奖励
                $member_rate = Db::name("LcUserMember")->where(['id'=>$user['member']])->value("member_rate");

                setRechargeRebate1($uid, $money,$member_rate,'个人充值奖励');
            }
        }
    }


    /**
     * 同意充值
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function agree()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        $recharge = Db::name($this->table)->find($id);
        if($recharge&&$recharge['status'] == 0||$recharge['status'] == 3){
            $money = $recharge['money'];
            $money2 = $recharge['money2'];
            $uid = $recharge['uid'];
            $type = $recharge['type'];
            $type_zh_hk = $recharge['type_zh_hk'];
            $type_en_us = $recharge['type_en_us'];

            $LcTips152 = Db::name('LcTips')->where(['id' => '152']);
            $LcTips153 = Db::name('LcTips')->where(['id' => '153']);
            
            if ($recharge['pid'] == 21) {
                $money = $money2;
            }
            addFinance($uid, $money,1,
                $type .$LcTips152->value("zh_cn").vnd_gsh(bcdiv($money,1,0)),
                $type_zh_hk .$LcTips152->value("zh_hk").vnd_gsh(bcdiv($money,1,0)),
                $type_en_us .$LcTips152->value("en_us").vnd_gsh(bcdiv($money,1,0)),
                $type .$LcTips152->value("th_th").vnd_gsh(bcdiv($money,1,0)),
                $type .$LcTips152->value("vi_vn").vnd_gsh(bcdiv($money,1,0)),
                $type .$LcTips152->value("ja_jp").vnd_gsh(bcdiv($money,1,0)),
                $type .$LcTips152->value("ko_kr").vnd_gsh(bcdiv($money,1,0)),
                $type .$LcTips152->value("ms_my").vnd_gsh(bcdiv($money,1,0)),
                "","",1
            );
            setNumber('LcUser', 'asset', $money, 1, "id = $uid");
            //成长值
            // setNumber('LcUser','value', $money2, 1, "id = $uid");

            $dd = Db::name("LcUser")->where("id = {$uid}")->find();

            // 增加累计充值金额
            Db::name("LcUser")->where("id = {$uid}")->setInc("czmoney", $money);

            $aes = new Aes();
            $dd['phone'] = $aes->decrypt($dd['phone']);
            $string =  '管理员'.$this->app->session->get('user.username').'同意【'.$dd['phone'].'】充值金额 【’'.$money.'】U';
            sysCheckLog('同意充值', $string);

            //设置会员等级
            $user = Db::name("LcUser")->find($uid);
            
            //标记
            Db::name('lc_user')->where('id', $uid)->update(['sign_status' => 0]);

            $memberId = setUserMember($uid,$user['value']);

            // 查询当前会员等级
            $userMember = Db::name("LcUserMember")->where(['id' => $memberId])->find();

            //上级奖励（一、二、三级）
            $top=$user['top'];
            $top2=$user['top2'];
            $top3=$user['top3'];
            //一级

            $member_rate = Db::name("LcUserMember")->where(['id'=>$user['member']])->value("member_rate");

            setRechargeRebate1($uid, $money,$member_rate,'个人充值奖励');
            $this->_save($this->table, ['status' => '1','time2' => date('Y-m-d H:i:s')]);
        }
    }
    public function setRechargeRebate1($tid, $money,$reward)
    {
        //会员等级

        $rebate = round($reward * $money / 100, 2);
        if (0 < $rebate) {
            $LcTips173 = Db::name('LcTips')->where(['id' => '173']);
            $LcTips174 = Db::name('LcTips')->where(['id' => '174']);
            // addFinance($tid, $rebate, 1,
            //     "下级充值返佣",
            //     $LcTips173->value("zh_cn").$money.$LcTips174->value("zh_cn").$rebate,
            //     $LcTips173->value("en_us").$money.$LcTips174->value("en_us").$rebate,
            //     $LcTips173->value("th_th").$money.$LcTips174->value("th_th").$rebate,
            //     $LcTips173->value("vi_vn").$money.$LcTips174->value("vi_vn").$rebate,
            //     $LcTips173->value("ja_jp").$money.$LcTips174->value("ja_jp").$rebate,
            //     $LcTips173->value("ko_kr").$money.$LcTips174->value("ko_kr").$rebate,
            //     $LcTips173->value("ms_my").$money.$LcTips174->value("ms_my").$rebate
            // );
            setNumber('LcUser', 'money', $rebate, 1, "id = $tid");
            setNumber('LcUser', 'income', $rebate, 1, "id = $tid");
        }
    }

    /**
     * 增减余额
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function change(){
        $this->applyCsrfToken();
        if($this->app->request->isPost()){
            $data = $this->request->param();
            if(!$data['name']) $this->error("用户账号必填");
            if(!$data['money']) $this->error("增减金额必填");
            $aes = new Aes();
            $uid = Db::name("LcUser")->where(['phone'=>$aes->encrypt($data['name'])])->value('id');
            if(!$uid) $this->error("暂无该用户");
            // addFinance($uid, $data['money'], $data['type'], $data['zh_cn'],$data['zh_hk'],$data['en_us'],$data['th_th'],$data['vi_vn'],$data['ja_jp'],$data['ko_kr'],$data['ms_my']);
            addFinance($uid, $data['money'], $data['type'], $data['zh_cn'],$data['zh_hk'],$data['en_us'],$data['en_us'],$data['en_us'],$data['en_us'],$data['en_us'],$data['en_us']);
            setNumber('LcUser', 'money', $data['money'], $data['type'], "id = $uid");

            sysCheckLog('同意充值', '账户'.$data['name'].'增减余额'.$data['money']);

            $this->success("操作成功");
        }
        $this->fetch('form');
    }

    /**
     * 拒绝充值
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function refuse1()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        $recharge = Db::name($this->table)->find($id);

//        sysCheckLog('充值记录', '拒绝充值金额');
        $dd = Db::name("LcUser")->where("id = {$recharge['uid']}")->find();
        $aes = new Aes();
        $dd['phone'] = $aes->decrypt($dd['phone']);
        $string =  '管理员'.$this->app->session->get('user.username').'拒绝【'.$dd['phone'].'】充值金额 【'.$recharge['money2'].'】U';
        sysCheckLog('拒绝充值', $string);



        sendSms(getUserPhone($recharge['uid']), '18006', $recharge['money']);
        $this->_save($this->table, ['status' => '2','time2' => date('Y-m-d H:i:s')]);
    }
    
    //拒绝
    public function refuse()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            Db::name('lc_recharge')->where('id', $data['id'])->update(['status' => 2, 'reason_zh_cn' => $data['reason_zh_cn'], 'reason_zh_hk' => $data['reason_zh_hk'], 'reason_en_us' => $data['reason_en_us']]);
            $this->success('操作成功');
        }
        $this->assign('id', $this->request->get('id'));
        return $this->fetch();
    }

    /**
     * 删除充值记录
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->applyCsrfToken();
        sysCheckLog('充值记录', '删除充值记录');
        $this->_delete($this->table);
    }
}
