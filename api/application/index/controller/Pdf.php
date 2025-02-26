<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2023  天美工作室 [   ]
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

/**
 * 应用入口
 * Class Index
 * @package app\index\controller
 */
class Pdf extends Controller
{
    public function show(){
        $invest_id = @input('invest_id');
        $language = $this->request->param('language', 'zh_cn');
        //获取合同模板
        $contract = Db::name('lc_contract') -> field('*,content as content_zh_cn')-> where('id', 1) -> find();
        $contract['content'] = $contract['content_'.$language];
        $contract = $contract['content'];
        
        //获取合同信息
        $invest = Db::name('lc_invest') -> where('id', $invest_id) -> find();
        // var_dump($invest);exit;
        if(empty($invest)) return '合同不存在';
        
        //获取项目信息
        $pid = $invest['pid'];
        $item = Db::name('lc_item') -> where('id', $pid) -> find();
        if(empty($item)) return '项目不存在';
        
        //获取用户信息
        $userInfo = Db::name('lc_user') -> where('id', $invest['uid']) -> find();
        if(empty($userInfo)) return '用户不存在';
        //获取用户等级
        $user_member = Db::name('lc_user_member') -> where('id', $userInfo['member']) -> find();
        $cur_member = Db::name('lc_user_member')->find($invest['user_member']);
        $user_member['rate'] = $invest['user_rate'];
        $user_member['name'] = $cur_member['name'];
        $user_member['value'] = $cur_member['value'];
        //首页热门精选获得高一等级的加息收益
        // if($item['show_home']==1){
        //     $next_member = Db::name("lc_user_member")->where('value > '.$user_member['value'])->order('value asc')->find();
        //     if($next_member) $user_member = $next_member;
        // }
        $period = $this -> q($item['cycle_type'], $invest['hour']);
        // $period = 1;
        
        //替换文本
        $nterest = $this -> nterest($invest['money'], $invest['hour'], $item['rate'], $item['cycle_type'], $period);
        
        $contract = str_replace('${name}', $invest[$language], $contract);
        $contract = str_replace('${rate}', $item['rate'], $contract);
        $contract = str_replace('${cycle}', $invest['hour'] / 24 . ' Ngày', $contract);
        $contract = str_replace('${cycleNum}', bcdiv($this -> q($item['cycle_type'], $invest['hour']),1,0), $contract);
        // $contract = str_replace('${cycleText}', $this->cycleText($item['cycle_type']), $contract);
        $contract = str_replace('${cycleText}', $this->cycle_type($item['cycle_type'], $language), $contract);
        $contract = str_replace('${userName}', $userInfo['name'], $contract);
        $contract = str_replace('${signName}', "<img src='" . $invest['sign_base64'] . "' style='height: 50px;'/>", $contract);
        $contract = str_replace('${cardNo}', $userInfo['idcard'], $contract);
        $contract = str_replace('${money}', $invest['money'], $contract);
        $contract = str_replace('${nterest}', $nterest, $contract);
        $contract = str_replace('${createTime}', $invest['time'], $contract);
        
        if ($item['add_rate'] == 0) {
            $user_member['rate'] = 0;
        }
        $investlist = Db::name('lc_invest_list')->where('iid', $invest['id'])->order('id asc')->select();
        
        // 对小数点处理
        foreach ($investlist as &$invItem){
            $invItem['money1'] = bcdiv($invItem['money1'],1,3);
        }
        
        $contract = str_replace('${table}', $this -> fl_table($invest['money'], $invest['hour'], $item['rate'], $item['cycle_type'], $invest['time'], $user_member, $period, $language, $investlist), $contract);
        if($item['show_home']==1){
            $contract = str_replace('${nextMember}', '享受高一等级的加息', $contract);
        }else{
            $contract = str_replace('${nextMember}', '', $contract);
        }
        $contract = str_replace('${memberName}', $user_member['name'], $contract);
        $contract = str_replace('${memberRate}', $user_member['rate'], $contract);
        $contract = str_replace('${memberIncome}', $this -> nterest($invest['money'], $invest['hour'], $user_member['rate'], $item['cycle_type'], $period) + $nterest, $contract);
        $title_arr = [
            'zh_cn' => '电子合同',
            'zh_hk' => 'Hợp đồng điện tử',
            'en_us' => 'Contract'
        ];
        
        return '<html><head><title>'.$title_arr[$language].'</title><meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0" /> </head>  <style>*{font-family: Arial,PingFang SC,Hiragino Sans GB,STHeiti,Microsoft YaHei,WenQuanYi Micro Hei,sans-serif,noto-fanyi;}</style><body>'.$contract . '<img alt="" src="/11.png" style="max-width:140px;border:0;position: relative;top: -300px;left: 150px;transform: rotate(10deg);"><img alt="" height="100px" id="seal2" src="/2.png" style="max-width:100%;border:0;position: relative;top: -90px;left: 30px;transform: rotate(60deg);" width="100px" />' ."</body></html>";
    }
    
    function cycle_type($value, $language) 
    {
        $arr_zh_cn = [
            '每小时返利，到期返本',
            '每日返利，到期返本',
            '每周返利，到期返本',
            '每月返利，到期返本',
            '到期返本返利',
            '每年返利，到期返本'
        ];
        $arr_zh_hk = [
            'Giảm giá mỗi giờ, hết hạn trở về nhà',
            'Giảm giá hàng ngày, hết hạn trở lại',
            'Giảm giá mỗi tuần, hết hạn trở lại',
            'Giảm giá hàng tháng, hết hạn trở lại',
            'Hết hạn trở về nhà giảm giá này',
            'Giảm giá hàng năm, hết hạn trở về nhà'
        ];
        $arr_en_us = [
            'Hourly rebate',
            'Daily rebates, principal refunds upon expiration',
            'Weekly rebates, principal refunds upon expiration',
            'Monthly rebates, principal refunds upon expiration',
            'Due principal rebate',
            'Annual rebate, principal refund upon expiration'
        ];
        if ($language == 'zh_cn') {
            return $arr_zh_cn[$value-1];
        } elseif ($language == 'zh_hk') {
            return $arr_zh_hk[$value-1];
        } elseif ($language == 'en_us') {
            return $arr_en_us[$value-1];
        }
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
            case 6:
                $q = ($hour / 24 / 365) < 1 ? 1 : ($hour / 24 / 365);
                break;
            default:
                $q = 0;
                break;
        }
        return $q;
    }
    //类型计算
    public function cycleText($type){
        $text = '';
        switch ($type) {
            case 1:
                $text = '每小时返利，到期返本';
                break;
            case 2:
                $text = '每日返利，到期返本';
                break;
            case 3:
                $text = '每周返利，到期返本';
                break;
            case 4:
                $text = '每月返利，到期返本';
                break;
            case 5:
                $text = '到期返本返利';
                break;
            case 6:
                $text = '每年返利，到期返本';
                break;
            default:
                $text = '未设置类型';
                break;
        }
        return $text;
    }
    
    //计算利息
    public function nterest($money, $hour, $rate, $type,$period){
        $rate = $rate / 100;
        $total = 0;
        $day = $hour / 24;
        switch ($type) {
            case 1:
                $total = $money * $rate * $hour / $period * $day;
                break;
            case 2:
                $q = ($hour / 24) < 1 ? 1 : ($hour / 24);
                $total = $money * $rate * $q / $period * $day;
                break;
            case 3:
                $q = ($hour / 24 /7 ) < 1 ? 1 : ($hour / 24 /7);
                $total = $money * $rate * $q / $period * $day;
                break;
            case 4:
                $q = ($hour / 24 / 30) < 1 ? 1 : ($hour / 24 / 30);
                $total = $money * $rate * $q / $period * $day;
                break;
            case 5:
                $text = '到期返本返利';
                $total = $money * $rate / $period * $day;
                break;
            case 6:
                $q = ($hour / 24 / 365) < 1 ? 1 : ($hour / 24 / 365);
                $total = $money * $rate * $q / $period * $day;
                break;
            default:
                $text = '未设置类型';
                break;
        }
        return round($total, 2);
    }
    
    //绘制表格
    public function fl_table($money, $hour, $rate, $type, $time, $user_member, $period, $language, $investlist){
        $rate = $rate / 100;
        $text = '';
        $day = $hour / 24;
        // $tb_money = ($money * $rate) + ($money * ($user_member['rate'] / 100));
        // $tb_money = round(($money / $period * $rate) + ($money / $period * ($user_member['rate'] / 100)), 2);
        $tb_money = round(($money / $period * $rate * $day) + ($money / $period * $day * ($user_member['rate'] / 100)), 2);
        switch ($type) {
            case 1:
                for($i = 1;$i <= $hour;$i++){
                    $new_time = date('Y-m-d H:i:s', strtotime($time) + (60 * 60 * $i));
                    if($i != $hour){
                        $text .= "<tr><td style='text-align:center;'>" . $i . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['money1'] . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['user_rate'] . "</td><td style='text-align:center;'>0</td><td style='text-align:center;'>" . $new_time . "</td></tr>";
                    }else{
                        $text .= "<tr><td style='text-align:center;'>" . $i . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['money1'] . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['user_rate'] . "</td><td style='text-align:center;'>" . $money . "</td><td style='text-align:center;'>" . $new_time . "</td></tr>";
                    }
                }
                break;
            case 2:
                $to = $hour / 24;
                if($to < 1) $to = 1;
                for($i = 1;$i <= $to;$i++){
                    $new_time = date('Y-m-d H:i:s', strtotime($time) + (24 * 60 * 60 * $i));
                    if($i != $to){
                        $text .= "<tr><td style='text-align:center;'>" . $i . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['money1'] . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['user_rate'] . "</td><td style='text-align:center;'>0</td><td style='text-align:center;'>" . $new_time . "</td></tr>";
                    }else{
                        $text .= "<tr><td style='text-align:center;'>" . $i . "</td><td style='text-align:center;'>" . $investlist[$i-1]['money1'] . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['user_rate'] . "</td><td style='text-align:center;'>" . $money . "</td><td style='text-align:center;'>" . $new_time . "</td></tr>";
                    }
                }
                break;
            case 3:
                // var_dump(count($investlist));
                $to = $hour / 24 / 7;
                // var_dump($to);exit;
                if($to < 1) $to = 1;
                for($i = 1;$i <= $to;$i++){
                    $new_time = date('Y-m-d H:i:s', strtotime($time) + (7 * 24 * 60 * 60 * $i));
                    if($i != $to){
                        $text .= "<tr><td style='text-align:center;'>" . $i . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['money1'] . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['user_rate'] . "</td><td style='text-align:center;'>0</td><td style='text-align:center;'>" . $new_time . "</td></tr>";
                    }else{
                        $text .= "<tr><td style='text-align:center;'>" . $i . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['money1'] . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['user_rate'] . "</td><td style='text-align:center;'>" . $money . "</td><td style='text-align:center;'>" . $new_time . "</td></tr>";
                    }
                }
                break;
            case 4:
                $to = $hour / 24 / 30;
                if($to < 1) $to = 1;
                for($i = 1;$i <= $to;$i++){
                    $new_time = date('Y-m-d H:i:s', strtotime($time) + (30 * 24 * 60 * 60 * $i));
                    if($i != $to){
                        $text .= "<tr><td style='text-align:center;'>" . $i . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['money1'] . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['user_rate'] . "</td><td style='text-align:center;'>0</td><td style='text-align:center;'>" . $new_time . "</td></tr>";
                    }else{
                        $text .= "<tr><td style='text-align:center;'>" . $i . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['money1']. "</td><td style='text-align:center;'>" .  $investlist[$i-1]['user_rate'] . "</td><td style='text-align:center;'>" . $money . "</td><td style='text-align:center;'>" . $new_time . "</td></tr>";
                    }
                }
                break;
            case 5:
                $new_time = date('Y-m-d H:i:s', strtotime($time) + (60 * 60 * $hour));
                $text = "<tr><td style='text-align:center;'>1</td><td style='text-align:center;'>" .  $investlist[0]['money1'] . "</td><td style='text-align:center;'>" .  $investlist[0]['user_rate'] . "</td><td style='text-align:center;'>" . $money . "</td><td style='text-align:center;'>" . $new_time . "</td></tr>";
                break;
            case 6:
                $to = $hour / 24 / 365;
                if($to < 1) $to = 1;
                for($i = 1;$i <= $to;$i++){
                    $new_time = date('Y-m-d H:i:s', strtotime($time) + (365 * 24 * 60 * 60 * $i));
                    if($i != $to){
                        $text .= "<tr><td style='text-align:center;'>" . $i . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['money1'] . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['user_rate'] . "</td><td style='text-align:center;'>0</td><td style='text-align:center;'>" . $new_time . "</td></tr>";
                    }else{
                        $text .= "<tr><td style='text-align:center;'>" . $i . "</td><td style='text-align:center;'>" .  $investlist[$i-1]['money1']. "</td><td style='text-align:center;'>" .  $investlist[$i-1]['user_rate'] . "</td><td style='text-align:center;'>" . $money . "</td><td style='text-align:center;'>" . $new_time . "</td></tr>";
                    }
                }
                break;
            default:
                $text = '未设置类型';
                break;
        }
        $qs_arr = [
            'zh_cn' => '期数',
            'zh_hk' => 'Giai đoạn',
            'en_us' => 'Periods'
        ];
        $lx_arr = [
            'zh_cn' => '应收利息/USDT',
            'zh_hk' => 'Lãi suất thu được/USDT',
            'en_us' => 'Interest'
        ];
        $jx_arr = [
            'zh_cn' => '加息率/%',
            'zh_hk' => 'Tỷ lệ tăng lãi suất/%',
            'en_us' => 'raise date'
        ];
        $bj_arr = [
            'zh_cn' => '应收本金/USDT',
            'zh_hk' => 'Thu nợ chính/USDT',
            'en_us' => 'Principal'
        ];
        $time_arr = [
            'zh_cn' => '到期时间',
            'zh_hk' => 'Thời gian hết hạn',
            'en_us' => 'Expire time'
        ];
        $text = '<table border="1" cellpadding="0" cellspacing="0"><tbody><tr><td style="text-align:center;">'.$qs_arr[$language].'</td><td style="text-align:center;"><span style="color:#000000;"><span style="font-family: Arial,PingFang SC,Hiragino Sans GB,STHeiti,Microsoft YaHei,WenQuanYi Micro Hei,sans-serif,noto-fanyi;">'.$lx_arr[$language].'</span></span></td><td style="text-align:center;"><span style="color:#000000;"><span style="font-family: Arial,PingFang SC,Hiragino Sans GB,STHeiti,Microsoft YaHei,WenQuanYi Micro Hei,sans-serif,noto-fanyi;">'.$jx_arr[$language].'</span></span></td><td style="text-align:center;"><span style="color:#000000;"><span style="font-family: Arial,PingFang SC,Hiragino Sans GB,STHeiti,Microsoft YaHei,WenQuanYi Micro Hei,sans-serif,noto-fanyi;">'.$bj_arr[$language].'</span></span></td><td style="text-align:center;"><span style="color:#000000;"><span style="font-family: Arial,PingFang SC,Hiragino Sans GB,STHeiti,Microsoft YaHei,WenQuanYi Micro Hei,sans-serif,noto-fanyi;">'.$time_arr[$language].'</span></span></td></tr></tbody>' . $text . '</table>';
        return $text;
    }
}