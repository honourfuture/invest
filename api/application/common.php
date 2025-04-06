<?php 
use think\Db;
use think\facade\Log;

// function setRechargeRebate1($uid, $money, $member_rate, $text){
//     //获取用户信息
//     $userInfo = Db::name('lc_user') -> where('id', $uid) -> find();
//     $tips = Db::name('lc_tips') -> where('name', $text) -> find();
    
//   $totalMoney = $money * ($member_rate / 100);
//     if(!empty($userInfo['id']) && !empty($uid) && $totalMoney > 0){
//         $data = [
//             'uid' => $uid,
//             'money' => $totalMoney,
//             'zh_cn' => $tips['zh_cn'],
//             'zh_hk' => $tips['zh_hk'],
//             'en_us' => $tips['en_us'],
//             'th_th' => $tips['th_th'],
//             'vi_vn' => $tips['vi_vn'],
//             'ja_jp' => $tips['ja_jp'],
//             'ko_kr' => $tips['ko_kr'],
//             'ms_my' => $tips['ms_my'],
//             'before' => $userInfo['money'],
//             'reason_type' => 1,
//             'time' => date('Y-m-d H:i:s', time())
//         ];
//          Db::name('lc_finance') -> insert($data);
//     }
   
//     return true;
// }

function check_sms_status($phone, $language)
{
    $user = Db::name('lc_user')->where('phone', $phone)->find();
    if ($user['sms_error_num'] >= 5 && $user['sms_error_time'] > time()-300) {
        return ['status' => 0, 'msg' => get_tip(231, $language)];
    }
    return ['status' => 1];
}

function check_code($phone, $code, $language)
{
    $countRecord = Db::name("LcSmsList")->where(['phone' => $phone, 'status' => 0, 'ip' => $code])->order('id desc')->find();
    if (!$countRecord) {    //验证码错误
        update_user_sms($phone);
        return ['status' => 0, 'msg' => get_tip(45, $language)];
    } elseif (strtotime($countRecord['time']) < (time() - 300)) {
        Db::name('LcSmsList')->where('id', $countRecord['id'])->update(['status' => 2]);
        return ['status' => 0, 'msg' => get_tip(230, $language)];
    }
    //修改验证码状态
    Db::name('LcSmsList')->where('id', $countRecord['id'])->update(['status' => 1]);
    //修改用户验证码
    // Db::name('LcUser')->where('phone', $phone)->update(['sms_error_num' => 0, 'sms_error_time' => time()]);
    return ['status' => 1];
}

function update_user_sms($phone)
{
    
    $sms_error_num = Db::name('lc_user')->where('phone', $phone)->value('sms_error_num');
    Db::name('lc_user')->where('phone', $phone)->update(['sms_error_num' => $sms_error_num+1, 'sms_error_time' => time()+300]);
}

function get_tip($id, $language)
{
    return Db::name('lc_tips')->find($id)[$language];
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
            'Giảm giá mỗi giờ, trả gốc khi đáo hạn',
            'Hoàn tiền hàng ngày, trả gốc khi đáo hạn',
            'Trả lãi hàng tuần, đáo hạn trả gốc',
            'Trả lãi hàng tháng, đáo hạn trả gốc',
            'Đáo hạn trả cả gốc và lãi',
            'Trả lãi từng năm, đáo hạn trả gốc'
        ];
        $arr_en_us = [
            'Soatlik chegirma, to`langan pulni qaytarish',
            'Kundalik chegirma, kerak bo`lganda pulni qaytarish',
            'Haftalik chegirmalar, kerak bo`lganda pulni qaytarish',
            'Oylik chegirmalar, kerak bo`lganda kapitalni qaytarish',
            'asosiy mablag‘ va foydasini qaytarish',
            'Yillik chegirma, kerak bo`lganda kapitalni qaytarish'
        ];
        if ($language == 'zh_cn') {
            return $arr_zh_cn[$value-1];
        } elseif ($language == 'zh_hk') {
            return $arr_zh_hk[$value-1];
        } elseif ($language == 'en_us') {
            return $arr_en_us[$value-1];
        }
    }

function setRechargeRebate1($tid, $money,$reward,$bz='')
{
    //会员等级
//   var_dump($tid);
//   var_dump($money);
//   var_dump($reward);die;
$type = 1;
$lv_num = "";
if($bz=='个人充值奖励'){
    $bz=$bz;
    $type = 2;
}else if($bz=='团队奖励'){
    $bz=$bz;
    $type = 3;
}else{
    $lv_num = $bz;
    $bz="下级".$bz."级会员返佣";
    $type = 4;
}
    $rebate = round($reward * $money / 100, 2);
    if (0 < $rebate) {
        $LcTips173 = Db::name('LcTips')->where(['id' => '173']);
        $LcTips174 = Db::name('LcTips')->where(['id' => '174']);
        if($type == 4){
            addFinance($tid, $rebate, 1,
            $bz,
            "Hoa hồng hội viên cấp {$lv_num} Lv. kế",
            "subcommission",
            $LcTips173->value("th_th").$money.$LcTips174->value("th_th").$rebate,
            $LcTips173->value("vi_vn").$money.$LcTips174->value("vi_vn").$rebate,
            $LcTips173->value("ja_jp").$money.$LcTips174->value("ja_jp").$rebate,
            $LcTips173->value("ko_kr").$money.$LcTips174->value("ko_kr").$rebate,
            $LcTips173->value("ms_my").$money.$LcTips174->value("ms_my").$rebate
            );
        }else{
            addFinance($tid, $rebate, 1,
            $bz,
            $LcTips173->value("zh_hk").$money.$LcTips174->value("zh_hk").$rebate,
            $LcTips173->value("en_us").$money.$LcTips174->value("en_us").$rebate,
            $LcTips173->value("th_th").$money.$LcTips174->value("th_th").$rebate,
            $LcTips173->value("vi_vn").$money.$LcTips174->value("vi_vn").$rebate,
            $LcTips173->value("ja_jp").$money.$LcTips174->value("ja_jp").$rebate,
            $LcTips173->value("ko_kr").$money.$LcTips174->value("ko_kr").$rebate,
            $LcTips173->value("ms_my").$money.$LcTips174->value("ms_my").$rebate
            );
        }
        
        setNumber('LcUser', 'money', $rebate, 1, "id = $tid");
        setNumber('LcUser', 'income', $rebate, 1, "id = $tid");
    }
}
function im_send_publish($id,$content){
    $push_api_url = "http://rest-singapore.goeasy.io/v2/pubsub/publish";
    $post_data = array(
       'appkey' => 'BC-951da9b528ca4cf88874395628b9ff17',
       'channel' => "my_channel_".$id,
       'content' => $content, 
       'notification_title' => 'PLUS500AI', 
       'notification_body' => $content
    );
    $ch = curl_init ();
    curl_setopt ( $ch, CURLOPT_URL, $push_api_url );
    curl_setopt ( $ch, CURLOPT_POST, 1 );
    curl_setopt ( $ch, CURLOPT_HEADER, 0 );
    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt ( $ch, CURLOPT_POSTFIELDS, $post_data );
    $return = curl_exec ( $ch );
    curl_close ( $ch );
    Log::error($return);
    //var_export($return);
}


function vnd_gsh($number) {
    return $number;
    return number_format($number, 0, ',', ',');
}



if (!function_exists('httpRequest')) {
    /**
     * 发起一个请求
     * @param $url
     * @param $params
     * @param string $method
     * @param array $header
     * @param false $multi
     * @return bool|string
     */
    function httpRequest($url, $params, $method = 'GET', $header = array(), $multi = false){
        $opts = array(
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => $header,
        );
        // Log::error(http_build_query($params));
        switch(strtoupper($method)){
            case 'GET':
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                break;
            case 'POST':
                $params = $multi ? $params : http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            default:
                return false;
        }
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $data  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error){
            return false;
        }
        return  $data;
    }
}