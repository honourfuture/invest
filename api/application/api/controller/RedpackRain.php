<?php

namespace app\api\controller;

use library\Controller;
use think\Db;

class RedpackRain extends Controller
{
    //活动状态
    public function activity()
    {
        $config = Db::name('lc_info')->find(1);
        $this->success('获取成功', ['status' => $config['redpack_rain']]);
    }
    
    //获取当前等级限购次数
    public function get_able_buy_num($member,$able_buy_num)
    {
        $able_buy_num = explode(',', $able_buy_num);
        $user_member = Db::name('LcUserMember')->select();
        $num = 0;
        foreach ($user_member as $key => $value) {
            if ($value['id'] == $member) {
                $num = $able_buy_num[$key];break;
            }
        }
        return $num;
    }
    
    //抽奖
    public function lottery()
    {
        // $uid = 38724;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user = Db::name('lc_user')->find($uid);
        
        //当天抽奖次数
        $start_time = strtotime(date('Y-m-d', time()));
        $cur_size = Db::name('lc_redpack_prize_log')->where('uid', $uid)->where("UNIX_TIMESTAMP(createtime) > $start_time")->count();
        
        //当前等级限购次数
        $info = Db::name('lc_info')->find(1);
        $redpack_lottery_nums = $this->get_able_buy_num($user['member'], $info['redpack_lottery_nums']);
        if ($cur_size >= $redpack_lottery_nums) {
            $this->error('抽奖次数已达当日上限');
        }
        
        //获取当前用户可以抽取的奖品
        $prizes = Db::name('lc_redpack_prize')->where("FIND_IN_SET({$user['member']},members)")->where('status', 1)->select();
        if (!count($prizes)) {
            $this->error('暂无可抽取的奖品');
        }
        
        //抽奖算法
        foreach ($prizes as $item) {
            $arr[$item['id']] = $item['rate'];
        }
        $rid = $this->get_rand($arr);
        $prize_id = $prizes[$rid-1]['id'];
        $prize = Db::name('lc_redpack_prize')->find($prize_id);
        
        //记录中奖信息
        Db::name('lc_redpack_prize_log')->insert([
            'uid' => $uid,
            'type' => $prize['type'],
            'name' => $prize['name'],
            'value' => $prize['value'],
            'coupon_id' => $prize['coupon_id'],
            'createtime' => date('Y-m-d H:i:s', time())
        ]);
        
        if ($prize['type'] == 1) {  //奖励代金券
            $coupon_info = Db::name('lc_coupon')->field('id,name,money,need_money,differ_num')->find($prize['coupon_id']);
            //生成优惠券记录
            $time = time();
            Db::name('lc_coupon_list')->insert([
                'coupon_id' => $coupon_info['id'],
                'uid' => $uid,
                'expire_time' => date('Y-m-d H:i:s', ($time+7*86400)),
                'money' => $coupon_info['money'],
                'need_money' => $coupon_info['need_money'],
                'createtime' => date('Y-m-d H:i:s', $time)
            ]);
            Db::name('lc_coupon')->where('id',$coupon_info['id'])->update(['differ_num' => ($coupon_info['differ_num']-1)]);
        } elseif ($prize['type'] == 2) { //奖励积分
            // 创建积分明细
            $pointRecord = array(
                'uid' => $uid,
                'num' => $prize['value'],
                'type' => 1,
                'zh_cn' => '红包雨抽奖获得'.$prize['value'].'积分',
                'zh_hk' => '紅包雨抽獎獲得'.$prize['value'].'積分',
                'en_us' => 'Red envelope rain lottery wins '.$prize['value'].' points',
                'th_th' => '红包雨抽奖获得'.$prize['value'].'积分',
                'vi_vn' => '红包雨抽奖获得'.$prize['value'].'积分',
                'ja_jp' => '红包雨抽奖获得'.$prize['value'].'积分',
                'ko_kr' => '红包雨抽奖获得'.$prize['value'].'积分',
                'ms_my' => '红包雨抽奖获得'.$prize['value'].'积分',
                'time' => date('Y-m-d H:i:s'),
                'before' => $user['point_num']
            );
            Db::name('LcPointRecord')->insert($pointRecord);
            // 赠送积分
            $point_num = bcadd($user['point_num'], $prize['value']);
            Db::name('LcUser')->where('id', $uid)->update(['point_num' => $point_num]);
        } elseif ($prize['type'] == 3) { //奖励余额
            //余额记录
            Db::name('lc_finance')->insert([
                'uid' => $uid,
                'money' => $prize['value'],
                'type' => 1,
                'zh_cn' => '红包雨抽奖获得'.$prize['value'].'元',
                'zh_hk' => '紅包雨抽獎獲得'.$prize['value'].'元',
                'en_us' => 'Red envelope rain lottery wins '.$prize['value'],
                'before' => $user['money'],
                'time' => date('Y-m-d H:i:s', time()),
                'reason_type' => 101,   //红包雨
                'after_money' => bcadd($user['money'], $prize['value'], 2),
                'after_asset' => $user['asset'],
                'before_asset' => $user['asset']
            ]);
            Db::name('lc_user')->where('id', $user['id'])->update([
                'money' => bcadd($user['money'], $prize['value'], 2)
            ]);
        }
        $this->success('获取成功', ['prize_name' => $prize['name']]);
        
    }
    
    public function get_rand($proArr)
    {
        $result = '';
        $proSum = array_sum($proArr);
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1,$proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }
        return $result;
    }
}