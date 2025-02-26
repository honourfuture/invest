<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2023  天美网络 [   ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\index\controller;

use library\Controller;
use think\Db;
use think\facade\Log;
use think\facade\Cache;
use app\api\controller\Aes;

/**
 * 应用入口
 * Class Index
 * @package app\index\controller
 */
class Index extends Controller
{
    public function getdata($date)
    {
       $start = strtotime($date);
        $last = $start+86400-1;
        $real_user = Db::name('lc_user')->where("UNIX_TIMESTAMP(time) BETWEEN $start AND $last")->where('is_sf', 0)->count();
        $inside_user = Db::name('lc_user')->where("UNIX_TIMESTAMP(time) BETWEEN $start AND $last")->whereIn('is_sf', [1,2])->count();
        $real_recharge = Db::name('lc_recharge')->alias('r')->join('lc_user u', 'r.uid = u.id')->where("UNIX_TIMESTAMP(r.time) BETWEEN $start AND $last")->where('is_sf', 0)->where('r.status', 1)->sum('r.money');
        $inside_recharge = Db::name('lc_recharge')->alias('r')->join('lc_user u', 'r.uid = u.id')->where("UNIX_TIMESTAMP(r.time) BETWEEN $start AND $last")->whereIn('is_sf', [1,2])->where('r.status', 1)->sum('r.money');
        $real_cash = Db::name('lc_cash')->alias('c')->join('lc_user u', 'c.uid = u.id')->where("UNIX_TIMESTAMP(c.time) BETWEEN $start AND $last")->where('is_sf',0)->where('c.status', 1)->sum('c.money');
        $inside_cash = Db::name('lc_cash')->alias('c')->join('lc_user u', 'c.uid = u.id')->where("UNIX_TIMESTAMP(c.time) BETWEEN $start AND $last")->whereIn('is_sf', [1,2])->where('c.status', 1)->sum('c.money');
        $real_profit = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->where('is_sf',0)->where('l.status', 1)->sum('pay1');
        $inside_profit = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->whereIn('is_sf', [1,2])->where('l.status', 1)->sum('pay1');
        $real_invest_num = Db::name('lc_invest')->alias('l')->join('lc_user u', 'l.uid = u.id')->where('is_sf',0)->where("UNIX_TIMESTAMP(l.time) BETWEEN $start AND $last")->count();
        $inside_invest_num = Db::name('lc_invest')->alias('l')->join('lc_user u', 'l.uid = u.id')->whereIn('is_sf', [1,2])->where("UNIX_TIMESTAMP(l.time) BETWEEN $start AND $last")->count();
        $real_invest = Db::name('lc_invest')->alias('l')->join('lc_user u', 'l.uid = u.id')->where('is_sf',0)->where("UNIX_TIMESTAMP(l.time) BETWEEN $start AND $last")->sum('l.money');
        $inside_invest = Db::name('lc_invest')->alias('l')->join('lc_user u', 'l.uid = u.id')->whereIn('is_sf', [1,2])->where("UNIX_TIMESTAMP(l.time) BETWEEN $start AND $last")->sum('l.money');
        
        $real_expire_money = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->where('is_sf',0)->where('l.status', 1)->sum('money2');
        $inside_expire_money = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->whereIn('is_sf', [1,2])->where('l.status', 1)->sum('money2');
        
        $real_interest = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->where('is_sf',0)->where('l.status', 1)->sum('money1');
        $inside_interest = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->whereIn('is_sf', [1,2])->where('l.status', 1)->sum('money1'); 
        
        return [
            'date' => $date,
            'real_user' => $real_user,
            'inside_user' => $inside_user,
            'real_recharge' => $real_recharge,
            'inside_recharge' => $inside_recharge,
            'real_cash' => $real_cash,
            'inside_cash' => $inside_cash,
            'real_profit' => $real_profit,
            'inside_profit' => $inside_profit,
            'real_invest_num' => $real_invest_num,
            'inside_invest_num' => $inside_invest_num,
            'real_invest' => $real_invest,
            'inside_invest' => $inside_invest,
            'real_expire_money' => $real_expire_money,
            'inside_expire_money' => $inside_expire_money,
            'real_interest' => $real_interest,
            'inside_interest' => $inside_interest,
        ];
    }
    
    //更新今日数据
    public function updatedata()
    {
        //获取本月开始的时间戳
        $beginThismonth=mktime(0,0,0,date('m'),1,date('Y'));
        //获取本月结束的时间戳
        $endThismonth=mktime(23,59,59,date('m'),date('t'),date('Y'));
        $arrs = [];
        $time = $beginThismonth;
        for($i = 1; $time < time(); $i++) {
            $time = $beginThismonth + $i * 86400 - 1;
            $arrs[] = date('Y-m-d', $time);
        }
        foreach ($arrs as &$arr) {
            $date = date('Y-m-d', time());
            $data = Db::name('lc_static')->where('date', $arr)->find();
            if (empty($data)) {
                $result = $this->getdata($arr);
                Db::name('lc_static')->insert($result);
                continue;
            }
            // if ($date != $data) continue;
            $result = $this->getdata($arr);
            Db::name('lc_static')->where('date', $arr)->update($result);
        }
        echo '更新成功';
        
        // $date = date('Y-m-d', time());
        // $start = strtotime($date);
        // $last = $start+86400-1;
        // $real_user = Db::name('lc_user')->where("UNIX_TIMESTAMP(time) BETWEEN $start AND $last")->where('is_sf', 0)->count();
        // $inside_user = Db::name('lc_user')->where("UNIX_TIMESTAMP(time) BETWEEN $start AND $last")->whereIn('is_sf', [1,2])->count();
        // $real_recharge = Db::name('lc_recharge')->alias('r')->join('lc_user u', 'r.uid = u.id')->where("UNIX_TIMESTAMP(r.time) BETWEEN $start AND $last")->where('is_sf', 0)->where('r.status', 1)->sum('r.money2');
        // $inside_recharge = Db::name('lc_recharge')->alias('r')->join('lc_user u', 'r.uid = u.id')->where("UNIX_TIMESTAMP(r.time) BETWEEN $start AND $last")->whereIn('is_sf', [1,2])->where('r.status', 1)->sum('r.money2');
        // $real_cash = Db::name('lc_cash')->alias('c')->join('lc_user u', 'c.uid = u.id')->where("UNIX_TIMESTAMP(c.time) BETWEEN $start AND $last")->where('is_sf',0)->where('c.status', 1)->sum('c.money');
        // $inside_cash = Db::name('lc_cash')->alias('c')->join('lc_user u', 'c.uid = u.id')->where("UNIX_TIMESTAMP(c.time) BETWEEN $start AND $last")->whereIn('is_sf', [1,2])->where('c.status', 1)->sum('c.money');
        // $real_profit = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->where('is_sf',0)->where('l.status', 1)->sum('pay1');
        // $inside_profit = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->whereIn('is_sf', [1,2])->where('l.status', 1)->sum('pay1');
        // $real_invest_num = Db::name('lc_invest')->alias('l')->join('lc_user u', 'l.uid = u.id')->where('is_sf',0)->where("UNIX_TIMESTAMP(l.time) BETWEEN $start AND $last")->count();
        // $inside_invest_num = Db::name('lc_invest')->alias('l')->join('lc_user u', 'l.uid = u.id')->whereIn('is_sf', [1,2])->where("UNIX_TIMESTAMP(l.time) BETWEEN $start AND $last")->count();
        // $real_invest = Db::name('lc_invest')->alias('l')->join('lc_user u', 'l.uid = u.id')->where('is_sf',0)->where("UNIX_TIMESTAMP(l.time) BETWEEN $start AND $last")->sum('l.money');
        // $inside_invest = Db::name('lc_invest')->alias('l')->join('lc_user u', 'l.uid = u.id')->whereIn('is_sf', [1,2])->where("UNIX_TIMESTAMP(l.time) BETWEEN $start AND $last")->sum('l.money');
        
        // $real_expire_money = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->where('is_sf',0)->where('l.status', 1)->sum('money2');
        // $inside_expire_money = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->whereIn('is_sf', [1,2])->where('l.status', 1)->sum('money2');
        
        // $real_interest = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->where('is_sf',0)->where('l.status', 1)->sum('money1');
        // $inside_interest = Db::name('lc_invest_list')->alias('l')->join('lc_user u', 'l.uid = u.id')->where("UNIX_TIMESTAMP(l.time1) BETWEEN $start AND $last AND status = 1")->whereIn('is_sf', [1,2])->where('l.status', 1)->sum('money1');
        
        // $data = Db::name('lc_static')->where('date', $date)->find();
        // if (!$data) {
        //     Db::name('lc_static')->insert([
        //         'date' => $date,
        //         'real_user' => $real_user,
        //         'inside_user' => $inside_user,
        //         'real_recharge' => $real_recharge,
        //         'inside_recharge' => $inside_recharge,
        //         'real_cash' => $real_cash,
        //         'inside_cash' => $inside_cash,
        //         'real_profit' => $real_profit,
        //         'inside_profit' => $inside_profit,
        //         'real_invest_num' => $real_invest_num,
        //         'inside_invest_num' => $inside_invest_num,
        //         'real_invest' => $real_invest,
        //         'inside_invest' => $inside_invest,
        //         'real_expire_money' => $real_expire_money,
        //         'real_expire_money' => $real_expire_money,
        //         'real_interest' => $real_interest,
        //         'inside_interest' => $inside_interest,
        //     ]);
        // } else {
        //     Db::name('lc_static')->where('date', $date)->update([
        //         'real_user' => $real_user,
        //         'inside_user' => $inside_user,
        //         'real_recharge' => $real_recharge,
        //         'inside_recharge' => $inside_recharge,
        //         'real_cash' => $real_cash,
        //         'inside_cash' => $inside_cash,
        //         'real_profit' => $real_profit,
        //         'inside_profit' => $inside_profit,
        //         'real_invest_num' => $real_invest_num,
        //         'inside_invest_num' => $inside_invest_num,
        //         'real_invest' => $real_invest,
        //         'inside_invest' => $inside_invest,
        //         'real_expire_money' => $real_expire_money,
        //         'real_expire_money' => $real_expire_money,
        //         'real_interest' => $real_interest,
        //         'inside_interest' => $inside_interest,
        //     ]);
        // }
        // echo '更新成功';
    }
    
    
    //定时更新平台数据
    public function platdata()
    {
        $info = Db::name('lc_info')->field('plat_total_num,today_inc_num,today_recharge_num,trade_total,today_trade,today_withdraw,rate_usd')->find(1);
        $info['plat_total_num'] += rand(10,20); //平台总人数
        $info['today_inc_num'] += rand(1,2);    //今日新增人数/每日修改1次
        $info['today_recharge_num']++;  //今日充值人数/每日修改1次
        $info['trade_total'] += rand(500,1000); //总交易金额
        $info['today_trade'] += rand(100,500);  //24h交易/每日修改1次
        $info['today_withdraw'] += rand(200,600);   //24h提现/每日修改1次
        $arr = $info;
        Db::name('lc_info')->where('id', 1)->update($arr);
        echo date('Y-m-d H:i:s').'更新成功';
    }
    
    //奖池开奖
    public function pool_open()
    {
        // $time = time()-60;
        // $list = Db::name('lc_reward_pool_period')->where('status', 1)->where("UNIX_TIMESTAMP(end_time) < $time")->select();
        $list = Db::name('lc_reward_pool_period')->where('status', 1)->select();
        $aes = new Aes();
        if (count($list)) {
            
            foreach ($list as &$item) {
                $pool = Db::name('lc_reward_pool')->find($item['pool_id']);
                $userIds = Db::name('lc_reward_pool_log')->where('period_id', $item['id'])->group('uid')->column('uid');
                //抽取中奖用户
                $lotteryUser = $userIds[array_rand($userIds, 1)];
                $user = Db::name('lc_user')->find($lotteryUser);
                Db::name("lc_user")->where('id', $user['id'])->setInc('money', $pool['money']);
                //发送站内信
                // Db::name('lc_msg')->insert([
                //     'title_zh_cn' => '参与'.$item['sn'].'期奖池中奖'.$pool['money'].'元',
                //     'title_zh_hk' => '參與'.$item['sn'].'期獎池中獎'.$pool['money'].'元',
                //     'title_en_us' => 'Winning in the prize pool'.$item['sn'].'Mid term prize'.$pool['money'].'first',
                //     'phone' => $aes->decrypt($user['phone']),
                //     'add_time' => date('Y-m-d H:i:s', time()),
                // ]);
                //弹窗信息
                Db::name('lc_pool_lottery_msg')->insert([
                    'uid' => $user['id'],
                    'msg' => '恭喜您'.$item['sn'].'期中奖获得'.$pool['money'].'元，现已返回到您账户余额'
                ]);
                //流水记录
                Db::name('lc_finance')->insert([
                    'uid' => $user['id'],
                    'money' => $pool['money'],
                    'type' => 1,
                    'zh_cn' => '参与'.$item['sn'].'期奖池中奖'.$pool['money'].'元',
                    'zh_hk' => '參與'.$item['sn'].'期獎池中獎'.$pool['money'].'元',
                    'en_us' => 'Winning in the prize pool'.$item['sn'].'Mid term prize'.$pool['money'].'first',
                    'before' => $user['money'],
                    'time' => date('Y-m-d H:i:s', time()),
                    'after_money' => bcadd($user['money'], $pool['money'], 2),
                    'after_asset' => $user['asset'],
                    'before_asset' => $user['asset']
                ]);
                //修改当期开奖状态
                Db::name('lc_reward_pool_period')->where('id', $item['id'])->update(['lottery_uid' => $user['id'], 'money' => $pool['money'], 'status' => 2, 'open_time' => date('Y-m-d H:i:s', time())]);
                //生成下一期
                Db::name('lc_reward_pool_period')->insert([
                    'pool_id' => $pool['id'],
                    'sn' => $item['sn']+1,
                    'start_time' => date('Y-m-d H:i:s', time()),
                    'quota' => $pool['quota'],
                ]);
                echo '开奖成功';
            }
        }
    }
    
    //处理到期代金券
    public function handle_coupon()
    {
        $time = time();
        $list = Db::name('lc_coupon_list')->where('status', 0)->where("UNIX_TIMESTAMP(expire_time) < $time")->select();
        if (count($list)) {
            foreach ($list as $item)
            {
                Db::name('lc_coupon_list')->where('id', $item['id'])->update(['status' => 2]);
            }
        }
        echo '任务已处理';
    }
    
    //定时任务检测僵尸号
    public function check_user()
    {
        $user = Db::name('lc_user')->select();
        $end_time = time();
        $start_time = $end_time - 86400*10;
        foreach ($user as $item)
        {
            //登录情况
            $login_info = Db::name('lc_login_log')->where('uid', $item['id'])->where($this->get_where($start_time, $end_time, 'create_time', 1))->count();
            //充值情况
            $recharge_info = Db::name('lc_recharge')->where('uid', $item['id'])->where($this->get_where($start_time, $end_time, 'time', 0))->count();
            //提现情况
            $cash_info = Db::name('lc_cash')->where('uid', $item['id'])->where($this->get_where($start_time, $end_time, 'time', 0))->count();
            //购买产品
            $invest_info = Db::name('lc_invest')->where('uid', $item['id'])->where($this->get_where($start_time, $end_time, 'time', 0))->count();
            
            //累计充值量
            $total_recharge = Db::name('lc_recharge')->where('uid', $item['id'])->count();
            //注册一天还没充值
            if (strtotime($item['time']) < (time()-86400) && !$total_recharge) {
                 Db::name('lc_user')->where('id', $item['id'])->update(['sign_status' => 2]);
                 continue;
            }
            
            if ($login_info || $recharge_info || $cash_info || $invest_info) {
                Db::name('lc_user')->where('id', $item['id'])->update(['sign_status' => 0]);
            } else {
                Db::name('lc_user')->where('id', $item['id'])->update(['sign_status' => 2]);
            }
            
        }
    }
    
    public function get_where($start_time, $end_time, $field, $type)
    {
        if ($type) {
            return "$field > $start_time AND $field < $end_time";
        }
        $where = "UNIX_TIMESTAMP($field) > $start_time AND UNIX_TIMESTAMP($field) < $end_time";
        return $where;
    }
    
    //红包超24小时未领取自动退回
    public function redpack_timeout()
    {
        //查询有剩余并未过期红包
        $now = time() - 86400;
        $list = Db::name('lc_redpack_record')->where("UNIX_TIMESTAMP(add_time) <= $now AND status = '0' AND remaining_num > 0")->select();
        
        if (!count($list)) {
            echo '没有待处理的红包记录';
            exit;
        }
        // var_dump($list);exit;
        
        foreach ($list as $item) {
            if ($item['remaining_amount'] > 0) {
                $user = Db::name('lc_user')->find($item['uid']);
                //退回当前用户剩余红包
                Db::name('lc_finance')->insert([
                    'uid' => $item['uid'],
                    'money' => $item['remaining_amount'],
                    'type' => 1,
                    'zh_cn' => '红包过期退回',
                    'zh_hk' => 'Phong bì màu đỏ hết hạn trở về',
                    'en_us' => 'Red envelope expired return',
                    'before' => $user['money'],
                    'time' => date('Y-m-d H:i:s', time()),
                    'reason_type' => 70,
                    'trade_type' => 2,
                    'after_money' => bcadd($user['money'], $item['remaining_amount'], 2),
                    'after_asset' => $user['asset'],
                    'before_asset' => $user['asset']
                ]);
                Db::name('lc_user')->where('id', $item['uid'])->update([
                    'money' => bcadd($user['money'], $item['remaining_amount'], 2)
                ]);
                echo '退回用户ID：'.$user['id'].'红包'.$item['remaining_amount'].'元';
            }
            Db::name('lc_redpack_record')->where('id', $item['id'])->update(['status' => 1]);
        }
        return true;
    }
    
    public function test()
    {
        $item = Db::name('lc_user')->find(38724);
         //当前会员等级
            $curGrade = Db::name('lc_member_grade')->find($item['grade_id']);
            //当前团队投资额
            $memberList = Db::name('lc_user')->field('id,phone,top,czmoney')->select();
            $itemList = $this->get_downline_list($memberList, $item['id']);
            $ids = [$item['id']];$recom=[];
            foreach ($itemList as $value) {
                $ids[] = $value['id'];
                $recom[] = $value['id'];
            }
            var_dump($ids);exit;
            $totalInvest = Db::name('lc_invest')->whereIn('uid', $ids)->sum('money');   //总投资额
            echo $totalInvest;exit;
    }
    
    public function update_grade()
    {
        $users = Db::name('lc_user l')->order('id asc')->select();
        
        foreach ($users as $user) {
            //下一级团队等级
            $nextGrade = Db::name("LcMemberGrade")->where("id > {$user['grade_id']}")->order("id asc")->limit(1)->find();
            //已达最高等级
            if (!$nextGrade) continue;
            //团队成员人数
            $teamIds = $this->getTeam($user['id']);
            $totalInvest = Db::name('lc_user')->whereIn('id', $teamIds)->sum('invest_sum');
            //判断是否达到累计投资金额
            if ($totalInvest < $nextGrade['all_activity']) continue;
            //直推一级团队长人数
            $firstTeam = Db::name('lc_user')->whereIn('id', $teamIds)->where('grade_id > 1')->count();
            if ($firstTeam < $nextGrade['recom_tz']) continue;
            //直推会员数
            $hyNum = Db::name('lc_user u')->join('lc_invest l', 'l.uid = u.id')->join('lc_item i', 'l.pid=i.id')->where('index_type', '<>', 7)->where('top', $user['id'])->group('l.uid')->count();
            if ($hyNum < $nextGrade['recom_number']) continue;
            
            //满足全部条件升级
            Db::name('lc_user')->where('id', $user['id'])->update(['grade_id' => $nextGrade['id'], 'grade_name' => $nextGrade['title']]);
            echo '会员：'.$user['id'].'，升级为'.$nextGrade['title'];
            echo '总投资：'.$totalInvest;
            echo '下级额度：'.$nextGrade['all_activity'];
            $extra_money = $totalInvest - $nextGrade['all_activity'];
            echo '额外超出金额：'.$extra_money;
            //普通用户升级一级团队长奖励
            if ($nextGrade['title'] == '一级团队长') {
                $extra_money = $totalInvest - $nextGrade['all_activity'];
                //额外超出金额
                if ($extra_money > 0) {
                    $rewardMoney = bcdiv($extra_money*$nextGrade['poundage'], 100, 2);
                    //赠送记录
                    Db::name('lc_finance')->insert([
                        'uid' => $user['id'],
                        'money' => $rewardMoney,
                        'type' => 1,
                        'zh_cn' => $nextGrade['title'].'奖励，投资'.$extra_money.'奖励'.$rewardMoney,
                        'zh_hk' => $nextGrade['title_zh_hk'].'Phần thưởng，Đầu tư'.$extra_money.'Phần thưởng'.$rewardMoney,
                        'en_us' => $nextGrade['title_en_us'].'rewards, Investments'.$extra_money.'reward'.$rewardMoney,
                        'before' => $user['money'],
                        'time' => date('Y-m-d H:i:s', time()),
                        'reason_type' => 8,
                        'after_money' => bcadd($user['money'], $rewardMoney, 2),
                        'after_asset' => $user['asset'],
                        'before_asset' => $user['asset']
                    ]);
                    Db::name('lc_user')->where('id', $user['id'])->update(['money' => bcadd($user['money'], $rewardMoney, 2)]);
                }
            }
            
        }
        echo '总人数：'.count($users);
        echo  '<br/>';
        echo '执行成功';
    }
    
    public function getTeam($user_id, $list = [], $flag = true)
    {
        static $list = [];
        if ($flag) {
            $list = [];
        }
        $userIds = Db::name('lc_user')->whereIn('top', $user_id)->column('id');
        //获取整个团队
        if (count($userIds)) {
            $list = array_merge($list, $userIds);
            $this->getTeam($userIds, $list, false);
        }
        return $list;
    }
    
    //更新会员团队等级
    public function update_grade2()
    {
        $user = Db::name('lc_user')->select();
        // $user = Db::name('lc_user')->limit(10)->column('id');
        // var_dump($user);exit;
        foreach ($user as &$item) {
            //当前会员等级
            $curGrade = Db::name('lc_member_grade')->find($item['grade_id']);
            //当前团队投资额
            $memberList = Db::name('lc_user')->field('id,phone,top,czmoney')->select();
            $itemList = $this->get_downline_list($memberList, $item['id']);
            // $ids = [$item['id']];
            $recom=[];$ids = [];
            foreach ($itemList as $value) {
                $ids[] = $value['id'];
                $recom[] = $value['id'];
            }
            $totalInvest = Db::name('lc_invest')->whereIn('uid', $ids)->sum('money');   //总投资额
            //当前直推用户
            // $curRecom = Db::name("lc_user")->where("top", $item['id'])->count();
            $curRecom = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')->where('u.top', $item['id'])->group('uid')->count();
            // $curRecom = Db::name('lc_invest l')->join('lc_item i', 'l.pid=i.id')->join('lc_user u', 'l.uid = u.id')->where('index_type', '<>', 7)->where('u.top', $item)->group('uid')->count();
        
            
            //当前一级以上团队长数量
            $curTeam = Db::name('lc_user')->whereIn('id', $recom)->where('grade_id > 1')->count();
            //下一级团队等级信息
            $nextGrade = Db::name("LcMemberGrade")->where("id > {$item['grade_id']}")->order("id asc")->limit(1)->find();
            if ($curRecom >= $nextGrade['recom_number'] && $curTeam >= $nextGrade['recom_tz'] && $totalInvest >= $nextGrade['all_activity']) {
                //更新会员团队
                Db::name('lc_user')->where('id', $item['id'])->update(['grade_id' => $nextGrade['id'], 'grade_name' => $nextGrade['title']]);
                echo '会员：'.$item['phone'].'升级为'.$nextGrade['title'];
                echo '总投资：'.$totalInvest;
                echo '下级额度：'.$nextGrade['all_activity'];
                $extra_money = $totalInvest - $nextGrade['all_activity'];
                echo '额外超出金额：'.$extra_money;
                //普通用户升级一级团队长奖励
                if ($nextGrade['title'] == '一级团队长') {
                    $extra_money = $totalInvest - $nextGrade['all_activity'];
                    //额外超出金额
                    if ($extra_money > 0) {
                        $rewardMoney = bcdiv($extra_money*$nextGrade['poundage'], 100, 2);
                        //赠送记录
                        Db::name('lc_finance')->insert([
                            'uid' => $item['id'],
                            'money' => $rewardMoney,
                            'type' => 1,
                            'zh_cn' => $nextGrade['title'].'奖励，投资'.$extra_money.'奖励'.$rewardMoney,
                            'zh_hk' => $nextGrade['title_zh_hk'].'Phần thưởng，Đầu tư'.$extra_money.'Phần thưởng'.$rewardMoney,
                            'en_us' => $nextGrade['title_en_us'].'rewards, Investments'.$extra_money.'reward'.$rewardMoney,
                            'before' => $item['money'],
                            'time' => date('Y-m-d H:i:s', time()),
                            'reason_type' => 8,
                            'after_money' => bcadd($item['money'], $rewardMoney, 2),
                            'after_asset' => $item['asset'],
                            'before_asset' => $item['asset']
                        ]);
                        Db::name('lc_user')->where('id', $item['id'])->update(['money' => bcadd($item['money'], $rewardMoney, 2)]);
                    }
                }
                
                
                // if ($nextGrade['give_status'] == 2) {
                //     //赠送升级奖励
                //     $allActivity = Db::name("lc_member_grade")->where("id = {$item['grade_id']}")->sum('all_activity');
                //     $reward = bcdiv(($nextGrade['all_activity']-$allActivity)*$nextGrade['poundage'], 100, 2);
                //     //赠送记录
                //     Db::name('lc_finance')->insert([
                //         'uid' => $item['id'],
                //         'money' => $reward,
                //         'type' => 1,
                //         'zh_cn' => '升级为'.$nextGrade['title'],
                //         'zh_hk' => '升級為'.$nextGrade['title_zh_hk'],
                //         'en_us' => 'Upgrade to '.$nextGrade['title_en_us'],
                //         'before' => $item['money'],
                //         'time' => date('Y-m-d H:i:s', time()),
                //         'reason_type' => 8
                //     ]);
                //     Db::name('lc_user')->where('id', $item['id'])->update(['money' => bcadd($item['money'], $reward, 2)]);
                //     echo '赠送会员'.$item['phone'].'团队奖励'.$reward;
                // }
            }
        }
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

    /**
     * @description：首页
     * @date: 2020/5/13 0013
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        if(getInfo("pc_open")) $this->fetch();
        if(check_wap()) $this->fetch();
    }
    
    public function rate($money, $rate, $type){
        
    }
    
    //盲盒产品结算
    public function blind_setting()
    {
        $list = Db::name('lc_blind_buy_log')->where(['pay_status' => 1, 'status' => 0])->select();
        if (empty($list)) exit('暂无结算');
        foreach ($list as &$item) {
            // if ($item['period'] == 1) {
            //     $setting_time = $item['create_time'] + 86400;
            // } elseif($item['period'] == 2) {
            //     $setting_time = $item['create_time'] + 7*86400;
            // } elseif($item['period'] == 2) {
            //     $setting_time = $item['create_time'] + 30*86400;
            // }
            $setting_time = $item['create_time'] + 86400*$item['days'];
            //判断产品是否已经到期结算
            if ($setting_time > time()) {
                continue;
            }
            
            //结算奖励
            $userinfo = Db::name('lc_user')->find($item['uid']);
            if($userinfo){
                $reward_money = bcdiv($item['money']*$item['rate']*$item['days'], 100, 2);
                Db::name('lc_user')->where('id', $userinfo['id'])->update(['money' => bcadd($userinfo['money'], $reward_money, 2)]);
                //资金变动记录
                Db::name('lc_finance')->insert([
                    'uid' => $userinfo['id'],
                    'money' => $reward_money,
                    'type' => 1,
                    'zh_cn' => '盲盒产品到期奖励 '.$reward_money,
                    'before' => $userinfo['money'],
                    'time' => date('Y-m-d H:i:s', time()),
                    'after_money' => bcadd($userinfo['money'], $reward_money, 2),
                    'after_asset' => $userinfo['asset'],
                    'before_asset' => $userinfo['asset']
                ]);
                //退还本金
                $userinfo = Db::name('lc_user')->find($item['uid']);
                Db::name('lc_user')->where('id', $userinfo['id'])->update(['money' => bcadd($userinfo['money'], $item['money'], 2)]);
                //资金变动记录
                Db::name('lc_finance')->insert([
                    'uid' => $userinfo['id'],
                    'money' => $item['money'],
                    'type' => 1,
                    'zh_cn' => '退还购买盲盒产品本金 '.$item['money'],
                    'before' => $userinfo['money'],
                    'time' => date('Y-m-d H:i:s', time()),
                    'after_money' => bcadd($userinfo['money'], $item['money'], 2),
                    'after_asset' => $userinfo['asset'],
                    'before_asset' => $userinfo['asset']
                ]);
            }else{
               //print_r($item);exit; 
            }
            //修改订单状态
            Db::name('lc_blind_buy_log')->where('id', $item['id'])->update(['status' => 1, 'setting_time' => time()]);
            echo '结算成功';
        }
    }
    
    // public function ebao_crotab()
    // {
    //     $h = date('H', 1688472000);
    //     echo $h;
    // }
    
    public function ebao_crotab()
    {
        $user = Db::name('LcUser')->where('ebao','>',0)->field('id,ebao')->select();
        $ebao_rate = Db::name('LcReward')->where('id', 1)->find()['ebao_rate'];
        $now = time();
        // var_dump($user);exit;
        
        foreach ($user as $item) {
            //判断今日是否结算
            // if (Db::name('LcEbaoRecord')->where('uid', $item['id'])->where("to_days(time) = to_days(now())")->find()) {
            //     continue;   
            // }
            $LcTips = Db::name('LcTips')->where(['id' => '182']);
            $tempMoney = $item['ebao'] * $ebao_rate / 100;
            Db::name('LcEbaoRecord')->insert([
                'type' => 1,
                'title' => '途游宝每日收益',
                'money' => $tempMoney,
                'uid' => $item['id'],
                'time' => date('Y-m-d H:i:s', time())
            ]);
            Db::name('LcUser')->where('id', $item['id'])->update(['ebao' => bcadd($item['ebao'], $tempMoney, 2)]);
            
        }
        echo '结算完毕';
    }
    
    public function q($type, $hour){
        $q = 0;
        switch ($type) {
            case 1:
                $q = $hour;
                break;
            case 2:
                $q = ($hour / 24) < 1 ? 1 : ($hour / 24);
                break;
            case 3:
                $q = ($hour / 24 /7 ) < 1 ? 1 : ($hour / 24 /7);
                break;
            case 4:
                $q = ($hour / 24 / 30) < 1 ? 1 : ($hour / 24 / 30);
                break;
            case 5:
                $q = 1;
                break;
            default:
                $q = 0;
                break;
        }
        return $q;
    }
    
    /**
     * Describe:定时结算任务 升级 2023/2/7
     * DateTime: 2020/5/14 22:22
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function item_crontab()
    {
        $now = time(); // 因为数据库里面已经是越南时间了
        $redisKey = 'LockKey*';
        // $lock = new \app\api\util\RedisLock();
        // $lock->unlock($redisKey);
        $handler = Cache::store('redis')->handler();
        $data = $handler->keys($redisKey);
        foreach ($data as $key){
            $handler->del($key);
        }
        // 缓存时间 <= 当前时间
        $invest_list = Db::name("LcInvestList")->where("UNIX_TIMESTAMP(time1) <= $now AND status = '0'")->select();
        // $invest_list = Db::name("LcInvestList")->where("status = '0'")->where('iid', 2583)->select();//调试结算分红
        // echo json_encode($invest_list);die;
        if (empty($invest_list)) exit('暂无返息计划-'.date("Y-m-d H:i:s")."|".$now);
        foreach ($invest_list as $k => $v) {
            
            // 查询这个用户的投资记录，按期数倒叙
            $max = Db::name("LcInvestList")->field('id')->where(['uid' => $v['uid'], 'iid' => $v['iid']])->order('num desc')->find();
            $is_last = false;
            // 如果当前期数是最大期数
            if ($v['id'] == $max['id']) $is_last = true;
            $data = array('time2' => date('Y-m-d H:i:s'), 'pay2' => $v['pay1'], 'status' => 1);
            if (Db::name("LcInvestList")->where(['id' => $v['id'], 'status'=>0])->update($data)) {
                if ($v['pay1'] > 0) {
                    
                    
                    if ($is_last) {
                        if ($v['pay1'] <= 0) $v['pay1'] = 0;
                        Db::name('LcInvest')->where(['id' => $v['iid']])->update(['status' => 1, 'time2' => date("Y-m-d H:i:s")]);
                    }
                    $LcTips = Db::name('LcTips')->where(['id' => '182']);
                    //获取项目信息
                    $investInfo = Db::name('lc_invest') -> where('id', $v['iid']) -> find();
                    $itemInfo = Db::name('lc_item') -> where('id', $investInfo['pid']) -> find();
                    
                    //收益期数
                    // $periods = $this ->q($itemInfo['cycle_type'], $investInfo['hour']);
                    
                    
                    //加息
                    $user = Db::name('lc_user u')
                    ->join('lc_user_member m', 'u.member = m.id')
                    ->where('u.id', $v['uid'])
                    ->field('u.id,rate,member')
                    ->find();
                    
                    //购买产品时加息率
                    $user['rate'] = $investInfo['user_rate'];
                    // $user['member'] = Db::name('lc_user_member')->find($investInfo['user_member']);
                    $user['member'] = $investInfo['user_member'];
                    if ($itemInfo['show_home']) {
                        //购买产品时等级
                        $memberList = Db::name('lc_user_member')->order('value asc')->field('id,rate')->select(); 
                        foreach ($memberList as $key => $item) {
                            if($item['id'] == $user['member']) {
                                if($key+1 == count($memberList)) {
                                    $user['rate'] = $memberList[$key]['rate'];
                                    break;
                                } else {
                                    $user['rate'] = $memberList[$key+1]['rate'];
                                    break;
                                }
                            }
                        }
                        
                    }
                    $periods = 1;
                    if ($itemInfo['add_rate'] == 0) {
                        $user['rate'] = 0;
                    }
                    // $tempMoney = round(($itemInfo['rate']+$user['rate'])*$investInfo['money']/100/$periods, 2);
                    
                    //产品是否参与会员加息
                    $user_rate = 0;
                    if ($itemInfo['add_rate']) {
                        if ($investInfo['rate'] < $investInfo['user_rate']) {
                            $rate = $investInfo['rate'];
                        } else {
                            $rate = $investInfo['rate'] - $investInfo['user_rate'];
                        }
                    } else {
                        $rate = $investInfo['rate'];
                    }
                    
                    
                    if ($itemInfo['add_rate']) {
                        //会员加息率
                        $user = Db::name("LcUser")->find($v['uid']);
                        $member = Db::name("LcUserMember")->find($user['member']);
                        //首页热门精选获得高一等级的加息收益
                        if($itemInfo['show_home']==1){
                            $next_member = Db::name("LcUserMember")->where('value > '.$member['value'])->order('value asc')->find();
                            if($next_member) $member = $next_member;
                        }
                        $rate = $rate+$member['rate'];
                        $user_rate = $member['rate'];
                    }
                    
                    $nums = 1;
                    $addTime = "day";
                    $hour = $itemInfo['hour'];
                    $day = $itemInfo['day'];
                    $indexType = $itemInfo['cycle_type'];
                    // 判断项目投资的返利模式
                    if($indexType == 1){
                        // 按小时
                        $nums = $hour;
                        $addTime = "hour";
                    }else if($indexType == 2){
                        // 按日 小时 * 24
                        $nums = $hour / 24;
                    }else if($indexType == 3){
                        // 每周
                        $nums = ceil(intval($hour / 24 / 7));
                        $addTime = "week"; 
                    }else if($indexType == 4){
                        // 每月返利
                        $nums = ceil(intval($hour / 24 / 30));
                        $addTime = "month";
                    } else if($indexType == 6){
                        // 每年返利
                        $nums = ceil(intval($hour / 24 / 365));
                        $addTime = "year";
                    } 
                    if($nums < 1) $nums = 1;
                    $day = $hour/$nums;
                    
                    $money1 = round($investInfo['money'] * $rate / 100 , 2);
                    // var_dump($investInfo['money']);
                    // var_dump($indexType);
                    $day = $hour/24;
                    if ($indexType == 1) {
                       $money1 = round($investInfo['money'] * $rate / 24 / 100 , 2);
                    } elseif ($indexType == 2) {
                        $money1 = round($investInfo['money'] * $rate / 100 , 2);
                    } elseif ($indexType == 3) {
                        $money1 = round($investInfo['money'] * $rate * 7 / 100 , 2);
                    } elseif ($indexType == 4) {
                        $money1 = round($investInfo['money'] * $rate * 30 / 100 , 2);
                    } elseif ($indexType == 6) {
                        $money1 = round($investInfo['money'] * $rate * 365 / 100 , 2);
                    } elseif ($indexType == 5) {
                        $money1 = round($investInfo['money'] * $rate * $day  / 100 , 2);
                    }
                    // var_dump($money1);exit;
                    
                    // 纠错，这个用户的投资单需要直接为已固定金额
                    if($v['iid'] == 2583){
                        $money1 = $v['money1'];
                    }
                    
                    Db::name('lc_invest_list')->where('id', $v['id'])->update(['money1' => $money1, 'user_rate' => $user_rate]);
                    
                    $tempMoney = $money1;
                    // $tempMoney = $v['money1'];
                    
                    addFinance($v['uid'], $tempMoney, 1,
                        "《".$itemInfo['zh_cn']."》 " . $LcTips->value("name").vnd_gsh(bcdiv($tempMoney,1,2)) ,
                        "《".$itemInfo['zh_hk']."》 " . $LcTips->value("zh_hk").vnd_gsh(bcdiv($tempMoney,1,2)) ,
                        "《".$itemInfo['en_us']."》 " . $LcTips->value("en_us").vnd_gsh(bcdiv($tempMoney,1,2)) ,
                        "《".$itemInfo['vi_vn']."》 " . $LcTips->value("vi_vn").vnd_gsh(bcdiv($tempMoney,1,2)) ,
                        "《".$itemInfo['ja_jp']."》 " . $LcTips->value("ja_jp").vnd_gsh(bcdiv($tempMoney,1,2)) ,
                        "《".$itemInfo['ko_kr']."》 " . $LcTips->value("ko_kr").vnd_gsh(bcdiv($tempMoney,1,2)) ,
                        "《".$itemInfo['ms_my']."》 " . $LcTips->value("ms_my").vnd_gsh(bcdiv($tempMoney,1,2)) ,
                        // $itemInfo['zh_hk'] . $LcTips->value("zh_hk").$tempMoney ,
                        // $itemInfo['en_us'] . $LcTips->value("en_us").$tempMoney ,
                        // $itemInfo['zh_cn'] . $LcTips->value("th_th").$tempMoney ,
                        // $itemInfo['zh_cn'] . $LcTips->value("vi_vn").$tempMoney ,
                        // $itemInfo['zh_cn'] . $LcTips->value("ja_jp").$tempMoney ,
                        // $itemInfo['zh_cn'] . $LcTips->value("ko_kr").$tempMoney ,
                        // $itemInfo['zh_cn'] . $LcTips->value("ms_my").$tempMoney ,
                        "","",11
                    );
                    setNumber('LcUser', 'money', $tempMoney, 1, "id = {$v['uid']}");
                    setNumber('LcUser', 'income', $v['money1'], 1, "id = {$v['uid']}");
                    
                    $uid = $v['uid'];
                    
                    
                    
                    //推送
                    im_send_publish($uid,'Xin chào, thu nhập '.$v['money1'].'U vào tài khoản!');  // Xin chúc mừng bạn mua《'.$itemInfo['zh_hk'].'》Thu nhập'.$v['money1'].'USDT，Vào tài khoản！
                    
                    // 给上级进行返佣
                    // 先查询用户信息
                    $user =  Db::name("LcUser")->where("id = {$uid}")->find();
                    
                    $wait_invest = $v['money1'];
                    $wait_money = 0;
                    
                    //返回本金
                    if($v['money2'] > 0) {
                        Db::name('LcFinance')->insert([
                            'uid' => $v['uid'],
                            'money' => $v['money2'],
                            'type' => 1,
                            'zh_cn' => "《".$itemInfo['zh_cn'].'》，投资完成返还本金',
                            'zh_hk' => "《".$itemInfo['zh_hk'].'》，Khoản đầu tư hoàn thành',
                            'en_us' => "《".$itemInfo['en_us'].'》，Return of principal upon completion of investment',
                            'th_th' => "《".$itemInfo['zh_cn'].'》，投资完成返还本金',
                            'vi_vn' => "《".$itemInfo['zh_cn'].'》，投资完成返还本金',
                            'ja_jp' => "《".$itemInfo['zh_cn'].'》，投资完成返还本金',
                            'ko_kr' => "《".$itemInfo['zh_cn'].'》，投资完成返还本金',
                            'ms_my' => "《".$itemInfo['zh_cn'].'》，投资完成返还本金',
                            'before' => $v['money2'],
                            'time' => date('Y-m-d H:i:s', time()),
                            'after_money' => bcadd($user['money'], $v['money2'], 2),
                            'after_asset' => $user['asset'],
                            'before_asset' => $user['asset']
                        ]);
                        Db::name('LcUser')->where('id', $v['uid'])->update(['money' => bcadd($user['money'], $v['money2'], 2)]);
                        $wait_money = $v['money2'];
                    }
                    //增加待收利息、待还本金
                    Db::name('lc_user')->where('id', $uid)->update([
                        'wait_invest' => bcsub($user['wait_invest'], $wait_invest, 2),
                        'wait_money' => bcsub($user['wait_money'], $wait_money, 2)
                    ]);

                    $top=$user['top'];
                    $top2=$user['top2'];
                    $top3=$user['top3'];

                    // // 一级
                    // $topuser = Db::name("LcUser")->find($top);
                    // if($topuser && $top){
                    //     $invest1 = Db::name("LcUserMember")->where(['id'=>$topuser['member']])->value("invest1");
                    //     setRechargeRebate1($top, $v['money1'],$invest1);
                    // }

                    // //二级
                    // $topuser2 = Db::name("LcUser")->find($top2);
                    // if($topuser2 && $top2){
                    //     $invest2 = Db::name("LcUserMember")->where(['id'=>$topuser2['member']])->value("invest2");
                    //     setRechargeRebate1($top2, $v['money1'],$invest2);
                    // }
                    // //三级
                    // $topuser3 = Db::name("LcUser")->find($top3);
                    // if($topuser3 && $top3){
                    //     $invest3 = Db::name("LcUserMember")->where(['id'=>$topuser3['member']])->value("invest3");
                    //     setRechargeRebate1($top3, $v['money1'],$invest3);
                    // }
                }
            }
        }
    }
    
    
    public function eBaoCrontab(){
        //获取途游宝购买记录
        $eBaoList = Db::name('lc_ebao_product_record r')->join('lc_ebao_product p','r.product_id = p.id')->where('r.status', 0)->where('p.type', 0)->field('r.*')->select();
        for($i = 0; $i < count($eBaoList); $i++){
            
            //判断是否到达最大锁仓天数
            if($eBaoList[$i]['lock_day'] == $eBaoList[$i]['current_day'] || $eBaoList[$i]['lock_day'] < $eBaoList[$i]['current_day']){
                //跳出当前循环
                continue;
            }
            //判断是否距离上次结算超过1天
            $addTIme = $eBaoList[$i]['add_time'];
            if($eBaoList[$i]['current_day'] != 0){
                if(strtotime($eBaoList[$i]['last_settlement_time']) + ($eBaoList[$i]['current_day'] * 86400) > time()){
                    continue;
                }
            }else{
                if((strtotime($addTIme) + 86400) > time()){
                    continue;
                }
            }
            
            //开始结算
            //获取途游宝信息
            $eBaoInfo = Db::name('lc_ebao_product') -> where('id', $eBaoList[$i]['product_id']) -> find();
            $last_settlement_amount = $eBaoList[$i]['money'] * ($eBaoInfo['day_rate'] / 100);
            $data = [
                'current_day' => $eBaoList[$i]['current_day'] + 1,
                'last_settlement_time' => date('Y-m-d H:i:s', time()),
                'last_settlement_amount' => $last_settlement_amount,
                'status' => $eBaoList[$i]['lock_day'] == $eBaoList[$i]['current_day'] + 1 ? 1 : 0
            ];
            //更新购买信息
            Db::name('lc_ebao_product_record') -> where('id',$eBaoList[$i]['id']) -> update($data);
            //写入途游宝日志
            Db::name('lc_ebao_record') -> insert([
                'type' => 1,
                'title' => '途游宝收益'.$last_settlement_amount,
                'money' => $last_settlement_amount,
                'uid' => $eBaoList[$i]['uid'],
                'time' => date('Y-m-d H:i:s', time()),
                'status' => 1
            ]);
            //加入用户途游宝余额
            Db::name('lc_user') -> where('id', $eBaoList[$i]['uid']) -> setInc('ebao', $last_settlement_amount);
            //增加用户途游宝总收益
            Db::name('lc_user') -> where('id', $eBaoList[$i]['uid']) -> setInc('ebao_total_income', $last_settlement_amount);
            //判断是否是今日收益
            $userInfo = Db::name('lc_user')-> where('id', $eBaoList[$i]['uid']) -> find();
            if(empty($eBaoList[$i]['ebao_last_time']) || date('d', strtotime($eBaoList[$i]['ebao_last_time'])) != date('d', time())){
                //增加今日收益
                //获取用户信息
                
                if(empty($userInfo['ebao_last_income'])){
                    Db::name('lc_user') -> where('id', $eBaoList[$i]['uid']) -> update(['ebao_last_income' => $last_settlement_amount]);
                }else{
                     Db::name('lc_user') -> where('id', $eBaoList[$i]['uid']) -> setInc('ebao_last_income', $last_settlement_amount);
                }
            }else{
                //重置今日收益
                Db::name('lc_user') -> where('id', $eBaoList[$i]['uid']) -> update(['ebao_last_income' => $last_settlement_amount]);
            }
            
            
            
            
            //投资完成返还本金
            if($eBaoList[$i]['current_day']+1 == $eBaoList[$i]['lock_day']) {
                //加入用户途游宝余额
                Db::name('lc_user') -> where('id', $eBaoList[$i]['uid']) -> setInc('ebao', $eBaoList[$i]['money']);
                //返还记录
                Db::name('lc_ebao_record')->insert([
                    'type' => 1,
                    'title' => '返还途游宝投资 '.$eBaoList[$i]['money'],
                    'money' => $eBaoList[$i]['money'],
                    'uid' => $eBaoList[$i]['uid'],
                    'time' => date('Y-m-d H:i:s', time()),
                    'status' => 2
                ]);
            }
            
            Db::name('lc_user') -> where('id', $eBaoList[$i]['uid']) -> update(['ebao_last_time' => date('Y-m-d H:i:s', time())]);
            echo "结算订单【ID：{$eBaoList[$i]['id']}，金额：{$last_settlement_amount}，时间：" . date('Y-m-d H:i:s', time()) . "<br>";
        }
        echo '结算完毕';
    }
    
    public function eBaoCrontab2()
    {
        //获取途游宝购买记录
        $eBaoList = Db::name('lc_ebao_product_record r')->join('lc_ebao_product p','r.product_id = p.id')->where('r.status', 0)->where('p.type', 1)->field('r.*')->select();
        if (count($eBaoList)) {
            foreach ($eBaoList as &$item) {
                $time = time();
                $expire_time = $item['lock_day']*86400 + strtotime($item['add_time']);
                if ($time > $expire_time) {
                    $user = Db::name('lc_user')->find($item['uid']);
                    
                    $eBaoInfo = Db::name('lc_ebao_product') -> where('id', $item['product_id']) -> find();
                    $settlement_amount = $item['money'] * ($eBaoInfo['day_rate'] * $item['lock_day'] / 100);
                    //写入途游宝日志
                    Db::name('lc_ebao_record') -> insert([
                        'type' => 1,
                        'title' => '途游宝收益'.$settlement_amount,
                        'money' => $settlement_amount,
                        'uid' => $item['uid'],
                        'time' => date('Y-m-d H:i:s', time()),
                        'status' => 1
                    ]);
                    //加入用户途游宝余额
                    Db::name('lc_user') -> where('id', $item['uid']) -> setInc('ebao', $settlement_amount);
                    
                    //到期返本-返还记录
                    Db::name('lc_ebao_record')->insert([
                        'type' => 1,
                        'title' => '返还途游宝投资 '.$item['money'],
                        'money' => $item['money'],
                        'uid' => $item['uid'],
                        'time' => date('Y-m-d H:i:s', time()),
                        'status' => 2
                    ]);
                    //加入用户途游宝余额
                    Db::name('lc_user') -> where('id', $item['uid']) -> setInc('ebao', $item['money']);
                    
                    Db::name('lc_ebao_product_record')->where('id', $item['id'])->update([
                        'status' => 1,
                        'current_day' => $item['lock_day'],
                        'last_settlement_time' => date('Y-m-d H:i:s', time()),
                        'last_settlement_amount' => $settlement_amount
                    ]);
                    
                }
            }
        }
    }
    
    /**
     * Describe:定时结算任务 升级 2023/2/7
     * DateTime: 2020/5/14 22:22
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function item_crontab_bak()
    {
        $now = time();
        // 缓存时间 <= 当前时间
        $invest_list = Db::name("LcInvestList")->where("UNIX_TIMESTAMP(time1) <= $now AND status = '0'")->select();
        if (empty($invest_list)) exit('暂无返息计划');
        foreach ($invest_list as $k => $v) {
            // 查询这个用户的投资记录，按期数倒叙
               $iid=$v['iid'];//项目id
               $item=Db::name("lcItem")->where(['id' => $iid])->find();
               
          if($item){
              //每小时返利，到期返本
              if($item['cycle_type']==1){
                  
              }
              //每日返利，到期返本
               if($item['cycle_type']==2){
                  
              }
              //每周返利，到期返本
               if($item['cycle_type']==3){
                  
              }
              //每月返利，到期返本
               if($item['cycle_type']==4){
                  
              }
              //到期返本返利
               if($item['cycle_type']==5){
                        $max = Db::name("LcInvestList")->field('id')->where(['uid' => $v['uid'], 'iid' => $v['iid']])->order('num desc')->find();
                $is_last = false;
                // 如果当前期数是最大期数
                if ($v['id'] == $max['id']) $is_last = true;
                $data = array('time2' => date('Y-m-d H:i:s'), 'pay2' => $v['pay1'], 'status' => 1);
                if (Db::name("LcInvestList")->where(['id' => $v['id']])->update($data)) {
                    if ($v['pay1'] > 0) {
                    if ($is_last) {
                        if ($v['pay1'] <= 0) $v['pay1'] = 0;
                        Db::name('LcInvest')->where(['id' => $v['iid']])->update(['status' => 1, 'time2' => date("Y-m-d H:i:s")]);
                    }
                    $LcTips = Db::name('LcTips')->where(['id' => '182']);
                    addFinance($v['uid'], $v['pay1'], 1,
                        $v['zh_cn'] . $LcTips->value("name").$v['pay1'] ,
                        $v['zh_cn'] . $LcTips->value("zh_cn").$v['pay1'] ,
                        $v['zh_cn'] . $LcTips->value("en_us").$v['pay1'] ,
                        $v['zh_cn'] . $LcTips->value("th_th").$v['pay1'] ,
                        $v['zh_cn'] . $LcTips->value("vi_vn").$v['pay1'] ,
                        $v['zh_cn'] . $LcTips->value("ja_jp").$v['pay1'] ,
                        $v['zh_cn'] . $LcTips->value("ko_kr").$v['pay1'] ,
                        $v['zh_cn'] . $LcTips->value("ms_my").$v['pay1'] ,
                        "","",11
                    );
                    setNumber('LcUser', 'money', $v['pay1'], 1, "id = {$v['uid']}");
                    setNumber('LcUser', 'income', $v['money1'], 1, "id = {$v['uid']}");

                    $uid = $v['uid'];
                    // 给上级进行返佣
                    // 先查询用户信息
                    $user =  Db::name("LcUser")->where("id = {$uid}")->find();

                    $top=$user['top'];
                    $top2=$user['top2'];
                    $top3=$user['top3'];

                    // 一级
                    $topuser = Db::name("LcUser")->find($top);
                    if($topuser && $top){
                        $invest1 = Db::name("LcUserMember")->where(['id'=>$topuser['member']])->value("invest1");
                        setRechargeRebate2($top, $v['money1'],$invest1);
                    }

                    //二级
                    $topuser2 = Db::name("LcUser")->find($top2);
                    if($topuser2 && $top2){
                        $invest2 = Db::name("LcUserMember")->where(['id'=>$topuser2['member']])->value("invest2");
                        setRechargeRebate2($top2, $v['money1'],$invest2);
                    }
                    //三级
                    $topuser3 = Db::name("LcUser")->find($top3);
                    if($topuser3 && $top3){
                        $invest3 = Db::name("LcUserMember")->where(['id'=>$topuser3['member']])->value("invest3");
                        setRechargeRebate2($top3, $v['money1'],$invest3);
                    }
                }
            }
              }
           
               }
      
        }
    }


    /**
     * 途游宝定时结算任务
     * @return void
     */
    public function ebao_crontab(){
        // 查询需要结算的数据
        $now = time();
        // 下次结算时间 <= 当前时间，并且途游宝有钱的用户
        $userList = Db::name("LcUser")->where("UNIX_TIMESTAMP(ebao_next_time) <= $now AND ebao >= 1")->select();
        if (empty($userList)) exit('暂无途游宝结算计划');
        // 查询利率
        $reward = Db::name("LcReward")->find(1);
        $rete = $reward['ebao_rate'];
        // 开始计算结算信息
        foreach ($userList as $k => $v) {
            // 开始计算收益
            $income = floor($rete * $v['ebao'] / 100);
            // 增加流水
            $ebaoRecord = array(
                'uid' => $v['id'],
                'money' => $income,
                'type' => 1,
                'title' => '途游宝收益 ' . $income,
                'time' => date('Y-m-d H:i:s')
            );
            $int = Db::name('LcEbaoRecord')->insert($ebaoRecord);
// var_dump($income);die;
            // 修改用户结算信息
            Db::name('LcUser')->where("id = {$v['id']}")->update([
                'ebao_last_time' => date('Y-m-d H:i:s'),
                'ebao_next_time' => date("Y-m-d",strtotime("+1 hours")),
                'ebao_last_income' => $income,
                'ebao_total_income' => $v['ebao_total_income'] + $income
            ]);
            // 增加收益
            Db::name('LcUser')->where("id = ". $v['id'])->setInc('ebao', $income);
        }
        if (empty($userList)) exit('任务结束');
    }

    /**
     * Describe:支付宝APP支付回调
     * DateTime: 2020/12/07 2:07
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function alipay_notify(){
        $data = $this->request->param();
        $out_trade_no = $data['out_trade_no'];
        $trade_status =  $data['trade_status'];
        if ($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
            require_once env("root_path") . "/vendor/alipayapp/aop/AopClient.php";
            $aop = new \AopClient();
            $aop->alipayrsaPublicKey = getInfo('alipay_public_key');
            $sign_check = $aop->rsaCheckV2($data, NULL, "RSA2");
            $recharge = Db::name("LcRecharge")->where(['orderid'=>$out_trade_no])->find();
            if($recharge&&$recharge['status'] == 0){
                $money = $recharge['money'];
                $uid = $recharge['uid'];
                $type = $recharge['type'];
                addFinance($uid, $money,1, $type . '入款' . $money);
                setNumber('LcUser', 'money', $money, 1, "id = $uid");
                sendSms(getUserPhone($uid), '18005', $money);
                $tid = Db::name('LcUser')->where('id', $uid)->value('top');
                if($tid) setRechargeRebate($tid, $money);
                $res = Db::name("LcRecharge")->where(['orderid'=>$out_trade_no])->update(['status' => '1','time2' => date('Y-m-d H:i:s')]);
                if($res) echo 'success';
            }elseif ($recharge['status'] == 1){
                echo 'success';
            }else {
                echo 'fail';
            }
        }else {
            echo "fail";
        }
    }


    public function item_auto_sale(){
        $item = Db::name("LcItem")->field("id,auto")->where("auto > 0")->select();
        if($item){
            foreach ($item as $v){
                setNumber('LcItem', 'sales_base', $v['auto'], 1, "id = {$v['id']}");
            }
        }
    }
    public function item_auto_percent(){
        $item = Db::name("LcItem")->field("id,percent,percent_add")->where("0<percent_add<100")->select();
        if($item){
            foreach ($item as $v){
                if($v['percent']+$v['percent_add']>=100){
                    Db::name("LcItem")->where(['id'=>$v['id']])->update(['percent' => 100, 'complete_time' => date('Y-m-d H:i:s', time())]);
                }else{
                    setNumber('LcItem', 'percent', $v['percent_add'], 1, "id = {$v['id']}");
                }
            }
        }
    }




    /**
     * 矿机定时结算任务
     * @return void
     */
    public function machines_crontab(){
        // 查询需要结算的数据
        $now = time();
        // 下次结算时间 <= 当前时间，并且没有到期
        $machinesList = Db::name("LcMachinesList")->where("UNIX_TIMESTAMP(next_run_time) <= $now AND UNIX_TIMESTAMP(end_time) > $now and num >= 1")->select();
        if (empty($machinesList)) exit('暂无矿机结算计划');
        // 查询利率
//        $reward = Db::name("LcReward")->find(1);
//        $rete = $reward['ebao_rate'];
        // 开始计算结算信息
        foreach ($machinesList as $k => $v) {

            $uid = $v['uid'];

            // 查询用户
            $user = Db::name('LcUser')->find($v['uid']);
            // 查询矿机收益率
            $userMember = Db::name('LcUserMember')->find($user['member']);

            $moneyBase = 1000;
            if($user['money'] > 1000){
                $moneyBase= $user['money'];
            }
            // 开始计算收益
            $income = floor($moneyBase * $userMember['machine_rate']);
            // 修改矿机收益
            Db::name('LcMachinesList')->where("id = {$v['id']}")->update([
                'next_run_time' => date("Y-m-d H:i:s",strtotime("6 hour")),
                'income' => $v['income'] + $income
            ]);

            $finance = array(
                'uid' => $v['uid'],
                'type' => 1,
                'title' => "矿机收益",
                'amount' => $income,
                'add_time' => date('Y-m-d H:i:s')
            );
            Db::name('LcMechinesFinance')->insert($finance);

            // 增加收益流水
//            addFinance($v['uid'], $income, 1,
//                '《矿机收益》，'. $income,
//                '《矿机收益》，'.$income,
//                '《矿机收益》，'.$income,
//                '《矿机收益》，'.$income,
//                '《矿机收益》，'.$income,
//                '《矿机收益》，'.$income,
//                '《矿机收益》，'.$income,
//                '《矿机收益》，'.$income,
//                "","",12
//            );
            // 增加收益
            Db::name('LcUser')->where("id = {$uid}")->setInc('kj_money', $income);
        }
        if (empty($userList)) exit('任务结束');
    }


    /**
     * 投资k线生成
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function wave_contab(){
        $now = time();
        // 查询上架中项目
        $items = Db::name("LcItem")->where("status = 1")->select();
        foreach ($items as $k => $v) {
            // 增加一次数据
            // 获取随机涨幅
            $num = rand(0, 100);
            // 增加流水
            $wave = array(
                'item_id' => $v['id'],
                'num' => $num,
                'type' => 1,
                'hour' => $v['hour'],
                'time' => date('Y-m-d H:i:s')
            );
            $int = Db::name('LcItemWave')->insert($wave);
        }
    }



    /**
     * 途游宝自动转入
     * @return void
     */
    public function in_ebao_crontab(){
        // 查询需要结算的数据
        $now = time();
        // 下次结算时间 <= 当前时间，并且途游宝有钱的用户
        // $userList = Db::name("LcUser")->where(" money >= 1 and auto_in_ebao = 1 and UNIX_TIMESTAMP(in_ebao_start) <= $now AND UNIX_TIMESTAMP(in_ebao_end) ")->select();
        $userList = Db::name("LcUser")->where(" money >= 1 and auto_in_ebao = 1")->select();
        // 开始计算结算信息
        foreach ($userList as $k => $v) {
            if ($v['money'] <= 0) {
                continue;
            }
            // 减少用户余额
            addFinance($v['id'], $v['money'], 2,
                '《途游助手自动转入途游宝》，'. $v['money'],
                '《途游助手自動轉入途遊寶》，'.$v['money'],
                '《Intelligent assistant automatically transfers to the piggy bank》，'.$v['money'],
                '《途游助手自动转入途游宝》，'.$v['money'],
                '《途游助手自动转入途游宝》，'.$v['money'],
                '《途游助手自动转入途游宝》，'.$v['money'],
                '《途游助手自动转入途游宝》，'.$v['money'],
                '《途游助手自动转入途游宝》，'.$v['money'],
                "","",12
            );
            Db::name('LcUser')->where("id = ". $v['id'])->setDec('money', $v['money']);
            // 增加途游宝流水
            $ebaoRecord = array(
                'uid' => $v['id'],
                'money' => $v['money'],
                'type' => 1,
                'title' => '途游助手自动转入 ' . $v['money'],
                'title_zh_hk' => '途游助手自動轉入 ' . $v['money'],
                'title_en_us' => 'Intelligent assistant automatically transfers in ' . $v['money'],
                'time' => date('Y-m-d H:i:s')
            );
            $int = Db::name('LcEbaoRecord')->insert($ebaoRecord);
            // 增加收益
            Db::name('LcUser')->where("id = ". $v['id'])->setInc('ebao', $v['money']);
        }
        if (empty($userList)) exit('任务结束');
    }



    /**
     * 途游宝自动转出
     * @return void
     */
    public function out_ebao_crontab(){
        // 查询需要结算的数据
        $now = time();
        // 下次结算时间 <= 当前时间，并且途游宝有钱的用户
        // $userList = Db::name("LcUser")->where(" ebao >= 1 and auto_out_ebao = 1 and UNIX_TIMESTAMP(in_ebao_start) <= $now AND UNIX_TIMESTAMP(in_ebao_end) ")->select();
        $userList = Db::name("LcUser")->where(" ebao >= 0.01 and auto_in_ebao = 1")->select();
        // var_dump($userList);exit;
        // 开始计算结算信息
        foreach ($userList as $k => $v) {
            // 增加流水
            $ebaoRecord = array(
                'uid' => $v['id'],
                'money' => $v['ebao'],
                'type' => 2,
                'title' => '途游助手自动转出 ' . $v['ebao'],
                'title_zh_hk' => '途遊助手自動轉出 ' . $v['ebao'],
                'title_en_us' => 'Tuyou Assistant Automatically Transfers Out ' . $v['ebao'],
                'time' => date('Y-m-d H:i:s')
            );
            $int = Db::name('LcEbaoRecord')->insert($ebaoRecord);

            // 减少途宝游金额
            Db::name('LcUser')->where("id = ". $v['id'])->setDec('ebao', $v['ebao']);

            // 增加用户余额
            addFinance($v['id'], $v['ebao'], 1,
                '《途游助手自动转出途游宝》，'. $v['ebao'],
                '《途游助手自動轉出途游宝》，'.$v['ebao'],
                '《Intelligent assistant automatically transfers out of the piggy bank》，'.$v['ebao'],
                '《途游助手自动转出途游宝》，'.$v['ebao'],
                '《途游助手自动转出途游宝》，'.$v['ebao'],
                '《途游助手自动转出途游宝》，'.$v['ebao'],
                '《途游助手自动转出途游宝》，'.$v['ebao'],
                '《途游助手自动转出途游宝》，'.$v['ebao'],
                "","",12
            );
            Db::name('LcUser')->where("id = ". $v['id'])->setInc('money', $v['ebao']);
        }
        if (empty($userList)) exit('任务结束');
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

    public function df_notify(){
        $runParam = $this->request->param();
        Log::error($runParam);
        $member_id = $this->request->param('member_id');
        $mch_order_no = $this->request->param('order_no');
        $order_no = $this->request->param('sys_order_no');
        $amount = $this->request->param('amount');
        $status = $this->request->param('status'); // 	0：处理中 1：已出款 2：已驳回 3：已冲正
        $sign = $this->request->param('sign');
        
        if(empty($member_id) || empty($mch_order_no) || empty($order_no) || empty($amount) || empty($status) || empty($sign)){
            return 'fail1';
        }
        
        $signArr = [
            'member_id' => $member_id,
            'order_no' => $mch_order_no,
            'sys_order_no' => $order_no,
            'amount' => $amount,
            'status' => $status,
        ];
        $signBet = $this->generateSignature($signArr, 'dieezvo6ewmade5l1nwbs48jjgo53aq7');
        
        // if($signBet != $sign){
        //     return 'fail1_sgin';
        // }
        
        if(!in_array($status, [1, 2, 3])){
            return 'fail1_status';
        }
        $betInfo = Db::name('LcCash')->where('order_no', $mch_order_no)->where('status', 1)->find();
        if(!$betInfo){
            return 'fail1_info';
        }
        if($betInfo['df_status'] != 1){
            return 'success';
        }
        if($status == 1){
            Db::name('LcCash')->where('order_no', $mch_order_no)->update([
                'df_status' => 2,
            ]);
            return 'success';
        }
        if($status == 2){
            Db::name('LcCash')->where('order_no', $mch_order_no)->update([
                'df_status' => 3,
                'status' => 2,
            ]);
            //拒绝时返还提现金额
            $LcTips = Db::name('LcTips')->where(['id' => '155']);
            addFinance($betInfo['uid'], $betInfo['money'],1, 
            $LcTips->value("zh_cn"). $betInfo['money'],
            $LcTips->value("zh_hk"). $betInfo['money'] ,
            $LcTips->value("en_us"). $betInfo['money'] ,
            $LcTips->value("th_th"). $betInfo['money'] ,
            $LcTips->value("vi_vn"). $betInfo['money'] ,
            $LcTips->value("ja_jp"). $betInfo['money'] ,
            $LcTips->value("ko_kr"). $betInfo['money'] ,
            $LcTips->value("ms_my"). $betInfo['money'] ,
            "","",2
            );
            setNumber('LcUser', 'money', $betInfo['money'], 1, "id = {$betInfo['uid']}");
            //返还手续费
            if($betInfo['charge']>0){
                $LcTips191 = Db::name('LcTips')->where(['id' => '191']);
                addFinance($betInfo['uid'], $betInfo['charge'],1, 
                $LcTips191->value("zh_cn"). $betInfo['charge'] ,
                $LcTips191->value("zh_hk"). $betInfo['charge'] ,
                $LcTips191->value("en_us"). $betInfo['charge'] ,
                $LcTips191->value("th_th"). $betInfo['charge'] ,
                $LcTips191->value("vi_vn"). $betInfo['charge'] ,
                $LcTips191->value("ja_jp"). $betInfo['charge'] ,
                $LcTips191->value("ko_kr"). $betInfo['charge'] ,
                $LcTips191->value("ms_my"). $betInfo['charge'] ,
                "","",9
                );
                setNumber('LcUser', 'money', $betInfo['charge'], 1, "id = {$betInfo['uid']}");
            }
            return 'success';
        }
        
        return 'fail';
    }
    
    public function pay_notify(){
        $runParam = $this->request->param();
        Log::error($runParam);
        $member_id = $this->request->param('member_id');
        $mch_order_no = $this->request->param('mch_order_no');
        $order_no = $this->request->param('order_no');
        $amount = $this->request->param('amount');
        $status = $this->request->param('status');
        $sign = $this->request->param('sign');
        
        if(empty($member_id) || empty($mch_order_no) || empty($order_no) || empty($amount) || empty($status) || empty($sign)){
            return 'fail1';
        }
        
        $signArr = [
            'member_id' => $member_id,
            'mch_order_no' => $mch_order_no,
            'order_no' => $order_no,
            'amount' => $amount,
            'status' => $status,
        ];
        $signBet = $this->generateSignature($signArr, 'dieezvo6ewmade5l1nwbs48jjgo53aq7');
        
        // if($signBet != $sign){
        //     return 'fail1_sgin';
        // }
        
        if($status != 1){
            return 'fail1_status';
        }
        
        $order = Db::name('LcRecharge')->where(['orderid' => $mch_order_no, 'status' => 0])->find();
        if(!$order){
            return 'OK';
        }
        
        // if(bcmul($amount, 100, 0) < $order['money2']){
        //     return 'fail1_money2';
        // }
        
        // 入账
        $rel = $this->autoSh($order['uid'], $order['id']);
        if(!$rel){
            return 'fail1_rz';
        }
        Db::name('LcRecharge')->where(['orderid' => $mch_order_no, 'status' => 0])->update(['status'=> 1]);
        return 'OK';
    }
    
    
    public function autoSh($uid,$oid){
        $recharge = Db::name('LcRecharge')->find($oid);
        if($recharge&&$recharge['status'] == 0||$recharge['status'] == 3){
            $money = $recharge['money'];
            $money2 = $recharge['money2'];
            $uid = $recharge['uid'];
            $type = $recharge['type'];

            $LcTips152 = Db::name('LcTips')->where(['id' => '152']);
            $LcTips153 = Db::name('LcTips')->where(['id' => '153']);
            
            // if ($recharge['pid'] == 21) {
            //     $money = $money2;
            // }
            addFinance($uid, $money,1,
                $type .$LcTips152->value("zh_cn").$money,
                $type .$LcTips152->value("zh_hk").$money,
                $type .$LcTips152->value("en_us").$money,
                $type .$LcTips152->value("th_th").$money,
                $type .$LcTips152->value("vi_vn").$money,
                $type .$LcTips152->value("ja_jp").$money,
                $type .$LcTips152->value("ko_kr").$money,
                $type .$LcTips152->value("ms_my").$money,
                "","",1
            );
            
            setNumber('LcUser', 'asset', $money, 1, "id = $uid");
            //成长值
            // setNumber('LcUser','value', $money, 1, "id = $uid");

            $dd = Db::name("LcUser")->where("id = {$uid}")->find();
            
            //标记
            Db::name('lc_user')->where('id', $uid)->update(['sign_status' => 0]);

            // 增加累计充值金额
            Db::name("LcUser")->where("id = {$uid}")->setInc("czmoney", $money);


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
           
            setRechargeRebate1($uid, $money,$member_rate,'个人充值奖励');
            //团队奖励
            //  $poundage = Db::name("LcMemberGrade")->where(['id'=>$user['grade_id']])->value("poundage");
            // setRechargeRebate1($uid, $money2,$poundage,'团队奖励');
            // //返给上级团长
            // $topuser = Db::name("LcUser")->find($top);
            // $poundage = Db::name("LcMemberGrade")->where(['id'=>$topuser['grade_id']])->value("poundage");
            // setRechargeRebate1($topuser['id'], $money2,$poundage,'团队奖励');
            
            return true;
        }
   }
}
