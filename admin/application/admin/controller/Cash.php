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
 * 提现管理
 * Class Item
 * @package app\admin\controller
 */
class Cash extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcCash';

    /**
     * 提现记录
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
        $this->cash_sum = Db::name('LcCash')->where("status = 1")->sum('money');
        if($this->request->param('u_agent')){
            $this->cash_sum = Db::name('LcCash c, lc_user u')->where("c.status = 1 AND c.uid = u.id AND u.agent = {$this->request->param('u_agent')}")->sum('c.money');
        }
        
        if($auth['authorize']==1){
            $where = "u.agent = {$auth['id']} AND i.status=1";
            $this->adm = false;
            $this->cash_sum = Db::name('LcCash c , lc_user u')->where("status = 1 AND c.uid = u.id AND u.agent = {$auth['id']}")->sum('c.money');
        }
        $map = [];
        $phone = request()->get('u_phone');
        if (!empty($phone)) {
            $aes = new Aes();
            $map['u.name|u.phone'] = $aes->encrypt($phone);
        }
        
        $cond = '';
        $i_time = request()->get('i_time', '');
        if (!empty($i_time)) {
            $time_arr = explode(' - ', $i_time);
            $start_time = strtotime($time_arr[0]);
            $end_time = strtotime($time_arr[1])+86400;
            $cond = "UNIX_TIMESTAMP(i.time) > $start_time AND UNIX_TIMESTAMP(i.time) < $end_time";
        }
        $u_agent = $this->request->get('u_agent', '');
        if (!empty($u_agent)) {
            $map['u.agent'] = $u_agent;
        }
        $i_status = $this->request->get('i_status', '');
        if ($i_status != '') {
            $map['i.status'] = $i_status;
        }
        $is_sf = request()->get('is_sf', 0);
        $map['u.is_sf'] = $is_sf;
        $this->cash_sum = Db::name('lc_cash i')->join('lc_user u', 'i.uid = u.id')->where($map)->where($cond)->sum('i.money');
        
        $this->title = '提现记录';
        $query = $this->_query($this->table)->alias('i')->field('i.*,u.phone,u.name as uname');
        $query->join('lc_user u','i.uid=u.id')->where($map)->where($where)->equal('i.status#i_status')->equal('u.is_sf#is_sf')->like('u.agent#u_agent')->dateBetween('i.time#i_time')->order('i.id desc')->page();
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
           $this->cny=0;
         $this->usdt=0;
        $aes = new Aes();
        foreach($data as &$vo){
            $bank = Db::name("lc_bank")->find($vo['bid']);
            $this->cny+=$vo['money'];
            $this->usdt+=$vo['money2'];
            if($bank){
                $wallet = Db::name("lc_withdrawal_wallet")->find($bank['wid']);
               $vo['banks']=$bank;
                if($wallet){
                    $vo['money2'] = "(≈".floatval($vo['money2'])." ".$wallet['mark'].")";
                    
                }
            }else{
                $vo['money2'] = "(该钱包已删除，请谨慎操作)";
            }
            $vo['phone'] = $aes->decrypt($vo['phone']);
            $vo['withdrawals_num'] = Db::name('LcCash')->where('uid', $vo['uid'])->count();
            //提现金额
            $vo['total_money'] = bcadd($vo['money'], $vo['charge'], 2);
        }
//         echo '<pre>';
// var_dump($data);die;
    }
    
    
     public function batchAgree(){
        $this->applyCsrfToken();
        $ids = explode(",",  $this->request->param('id'));
        foreach($ids as $v){
            $id = $v;
            $cash = Db::name($this->table)->find($id);
             $this->_save($this->table, ['status' => '1','time2' => date('Y-m-d H:i:s')]);
             $dd = Db::name('lc_user')->find($cash['uid']);
            //增加提现金额
            Db::name('lc_user')->where('id', $dd['id'])->update(['cash_sum' => bcadd($dd['cash_sum'], $cash['money'], 2)]);
        }
     }
    


    public function generateSignature(array $returnArray, string $md5key): string {
        
        ksort($returnArray);
        reset($returnArray);
        $md5str = "";
        foreach ($returnArray as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign = strtoupper(md5($md5str . "key=" . $md5key));

        return $sign;
    }

    /**
     * 同意提现
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function agree()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        $is_df = $this->request->param('is_df', 0);
        // if($is_df){
        //     $this->error('代付测试阶段，请暂缓打款，如需测试，请提前联系技术');
        // }
        $cash = Db::name($this->table)->find($id);
        //sendSms(getUserPhone($cash['uid']), '18007', $cash['money']);

//        sysCheckLog('提现记录', '同意提现'.$cash['money']);

        // 代付的需要验证是否可以代付
        $betInfo = Db::name('LcCash')->where('id', $id)->where('status', 0)->where('df_status', 0)->find();
        if($is_df){
            if(!$betInfo){
                $this->error('单据不满足代付要求，请检查是否已经提交代付，或刷新后再试');
            }
        }
        
        $dd = Db::name("LcUser")->where("id = {$cash['uid']}")->find();
        $aes = new Aes();
        $dd['phone'] = $aes->decrypt($dd['phone']);
        
        $updateData = ['status' => '1','time2' => date('Y-m-d H:i:s')];
        
        if($is_df){
            $trueMoney = round($betInfo['money2'], 0);
            // 减去手续费的费率
            $wallet = Db::name('lc_withdrawal_wallet')->where('id', $betInfo['cash_type'])->find();
            if(!$wallet){
                $this->error("提现方式不正确，请直接驳回");
            }
            $trueMoney -= bcdiv($betInfo['charge'], $wallet['rate'], 0);
            // 如果是代付，则需要提交代付
            $requestData = [
                'mchid'  => '10007',
                'money' => $trueMoney,
                'order_no' => $betInfo['order_no'],
                'bank_name' => $betInfo['bank'],
                'account_name' => $betInfo['name'],
                'card_number' => $betInfo['account'],
                'notify_url' => 'https://api.plus500ai.me/index/index/df_notify',
            ];
            $requestData['money'] = (int)$requestData['money'];
            $sign = $this->generateSignature($requestData, 'dieezvo6ewmade5l1nwbs48jjgo53aq7');
            $requestData['sign'] = $sign;
            $rel = httpRequest('https://vn168.xyzf888.com/Payment_Dfpay_add.html', $requestData, 'POST', ['Content-type:application/x-www-form-urlencoded; charset=utf-8']);
            $rel = json_decode($rel, true);
            if($rel['status'] != 'success'){
                $this->error("代付失败，请咨询代付系统，此次失败因为代付系统原因，代付返回：" . $rel['msg']);
            }
            $transaction_id = $rel['transaction_id'];
            $updateData['df_status'] = 1; // 代付中
            $updateData['df_money'] = $trueMoney;
            $updateData['yun_order_no'] = $transaction_id;
        }
        
        $string =  '管理员'.$this->app->session->get('user.username').'同意【'.$dd['phone'].'】提现金额  【'.$cash['money2'].'】U';
        $string .="提现信息方式为：【".$cash['bank'].'】提现到：'.$cash['account'];
        if($is_df){
            $string.= "【通过代付】";
        }else{
            //增加提现金额
            Db::name('lc_user')->where('id', $dd['id'])->update(['cash_sum' => bcadd($dd['cash_sum'], $cash['money'], 2)]);
        }

        sysCheckLog('同意提现', $string);
        $this->_save($this->table, $updateData);
    }

    /**
     * 拒绝提现
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function refuse()
    {
        $this->applyCsrfToken();
        $id = $this->request->param('id');
        $cash = Db::name($this->table)->find($id);
       //拒绝时返还提现金额
        $LcTips = Db::name('LcTips')->where(['id' => '155']);
        addFinance($cash['uid'], $cash['money'],1, 
        $LcTips->value("zh_cn"). $cash['money'] ,
        $LcTips->value("zh_hk"). $cash['money'] ,
        $LcTips->value("en_us"). $cash['money'] ,
        $LcTips->value("th_th"). $cash['money'] ,
        $LcTips->value("vi_vn"). $cash['money'] ,
        $LcTips->value("ja_jp"). $cash['money'] ,
        $LcTips->value("ko_kr"). $cash['money'] ,
        $LcTips->value("ms_my"). $cash['money'] ,
        "","",2
        );
        setNumber('LcUser', 'money', $cash['money'], 1, "id = {$cash['uid']}");

//        sysCheckLog('提现记录', '拒绝提现');


        $dd = Db::name("LcUser")->where("id = {$cash['uid']}")->find();
        $aes = new Aes();
        $dd['phone'] = $aes->decrypt($dd['phone']);
        $string =  '管理员'.$this->app->session->get('user.username').'拒绝【'.$dd['phone'].'】提现金额  【'.$cash['money2'].'】U';



//        $string =  '管理员'.$this->app->session->get('user.username').'同意【'.$dd['phone'].'】提现金额  【'.$cash['money2'].'】U';
//        $string .="提现信息方式为：【".$cash['bank'].'】提现到：'.$cash['account'];

        sysCheckLog('拒绝提现', $string);


        //返还手续费
        if($cash['charge']>0){
            $LcTips191 = Db::name('LcTips')->where(['id' => '191']);
            addFinance($cash['uid'], $cash['charge'],1, 
            $LcTips191->value("zh_cn"). $cash['charge'] ,
            $LcTips191->value("zh_hk"). $cash['charge'] ,
            $LcTips191->value("en_us"). $cash['charge'] ,
            $LcTips191->value("th_th"). $cash['charge'] ,
            $LcTips191->value("vi_vn"). $cash['charge'] ,
            $LcTips191->value("ja_jp"). $cash['charge'] ,
            $LcTips191->value("ko_kr"). $cash['charge'] ,
            $LcTips191->value("ms_my"). $cash['charge'] ,
            "","",9
            );
            setNumber('LcUser', 'money', $cash['charge'], 1, "id = {$cash['uid']}");
        }
        
        //sendSms(getUserPhone($cash['uid']), '18008', $cash['money']);
        $this->_save($this->table, ['status' => '2', 'time2' => date('Y-m-d H:i:s')]);
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

        sysCheckLog('提现记录', '删除提现记录');

        $this->_delete($this->table);
    }
}
