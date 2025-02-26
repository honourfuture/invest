<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2023  TG@YLFC666 [   ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\api\controller;

use library\Controller;
use Endroid\QrCode\QrCode;
use think\Db;
use think\facade\Request;
use think\facade\Log;
/**
 * 用户中心infomyTeam
 * Class Index
 * @package app\index\controller
 */
class User extends Controller
{
    public function asset_balance()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 38616;
        $user = Db::name('lc_user')->field('asset,money')->find($uid);
        $this->success('获取成功', $user);
    }
    
    //余额变动记录
    public function balance_change()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 38611;
        $list = Db::name('lc_finance')->where('uid', $uid)
            ->whereNotIn('reason_type', [1,17,6,18])
            // ->where('zh_cn', 'notlike', '%首次投资%')
            ->field('id,money,zh_cn,reason_type,type')
            ->order('id desc')
            ->select();
        $this->success('获取成功', $list);
    }
    
    //资产变动记录
    public function asset_change()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 38611;
        $list = Db::name('lc_finance')->where('uid', $uid)
            ->whereIn('reason_type', [1,17,6,18])
            ->where('zh_cn', 'notlike', '%首次投资%')
            ->field('id,money,zh_cn,reason_type,type')
            ->order('id desc')
            ->select();
        $this->success('获取成功', $list);
    }
    
    //团队奖励
    public function team_reward()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 38598;
        $list = Db::name('lc_finance')->where('zh_cn', 'like', '升级%')->where('uid', $uid)
            ->field('id,money,zh_cn,time')
            ->order('id desc')->select();
        
        $this->success('获取成功', $list);
    }
    
    //查看会员权益
    public function member_privilege()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 38598;
        $user = Db::name("LcUser")->find($uid);
        $member = Db::name('lc_user_member')->find($user['member']);
        $next_member = Db::name('lc_user_member')->where('value', '>', $member['value'])->find();
        $progress = bcdiv(($user['value']-$member['value'])*100, $next_member['value']-$member['value']);
        $list = Db::name('lc_user_member')->order('value asc')->select();
        $data = [
            'member_name' => $member['name'],
            'cur_rate' => $member['rate'],
            'cur_value' => $user['value'],
            'next_value' => $next_member['value'],
            'progress' => $progress,
            'list' => $list
        ];
        $this->success('获取成功', $data);
    }
    
    //查看团队权益
    public function team_privilege()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 38598;
        //总投资额
        $members = Db::name('LcUser')->find($uid);
        $memberList = Db::name('LcUser')->field('id,phone,top,czmoney')->select();
        $itemList = $this->get_downline_list($memberList, $members['id']);
        $ids = [$uid];$comIds = [];
        foreach ($itemList as $item) {
            $ids[] = $item['id'];
            $comIds[] = $item['id'];
        }
        $totalInvest = Db::name('lc_invest t')->join('lc_item m','t.pid = m.id')
            ->where('m.index_type', '<>', 7)
            ->whereIn('t.uid', $ids)->sum('t.money');
            
        $tznum = Db::name('LcUser')->where([['top' ,'=',$uid],['grade_id','>',1]])->count();
        $huiyuannum = Db::name('LcUser')->where([['top', '=',$uid]])->count();
        //下一级升级条件
        $grade_info = Db::name('LcMemberGrade')->where("id", $members['grade_id'])->field("id,poundage,title,recom_number,all_activity,recom_tz")->find();
        $next_grade = Db::name('LcMemberGrade')->where("all_activity",'>',$grade_info['all_activity'])->field("id,poundage,title,recom_number,all_activity,recom_tz")->order('all_activity asc')->find();
        //当前团队投资额
        $tzCur = $totalInvest;
        $tzNeed = $next_grade['all_activity'] - $tzCur;
        if ($tzNeed <= 0) $tzNeed = '已达标';
        $tzProgress = intval($tzCur / $next_grade['all_activity'] * 100);
        //当前直推数量
        $ztCur = $huiyuannum;
        $ztNeed = $next_grade['recom_number'] - $ztCur;
        if ($ztNeed <= 0) $ztNeed = '已达标';
        $ztProgress = intval($ztCur / $next_grade['recom_number'] * 100);
        //团队数量
        $tdCur = $tznum;
        $tdNeed = $next_grade['recom_tz'] - $tdCur;
        if ($tdNeed <= 0) $tdNeed = '已达标';
        if ($next_grade['recom_tz'] == 0) {
            $tdNeed = '已达标';
            $tdProgress = 100;
        } else {
            $tdProgress = intval($tdCur / $next_grade['recom_tz'] * 100);
            if ($tdProgress == 100) $tdNeed = '已达标';
        }
        
         $data['next'] = [
            'touzi'=>['cur'=>$tzCur,'need'=>$tzNeed,'progress'=>$tzProgress],
            'huiyuan'=>['cur'=>$ztCur,'need'=>$ztNeed,'progress'=>$ztProgress],
            'tuanzhang'=>['cur'=>$tdCur,'need'=>$tdNeed,'progress'=>$tdProgress],
        ];
        $data['cur_name'] = $grade_info['title'];
        $data['next_name'] = $next_grade['title'];
        $data['poundage'] = $grade_info['poundage'];
        $data['list'] = Db::name('LcMemberGrade')->order('all_activity asc')->select();
        
        $this->success('获取成功', $data);
    }
    
    //获取复投率
    public function repeat_rate()
    {
        $this->success('获取成功', Db::name('lc_info')->find(1)['repeat_rate']);
    }
    
    //计算复投实际转入资产
    public function calc_repeat_asset()
    {
        $money = $this->request->get('money');
        $repeat_rate = Db::name('lc_info')->find(1)['repeat_rate'];
        $asset = bcadd($money, bcdiv($money*$repeat_rate, 100, 2), 2);
        $this->success('获取成功', $asset);
    }
    
    //复投
    public function repeat_invest()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 38598;
        $user = Db::name("LcUser")->find($uid);
        $data = $this->request->param();
        if (!isset($data['money']) || !$data['money']) {
            $this->error('参数异常');
        }
        if (!isset($data['password'])) {
            $this->error('参数异常');
        }
        //校验密码
        if (md5($data['password']) != $user['password2']) {
            $this->error('支付密码错误');
        }
        if ($user['money'] < $data['money']) {
            $this->error('余额不足');
        }
        
        $repeat_rate = Db::name('lc_info')->find(1)['repeat_rate'];
        $asset = bcadd($data['money'], bcdiv($data['money']*$repeat_rate, 100, 2), 2);
        //扣除余额记录
        Db::name('lc_finance')->insert([
            'uid' => $uid,
            'money' => $data['money'],
            'type' => 2,
            'zh_cn' => '复投减去余额钱包',
            'before' => $user['money'],
            'time' => date('Y-m-d H:i:s', time()),
            'reason_type' => 16
        ]);
        //资产变动记录
        Db::name('lc_finance')->insert([
            'uid' => $uid,
            'money' => $asset,
            'type' => 1,
            'zh_cn' => '复投增加资产钱包',
            'before' => $user['asset'],
            'time' => date('Y-m-d H:i:s', time()),
            'reason_type' => 18
        ]);
        Db::name('lc_user')->where('id', $uid)->update([
            'money' => bcsub($user['money'], $data['money'], 2),
            'asset' => bcadd($user['asset'], $asset, 2)
        ]);
        $this->success('提交成功');
    }
    
    public function info()
    {
        $domain = Request::domain();
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        if($user){
            
        
        $wait_money = Db::name('LcInvestList')->where("uid = $uid AND status = '0' AND money2 > 0")->sum('money2');
        $wait_lixi = Db::name('LcInvestList')->where("uid = $uid AND status = '0' AND money1 > 0")->sum('money1');

        $all_lixi = Db::name('LcInvestList')->where("uid = $uid AND status = '1' AND money1 > 0")->sum('money1');
        if(!empty($user["asset"])){
            $all_money = $user["asset"]; 
        }
        
        $certificate = Db::name('lc_certificate')->where('uid', $user['id'])->where('status', 0)->find();
        if($certificate) {
            $user['auth'] = 2;
            $user['name'] = $certificate['name'];
            $user['idcard'] = $certificate['idcard'];
            $user['card_front'] = $certificate['card_front'];
            $user['card_back'] = $certificate['card_back'];
        }
       
        // $all_money = $user["asset"];
        $sum_money = $all_money + $user["money"] + $user["ebao"] + $user['income'];
        // $all_money = Db::name('LcRecharge')->where("uid = $uid AND status = '1'")->sum('money2');
        $member_value = "";
        if ($user['member']) {
            $member_value = Db::name("LcUserMember")->where("id", $user['member'])->value("value");
        }
        $language='zh_cn';
      $list = Db::name('LcInvest')->field("$language,id,money,rate,hour,status,time,time2, pid, grouping_num, grouping_income")->where('uid', $uid)->order("id desc")->select();
       $wait_lixi=0;
        foreach ($list as $k=>$v){
            // item.money * item.rate / 100 * item.hour / 24
            if(!$v['status']) {
                $wait_lixi+=$v['money']*$v['rate']/100*$v['hour']/24;
            }
            
        }
        // 余额＝下级返佣+团队奖励+首次投资奖励+投资收益+投资本金+充值奖励+投资赠送红包
        //   $money=
        $total_recharge = Db::name('LcFinance')->where("uid = $uid AND type = '1' AND reason_type in(1,3)")->sum('money');
        $gr_recharge = Db::name('LcRecharge')->where("uid = $uid AND pid = 15  AND status = 1")->sum('money2');//个人充值
       $gr_recharges = Db::name('LcRecharge')->where("uid = $uid AND pid = 6  AND status = 1")->sum('money2');//个人充值
       $ye_recharge = Db::name('LcRecharge')->where("uid = $uid AND pid = 20 AND status = 1 AND type='余额转资产'")->sum('money');//余额转资产
       //$total_recharge=$gr_recharge+$ye_recharge+$gr_recharges;
       //$domain = Request::domain();
       $aes = new Aes();
       $user['phone'] = $aes->decrypt($user['phone']);
        $data = array(
            "wait_lixi" => sprintf("%.2f", $wait_lixi),
            "wait_money" => sprintf("%.2f", $wait_money),
            "all_lixi" => sprintf("%.2f", $all_lixi),
            "reward" => sprintf("%.2f", $user['reward']),
            "mobile" => $user['phone'],
            "money" => $user['money'],
            "all_money" => sprintf("%.2f", $all_money),
            "sum_money" => sprintf("%.2f", $sum_money),
            "name" => $user['name'],
            "idcard" => $user['idcard'],
            "uid" => $uid,
            "asset" => sprintf("%.2f", $all_money),
            "is_auth" => $user['auth'],
            "user_icon" => $domain . '/upload/' . $user['avatar'],
            "pointNum" => $user['point_num'],
            "vip_name" => $user['member'] ? getUserMember($user['member']) : '普通会员',
            "member_value" => $member_value,
            'kj_money' => $user['kj_money'],
            'income' => $user['income'],
            'total_recharge' => sprintf("%.2f", $total_recharge),
            'receipt_name' => $user['receipt_name'],
            'receipt_phone' => $user['receipt_phone'],
            'receipt_address' => $user['receipt_address'],
            "cur_value" => $user['value'],
        );
       
        if(stripos($user['avatar'],'http')!==false) $data['user_icon'] = $user['avatar'];
        $res = Db::name('LcUserMember')->where("value > ".$user['value'].' and value >0')->order('value asc')->find();
        $resa = Db::name('LcUserMember')->where("value < ".$res['value'].' and value >0')->order('value asc')->find();
        $data['progress'] = (($user['value']-$resa['value'])/($res['value']-$resa['value']))*100;
        $data['next_member'] = $res['value']-$user['value'];
        
        //项目收益
        $data['project_profit'] = 0;
        $project_list = $list = Db::name('LcInvest')->field("id,money,rate,hour,status,time,time2, pid, grouping_num, grouping_income,uid")->where('uid', $uid)->where('status', 1)->order("id desc")->select();
        foreach ($project_list as $value) {
            $data['project_profit'] += $value['money']*$value['rate']/100*$value['hour']/24;
        }
        //途游宝收益
        $data['ebao_profit'] = $user['ebao_total_income'];
        //盲盒收益
        $data['blind_profit'] = Db::name('LcFinance')->where('uid', $user['id'])->where('zh_cn', 'like', '盲盒产品到期奖励%')->sum('money');
        //数字藏品收益
        $data['figure_collect_profit'] = 0;
        $figure_collect_list = Db::name('LcFigureCollectLog')->where('uid', $user['id'])->where('status', 2)->select();
        foreach ($figure_collect_list as $value) {
            $data['figure_collect_profit'] = number_format($value['money']*$value['sell_rate']/100, 2);
        }
        //固定收益
        $data['gd_profit'] = bcmul($data['project_profit']+$data['ebao_profit']+$data['blind_profit']+$data['figure_collect_profit'], 1, 2);
        //推广奖
        $data['tg_profit'] = Db::name('LcFinance')->where('uid', $user['id'])->where('zh_cn', 'like', '下级%返佣')->sum('money');
        //团队奖励
        $data['team_profit'] = Db::name('LcFinance')->where('uid', $user['id'])->where('zh_cn', 'like', '升级为%')->sum('money');
        //不固定收益
        $data['no_gd_profit'] = bcadd($data['tg_profit'], $data['team_profit'], 2);
        //系统奖励
        $data['system_profit'] = 0;
        //注册
        $data['register_profit'] = Db::name('LcFinance')->where('uid', $user['id'])->where('zh_cn', 'like', '会员注册，系统赠送%')->sum('money');
        //实名认证
        $data['realauth_profit'] = Db::name('LcFinance')->where('uid', $user['id'])->where('zh_cn', 'like', '实名认证奖励%')->sum('money');
        //购买项目红包
        $data['buy_project_rb'] = Db::name('LcFinance')->where('uid', $user['id'])->where('zh_cn', 'like', '%投资红包%')->sum('money');
        //抽奖红包
        $data['cj_rb'] = Db::name('LcFinance')->where('uid', $user['id'])->where('zh_cn', 'like', '%抽奖获得%')->sum('money');
        $data['system_profit'] = bcadd($data['realauth_profit']+$data['realauth_profit']+$data['buy_project_rb'], $data['cj_rb'], 2);
        //总资产
        //途游宝
        $data['ebao'] = $user['ebao'];
        //途游宝产品
        $data['ebao_wait'] = 0;
        $ebao_product_list = Db::name('LcEbaoProductRecord')->where('uid', $user['id'])->where('status', 0)->select();
        foreach ($ebao_product_list as $value) {
            $cur_day = $value['lock_day'] - $value['current_day'];
            if ($cur_day) {
                $product = Db::name('LcEbaoProduct')->find($value['product_id']);
                $data['ebao_wait'] += bcdiv($value['money']*$product['day_rate']*$cur_day, 100, 2);
            }
        }
        //待收本金
        $data['wait_money'] = Db::name('LcInvestList')->where("uid = $uid AND status = '0' AND money2 > 0")->sum('money2');
        //待收利息
        $data['wait_lixi'] = 0;
        $wait_list = Db::name('LcInvest')->field("$language,id,money,rate,hour,status,time,time2, pid, grouping_num, grouping_income,uid")->where('uid', $uid)->where('status', 0)->order("id desc")->select();
        foreach ($wait_list as $value) {
            $data['wait_lixi']+=$value['money']*$value['rate']/100*$value['hour']/24;
        }
        ///盲盒本金+收益
        $data['blind_wait'] = 0;
        $blind_list = Db::name('LcBlindBuyLog')->where('uid', $user['id'])->where('status', 0)->select();
        foreach ($blind_list as $value) {
            $data['blind_wait'] += $value['money'] + $value['money']*$value['rate']/100;
        }
        //数字藏品+收益
        $data['figure_collect_wait'] = 0;
        $figure_collect_list = Db::name('LcFigureCollectLog')->where('uid', $user['id'])->whereIn('status', [0,1])->select();
        foreach ($figure_collect_list as $value) {
            $data['figure_collect_wait'] += $value['money'] + $value['money']*$value['sell_rate']/100;
        }
        //账户余额
        $data['total_asset'] = bcadd($data['ebao']+$data['ebao_wait']+$user['money']+$data['wait_money']+$data['wait_lixi']+$data['blind_wait'], $data['figure_collect_wait'], 2);
        
        
       $rate_usd = Db::name('lc_info')->find(1)['rate_usd'];
       $data['asset'] = $data['asset'].'≈'.bcdiv($data['asset'], $rate_usd, 2);
       $data['all_money'] = $data['all_money'].'≈'.bcdiv($data['all_money'], $rate_usd, 2);
       $data['money'] = $data['money'].'≈'.bcdiv($data['money'], $rate_usd, 2);
       $data['total_recharge'] = $data['total_recharge'].'≈'.bcdiv($data['total_recharge'], $rate_usd, 2);
       $data['wait_money'] = $data['wait_money'].'≈'.bcdiv($data['wait_money'], $rate_usd, 2);
       $data['wait_lixi'] = $data['wait_lixi'].'≈'.bcdiv($data['wait_lixi'], $rate_usd, 2);
       
         $this->success("获取成功", $data);
        }
       
    }

    

    /**
     * Describe:我的团队
     * DateTime: 2020/5/17 14:03
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function myTeam()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user_info = Db::name('LcUser')->where(['id' => $uid])->find();
        $member = Db::name('LcUser')->where(['id' => $uid])->value('member');
        $invite = Db::name('LcUser')->where(['id' => $uid])->value('invite');
        $reward = Db::name('LcUserMember')->where("id", $member)->field("invest1,invest2,invest3")->find();
        $qrCode = new QrCode();
          //$aap_down=Db::name('LcVersion')->where(['id' => 1])->value('android_app_down_url');
          $aap_down = getInfo('domain') . '/#/pages/main/login/reg?m=' . $invite;
        //$qrCode->setText(getInfo('domain') . '/#/register?m=' . $phone);
        // $qrCode->setText(getInfo('domain') . '/pages/main/login/reg?m=' . $invite);
          $qrCode->setText($aap_down);
        $qrCode->setSize(300);
        $shareCode = $qrCode->getDataUri();
        //$shareLink = getInfo('domain') . '/#/register?m=' . $phone;
        // var_dump(getInfo('domain'));die;
      
        
        $qrCode->setText($aap_down);
        $qrCode->setSize(300);
        $appDownCode = $qrCode->getDataUri();
        
        //$shareLink = getInfo('domain') . '/pages/main/login/reg?m=' . $invite;
        $shareLink = $aap_down;
        $top1 = Db::name('LcUser')->where(['top' => $uid])->field('id,phone,name,time, auth, czmoney')->order("czmoney desc")->select();
        $top2 = Db::name('LcUser')->where(['top2' => $uid])->field('id,phone,name,time, auth, czmoney')->order("czmoney desc")->select();
        $top3 = Db::name('LcUser')->where(['top3' => $uid])->field('id,phone,name,time, auth, czmoney')->order("czmoney desc")->select();

        $aes = new Aes();
        if (!empty($top1)) {
            foreach ($top1 as $key => $value) {
                $top1[$key]['time'] = date("Y/m/d H:i:s", strtotime($value['time']));
                $top1[$key]['phone'] = $aes->decrypt($value['phone']);
            }
        }
        if (!empty($top2)) {
            foreach ($top2 as $key => $value) {
                $top2[$key]['time'] = date("Y/m/d H:i:s", strtotime($value['time']));
                $top3[$key]['phone'] = $aes->decrypt($value['phone']);
            }
        }

        if (!empty($top3)) {
            foreach ($top3 as $key => $value) {
                $top3[$key]['time'] = date("Y/m/d H:i:s", strtotime($value['time']));
                $top3[$key]['phone'] = $aes->decrypt($value['phone']);
            }
        }
      $memberList = Db::name('LcUser')->field('id, phone, top,czmoney,name,time, auth')->select();
      
      $itemList = $this->get_downline_list($memberList,$uid);
    //   var_dump($itemList);die;
      $all_czmoney=0;
      $top4=[];
      $top5=[];
      $top6=[];
      $top7=[];
      $top8=[];
      $top9=[];
      $top10=[];     
       $is_sf = Db::name('LcUser')->where(['id' => $uid])->value('is_sf');
    //   var_dump($this->userInfo['czmoney']);
    //   var_dump($this->userInfo['is_sf']);die;
      if($is_sf==0){
        //   $all_czmoney=$this->userInfo['czmoney'];
            $all_czmoney = Db::name('LcUser')->where(['id' => $uid])->value('czmoney');
      }
                foreach ($itemList as $k=>$v){
                    $all_czmoney+=$v['czmoney'];
                    if($v['level']==4){
                       $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top4[]=$v;
                    //   $top4=array_merge($top4,$arr); 
                    }
                    if($v['level']==5){
                         $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top5[]=$v; 
                    }   
                    if($v['level']==6){
                         $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top6[]=$v; 
                    }
                    if($v['level']==7){
                         $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top7[]=$v; 
                    }  
                    if($v['level']==8){
                         $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top8[]=$v; 
                    }
                    if($v['level']==9){
                         $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top9[]=$v; 
                    }   
                    if($v['level']==10){
                        $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top10[]=$v;  
                    }
                                          
                }
        $myRecharge = Db::name('LcRecharge r , lc_user u')->where("status = 1 AND r.uid = u.id AND u.id = $uid")->sum('r.money');
        $top1Recharge = Db::name('LcRecharge r , lc_user u')->where("status = 1 AND r.uid = u.id AND u.top = $uid")->sum('r.money');
        $top2Recharge = Db::name('LcRecharge r , lc_user u')->where("status = 1 AND r.uid = u.id AND u.top2 = $uid")->sum('r.money');
        $top3Recharge = Db::name('LcRecharge r , lc_user u')->where("status = 1 AND r.uid = u.id AND u.top3 = $uid")->sum('r.money');
        $countRecharge = $myRecharge + $top1Recharge + $top2Recharge + $top3Recharge;
        $countCommission = Db::name('LcFinance')
                            ->where("uid = $uid")
                            ->where("reason_type = 3 OR reason_type = 4 OR reason_type = 5 OR reason_type = 6 OR reason_type = 7 OR reason_type = 8 OR reason_type = 10")
                            ->sum('money');
                            
        
        $myProject = Db::name('LcInvest r , lc_user u')->where("r.uid = u.id AND u.id = $uid")->sum('r.money');
        $top1Project =  Db::name('LcInvest r , lc_user u')->where("r.uid = u.id AND u.top = $uid")->sum('r.money');
        $top2Project =  Db::name('LcInvest r , lc_user u')->where("r.uid = u.id AND u.top2 = $uid")->sum('r.money');
        $top3Project =  Db::name('LcInvest r , lc_user u')->where("r.uid = u.id AND u.top3 = $uid")->sum('r.money');
        $countProject = $myProject + $top1Project + $top2Project + $top3Project;
                            
        //$countCommission = 10;                    
        $info = Db::name('LcInfo')->find(1);
        
        
        //总投资额
        $members = Db::name('LcUser')->find($uid);
        $memberList = Db::name('LcUser')->field('id,phone,top,czmoney')->select();
        $itemList = $this->get_downline_list($memberList, $members['id']);
        $ids = [$uid];$comIds = [];
        foreach ($itemList as $item) {
            $ids[] = $item['id'];
            $comIds[] = $item['id'];
        }
        $totalInvest = Db::name('lc_invest t')->join('lc_item m','t.pid = m.id')
            ->where('m.index_type', '<>', 7)
            ->whereIn('t.uid', $ids)->sum('t.money');
        
        //团队奖
        // $countCommission = Db::name('LcFinance')->whereIn('uid', $comIds)->where('reason_type', 'in', '5,7,8') -> sum('money');
        $countCommission = Db::name('LcFinance')->where('zh_cn', 'like', '升级为%')->whereIn('uid', $ids)->sum('money');
        // var_dump($ids);exit;
        
        $data = array(
            'share_image_url' => $shareCode,
            'share_link' => $shareLink,
            'user_icon' => getInfo('logo_img'),
            'invite' => $invite,
            'reward' => $reward,
            'top1' => $top1,
            'top2' => $top2,
            'top3' => $top3,
            'top4' => $top4,
            'top5' => $top5,
            'top6' => $top6,
            'top7' => $top7,
            'top8' => $top8,
            'top9' => $top9,
            'top10' => $top10,
            'aap_down'=>$aap_down,
            'appDownCode'=>$appDownCode,
            'count_recharge' => $countRecharge,
            'count_project' => $countProject,
            'countCommission' => $countCommission,
            'is_see' => $info['is_see'],
            'myTeanNum'=>count($itemList),
            'all_czmoney'=>sprintf("%.2f", $all_czmoney),
            'total_invest' => $totalInvest
        );
        $grade_info = Db::name('LcMemberGrade')->where("id", $user_info['grade_id'])->field("id,recom_number,all_activity,recom_tz")->find();
        $next_grade = Db::name('LcMemberGrade')->where("all_activity",'>',$grade_info['all_activity'])->field("id,recom_number,all_activity,recom_tz")->order('all_activity asc')->find();
        //var_dump($next_grade);
        //var_dump($grade_info);
        $tznum = Db::name('LcUser')->where([['top' ,'=',$uid],['grade_id','>',1]])->count();
        $huiyuannum = Db::name('LcUser')->where([['top', '=',$uid]])->count();
        // $data['next'] = [
        //     'touzi'=>['cur'=>$countRecharge,'need'=>$next_grade['all_activity']-$countRecharge < 0 ? '已达标':$next_grade['all_activity']-$countRecharge,'progress'=>(($countRecharge-$grade_info['all_activity'])/($next_grade['all_activity']-$grade_info['all_activity']))*100],
        //     'tuanzhang'=>['cur'=>$tznum,'need' => $next_grade['recom_tz']-$tznum < 0 ? '已达标' : $next_grade['recom_tz']-$tznum,'progress'=>(($tznum-$grade_info['recom_tz'])/($next_grade['recom_tz']-$grade_info['recom_tz']))*100],
        //     'huiyuan'=>['cur'=>$huiyuannum,'need'=>$next_grade['recom_number']-$huiyuannum,'progress'=>(($huiyuannum-$grade_info['recom_number'])/($next_grade['recom_number']-$grade_info['recom_number']))*100],
        // ];
        
        //下一级升级条件
        $grade_info = Db::name('LcMemberGrade')->where("id", $user_info['grade_id'])->field("id,recom_number,all_activity,recom_tz")->find();
        $next_grade = Db::name('LcMemberGrade')->where("all_activity",'>',$grade_info['all_activity'])->field("id,recom_number,all_activity,recom_tz")->order('all_activity asc')->find();
        if(!$next_grade) {
            $data['next'] = [
                'touzi'=>['cur'=>$totalInvest,'need'=>'已达标','progress'=>100],
                'huiyuan'=>['cur'=>$huiyuannum,'need'=>'已达标','progress'=>100],
                'tuanzhang'=>['cur'=>$tznum,'need'=>'已达标','progress'=>100],
            ];
        } else {
            //当前团队投资额
            $tzCur = $totalInvest;
            $tzNeed = $next_grade['all_activity'] - $tzCur;
            if ($tzNeed <= 0) $tzNeed = '已达标';
            if ($next_grade['all_activity']) {
                $tzProgress = intval($tzCur / $next_grade['all_activity'] * 100);
            } else {
                $tzProgress = 100;
            }
            //当前直推数量
            $ztCur = $huiyuannum;
            $ztNeed = $next_grade['recom_number'] - $ztCur;
            if ($ztNeed <= 0) $ztNeed = '已达标';
            if ($next_grade['recom_number']) {
                $ztProgress = intval($ztCur / $next_grade['recom_number'] * 100);
            } else {
                $ztProgress = 100;
            }
            
            //团队数量
            $tdCur = $tznum;
            $tdNeed = $next_grade['recom_tz'] - $tdCur;
            if ($tdNeed <= 0) $tdNeed = '已达标';
            if ($next_grade['recom_tz'] == 0) {
                $tdNeed = '已达标';
                $tdProgress = 100;
            } else {
                $tdProgress = intval($tdCur / $next_grade['recom_tz'] * 100);
                if ($tdProgress == 100) $tdNeed = '已达标';
            }
            
            $data['next'] = [
                'touzi'=>['cur'=>$tzCur,'need'=>$tzNeed,'progress'=>$tzProgress],
                'huiyuan'=>['cur'=>$ztCur,'need'=>$ztNeed,'progress'=>$ztProgress],
                'tuanzhang'=>['cur'=>$tdCur,'need'=>$tdNeed,'progress'=>$tdProgress],
            ];
        }
        // //当前团队投资额
        // $tzCur = $totalInvest;
        // $tzNeed = $next_grade['all_activity'] - $tzCur;
        // if ($tzNeed <= 0) $tzNeed = '已达标';
        // $tzProgress = intval($tzCur / $next_grade['all_activity'] * 100);
        // //当前直推数量
        // $ztCur = $huiyuannum;
        // $ztNeed = $next_grade['recom_number'] - $ztCur;
        // if ($ztNeed <= 0) $ztNeed = '已达标';
        // $ztProgress = intval($ztCur / $next_grade['recom_number'] * 100);
        // //团队数量
        // $tdCur = $tznum;
        // $tdNeed = $next_grade['recom_tz'] - $tdCur;
        // if ($tdNeed <= 0) $tdNeed = '已达标';
        // if ($next_grade['recom_tz'] == 0) {
        //     $tdNeed = '已达标';
        //     $tdProgress = 100;
        // } else {
        //     $tdProgress = intval($tdCur / $next_grade['recom_tz'] * 100);
        //     if ($tdProgress == 100) $tdNeed = '已达标';
        // }
        
        //  $data['next'] = [
        //     'touzi'=>['cur'=>$tzCur,'need'=>$tzNeed,'progress'=>$tzProgress],
        //     'huiyuan'=>['cur'=>$ztCur,'need'=>$ztNeed,'progress'=>$ztProgress],
        //     'tuanzhang'=>['cur'=>$tdCur,'need'=>$tdNeed,'progress'=>$tdProgress],
        // ];
        
        
        // $data['next'] = [
        //     'touzi'=>['cur'=>$totalInvest,'need'=>$next_grade['all_activity']-$totalInvest < 0 ? '已达标':$next_grade['all_activity']-$totalInvest,'progress'=>$next_grade['all_activity']* 100],
        //     'tuanzhang'=>['cur'=>$tznum,'need' => $next_grade['recom_tz']-$tznum < 0 ? '已达标' : $next_grade['recom_tz']-$tznum,'progress'=> $next_grade['recom_tz']* 100],
        //     'huiyuan'=>['cur'=>$huiyuannum,'need'=>$next_grade['recom_number']-$huiyuannum,'progress'=>$next_grade['recom_number'] * 100],
        // ];
        $teamName = Db::name('LcUser a , lc_member_grade b')->where("a.grade_id = b.id AND a.id = $uid")->value('b.title');
        $data['team_name'] = $teamName;
        $this->success("获取成功", $data);
    }
   
    public function get_downline_list($user_list, $telephone, $level = 0)
    {
        // var_dump($telephone);
        $arr = array();
        foreach ($user_list as $key => $v) { 
            // var_dump($v['id']);die;
            // if($level<=2){
                 if ($v['top'] == $telephone) {  //inviteid为0的是顶级分类
                $v['level'] = $level + 1;
                $arr[] = $v;
                // var_dump($arr);die;
                $arr = array_merge($arr, $this->get_downline_list($user_list, $v['id'], $level + 1));
            }
            // }
           
        }
        return $arr;
    }

    public function myNextTeam()
    {
        $this->checkToken();
//        var_dump();
//        exit;
        $post = $this->request->post();
        $uid = $post['userId'];
        $member = Db::name('LcUser')->where(['id' => $uid])->value('member');
        $memberData = Db::name('LcUser')->where(['id' => $uid])->find();
        $invite = Db::name('LcUser')->where(['id' => $uid])->value('invite');
        $reward = Db::name('LcUserMember')->where("id", $member)->field("invest1,invest2,invest3")->find();
        $qrCode = new QrCode();
        //$qrCode->setText(getInfo('domain') . '/#/register?m=' . $phone);
             $aap_down=Db::name('LcVersion')->where(['id' => 1])->value('android_app_down_url');
        //$qrCode->setText(getInfo('domain') . '/#/register?m=' . $phone);
        // $qrCode->setText(getInfo('domain') . '/pages/main/login/reg?m=' . $invite);
          $qrCode->setText($aap_down);
        $qrCode->setSize(300);
        $shareCode = $qrCode->getDataUri();
        //$shareLink = getInfo('domain') . '/#/register?m=' . $phone;
        $shareLink = getInfo('domain') . '/pages/main/login/reg?m=' . $invite;
        $top1 = Db::name('LcUser')->where(['top' => $uid])->field('id,phone,name,time, auth, czmoney')->order("czmoney desc")->select();
        $top2 = Db::name('LcUser')->where(['top2' => $uid])->field('id,phone,name,time, auth, czmoney')->order("czmoney desc")->select();
        $top3 = Db::name('LcUser')->where(['top3' => $uid])->field('id,phone,name,time, auth, czmoney')->order("czmoney desc")->select();


        if (!empty($top1)) {
            foreach ($top1 as $key => $value) {
                $top1[$key]['time'] = date("Y/m/d H:i:s", strtotime($value['time']));
            }
        }
        if (!empty($top2)) {
            foreach ($top2 as $key => $value) {
                $top2[$key]['time'] = date("Y/m/d H:i:s", strtotime($value['time']));
            }
        }

        if (!empty($top3)) {
            foreach ($top3 as $key => $value) {
                $top3[$key]['time'] = date("Y/m/d H:i:s", strtotime($value['time']));
            }
        }

   $memberList = Db::name('LcUser')->field('id, phone, top,czmoney,name,time, auth')->select();
      
      $itemList = $this->get_downline_list($memberList,$uid);
    //   var_dump($itemList);die;
      $all_czmoney=0;
      $top4=[];
      $top5=[];
      $top6=[];
      $top7=[];
      $top8=[];
      $top9=[];
      $top10=[];      
                foreach ($itemList as $k=>$v){
                    $all_czmoney+=$v['czmoney'];
                    if($v['level']==4){
                       $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top4[]=$v;
                    //   $top4=array_merge($top4,$arr); 
                    }
                    if($v['level']==5){
                         $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top5[]=$v; 
                    }   
                    if($v['level']==6){
                         $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top6[]=$v; 
                    }
                    if($v['level']==7){
                         $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top7[]=$v; 
                    }  
                    if($v['level']==8){
                         $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top8[]=$v; 
                    }
                    if($v['level']==9){
                         $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top9[]=$v; 
                    }   
                    if($v['level']==10){
                        $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                       $top10[]=$v;  
                    }
                                          
                }
        $myRecharge = Db::name('LcRecharge r , lc_user u')->where("status = 1 AND r.uid = u.id AND u.id = $uid")->sum('r.money');
        $top1Recharge = Db::name('LcRecharge r , lc_user u')->where("status = 1 AND r.uid = u.id AND u.top = $uid")->sum('r.money');
        $top2Recharge = Db::name('LcRecharge r , lc_user u')->where("status = 1 AND r.uid = u.id AND u.top2 = $uid")->sum('r.money');
        $top3Recharge = Db::name('LcRecharge r , lc_user u')->where("status = 1 AND r.uid = u.id AND u.top3 = $uid")->sum('r.money');
        $countRecharge = $myRecharge + $top1Recharge + $top2Recharge + $top3Recharge;
        $countCommission = Db::name('LcFinance')->where("uid = $uid AND reason LIKE '%推荐_%'")->sum('money');

        $data = array(
            'share_image_url' => $shareCode,
            'share_link' => $shareLink,
            'user_icon' => getInfo('logo_img'),
            'invite' => $invite,
            'reward' => $reward,
            'top1' => $top1,
            'top2' => $top2,
            'top3' => $top3,
            'top4' => $top4,
            'top5' => $top5,
            'top6' => $top6,
            'top7' => $top7,
            'top8' => $top8,
            'top9' => $top9,
            'all_czmoney'=>$all_czmoney,
            'top10' => $top10,
            'count_recharge' => $countRecharge,
            'countCommission' => $countCommission,
            'name' => $memberData['name'],
            'mobile' => $memberData['phone'],
        );
        $this->success("获取成功", $data);
    }
    
    /**
     * 计算利息
     * */
    public function nterest($money, $hour, $rate, $type){
        $rate = $rate / 100;
        $total = 0;
        switch ($type) {
            case 1:
                $total = $money * $rate * $hour;
                break;
            case 2:
                $q = ($hour / 24) < 1 ? 1 : ($hour / 24);
                $total = $money * $rate * $q;
                break;
            case 3:
                $q = ($hour / 24 /7 ) < 1 ? 1 : ($hour / 24 /7);
                $total = $money * $rate * $q;
                break;
            case 4:
                $q = ($hour / 24 / 30) < 1 ? 1 : ($hour / 24 / 30);
                $total = $money * $rate * $q;
                break;
            case 5:
                $text = '到期返本返利';
                $total = $money * $rate;
                break;
            default:
                $text = '未设置类型';
                break;
        }
        return round($total, 2);
    }
    
    /**
     * Describe:订单列表
     * DateTime: 2020/9/5 13:41
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function order()
    {

        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $language = $params["language"];

        $list = Db::name('LcInvest')->field("$language,id,money,rate,hour,status,time,time2, pid, grouping_num, grouping_income,uid")->where('uid', $uid)->order("id desc")->select();
        $wait_money = Db::name('LcInvestList')->where("uid = $uid AND status = '0' AND money2 > 0")->sum('money2');
        $wait_lixi = Db::name('LcInvestList')->where("uid = $uid AND status = '0' AND money1 > 0")->sum('money1');
    //       echo '<pre>';
    // var_dump($wait_lixi);die;
    //echo json_encode($list);exit;
       $wait_lixi=0;$all_money=0;
        foreach ($list as $k=>$v){
            // item.money * item.rate / 100 * item.hour / 24
            if(!$v['status']) {
                $wait_lixi+=$v['money']*$v['rate']/100*$v['hour']/24;
            } else {
                $all_money+=$v['money']*$v['rate']/100*$v['hour']/24;
            }
        }
        
        for($i = 0; $i < count($list); $i++){
            //获取用户信息
            $userInfo = Db::name('lcUser') -> where('id', $list[$i]['uid']) -> find();
            //获取项目信息
            $item = Db::name('lc_item') -> where('id', $list[$i]['pid']) -> find();
            //获取用户分组信息
            $member = Db::name('lcUserMember') -> where('id', $userInfo['member']) -> find();
            //获取首页项目加成
            if($item['show_home']==1){
                $next_member = Db::name("lcUserMember")->where('value > '.$member['value'])->order('value asc')->find();
                if($next_member) $member = $next_member;
            }
            //$list[$i]['sy'] = $list[$i]['money'] * ($list[$i]['money'] / 100) + $list[$i]['money'] * ($member['rate'] / 100);
            //$list[$i]['sy'] = $list[$i]['money'] * ($list[$i]['rate'] / 100) + $list[$i]['money'] * ($member['rate'] / 100);
            
            
            $nterest = $this -> nterest($list[$i]['money'], $list[$i]['hour'], $item['rate'], $item['cycle_type']);
            $userNterest = $this -> nterest($list[$i]['money'], $list[$i]['hour'], $member['rate'], $item['cycle_type']);
            // $list[$i]['sy'] = $nterest + $userNterest;
            $list[$i]['sy'] = bcadd($nterest, $userNterest, 2);
            
        }
        // $all_money = Db::name('LcInvestList')->where("uid = $uid AND status = '1' AND money1 > 0")->sum('money1');
        
        Log::record($all_money, 'error');
        $this->success("获取成功", ['list' => $list, 'on_money' => sprintf("%.2f", $wait_money), 'on_apr_money' => sprintf("%.2f", $wait_lixi), 'ok_apr_money' => sprintf("%.2f", $all_money)]);
    }

    /**
     * Describe:获取银行卡
     * DateTime: 2020/5/16 16:37
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function bank()
    {
        $this->checkToken();
        $userInfo = $this->userInfo;
        $bank = Db::name('LcBank')->where(['uid' => $userInfo['id']])->order('id desc')->select();
        foreach ($bank as $k => $v) {
            $bank[$k]['account'] = dataDesensitization($v['account'], 4, 8);
        }
        $data = array(
            'count' => count($bank),
            'list' => $bank,
        );
        $this->success("获取成功", $data);
    }

    /**
     * Describe:获取银行卡及支付宝
     * DateTime: 2020/5/17 21:59
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function my_bank()
    {
        $this->checkToken();
        $userInfo = $this->userInfo;
        $this->user = Db::name('LcUser')->find($userInfo['id']);
        $banks = Db::name('LcBank')->where('uid',$userInfo['id'])->select();
        
        $intPwd = $this->user['mwpassword2'] == '123456' ? true : false;
        $bank = Db::name('LcBank bank,lc_withdrawal_wallet wallet')->field("bank.account as account,bank.bank as bank,bank.id as id,wallet.charge as charge,wallet.type as type,wallet.rate as rate,wallet.mark as mark, bank.bank_type as bankType")->where('bank.wid=wallet.id AND wallet.show=1')->where(['bank.uid' => $userInfo['id']])->order('bank.id desc')->select();
        foreach ($bank as $k => $v) {
            if (strlen($v['account']) > 6) {
                $bank[$k]['account'] = dataDesensitization($v['account'], 2, strlen($v['account']) - 6);
            }
        }

        // 查询矿币兑换比例
        $machines = Db::name("LcMachines")->find();

        $data = array(
            'count' => count($bank),
            'bank' => $bank,
            'money' => $this->user['money'],
            'kjMoney' => $this->user['kj_money'],
            'intPwd' => $intPwd,
            'asset' => $this->user['asset'],
            'machines_rate' => $machines['rate'],
            'banksid' => $banks
        );
        $this->success("获取成功", $data);
    }

    /**
     * Describe:添加银行卡
     * DateTime: 2020/5/16 16:47
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function bank_add()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $card = input('post.account/s', '');
        $bank = input('post.bank/s', '');
        //$bankType = input('post.bankType/s', '');
        $area = input('post.area/s', '');
        $img = input('post.img/s', '');
        $type = input('post.type/s', '');
        $this->user = Db::name('LcUser')->find($uid);
        $name = $this->user['phone'];
        $params = $this->request->param();
        $language = $params["language"];
        $bankType = $params["bankType"];
        var_dump(123);exit;
        //判断是否添加过
        $bankRes = DB::name('lc_bank') -> where(['uid' => $uid, 'type' => $type]) -> find();
        if(!empty($bankRes['id'])) $this->error(Db::name('LcTips')->field("$language")->find('210'));
        
        //判断是否为实名名字
        if($type == 4 && (empty($params['name']) || $this->user['name'] != $params['name'])) $this->error(Db::name('LcTips')->field("$language")->find('211'));
        
        $user = Db::name('LcUser')->find($uid);
        if (!$params['code']) $this->error(Db::name('LcTips')->field("$language")->find('44'));
        $sms_code = Db::name("LcSmsList")->where("date_sub(now(),interval 5 minute) < time")->where("phone = '{$user['phone']}'")->order("id desc")->value('ip');
        if ($params['code'] != $sms_code) $this->error(Db::name('LcTips')->field("$language")->find('45'));
        
        if (!$card) $this->error(Db::name('LcTips')->field("$language")->find('79'));
        $wallet = Db::name('lc_withdrawal_wallet')->find($params['wid']);
        if (!$wallet) $this->error(Db::name('LcTips')->field("$language")->find('190'));
        if ($params['name']) {
            $name = $params['name'];
        }
        // if ($this->user['auth'] != 1) $this->error(Db::name('LcTips')->field("$language")->find('105'));
        $check_bank = Db::name('LcBank')->where(['account' => $card])->find();
        if ($check_bank) $this->error(Db::name('LcTips')->field("$language")->find('106'));
        if (getInfo('bank') == 1) {
            // $auth_check = bankAuth($this->user['name'], $card, $this->user['idcard']);
            // if ($auth_check['code'] == 0) $this->error($auth_check['msg']);
            $bank = $auth_check['bank'];
        }
        
        //记录IP信息和地址
        $ip = $this->request->ip();
            $ips = new \Ip2Region();
            $btree = $ips->btreeSearch($ip);
            $region = isset($btree['region']) ? $btree['region'] : '';
            $region = str_replace(['内网IP', '0', '|'], '', $region);
            // echo $region;

        $add = ['uid' => $uid, 'bank' => $bank, 'area' => $area, 'account' => $card, 'img' => $img, 'name' => $name, 'type' => $type, 'wid' => $wallet['id'], 'bank_type' => $bankType];
        $add['ip'] = $ip;
        $add['region'] = $region;
        // var_dump($add);exit;
        
        
        if (Db::name('LcBank')->insert($add)) $this->success(Db::name('LcTips')->field("$language")->find('107'));
        $this->error(Db::name('LcTips')->field("$language")->find('108'));
    }

    /**
     * Describe:删除银行卡
     * DateTime: 2020/5/16 16:38
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function bank_remove()
    {
        $this->checkToken();
        $userInfo = $this->userInfo;
        $id = input('post.id/d', '');
        $re = Db::name('LcBank')->where(['uid' => $userInfo['id'], 'id' => $id])->delete();
        if ($re) $this->success("操作成功");
        $this->error("操作失败");
    }

    /**
     * Describe:设置初始交易密码
     * DateTime: 2020/5/16 16:59
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function setIniPwd()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $language = $params["language"];
        $userInfo = $this->userInfo;

        $user = Db::name('LcUser')->find($uid);
        if (!$user) $this->error(Db::name('LcTips')->field("$language")->find('46'));

        if (payPassIsContinuity($params['password'])) $this->error(Db::name('LcTips')->field("$language")->find('122'));
        $data = ['password2' => md5($params['password']), 'mwpassword2' => $params['password']];
        //开启事务
        Db::startTrans();
        $res = Db::name('LcUser')->where('id', $uid)->update($data);
        if ($res) {
            Db::commit();
            $this->success(Db::name('LcTips')->field("$language")->find('112'));
        } else {
            Db::rollback();
            $this->error(Db::name('LcTips')->field("$language")->find('113'));
        }
    }

    /**
     * Describe:重置交易密码
     * DateTime: 2020/5/16 16:59
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function resetpaypwd_code()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $language = $params["language"];
        $userInfo = $this->userInfo;
        $user = Db::name('LcUser')->find($uid);
        if (!$user) $this->error(Db::name('LcTips')->field("$language")->find('46'));
        if (!$params['code']) $this->error(Db::name('LcTips')->field("$language")->find('119'));
        $sms_code = Db::name("LcSmsList")->where("phone = '{$user['phone']}'")->order("id desc")->value('ip');
        if ($params['code'] != $sms_code) $this->error(Db::name('LcTips')->field("$language")->find('120'));
        if (payPassIsContinuity($params['npassword'])) $this->error(Db::name('LcTips')->field("$language")->find('122'));
        $data = ['password2' => md5($params['npassword']), 'mwpassword2' => $params['npassword']];
        //开启事务
        Db::startTrans();
        $res = Db::name('LcUser')->where('id', $uid)->update($data);
        if ($res) {
            Db::commit();
            $this->success(Db::name('LcTips')->field("$language")->find('112'));
        } else {
            Db::rollback();
            $this->error(Db::name('LcTips')->field("$language")->find('113'));
        }
    }

    /**
     * Describe:提现申请
     * DateTime: 2020/5/16 18:06
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function cost_apply()
    {
        $this->checkToken();
        $params = $this->request->param();
        if(!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $params['money'])) $this->error('请输入正确的金额');
         //充值提现时间为：：9：00——24：00
        date_default_timezone_set("Asia/Shanghai");

        if(date('G')<9||date('G')>=22){
          //$this->error("提现时间为：9：00——22：00");die;
        }
        $language = $params["language"];
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        $this->min_cash = getInfo('cash');
        $this->withdraw_num = getInfo('withdraw_num');
        $this->bank = Db::name('LcBank')->where('uid', $uid)->order("id desc")->select();
        
        //判断当前是否绑定银行卡
        if (!Db::name('LcBank')->where(['uid' =>  $uid, 'bank' => '银行卡'])->find()) {
            $this->error('请先绑定银行卡');die;
        }
        
        if($this->user['money']==0){
            $this->error('你的账户余额不足');die;
        }
        if ($this->app->request->isPost()) {
            $bank = "";
            $wallet = "";
            //if ($this->user['auth'] != 1) $this->error(Db::name('LcTips')->field("$language")->find('126'));
            $data = $this->request->param();
            if (!is_numeric($data['money']) || $data['money'] <= 0) $this->error('ERROR 404');
            if (!$this->bank) $this->error(Db::name('LcTips')->field("$language")->find('127'));
            if ($data['bank_id'] != 0) {
                $bank = Db::name('LcBank')->where('id', $data['bank_id'])->find();
                if ($bank['uid'] != $uid || empty($bank)) $this->error(Db::name('LcTips')->field("$language")->find('128'));
            } else {
                if (empty($this->user['alipay'])) $this->error(Db::name('LcTips')->field("$language")->find('129'));
            }
            $wallet = Db::name('lc_withdrawal_wallet')->where('id', $bank['wid'])->find();
            if (!$wallet) $this->error(Db::name('LcTips')->field("$language")->find('128'));

            $invest = Db::name('LcInvest')->where('uid', $uid)->find();
            $today = date('Y-m-d 00:00:00');
            if ($this->user['password2'] != md5($data['passwd'])) $this->error(Db::name('LcTips')->field("$language")->find('130'));
            if ($data['money'] < $this->min_cash) {
                $returnData = array(
                    "$language" => Db::name('LcTips')->where(['id' => '131'])->value("$language") . $this->min_cash . Db::name('LcTips')->where(['id' => '180'])->value("$language")
                );
                $this->error($returnData);
            }
            if ($this->user['money'] < $data['money']) $this->error(Db::name('LcTips')->field("$language")->find('132'));
            if (empty($invest)) $this->error(Db::name('LcTips')->field("$language")->find('133'));
            if ($this->withdraw_num <= Db::name('LcCash')->where("uid = $uid AND time > '$today' AND (status = 1 OR status = 0)")->count()) {
                $returnData = array(
                    "$language" => Db::name('LcTips')->where(['id' => '134'])->value("$language") . $this->withdraw_num
                );
                $this->error($returnData);
            }
            $chargeMoney = 0.00;
            if ($wallet['charge'] > 0) {
                $chargeMoney = round($data['money'] * $wallet['charge'] / 100, 2);
            }
            $num11 = 2;
            if ($wallet['type'] == 1) {
                if ($wallet['rate'] > 10) $num11 = 4;
                if ($wallet['rate'] > 1000) $num11 = 6;
                if ($wallet['rate'] > 10000) $num11 = 8;
            }
            if ($data['bank_id'] == 0) {
                $add = array('uid' => $uid, 'name' => $bank['name'], 'bid' => $data['bank_id'], 'bank' => "Alipay", 'area' => 0, 'account' => $this->user['alipay'], 'money' => $data['money'], 'charge' => $chargeMoney, 'status' => 0, 'time' => date('Y-m-d H:i:s'), 'time2' => '0000-00-00 00:00:00');
            } else {
                $money2 = round($data['money'] / $wallet['rate'], $num11) - $chargeMoney;;
                if ($wallet['type'] == 1) { //改过
                    $money2 = round($data['money'] / $wallet['rate'], $num11) - $chargeMoney;
                } else if ($wallet['type'] == 4) {
                    $money2 = round($data['money'], $num11) - $chargeMoney;
                }
                $add = array('uid' => $uid, 'name' => $bank['name'], 'bid' => $data['bank_id'], 'bank' => $bank['bank'], 'area' => $bank['area'] ?: 0, 'account' => $bank['account'], 'img' => $bank['img'], 'money' => $data['money'] - $chargeMoney, 'money2' => $money2, 'charge' => $chargeMoney, 'status' => 0, 'time' => date('Y-m-d H:i:s'), 'time2' => '0000-00-00 00:00:00');
            }
             //内部账号提现自动审核
            if($this->user['is_sf']==1||$this->user['is_sf']==2){
                   if ($data['bank_id'] == 0) {
                $add = array('uid' => $uid, 'name' => $bank['name'], 'bid' => $data['bank_id'], 'bank' => "Alipay", 'area' => 0, 'account' => $this->user['alipay'], 'money' => $data['money'], 'charge' => $chargeMoney, 'status' => 1, 'time' => date('Y-m-d H:i:s'), 'time2' => '0000-00-00 00:00:00');
            } else {
                $money2 = round($data['money'] / $wallet['rate'], $num11) - $chargeMoney;;
                if ($wallet['type'] == 4) {
                    $money2 = round($data['money'] * $wallet['rate'], $num11) - $chargeMoney;
                } else if ($wallet['type'] == 1) {
                    $money2 = round($data['money'], $num11) - $chargeMoney;
                }
                $add = array('uid' => $uid, 'name' => $bank['name'], 'bid' => $data['bank_id'], 'bank' => $bank['bank'], 'area' => $bank['area'] ?: 0, 'account' => $bank['account'], 'img' => $bank['img'], 'money' => $data['money'] - $chargeMoney, 'money2' => $money2, 'charge' => $chargeMoney, 'status' => 1, 'time' => date('Y-m-d H:i:s'), 'time2' => '0000-00-00 00:00:00');
            }
            }
            if (Db::name('LcCash')->insert($add)) {
                //手续费
                $withdrawMoney = $data['money'];
                if ($wallet['charge'] > 0) {
                    $charge = round($data['money'] * $wallet['charge'] / 100, 2);
                    //提现金额为：提现金额-手续费
                    $withdrawMoney = $withdrawMoney - $charge;
                    $LcTips = Db::name('LcTips')->where(['id' => '191']);
                    addFinance($uid, $charge, 2,
                        $LcTips->value("zh_cn") . $charge,
                        $LcTips->value("zh_hk") . $charge,
                        $LcTips->value("en_us") . $charge,
                        $LcTips->value("th_th") . $charge,
                        $LcTips->value("vi_vn") . $charge,
                        $LcTips->value("ja_jp") . $charge,
                        $LcTips->value("ko_kr") . $charge,
                        $LcTips->value("ms_my") . $charge,
                        "", "", 9
                    );
                    setNumber('LcUser', 'money', $charge, 2, "id = $uid");
                }

                $desc = "";
                if ($wallet['type'] == 1) {
                    $desc = "余额提现至USDT";
                } else {
                    $desc = "余额提现至银行卡";
                }

                //提现流水
                $LcTips = Db::name('LcTips')->where(['id' => '136']);
                addFinance($uid, $withdrawMoney, 2,
                    $desc . $withdrawMoney,
                    $desc . $withdrawMoney,
                    $LcTips->value("en_us") . $withdrawMoney,
                    $LcTips->value("th_th") . $withdrawMoney,
                    $LcTips->value("vi_vn") . $withdrawMoney,
                    $LcTips->value("ja_jp") . $withdrawMoney,
                    $LcTips->value("ko_kr") . $withdrawMoney,
                    $LcTips->value("ms_my") . $withdrawMoney,
                    "", "", 2
                );
                setNumber('LcUser', 'money', $withdrawMoney, 2, "id = $uid");
                $this->success(Db::name('LcTips')->field("$language")->find('137'));
            } else {
                $this->error(Db::name('LcTips')->field("$language")->find('138'));
            }
        }
    }

    /**
     * Describe:提现列表
     * DateTime: 2020/5/17 13:41
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function cash_search()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $result = Db::name("LcCash")->where(['uid' => $uid, 'status' => 0])->select();
        $this->success('获取成功！', $result);
    }

    /**
     * Describe:充值选项
     * DateTime: 2020/5/17 13:41
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function recharge()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user = Db::name('LcUser')->find($uid);
        $info = Db::name('LcInfo')->find(1);
        $payment = Db::name('LcPayment')->where(['show' => 1])->where("level <= " . $user['value'])->order("sort asc,id desc")->select();
        $list = array();
        if ($payment) {
            foreach ($payment as $k => $v) {
                $list[$k]["id"] = $v["id"];
                $list[$k]["type"] = $v["type"];
                $list[$k]["logo"] = $v["logo"];
                $list[$k]["give"] = $v["give"];
                $list[$k]["rate"] = $v["rate"];
                $list[$k]["mark"] = $v["mark"];
                $list[$k]["description"] = $v["description"];
                switch ($v["type"]) {
                    case 1:
                        $list[$k]["name"] = $v["crypto"];
                        $list[$k]["address"] = $v["crypto_qrcode"];
                        $list[$k]["qrcode"] = $v["crypto_link"];
                        break;
                    case 2:
                        $list[$k]["name"] = $v["alipay"];
                        $list[$k]["qrcode"] = $v["alipay_qrcode"];
                        break;
                    case 3:
                        $list[$k]["name"] = $v["wx"];
                        $list[$k]["qrcode"] = $v["wx_qrcode"];
                        break;
                    case 4:
                        $list[$k]["name"] = $v["bank"];
                        $list[$k]["user"] = $v["bank_name"];
                        $list[$k]["account"] = $v["bank_account"];
                        break;
                    default:
                }
            }
        }
        $data = array(
            'money' => $user['money'],
            'min_recharge' => $info['min_recharge'],
            'payment' => $list,
        );
        $this->success('获取成功！', $data);
    }

    /**
     * Describe:钱包选项
     * DateTime: 2020/5/17 13:41
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function wallet_type()
    {
        $this->checkToken();
        $params = $this->request->param();
        $language = $params["language"];
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        if ($this->user['auth'] != 1) $this->error(Db::name('LcTips')->field("$language")->find('126'));
        $wallet = Db::name('lc_withdrawal_wallet')->where(['show' => 1])->order("sort asc,id desc")->select();
        $this->success('获取成功！', $wallet);
    }

    /**
     * Describe:充值
     * DateTime: 2020/5/17 13:26
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function recharge_type()
    {
        $this->checkToken();
        $params = $this->request->param();
         $money = $params["money"];
        //验证输入金额
        if(!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $money)) $this->error('请输入正确的金额');
        //充值提现时间为：：9：00——24：00
        date_default_timezone_set("Asia/Shanghai");

        if(date('G')<9){
        //   $this->error("充值时间为：9：00——24：00");die;
        }
        //var_dump($this->userInfo);exit;
        $language = $params["language"];
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
       
        $paymentId = $params["id"];
        $info = Db::name('LcInfo')->find(1);
        if ($money < $info['min_recharge']) {
            $returnData = array(
                "$language" => Db::name('LcTips')->where(['id' => '140'])->value("$language") . $info['min_recharge']
            );
            $this->error($returnData);
        }
        $payment = Db::name('LcPayment')->find($paymentId);
        $paymentArr = array();
        if (!$payment) {
            $this->error(Db::name('LcTips')->field("$language")->find('141'));
        } else {
            $paymentArr["id"] = $payment["id"];
            $paymentArr["type"] = $payment["type"];
            $paymentArr["give"] = $payment["give"];
            $paymentArr["logo"] = $payment["logo"];
            $paymentArr["rate"] = $payment["rate"];
            $paymentArr["mark"] = $payment["mark"];
            $paymentArr["description"] = $payment["description"];
            switch ($payment["type"]) {
                case 1:

                    $paymentArr["name"] = $payment["crypto"];
                    $paymentArr["qrcode"] = $payment["crypto_qrcode"];
                    $paymentArr["address"] = $payment["crypto_link"];
                    break;
                case 2:
                    $paymentArr["name"] = $payment["alipay"];
                    $paymentArr["qrcode"] = $payment["alipay_qrcode"];
                    break;
                case 3:
                    $paymentArr["name"] = $payment["wx"];
                    $paymentArr["qrcode"] = $payment["wx_qrcode"];
                    break;
                case 4:
                    $paymentArr["name"] = $payment["bank"] . "-" . $payment["bank_name"];
                    $paymentArr["bank"] = $payment["bank"];
                    $paymentArr["username"] = $payment["bank_name"];
                    $paymentArr["account"] = $payment["bank_account"];
                    break;
                default:
            }
        }
        //if ($this->user['auth'] != 1) $this->error(Db::name('LcTips')->field("$language")->find('142'), "", 405);
        $orderid = 'PAY' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
        $num11 = 2;
        if ($payment['type'] == 1) {
            if ($payment['rate'] > 10) $num11 = 4;
            if ($payment['rate'] > 1000) $num11 = 6;
            if ($payment['rate'] > 10000) $num11 = 8;
        }
        $add = array(
            'orderid' => $orderid,
            'uid' => $uid,
            'pid' => $paymentId,
            'money' => $money,
            'money2' => round($money / $payment['rate'], $num11),
            'type' => $paymentArr["name"],
            'status' => 3,
            'time' => date('Y-m-d H:i:s'),
            'time2' => '0000-00-00 00:00:00'
        );
   
        $re = Db::name('LcRecharge')->insertGetId($add);
             //如果是内部自动审核通过
        if( $this->user['is_sf']==1|| $this->user['is_sf']==2 ){
            
           $this->autoSh($uid,$re);
         
        }
        $data = array(
            'payment' => $paymentArr,
            'orderId' => $re
        );
        if ($re) $this->success('获取成功！', $data);
        $this->error("操作失败");
    }
 /*
     *充值自动审核 
     *
    */
   public function autoSh($uid,$oid){
        $recharge = Db::name('LcRecharge')->find($oid);
        if($recharge&&$recharge['status'] == 0||$recharge['status'] == 3){
            $money = $recharge['money'];
            $money2 = $recharge['money2'];
            $uid = $recharge['uid'];
            $type = $recharge['type'];

            $LcTips152 = Db::name('LcTips')->where(['id' => '152']);
            $LcTips153 = Db::name('LcTips')->where(['id' => '153']);
            addFinance($uid, $money2,1,
                $type .$LcTips152->value("zh_cn").$money2,
                $type .$LcTips152->value("zh_hk").$money2,
                $type .$LcTips152->value("en_us").$money2,
                $type .$LcTips152->value("th_th").$money2,
                $type .$LcTips152->value("vi_vn").$money2,
                $type .$LcTips152->value("ja_jp").$money2,
                $type .$LcTips152->value("ko_kr").$money2,
                $type .$LcTips152->value("ms_my").$money2,
                "","",1
            );
            setNumber('LcUser', 'asset', $money2, 1, "id = $uid");
            //成长值
            setNumber('LcUser','value', $money2, 1, "id = $uid");

            $dd = Db::name("LcUser")->where("id = {$uid}")->find();

            // 增加累计充值金额
            Db::name("LcUser")->where("id = {$uid}")->setInc("czmoney", $money2);


            // $string =  '管理员前台内部会员自动审核'.'同意【'.$dd['phone'].'】充值金额 【’'.$money2.'】U';
            // sysCheckLog('同意充值', $string);

            //设置会员等级
            $user = Db::name("LcUser")->find($uid);

            $memberId = setUserMember($uid,$user['value']);

            // 查询当前会员等级
            $userMember = Db::name("LcUserMember")->where(['id' => $memberId])->find();
            // 赠送充值奖励
            // $rechargeAmount = round($userMember['member_rate'] * $money2 / 100, 2);
            // if($rechargeAmount > 0){
            //     增加流水
            //     // $ebaoRecord = array(
            //     //     'uid' => $uid,
            //     //     'money' => $rechargeAmount,
            //     //     'type' => 1,
            //     //     'title' => '充值' . $money2 . "奖励" . $rechargeAmount,
            //     //     'time' => date('Y-m-d H:i:s')
            //     // );
            //     $int = Db::name('LcEbaoRecord')->insert($ebaoRecord);
            //     setNumber('LcUser','asset', $rechargeAmount, 1, "id = $uid");
            // }



            // gradeUpgrade($uid);

            //上级奖励（一、二、三级）
            $top=$user['top'];
            $top2=$user['top2'];
            $top3=$user['top3'];
            //一级
            $member_rate = Db::name("LcUserMember")->where(['id'=>$user['member']])->value("member_rate");
           
            setRechargeRebate1($uid, $money2,$member_rate,'个人充值奖励');
            //团队奖励
            //  $poundage = Db::name("LcMemberGrade")->where(['id'=>$user['grade_id']])->value("poundage");
            // setRechargeRebate1($uid, $money2,$poundage,'团队奖励');
            // //返给上级团长
            // $topuser = Db::name("LcUser")->find($top);
            // $poundage = Db::name("LcMemberGrade")->where(['id'=>$topuser['grade_id']])->value("poundage");
            // setRechargeRebate1($topuser['id'], $money2,$poundage,'团队奖励');
           
   }
       
   }
  
    /**
     * Describe:充值申请
     * DateTime: 2020/5/17 13:40
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function recharge_apply()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $data = $this->request->param();
        // var_dump($data);exit;
        $update = array('status' => '0',
            'warn' => '0',
            'bank_name'=>isset($data['bankName'])?$data['bankName']:'',
            'card_name'=>isset($data['cardName'])?$data['cardName']:'',
            'card_no'=>isset($data['cardNo'])?$data['cardNo']:'',
            'image' => $data['image']);
             $this->user = Db::name('LcUser')->find($uid);
           //如果是内部自动审核通过
        if( $this->user['is_sf']==1|| $this->user['is_sf']==2 ){
            $update['status']=1;
           
         
        }
        $re = Db::name('LcRecharge')->where(['uid' => $uid, 'status' => 3, 'id' => $data['id']])->update($update);
        if ($re) $this->success('获取成功！');
        $this->error("操作失败");
    }

    /**
     * Describe:充值申请
     * DateTime: 2020/5/17 13:40
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function bank_apply()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $data = $this->request->param();
        $update = array('status' => '0',
            'warn' => '0',
            'reason' => '付款人：' . $data['name'] . '<br/>转账附言：' . $data['remark']);
             $this->user = Db::name('LcUser')->find($uid);
           //如果是内部自动审核通过
        if( $this->user['is_sf']==1|| $this->user['is_sf']==2 ){
            $update['status']=1;
           
         
        }
        $re = Db::name('LcRecharge')->where(['uid' => $uid, 'status' => 3, 'id' => $data['id']])->update($update);
        if ($re) $this->success('获取成功！');
        $this->error("操作失败");
    }


    /**
     * @description：检查身份认证
     * @date: 2020/5/8 0008
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function check_auth()
    {
        $this->checkToken();
        $userInfo = $this->userInfo;
        $user = Db::name('LcUser')->find($userInfo['id']);
        $data = array(
            "idcard" => $user['idcard'],
            "is_auth" => $user['auth'] ? 'Y' : 'N',
            "mobile" => $user['phone'],
            "name" => $user['name']
        );
        $this->success("获取成功", $data);
    }

    /**
     * @description：身份认证
     * @date: 2020/5/15 0015
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function auth_email()
    {
        $this->checkToken();
        $userInfo = $this->userInfo;
        $params = $this->request->param();
        $language = $params["language"];
        $data = $this->request->param();
        $user = Db::name('LcUser')->find($userInfo['id']);
        if ($user['auth'] == 1) $this->error(Db::name('LcTips')->field("$language")->find('144'));
        if (!$data['code']) $this->error(Db::name('LcTips')->field("$language")->find('87'));
        $sms_code = Db::name("LcSmsList")->where("phone = '{$user['phone']}'")->order("id desc")->value('ip');

        if ($data['code'] != $sms_code) $this->error(Db::name('LcTips')->field("$language")->find('88'));
        //开启事务
        $data = ['auth' => 1];
        Db::startTrans();
        $res = Db::name('LcUser')->where('id', $userInfo['id'])->update($data);
        if ($res) {
            Db::commit();
            $this->success(Db::name('LcTips')->field("$language")->find('148'));
        } else {
            Db::rollback();
            $this->error(Db::name('LcTips')->field("$language")->find('149'));
        }
    }

    /**
     * @description：签到
     * @date: 2020/5/15 0015
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function sign()
    {

        $this->checkToken();
        $params = $this->request->param();
        $language = $params["language"];
        $uid = $this->userInfo['id'];
        // 获取签到次数和签到时间
        $user = Db::name('LcUser')->field("qiandao,qdnum,member, point_num")->find($uid);
        $today = strtotime(date('Y-m-d'));
        // 如果已经签到
        if ($today <= strtotime($user['qiandao'])) $this->error(Db::name('LcTips')->field("$language")->find('188'));
        // 增加签到次数
        $days = $user['qdnum'] + 1;

        $num = 0;
        // 类型，0赠送积分，1赠送矿币
        $type = 0;

        // 如果是挖矿日
        $currentSignTime = Db::name("LcSignReward")
            ->where("to_days(day) = to_days(now())")
            ->find();
        if ($currentSignTime) {
            // 查询当前用户等级
            // 查询当前用户等级
            $memberLevel = Db::name("LcUserMember")->where(['id' => $user['member']])->find();

            // 获取矿币数量
            $num = rand($memberLevel['min_sign_ibm'], $memberLevel['max_sign_ibm']);
            // 开始赠送矿币
            Db::name('LcUser')->where("id = {$uid}")->setInc("kj_money", $num);

            // 增加明细
            $record = array(
                'uid' => $uid,
                'amount' => $num,
                'type' => 1,
                'add_time' => date('Y-m-d H:i:s'),
                'title' => '挖矿日'
            );
            Db::name("LcMechinesFinance")->insertGetId($record);
            // 设置类型为奖励矿币
            $type = 1;
        } else {
            // 执行积分奖励
            $num = getReward('qiandao');
            // 赠送积分奖励
            Db::name("LcUser")->where("id = {$uid}")->setInc("point_num", $num);

            // 创建积分明细
            //$LcTips75 = Db::name('LcTips')->where(['id' => '75']);
            $pointRecord = array(
                'uid' => $uid,
                'num' => $num,
                'type' => 1,
                'zh_cn' => "签到赠送积分",
                'zh_hk' => "签到赠送积分",
                'en_us' => "签到赠送积分",
                'th_th' => "签到赠送积分",
                'vi_vn' => "签到赠送积分",
                'ja_jp' => "签到赠送积分",
                'ko_kr' => "签到赠送积分",
                'ms_my' => "签到赠送积分",
                'time' => date('Y-m-d H:i:s'),
                'before' => $user['point_num']
            );
            Db::name('LcPointRecord')->insert($pointRecord);
        }


        Db::name('LcUser')->where(['id' => $uid])->update(['qiandao' => date('Y-m-d H:i:s')]);
        Db::name("LcUserSignLog")->insert(['date' => date("Y-m-d"), 'uid' => $uid]);
        setNumber('LcUser', 'qdnum', 1, 1, "id=$uid");


        $this->success("签到成功", ['type' => $type, 'num' => $num]);


        // $this->checkToken();
        // $params = $this->request->param();
        // $language = $params["language"];
        // $uid = $this->userInfo['id'];
        // // 获取签到次数和签到时间
        // $user = Db::name('LcUser')->field("qiandao,qdnum")->find($uid);
        // $today = strtotime(date('Y-m-d'));
        // // 如果已经签到
        // if ($today <= strtotime($user['qiandao'])) $this->error(Db::name('LcTips')->field("$language")->find('188'));
        // // 增加签到次数
        // $days = $user['qdnum'] + 1;
        // // 如果有符合次数到签到配置
        // $reward = Db::name("LcSignReward")->where(['days' => $days])->find();
        // // 如果有签到记录
        // if ($reward) {
        //     $money = $reward['reward_num'];
        //     // 赠送签到奖励
        //     $this->sign_reward_money($reward['reward_num'], $uid, $reward['machines_num']);
        // } else {
        //     // 第一次签到
        //     $money = getReward('qiandao');
        //     $this->sign_reward_money($money, $uid, 0);
        // }
        // Db::name('LcUser')->where(['id' => $uid])->update(['qiandao' => date('Y-m-d H:i:s')]);
        // Db::name("LcUserSignLog")->insert(['date' => date("Y-m-d"), 'uid' => $uid]);
        // setNumber('LcUser', 'qdnum', 1, 1, "id=$uid");
        // $this->success("签到成功", ['days' => $days, 'reward_num' => $money, 'reward_type' => 1]);
    }

    /**
     * Describe:签到处理
     * DateTime: 2020/9/5 18:23
     * @param $money
     * @param $uid
     * @param $machineNum 矿机数量
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function sign_reward_money($money, $uid, $machineNum)
    {
        $LcTips186 = Db::name('LcTips')->where(['id' => '189']);
        addFinance($uid, $money, 1,
            $LcTips186->value("zh_cn") . $money,
            $LcTips186->value("zh_hk") . $money,
            $LcTips186->value("en_us") . $money,
            $LcTips186->value("th_th") . $money,
            $LcTips186->value("vi_vn") . $money,
            $LcTips186->value("ja_jp") . $money,
            $LcTips186->value("ko_kr") . $money,
            $LcTips186->value("ms_my") . $money,
            "", "", 4
        );
        setNumber('LcUser', 'money', $money, 1, "id=$uid");
        setNumber('LcUser', 'reward', $money, 1, "id=$uid");


        // 是否有赠送矿机
        if ($machineNum > 0) {
            // 获取矿机配置时间
            $machines = Db::name('LcMachines')->find(1);
            // 赠送矿机
            $machinesList = array(
                'uid' => $uid,
                'end_time' => date("Y-m-d H:i:s", strtotime("+" . $machines['days'] . " day")),
                'time' => date('Y-m-d H:i:s'),
                'next_run_time' => date("Y-m-d H:i:s", strtotime("+6 hour")),
                'num' => $machineNum
            );
            $int = Db::name('LcMachinesList')->insert($machinesList);
        }
    }

    /**
     * Describe:本月签到记录
     * DateTime: 2020/9/5 16:17
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function sign_log()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $month = getAllMonthDays();
        foreach ($month as $k => $v) {
            $sign_log = Db::name("LcUserSignLog")->where(['date' => $v, 'uid' => $uid])->find();
            $data[$k]['date'] = $v;
            $data[$k]['is_signin'] = $sign_log ? 1 : 0;
        }
        $this->success("获取成功", ['date_list' => $data]);
    }

    /**
     * Describe:签到奖励列表
     * DateTime: 2020/9/5 17:47
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function sign_reward()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $today = strtotime(date('Y-m-d'));
        $today_sign = false;
        $user = Db::name("LcUser")->field("qiandao,qdnum")->find($uid);
        $sign_num = $user['qdnum'];
        if ($today <= strtotime($user['qiandao'])) $today_sign = true;
        if (!$today_sign) $sign_num = $sign_num + 1;
        $today_reward = Db::name("LcSignReward")->where(['days' => $sign_num])->find();
        if (!$today_reward) {
            $today_reward['reward_type'] = 1;
            $today_reward['reward_num'] = getReward('qiandao');
        }
        $reward = Db::name('LcSignReward')->select();
        foreach ($reward as &$v) {
            $v['can_draw'] = $user['qdnum'] >= $v['days'] ? 2 : 0;
        }
        $this->success("获取成功", ['reward_list' => $reward, 'signin_days' => $user['qdnum'], 'isSign' => $today_sign, 'today_reward' => $today_reward]);
    }

    /**
     * @description：消息列表
     * @date: 2020/5/15 0015
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function notice()
    {
        $this->checkToken();
         $uid = $this->userInfo['id'];
        $msgtop = Db::name('LcMsg')->alias('msg')->where('(msg.uid = ' . $uid . ' or msg.uid = 0 ) and (select count(*) from lc_msg_is as msg_is where msg.id = msg_is.mid  and ((msg.uid = 0 and msg_is.uid = ' . $uid . ') or ( msg.uid = ' . $uid . ' and msg_is.uid = ' . $uid . ') )) = 0')->select();
         
        $msgfoot = Db::name('LcMsg')->alias('msg')->where('(select count(*) from lc_msg_is as msg_is where msg.id = msg_is.mid and msg_is.uid = ' . $uid . ') > 0')->select();
     
        $list = [];
        if ($msgtop) {
            foreach ($msgtop as $v) {
                $push['id'] = $v['id'];
                $push['time'] = $v['add_time'];
                $push['title'] = $v['title'];
                $push['title_zh_cn'] = $v['title_zh_cn'];
                $push['title_zh_hk'] = $v['title_zh_hk'];
                $push['title_en_us'] = $v['title_en_us'];
                $push['title_th_th'] = $v['title_th_th'];
                $push['title_vi_vn'] = $v['title_vi_vn'];
                $push['title_ja_jp'] = $v['title_ja_jp'];
                $push['title_ko_kr'] = $v['title_ko_kr'];
                $push['title_ms_my'] = $v['title_ms_my'];
                $push['content'] = strip_tags($v['content']);
                $push['content_zh_cn'] = strip_tags($v['content_zh_cn']);
                $push['content_zh_hk'] = strip_tags($v['content_zh_hk']);
                $push['content_en_us'] = strip_tags($v['content_en_us']);
                $push['content_th_th'] = strip_tags($v['content_th_th']);
                $push['content_vi_vn'] = strip_tags($v['content_vi_vn']);
                $push['content_ja_jp'] = strip_tags($v['content_ja_jp']);
                $push['content_ko_kr'] = strip_tags($v['content_ko_kr']);
                $push['content_ms_my'] = strip_tags($v['content_ms_my']);
                $push['is_read'] = false;
                array_push($list, $push);
            }
        }
        if ($msgfoot) {
            foreach ($msgfoot as $v) {
                $push['id'] = $v['id'];
                $push['time'] = $v['add_time'];
                $push['title'] = $v['title'];
                $push['title_zh_cn'] = $v['title_zh_cn'];
                $push['title_zh_hk'] = $v['title_zh_hk'];
                $push['title_en_us'] = $v['title_en_us'];
                $push['title_th_th'] = $v['title_th_th'];
                $push['title_vi_vn'] = $v['title_vi_vn'];
                $push['title_ja_jp'] = $v['title_ja_jp'];
                $push['title_ko_kr'] = $v['title_ko_kr'];
                $push['title_ms_my'] = $v['title_ms_my'];
                $push['content'] = strip_tags($v['content']);
                $push['content_zh_cn'] = strip_tags($v['content_zh_cn']);
                $push['content_zh_hk'] = strip_tags($v['content_zh_hk']);
                $push['content_en_us'] = strip_tags($v['content_en_us']);
                $push['content_th_th'] = strip_tags($v['content_th_th']);
                $push['content_vi_vn'] = strip_tags($v['content_vi_vn']);
                $push['content_ja_jp'] = strip_tags($v['content_ja_jp']);
                $push['content_ko_kr'] = strip_tags($v['content_ko_kr']);
                $push['content_ms_my'] = strip_tags($v['content_ms_my']);
                $push['is_read'] = true;
                array_push($list, $push);
            }
        }
        $this->success("获取成功", ['list' => $list, 'ok_read_num' => count($msgtop)]);
    }

    /**
     * @description：读取信息
     * @date: 2020/5/15 0015
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function notice_view()
    {
        $this->checkToken();
        $id = $this->request->param('id');
        $uid = $this->userInfo['id'];
        $where['uid'] = $uid;
        $where['mid'] = $id;
        $ret = Db::name('LcMsgIs')->where($where)->find();
        if (!$ret) Db::name('LcMsgIs')->insertGetId(['uid' => $uid, 'mid' => $id]);
        $notice = Db::name('LcMsg')->find($id);
        $data = array('view' => $notice,);
        $this->success("获取成功", $data);
    }

    // 全部已读
    public function notice_read()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $msgtop = Db::name('LcMsg')->alias('msg')->where('(msg.uid = ' . $uid . ' or msg.uid = 0 ) and (select count(*) from lc_msg_is as msg_is where msg.id = msg_is.mid  and ((msg.uid = 0 and msg_is.uid = ' . $uid . ') or ( msg.uid = ' . $uid . ' and msg_is.uid = ' . $uid . ') )) = 0')->select();
        if ($msgtop) {
            foreach ($msgtop as $v) {
                $where['mid'] = $v['id'];
                $where['uid'] = $uid;
                $ret = Db::name('LcMsgIs')->where($where)->find();
                if (!$ret) Db::name('LcMsgIs')->insertGetId(['uid' => $uid, 'mid' => $v['id']]);
            }
        }
        $this->success("获取成功", []);
    }

    /**
     * @description：资金流水
     * @date: 2020/5/15 0015
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function funds()
    {
        $this->checkToken();
        $language = $this->request->param('language');
        $uid = $this->userInfo['id'];
        $reason_id = $this->request->param('reason_id');
        $reason = array(
            "1" => "充值",
            "2" => "提现",
            "3" => "赠送",
            "4" => "签到",
            "5" => "分享奖励",
            "6" => "购买商品",
            "7" => "红包",
            "8" => "奖励",
            "9" => "新人福利",
            "10" => "推荐",
            "11" => "收益",
        );
        $user = Db::name("LcUser")->find($uid);
        $where[] = ['uid', 'eq', $uid];
        if ($reason_id) $where[] = ['reason_type', 'eq', "$reason_id"];
        $data = Db::name('LcFinance')->field("$language,id,money,type,reason,before,time,remark,reason_type")->where($where)->order("id desc")->select();
        // foreach ($data as $value) {
        //     echo $value['money'];
        // }exit();
        $money = array_column($data, "money");
        $this->success("获取成功", ['list' => $data, 'asset' => $user['asset'], 'money' => $user['money'], 'username' => $user['name'] ?: $user['phone'], 'share_reward' => array_sum($money)]);
    }


    /**
     * @description：积分流水
     * @date: 2022/12/4 0015
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function pointRecord()
    {
        $this->checkToken();
        $language = $this->request->param('language');
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        $where[] = ['uid', 'eq', $uid];
        $data = Db::name('LcPointRecord')->field("$language,id,num,type,reason,before,time,remark")->where($where)->order("id desc")->select();
        $this->success("获取成功", ['list' => $data, 'pointNum' => $user['point_num'], 'username' => $user['name'] ?: $user['phone']]);
    }


    /**
     * 途游宝信息
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ebaoInfo()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);

        // 查询利率
        $reward = Db::name("LcReward")->find(1);

        $data = array(
            "ebao" => $user['ebao'],
            'ebao_total_income' => $user['ebao_total_income'],
            'ebao_last_income' => $user['ebao_last_income'],
            'ebao_rate' => $reward['ebao_rate']
        );
        $this->success("获取成功", $data);
    }


    /**
     * 途游宝转入
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function addEbao()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        $params = $this->request->param();
        $money = floatval($params["money"]);
        if($money<=0) $this->error('请输入正确的金额');
        if($this->user['money']<=0) $this->error('余额不足');
        
        $language = $params["language"];
        if(!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $money)) $this->error('请输入正确的金额');
        // 余额够不够
        if ($this->user['money'] < $money)
            $this->error(Db::name('LcTips')->field("$language")->find('65'));

        // 查询最低转入限制是否达标
        $reward = Db::name("LcReward")->find(1);

        if ($money < $reward['ebao_min']) {
            $this->error(array(
                'zh_cn' => "转入金额没有达到途游宝最低限制金额：" . $reward['ebao_min'] . "USDT"
            ));
        } else if ($money > $reward['ebao_max']) {
            $this->error(array(
                'zh_cn' => "转入金额超过途游宝最高限制金额：" . $reward['ebao_min'] . "USDT"
            ));
        }

        $LcTips73 = Db::name('LcTips')->where(['id' => '73']);

        // 扣除用户余额
        // addFinance($uid, $money, 2,
        //     $LcTips73->value("zh_cn") . '《转入途游宝》，' . $money,
        //     $LcTips73->value("zh_hk") . '《转入途游宝》，' . $money,
        //     $LcTips73->value("en_us") . '《转入途游宝》，' . $money,
        //     $LcTips73->value("th_th") . '《转入途游宝》，' . $money,
        //     $LcTips73->value("vi_vn") . '《转入途游宝》，' . $money,
        //     $LcTips73->value("ja_jp") . '《转入途游宝》，' . $money,
        //     $LcTips73->value("ko_kr") . '《转入途游宝》，' . $money,
        //     $LcTips73->value("ms_my") . '《转入途游宝》，' . $money,
        //     "", "", 6
        // );
        addFinance($uid, $money, 2,
            $LcTips73->value("zh_cn") . '《转入途游宝》，' . $money,
            $LcTips73->value("zh_hk") . '《转入途游宝》，' . $money,
            $LcTips73->value("en_us") . '《转入途游宝》，' . $money,
            $LcTips73->value("th_th") . '《转入途游宝》，' . $money,
            $LcTips73->value("vi_vn") . '《转入途游宝》，' . $money,
            $LcTips73->value("ja_jp") . '《转入途游宝》，' . $money,
            $LcTips73->value("ko_kr") . '《转入途游宝》，' . $money,
            $LcTips73->value("ms_my") . '《转入途游宝》，' . $money,
            "", "", 19
        );
        setNumber('LcUser', 'money', $money, 2, "id = $uid");


        // 增加途游宝流水
        $ebaoRecord = array(
            'uid' => $uid,
            'money' => $money,
            'type' => 1,
            'title' => '途游宝转入 ' . $money,
            'time' => date('Y-m-d H:i:s')
        );
        $int = Db::name('LcEbaoRecord')->insert($ebaoRecord);

        // 重置时间
        Db::name('LcUser')->where("id = $uid")->update([
            'ebao_next_time' => date("Y-m-d H:i:s", strtotime("+1 hours"))
        ]);

        // 增加途游宝金额
        Db::name('LcUser')->where("id = $uid")->setInc('ebao', $money);


        $this->success(array(
            'zh_cn' => '操作成功'
        ));
    }


    public function transferTo()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        $params = $this->request->param();
        $money = $params["amount"];
        $language = $params["language"];

        // 余额够不够
        if ($this->user['asset'] < $params["amount"]) $this->error(Db::name('LcTips')->field("$language")->find('65'));

        // 查询最低转入限制是否达标
//        $reward = Db::name("LcReward")->find(1);
//
//        if($money < $reward['ebao_min']){
//            $this->error(array(
//                'zh_cn' => "转入金额没有达到途游宝最低限制金额：" . $reward['ebao_min'] . "USDT"
//            ));
//        }else if($money > $reward['ebao_max']){
//            $this->error(array(
//                'zh_cn' => "转入金额超过途游宝最高限制金额：" . $reward['ebao_min'] . "USDT"
//            ));
//        }

        $LcTips73 = Db::name('LcTips')->where(['id' => '73']);

        // 扣除用户余额
        addFinance($uid, $money, 1,
            $LcTips73->value("zh_cn") . '《资产转入余额》，' . $money,
            $LcTips73->value("zh_hk") . '《资产转入余额》，' . $money,
            $LcTips73->value("en_us") . '《资产转入余额》，' . $money,
            $LcTips73->value("th_th") . '《资产转入余额》，' . $money,
            $LcTips73->value("vi_vn") . '《资产转入余额》，' . $money,
            $LcTips73->value("ja_jp") . '《资产转入余额》，' . $money,
            $LcTips73->value("ko_kr") . '《资产转入余额》，' . $money,
            $LcTips73->value("ms_my") . '《资产转入余额》，' . $money,
            "", "", 6
        );
        setNumber('LcUser', 'money', $money, 1, "id = $uid");
      
        setNumber('LcUser', 'asset', $money, 2, "id = $uid");
        $orderid = 'PAY' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
        $add = array(
            'orderid' => $orderid,
            'uid' => $uid,
            'pid' => 20,
            'money' => $money,
            'money2' => $money,
            'type' => '余额转资产',
            'status' => 1,
            'time' => date('Y-m-d H:i:s'),
            'time2' => '0000-00-00 00:00:00'
        );
        $re = Db::name('LcRecharge')->insertGetId($add);

        $addt = array('uid' => $uid, 'name' => $this->user['name'], 'bid' => 1, 'bank' => '余额转资产', 'area' => '余额转资产', 'account' => '', 'img' => $this->user['phone'], 'money' => $money, 'money2' => $money, 'charge' => $money, 'status' => 1, 'time' => date('Y-m-d H:i:s'), 'time2' => '0000-00-00 00:00:00');
        Db::name('LcCash')->insert($addt);
        // 增加途游宝流水
//        $ebaoRecord = array(
//            'uid' => $uid,
//            'money' => $money,
//            'type' => 1,
//            'title' => '途游宝转入 ' . $money,
//            'time' => date('Y-m-d H:i:s')
//        );
//        $int = Db::name('LcEbaoRecord')->insert($ebaoRecord);
//
//        // 重置时间
//        Db::name('LcUser')->where("id = $uid")->update([
//            'ebao_next_time' => date("Y-m-d H:i:s",strtotime("+1 hours"))
//        ]);
//
//        // 增加途游宝金额
//        Db::name('LcUser')->where("id = $uid")->setInc('ebao', $money);


        $this->success(array(
            'zh_cn' => '操作成功'
        ));
    }


    public function transferToAsset()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        $params = $this->request->param();
        $money = $params["amount"];
        if(!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $money)) $this->error('请输入正确的金额');

        // 余额够不够
        if ($this->user['money'] < $params["amount"]) $this->error(Db::name('LcTips')->field("$language")->find('65'));

        // 查询最低转入限制是否达标
//        $reward = Db::name("LcReward")->find(1);
//
//        if($money < $reward['ebao_min']){
//            $this->error(array(
//                'zh_cn' => "转入金额没有达到途游宝最低限制金额：" . $reward['ebao_min'] . "USDT"
//            ));
//        }else if($money > $reward['ebao_max']){
//            $this->error(array(
//                'zh_cn' => "转入金额超过途游宝最高限制金额：" . $reward['ebao_min'] . "USDT"
//            ));
//        }

        $orderid = 'PAY' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
        $add = array(
            'orderid' => $orderid,
            'uid' => $uid,
            'pid' => 20,
            'money' => $money,
            'money2' => -$money,
            'type' => '余额转资产',
            'status' => 1,
            'time' => date('Y-m-d H:i:s'),
            'time2' => '0000-00-00 00:00:00'
        );
        $re = Db::name('LcRecharge')->insertGetId($add);

        $LcTips73 = Db::name('LcTips')->where(['id' => '73']);

        // 扣除用户余额
        addFinance($uid, $money, 2,
            $LcTips73->value("zh_cn") . '《余额转出到资产》，' . $money,
            $LcTips73->value("zh_hk") . '《余额转出到资产》，' . $money,
            $LcTips73->value("en_us") . '《余额转出到资产》，' . $money,
            $LcTips73->value("th_th") . '《余额转出到资产》，' . $money,
            $LcTips73->value("vi_vn") . '《余额转出到资产》，' . $money,
            $LcTips73->value("ja_jp") . '《余额转出到资产》，' . $money,
            $LcTips73->value("ko_kr") . '《余额转出到资产》，' . $money,
            $LcTips73->value("ms_my") . '《余额转出到资产》，' . $money,
            "", "", 6
        );
        setNumber('LcUser', 'money', $money, 2, "id = $uid");
        setNumber('LcUser', 'asset', $money, 1, "id = $uid");
        setNumber('LcUser', 'czmoney', $money, 1, "id = $uid");

        // 增加途游宝流水
//        $ebaoRecord = array(
//            'uid' => $uid,
//            'money' => $money,
//            'type' => 1,
//            'title' => '途游宝转入 ' . $money,
//            'time' => date('Y-m-d H:i:s')
//        );
//        $int = Db::name('LcEbaoRecord')->insert($ebaoRecord);
//
//        // 重置时间
//        Db::name('LcUser')->where("id = $uid")->update([
//            'ebao_next_time' => date("Y-m-d H:i:s",strtotime("+1 hours"))
//        ]);
//
//        // 增加途游宝金额
//        Db::name('LcUser')->where("id = $uid")->setInc('ebao', $money);


        $this->success(array(
            'zh_cn' => '操作成功'
        ));
    }


    /**
     * @description：途游宝流水
     * @date: 2022/12/7 0015
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ebaoRecord()
    {
        $this->checkToken();
        $language = $this->request->param('language');
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);
        $where[] = ['uid', 'eq', $uid];
        $data = Db::name('LcEbaoRecord')->field("title,id,money,type,time")->where('title', 'notlike', '充值%奖励%')->where($where)->order("id desc")->select();
        $this->success("获取成功", ['list' => $data]);
    }


    /**
     * 途游宝转出
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function subEbao()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        $params = $this->request->param();
        $money = floatval($params["money"]);
        if($money<=0) $this->error('请输入正确的金额');
        if($this->user['ebao']<=0) $this->error('余额不足');
        
        if(!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $money)) $this->error('请输入正确的金额');
            if($money<0){
                $this->error('不能为负数');
            }
        // 余额够不够
        if ($this->user['ebao'] < $money) $this->error(Db::name('LcTips')->field("$language")->find('65'));

        $LcTips73 = Db::name('LcTips')->where(['id' => '73']);

        // 增加用户余额
        // addFinance($uid, $money, 1,
        //     $LcTips73->value("zh_cn") . '《转出途游宝》，' . $money,
        //     $LcTips73->value("zh_hk") . '《转出途游宝》，' . $money,
        //     $LcTips73->value("en_us") . '《转出途游宝》，' . $money,
        //     $LcTips73->value("th_th") . '《转出途游宝》，' . $money,
        //     $LcTips73->value("vi_vn") . '《转出途游宝》，' . $money,
        //     $LcTips73->value("ja_jp") . '《转出途游宝》，' . $money,
        //     $LcTips73->value("ko_kr") . '《转出途游宝》，' . $money,
        //     $LcTips73->value("ms_my") . '《转出途游宝》，' . $money,
        //     "", "", 6
        // );
        addFinance($uid, $money, 1,
            $LcTips73->value("zh_cn") . '《转出途游宝》，' . $money,
            $LcTips73->value("zh_hk") . '《转出途游宝》，' . $money,
            $LcTips73->value("en_us") . '《转出途游宝》，' . $money,
            $LcTips73->value("th_th") . '《转出途游宝》，' . $money,
            $LcTips73->value("vi_vn") . '《转出途游宝》，' . $money,
            $LcTips73->value("ja_jp") . '《转出途游宝》，' . $money,
            $LcTips73->value("ko_kr") . '《转出途游宝》，' . $money,
            $LcTips73->value("ms_my") . '《转出途游宝》，' . $money,
            "", "", 20
        );
        setNumber('LcUser', 'money', $money, 1, "id = $uid");


        // 增加途游宝流水
        $ebaoRecord = array(
            'uid' => $uid,
            'money' => $money,
            'type' => 2,
            'title' => '途游宝转出 ' . $money,
            'time' => date('Y-m-d H:i:s')
        );
        $int = Db::name('LcEbaoRecord')->insert($ebaoRecord);
        // 增加途游宝金额
        Db::name('LcUser')->where("id = $uid")->setDec('ebao', $money);


        $this->success(array(
            'zh_cn' => '操作成功'
        ));
    }


    /**
     * 矿机信息
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function machinesInfo()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user = Db::name("LcUser")->find($uid);

        // 查询用户可用的矿机数量
        $now = time();
        $availableNum = Db::name("LcMachinesList")->where("uid = ${uid} and UNIX_TIMESTAMP(end_time) >= $now")->sum("num");

        // 总收益
        $totalIncome = Db::name("LcMachinesList")->where("uid = ${uid}")->sum("income");

        // 查询利率
        $member = Db::name("LcUserMember")->find($user['member']);

        $data = array(
            "availableNum" => $availableNum,
            'totalIncome' => $totalIncome,
            'userMoney' => $user['money'],
            'kjMoney' => $user['kj_money'],
            'rate' => $member['machine_rate']
        );
        $this->success("获取成功", $data);
    }


    /**
     * 矿机流水
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function machinesRecord()
    {
        $this->checkToken();
//        $language = $this->request->param('language');
        $uid = $this->userInfo['id'];
//        $user = Db::name("LcUser")->find($uid);
//        $where[] = ['uid', 'eq', $uid];
        $data = Db::name('LcMechinesFinance')->field("title	,id,amount money,type,add_time time")->where("uid = ${uid} ")->order("id desc")->select();
        $this->success("获取成功", ['list' => $data]);
    }
    
    public function setCard()
    {
        
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $name = $this->request->param('name');
        $cardNo = $this->request->param('cardNo');
        $cardFront = $this->request->param('cardFront');
        $cardBack = $this->request->param('cardBack');
        
        // 检查是否已完成认证
        $this->user = Db::name('LcUser')->find($uid);
        if ($this->user['auth'] == 1) {
            $this->error(array(
                'zh_cn' => "你的账号已完成认证，请勿重复操作！"
            ));
        }
        
        $cardNo = strtoupper($cardNo);
        // 检查该身份证是否已认证过
        $iscount = Db::name('LcUser')->where(["idcard" =>"{$cardNo}"])->count();
        if ($iscount >= 1) {
            $this->error(array(
                'zh_cn' => "一个身份证只能实名一个账号！"
            ));
        }

        // 请求认证
        $host = "https://eid.shumaidata.com";
        $path = "/eid/check";
        $method = "POST";
        $appcode = "aff7cfa728344dab9180c500d73e07ae";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "idcard=" . $cardNo . "&name=" . urlencode($name);
        $bodys = "";
        $url = $host . $path . "?" . $querys;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //设定返回信息中是否包含响应信息头，启用时会将头文件的信息作为数据流输出，true 表示输出信息头, false表示不输出信息头
        //如果需要将字符串转成json，请将 CURLOPT_HEADER 设置成 false
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $result = curl_exec($curl);

        // 开始解析数据
        $resultObj = json_decode($result);
        if (0 != $resultObj->code) {
            // 错误
            $this->error(array(
                'zh_cn' => $resultObj->message
            ));
        }
        $result = json_decode(json_encode($resultObj->result), true);
        if ($result['res'] == 2) {
            $this->error('姓名身份证不匹配');
        }
        
        // Log::record($resultObj, 'error');
        // Log::record($result, 'error');
        
        
        Db::name('lc_certificate')->insert([
            'uid' => $uid,
            'name' => $name,
            'idcard' => $cardNo,
            'card_front' => $cardFront,
            'card_back' => $cardBack,
            'status' => 0,
            'create_time' => time()
        ]);
        
        $this->success("操作成功");
    }


    public function setCard1()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $name = $this->request->param('name');
        $cardNo = $this->request->param('cardNo');
        $cardFront = $this->request->param('cardFront');
        $cardBack = $this->request->param('cardBack');


        // 检查是否已完成认证
        $this->user = Db::name('LcUser')->find($uid);
        if ($this->user['auth'] == 1) {
            $this->error(array(
                'zh_cn' => "你的账号已完成认证，请勿重复操作！"
            ));
        }

        $cardNo = strtoupper($cardNo);
        // 检查该身份证是否已认证过
        $iscount = Db::name('LcUser')->where(["idcard" =>"{$cardNo}"])->count();
        if ($iscount >= 1) {
            $this->error(array(
                'zh_cn' => "一个身份证只能实名一个账号！"
            ));
        }

        // 请求认证
        $host = "https://eid.shumaidata.com";
        $path = "/eid/check";
        $method = "POST";
        $appcode = "aff7cfa728344dab9180c500d73e07ae";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "idcard=" . $cardNo . "&name=" . urlencode($name);
        $bodys = "";
        $url = $host . $path . "?" . $querys;

//        $this->success("获取成功", $url);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //设定返回信息中是否包含响应信息头，启用时会将头文件的信息作为数据流输出，true 表示输出信息头, false表示不输出信息头
        //如果需要将字符串转成json，请将 CURLOPT_HEADER 设置成 false
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $result = curl_exec($curl);

        // 开始解析数据
        $resultObj = json_decode($result);
        // if(empty($resultObj->code)){
        //     $this->error(array(
        //         'zh_cn' => '认证失败'
        //     ));
        // } 
        // var_dump($resultObj);die;
        if (0 != $resultObj->code) {
            // 错误
            $this->error(array(
                'zh_cn' => $resultObj->message
            ));
        }


        $res = Db::name('LcUser')->where('id', $uid)->update([
            'card_front' => $cardFront,
            'card_back' => $cardBack,
            'name' => $name,
            'idcard' => $cardNo,
            'auth' => 1
        ]);

         $rsd=Db::name('LcRecharge')->where(['uid'=>$uid,'type'=>'实名奖励'])->find();
         if(!$rsd){
                 $dd = Db::name('LcReward')->where(['id'=>1])->find();
        
        $orderid = 'PAY' . date('YmdHis') . rand(1000, 9999) . rand(100, 999);
        $add = array(
            'orderid' => $orderid,
            'uid' => $uid,
            'pid' => 20,
            'money' => $dd['real_name'],
            'money2' => $dd['real_name'],
            'type' => '实名奖励',
            'status' => 1,
            'time' => date('Y-m-d H:i:s'),
            'time2' => '0000-00-00 00:00:00'
        );
        setNumber('LcUser', 'asset', $dd['real_name'], 1, "id = $uid");
           $re = Db::name('LcRecharge')->insertGetId($add);
         }
    

     

        $this->success("操作成功");
    }


    public function cardAuthInfo()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);

        // 返回认证信息
        $data = array(
            "name" => $this->user['name'],
            "cardNo" => $this->user['idcard'],
            "cardFront" => $this->user['card_front'],
            "cardBack" => $this->user['card_back'],
        );
        $this->success("获取成功", $data);
    }

    public function profile()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $avatar_url = $this->request->param('avatar_url');
        $avatar = $this->request->param('avatar');
        if(stripos($avatar,'http')!==false){
            $avatar_url = $avatar;
        }
        $res = Db::name('LcUser')->where('id', $uid)->update([
            'avatar' => $avatar_url
        ]);
        $this->success("操作成功");
    }


    public function kjOut()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $amount = floatval($this->request->param('amount'));
        if($amount<=0) $this->error('请输入正确的金额');
        
        if(!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $amount)) $this->error('请输入正确的金额');

        // 判断矿机余额是否足够
        $this->user = Db::name('LcUser')->find($uid);

        if ($this->user['kj_money'] < $amount) {
            $this->error(array(
                'zh_cn' => "MMH余额不足！！"
            ));
        }
        if($this->user['kj_money']<=0) $this->error('余额不足');

        // 查询矿币兑换比例
        $machines = Db::name("LcMachines")->find();

        $addMoney = $machines['rate'] * $amount;

        //
        Db::name('LcUser')->where(['id' => $uid])->setDec('kj_money', $amount);
        Db::name('LcUser')->where(['id' => $uid])->setInc('money', $addMoney);

        // 扣除矿机流水
        $finance = array(
            'uid' => $uid,
            'type' => 2,
            'title' => "MMH兑换余额",
            'amount' => $amount,
            'add_time' => date('Y-m-d H:i:s')
        );
        Db::name('LcMechinesFinance')->insert($finance);

        // 添加矿机账户
        $income = $amount;
        addFinance($uid, $addMoney, 1,
            '《MMH兑换余额》，' . $income,
            '《MMH兑换余额》，' . $income,
            '《MMH兑换余额》，' . $income,
            '《MMH兑换余额》，' . $income,
            '《MMH兑换余额》，' . $income,
            '《MMH兑换余额》，' . $income,
            '《MMH兑换余额》，' . $income,
            '《MMH兑换余额》，' . $income,
            "", "", 12
        );

        $this->success("操作成功");
    }


    /**
     * 途游助手设置
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getEbaoSwitchInfo()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];

        // 用户信息
        $this->user = Db::name('LcUser')->find($uid);

        $data = array(
            'id' => $this->user['id'],
            'auto_in_ebao' => $this->user['auto_in_ebao'],
            'auto_out_ebao' => $this->user['auto_out_ebao'],
            'in_ebao_start' => $this->user['in_ebao_start'],
            'in_ebao_end' => $this->user['in_ebao_end'],
            'out_ebao_start' => $this->user['out_ebao_start'],
            'out_ebao_end' => $this->user['out_ebao_end'],
        );

        $this->success("操作成功", $data);
    }


    public function setEbaoSwitch()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];

        // 用户信息
        $this->user = Db::name('LcUser')->find($uid);

        // 开始理财时间
        Db::name('LcUser')->where("id = {$uid}")->update([
            'auto_in_ebao' => $this->request->param('auto_in_ebao'),
            'auto_out_ebao' => $this->request->param('auto_out_ebao'),
            'in_ebao_start' => $this->request->param('in_ebao_start'),
            'in_ebao_end' => $this->request->param('in_ebao_end'),
            'out_ebao_start' => $this->request->param('out_ebao_start'),
            'out_ebao_end' => $this->request->param('out_ebao_end'),
        ]);
        $this->success("操作成功");
    }


    public function kjTrade()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $amount = floatval($this->request->param('amount'));
        $account = $this->request->param('account');
        $jyPassword = $this->request->param('jyPassword');
        if($amount<=0) $this->error('请输入正确的金额');
        
        if(!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $amount)) $this->error('请输入正确的金额');
        // 查询目标账户是否存在
        $aes = new Aes();
        $account = $aes->encrypt($account);
        $tUser = Db::name("LcUser")->where('phone', $account)->find();
        if (!$tUser) {
            $this->error(array(
                'zh_cn' => "转账用户不存在！"
            ));
        }
        

        // 判断矿机余额是否足够
        $this->user = Db::name('LcUser')->find($uid);

//        // 开始验证交易密码
//        if(md5($jyPassword) != $this->user['password2']){
//            $this->error(array(
//                'zh_cn' => "交易密码不正确！"
//            ));
//        }


        if ($this->user['kj_money'] < $amount) {
            $this->error(array(
                'zh_cn' => "MMH余额不足！！"
            ));
        }
        if($this->user['kj_money']<=0) $this->error('余额不足');

        // 扣除矿币数量
        Db::name('LcUser')->where(['id' => $uid])->setDec('kj_money', $amount);
        // 扣除矿机流水
        $finance = array(
            'uid' => $uid,
            'type' => 2,
            'title' => "MMH转账支出",
            'amount' => $amount,
            'add_time' => date('Y-m-d H:i:s')
        );
        Db::name('LcMechinesFinance')->insert($finance);

        // 对方账户增加
        Db::name('LcUser')->where(['id' => $tUser['id']])->setInc('kj_money', $amount);
        // 扣除矿机流水
        $finance = array(
            'uid' => $tUser['id'],
            'type' => 1,
            'title' => "收到MMH转账",
            'amount' => $amount,
            'add_time' => date('Y-m-d H:i:s')
        );
        Db::name('LcMechinesFinance')->insert($finance);

        $this->success("操作成功");
    }


    public function tuangouRecord()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];

        $list = Db::name("LcInvest i")
            ->leftJoin("lc_user u", " i.uid = u.id")
            ->field("i.* , u.name userName, u.phone")
            ->where("share_uid = {$uid}")
            ->select();

        $aes = new Aes();
        foreach ($list as $k => $v) {
            $list[$k]['phone'] = substr($aes->decrypt($v['phone']), 0, 3) . '****' . substr($aes->decrypt($v['phone']), 7);
        }

        $this->success("操作成功", $list);
    }


    public function pinzanRecord()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];

        $list = Db::name("LcInvestLive il")
            ->leftJoin("lc_invest i", "i.id = il.invest_id")
            ->leftJoin("lc_user u", "u.id = il.uid")
            ->field("u.phone, i.money, il.time, i.zh_cn")
            ->where("i.uid = {$uid}")
            ->select();

        $aes = new Aes();
        foreach ($list as $k => $v) {
            $list[$k]['phone'] = substr($aes->decrypt($v['phone']), 0, 3) . '****' . substr($aes->decrypt($v['phone']), 7);
        }

        $this->success("操作成功", $list);
    }


    public function setUserAddress()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];

        // 获取数据
        $receipt_name = $this->request->param('receipt_name');
        $receipt_phone = $this->request->param('receipt_phone');
        $receipt_address = $this->request->param('receipt_address');

        // 修改入库
        Db::name("LcUser")->where("id = {$uid}")->update([
            'receipt_name' => $receipt_name,
            'receipt_phone' => $receipt_phone,
            'receipt_address' => $receipt_address
        ]);
        $this->success("操作成功");
    }


    /**
     * 获取升级团队信息
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserGradeInfo()
    {

        $this->checkToken();
        $uid = $this->userInfo['id'];

        // 获取当前用户的团队等级
        // $user = Db::name("LcUser")->where("id = {$uid}")->where('is_sf',1)->find();
         $user = Db::name("LcUser")->where("id = {$uid}")->find();
        // var_dump($user);die;
 
        // 查询当前会员等级
        $currentMemberGrade = Db::name("LcMemberGrade")->where(['id' => $user['grade_id']])->find();

        $currentGradeId = $user['grade_id'];

        // 查询下一个等级
        $nextGrade = Db::name("LcMemberGrade")->where("id > {$currentGradeId}")->limit(1)->find();

        //直推人数
        $tg_num = Db::name("LcUser")->where("recom_id", $uid)->count();

        //邀请直推团长数
        $where_find = [
            "grade_id" => ["gt", "1"]
        ];
        $tz_num = Db::name("LcUser")->where("recom_id", $uid)->where("grade_id > 1")->count();
// var_dump($uid;);
// var_dump(Db::name("LcUser")->where("recom_id", $uid)->where('is_sf',0)->sum("czmoney"));die;
        $xjlj_money = Db::name("LcUser")->where("recom_id", $uid)->where('is_sf',0)->sum("czmoney");

        $xjlj_money += Db::name("LcUser")->where("top2", $uid)->where('is_sf',0)->sum("czmoney");
        $xjlj_money += Db::name("LcUser")->where("top3", $uid)->where('is_sf',0)->sum("czmoney");


        $memberList = Db::name('LcUser')->field('id, phone, top,czmoney,name,time, auth')->select();
      
      $itemList = $this->get_downline_list($memberList,$uid);
    //   var_dump($itemList);die;
      $all_czmoney=0;
      
       $is_sf = Db::name('LcUser')->where(['id' => $uid])->value('is_sf');
    //   var_dump($this->userInfo['czmoney']);
    //   var_dump($this->userInfo['is_sf']);die;
      if($is_sf==0){
        //   $all_czmoney=$this->userInfo['czmoney'];
            $all_czmoney = Db::name('LcUser')->where(['id' => $uid])->value('czmoney');
      }
                foreach ($itemList as $k=>$v){
                    $all_czmoney+=$v['czmoney'];
                   
                }
//
//         $twoUser = Db::name("LcUser")->where("recom_id", $uid)->select();
//         foreach ($twoUser as $user) {
//             $dd = Db::name("LcUser")->where("recom_id", $user['id'])->sum("czmoney");
//             $cc = Db::name("LcUser")->where("recom_id", $user['id'])->select();
//             $xjlj_money += $dd;
//         }
//
//         foreach ($cc as $user) {
//             $dd = Db::name("LcUser")->where("recom_id", $user['id'])->sum("czmoney");
////             $cc = Db::name("LcUser")->where("recom_id", $user['id'])->select();
//             $xjlj_money += $dd;
//         }


        $this->success("操作成功", array(
            'currentMemberGrade' => $currentMemberGrade,
            'nextGrade' => $nextGrade,
            'czmoney' => sprintf('%.2f',$all_czmoney),
            'tg_num' => $tg_num,
            'tz_num' => $tz_num
        ));
    }


    public function getNxetUserGradeInfo()
    {

        $this->checkToken();
//        $uid = $this->userInfo['id'];

        $post = $this->request->post();
        $uid = $post['userId'];


        // 获取当前用户的团队等级
        $user = Db::name("LcUser")->where("id = {$uid}")->find();

        // 查询当前会员等级
        $currentMemberGrade = Db::name("LcMemberGrade")->where(['id' => $user['grade_id']])->find();

        $currentGradeId = $user['grade_id'];

        // 查询下一个等级
        $nextGrade = Db::name("LcMemberGrade")->where("id > {$currentGradeId}")->limit(1)->find();

        //直推人数
        $tg_num = Db::name("LcUser")->where("recom_id", $uid)->count();

        //邀请直推团长数
        $where_find = [
            "grade_id" => ["gt", "1"]
        ];
        $tz_num = Db::name("LcUser")->where("recom_id", $uid)->where("grade_id > 1")->count();


        $xjlj_money = Db::name("LcUser")->where("recom_id", $uid)->sum("czmoney");


        $this->success("操作成功", array(
            'currentMemberGrade' => $currentMemberGrade,
            'nextGrade' => $nextGrade,
            'czmoney' => $xjlj_money,
            'tg_num' => $tg_num,
            'tz_num' => $tz_num
        ));
    }


    /**
     * 查询我购买的途游宝产品
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getMyEbaoProduct()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];

        $list = Db::name("LcEbaoProductRecord")->where("uid = {$uid}")->order("id desc")->select();
        $this->success("操作成功", array(
            'list' => $list
        ));
    }


}
