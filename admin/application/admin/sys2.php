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

use library\File;
use library\service\AdminService;
use library\service\CaptchaService;
use library\service\SystemService;
use think\Db;
use think\facade\Middleware;
use think\facade\Route;
use think\Request;


/**
 * @description：获取网站配置
 * @date: 2020/5/14 0014
 * @param $value
 * @return mixed
 */
function getInfo($value)
{
    return Db::name('LcInfo')->where('id', 1)->value($value);
}

if (!function_exists('auth')) {
    /**
     * 节点访问权限检查
     * @param string $node 需要检查的节点
     * @return boolean
     * @throws ReflectionException
     */
    function auth($node)
    {
        return AdminService::instance()->check($node);
    }
}

if (!function_exists('sysdata')) {
    /**
     * JSON 数据读取与存储
     * @param string $name 数据名称
     * @param mixed $value 数据内容
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    function sysdata($name, $value = null)
    {
        if (is_null($value)) {
            return SystemService::instance()->getData($name);
        } else {
            return SystemService::instance()->setData($name, $value);
        }
    }
}

if (!function_exists('sysoplog')) {
    /**
     * 写入系统日志
     * @param string $action 日志行为
     * @param string $content 日志内容
     * @return boolean
     */
    function sysoplog($action, $content)
    {
        return SystemService::instance()->setOplog($action, $content);
    }
}

if (!function_exists('sysCheckLog')) {
    /**
     * 写入系统日志
     * @param string $action 日志行为
     * @param string $content 日志内容
     * @return boolean
     */
    function sysCheckLog($action, $content)
    {
        return SystemService::instance()->sysCheckLog($action, $content);
    }
}


if (!function_exists('sysqueue')) {
    /**
     * 创建异步处理任务
     * @param string $title 任务名称
     * @param string $loade 执行内容
     * @param integer $later 延时执行时间
     * @param array $data 任务附加数据
     * @param integer $double 任务多开
     * @return boolean
     * @throws \think\Exception
     */
    function sysqueue($title, $loade, $later = 0, $data = [], $double = 1)
    {
        $map = [['title', 'eq', $title], ['status', 'in', [1, 2]]];
        if (empty($double) && Db::name('SystemQueue')->where($map)->count() > 0) {
            throw new \think\Exception('该任务已经创建，请耐心等待处理完成！');
        }
        $result = Db::name('SystemQueue')->insert([
            'title' => $title, 'preload' => $loade,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'time' => $later > 0 ? time() + $later : time(),
            'double' => intval($double), 'create_at' => date('Y-m-d H:i:s'),
        ]);
        return $result !== false;
    }
}

if (!function_exists('local_image')) {
    /**
     * 下载远程文件到本地
     * @param string $url 远程图片地址
     * @param boolean $force 是否强制重新下载
     * @param integer $expire 强制本地存储时间
     * @return string
     */
    function local_image($url, $force = false, $expire = 0)
    {
        $result = File::down($url, $force, $expire);
        if (isset($result['url'])) {
            return $result['url'];
        } else {
            return $url;
        }
    }
}

if (!function_exists('base64_image')) {
    /**
     * base64 图片上传接口
     * @param string $content 图片base64内容
     * @param string $dirname 图片存储目录
     * @return string
     */
    function base64_image($content, $dirname = 'base64/')
    {
        try {
            if (preg_match('|^data:image/(.*?);base64,|i', $content)) {
                list($ext, $base) = explode('|||', preg_replace('|^data:image/(.*?);base64,|i', '$1|||', $content));
                $info = File::save($dirname . md5($base) . '.' . (empty($ext) ? 'tmp' : $ext), base64_decode($base));
                return $info['url'];
            } else {
                return $content;
            }
        } catch (\Exception $e) {
            return $content;
        }
    }
}

/**
 * @description：充值奖励
 * @date: 2020/5/14 0014
 * @param $tid
 * @param $money
 * @throws \think\Exception
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function setRechargeRebate($tid, $money)
{
    $reward = Db::name('LcReward')->where(['id' => 1])->value("recharge");
    $rebate = round($reward * $money / 100, 2);
    if (0 < $rebate) {
        $LcTips173 = Db::name('LcTips')->where(['id' => '173']);
        $LcTips174 = Db::name('LcTips')->where(['id' => '174']);
        addFinance($tid, $rebate, 1,
            $LcTips173->value("name") . $money . $LcTips174->value("name") . $rebate,
            $LcTips173->value("zh_cn") . $money . $LcTips174->value("zh_cn") . $rebate,
            $LcTips173->value("en_us") . $money . $LcTips174->value("en_us") . $rebate,
            $LcTips173->value("th_th") . $money . $LcTips174->value("th_th") . $rebate,
            $LcTips173->value("vi_vn") . $money . $LcTips174->value("vi_vn") . $rebate,
            $LcTips173->value("ja_jp") . $money . $LcTips174->value("ja_jp") . $rebate,
            $LcTips173->value("ko_kr") . $money . $LcTips174->value("ko_kr") . $rebate,
            $LcTips173->value("ms_my") . $money . $LcTips174->value("ms_my") . $rebate
        );
        setNumber('LcUser', 'money', $rebate, 1, "id = $tid");
        setNumber('LcUser', 'income', $rebate, 1, "id = $tid");
    }
}

/**
 * @description：返佣
 * @date: 2020/5/14 0014
 * @param $tid
 * @param $money
 * @throws \think\Exception
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function setRechargeRebate1($tid, $money, $reward, $bz = '')
{
    //会员等级
    //   var_dump($tid);
    //   var_dump($money);
    //   var_dump($reward);die;
    if ($bz == '个人充值奖励') {
        $bz = $bz;
    } else if ($bz == '团队奖励') {
        $bz = $bz;
    } else {
        $bz = "下级" . $bz . "会员返佣";
    }
    $rebate = round($reward * $money / 100, 2);
    if (0 < $rebate) {
        $LcTips173 = Db::name('LcTips')->where(['id' => '173']);
        $LcTips174 = Db::name('LcTips')->where(['id' => '174']);
        addFinance($tid, $rebate, 1,
            $bz,
            $LcTips173->value("zh_cn") . $money . $LcTips174->value("zh_cn") . $rebate,
            $LcTips173->value("en_us") . $money . $LcTips174->value("en_us") . $rebate,
            $LcTips173->value("th_th") . $money . $LcTips174->value("th_th") . $rebate,
            $LcTips173->value("vi_vn") . $money . $LcTips174->value("vi_vn") . $rebate,
            $LcTips173->value("ja_jp") . $money . $LcTips174->value("ja_jp") . $rebate,
            $LcTips173->value("ko_kr") . $money . $LcTips174->value("ko_kr") . $rebate,
            $LcTips173->value("ms_my") . $money . $LcTips174->value("ms_my") . $rebate
        );
        setNumber('LcUser', 'money', $rebate, 1, "id = $tid");
        setNumber('LcUser', 'income', $rebate, 1, "id = $tid");
    }
}

function setRechargeRebate2($tid, $money, $reward)
{
    //会员等级

    $rebate = round($reward * $money / 100, 2);
    if (0 < $rebate) {
        $LcTips173 = Db::name('LcTips')->where(['id' => '173']);
        $LcTips174 = Db::name('LcTips')->where(['id' => '174']);
        addFinance($tid, $rebate, 1,
            "下级会员返佣",
            $LcTips173->value("zh_cn") . $money . $LcTips174->value("zh_cn") . $rebate,
            $LcTips173->value("en_us") . $money . $LcTips174->value("en_us") . $rebate,
            $LcTips173->value("th_th") . $money . $LcTips174->value("th_th") . $rebate,
            $LcTips173->value("vi_vn") . $money . $LcTips174->value("vi_vn") . $rebate,
            $LcTips173->value("ja_jp") . $money . $LcTips174->value("ja_jp") . $rebate,
            $LcTips173->value("ko_kr") . $money . $LcTips174->value("ko_kr") . $rebate,
            $LcTips173->value("ms_my") . $money . $LcTips174->value("ms_my") . $rebate
        );
        setNumber('LcUser', 'money', $rebate, 1, "id = $tid");
        setNumber('LcUser', 'income', $rebate, 1, "id = $tid");
    }
}

// 访问权限检查中间键
Middleware::add(function (Request $request, \Closure $next) {
    if (AdminService::instance()->check()) {
        return $next($request);
    } elseif (AdminService::instance()->isLogin()) {
        return json(['code' => 0, 'msg' => '抱歉，没有访问该操作的权限！']);
    } else {
        return json(['code' => 0, 'msg' => '抱歉，需要登录获取访问权限！', 'url' => url('@admin/login')]);
    }
});

// ThinkAdmin 图形验证码
Route::get('/think/admin/captcha', function () {
    $image = CaptchaService::instance();
    return json(['code' => '1', 'info' => '生成验证码', 'data' => [
        'uniqid' => $image->getUniqid(), 'image' => $image->getData()
    ]]);
});

/**
 * Describe:添加流水
 * DateTime: 2020/9/5 19:52
 * @param $uid
 * @param $money
 * @param $type
 * @param $reason
 * @param $zh_cn
 * @param $en_us
 * @param $th_th
 * @param $vi_vn
 * @param $ja_jp
 * @param $ko_kr
 * @param $ms_my
 * @param string $remark
 * @return bool
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function addFinance($uid, $money, $type, $zh_cn, $zh_hk, $en_us, $th_th, $vi_vn, $ja_jp, $ko_kr, $ms_my, $remark = "", $reason = "", $reason_type = 0)
{
    $user = Db::name('LcUser')->find($uid);
    if (!$user) return false;
    if ($user['money'] < 0) return false;
    $data = array(
        'uid' => $uid,
        'money' => $money,
        'type' => $type,
        'reason' => $reason,
        "zh_cn" => $zh_cn,
        "zh_hk" => $zh_hk,
        "en_us" => $en_us,
        "th_th" => $th_th,
        "vi_vn" => $vi_vn,
        "ja_jp" => $ja_jp,
        "ko_kr" => $ko_kr,
        "ms_my" => $ms_my,
        'remark' => $remark,
        'reason_type' => $reason_type,
        'before' => $user['money'],
        'time' => date('Y-m-d H:i:s')
    );
    Db::startTrans();
    $re = Db::name('LcFinance')->insert($data);
    // var_dump($re);die;
    if ($re) {
        Db::commit();
        return true;
    } else {
        Db::rollback();
        return false;
    }
}

/**
 * @description：设置
 * @date: 2020/5/13 0013
 * @param $database
 * @param $field
 * @param $value
 * @param int $type
 * @param string $where
 * @return int|true
 * @throws \think\Exception
 */
function setNumber($database, $field, $value, $type = 1, $where = '')
{
    if ($type != 1) {
        $re = Db::name($database)->where($where)->setDec($field, $value);
    } else {
        $re = Db::name($database)->where($where)->setInc($field, $value);
    }
    return $re;
}

function setUserMember($uid, $value)
{

    $member = Db::name('LcUserMember')->where("value <= '{$value}'")->order('value desc')->find();

    if (empty($member)) {
        $mid = 0;
    } else {
        $mid = $member['id'];
    }
    Db::name('LcUser')->where("id = {$uid}")->update(array('member' => $mid));
    return $mid;
}

/**
 * 校验是否可升级团队
 * @param $uid
 * @return void
 * @throws \think\Exception
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 * @throws \think\exception\PDOException
 */
function gradeUpgrade($uid)
{
    header("Content-type:text/html;charset=utf-8");
    // 查询用户信息
    $member = Db::name("LcUser")->find($uid);

    //团队升级 需要满足充值金额 直推人数 直推团长数
    //团队升级 本人
    //直推人数
    $tg_num = Db::name("LcUser")->where("recom_id", $uid)->count();
    //邀请直推团长数
    $where_find = [
        "grade_id" => ["gt", "1"]
    ];
    $tz_num = Db::name("LcUser")->where("recom_id", $uid)->where("grade_id > 1")->count();

    //统计下级直推累计金额
    // $xjlj_money = Db::name("LcUser")->where("recom_id", $uid)->sum("czmoney");
    //团队充值 本人累计充值 + 下级直推累计充值
    // $lj_money = $member['czmoney'] + $xjlj_money;
    $memberList = Db::name('LcUser')->field('id, phone, top,czmoney,name,time, auth')->select();

    $itemList = get_downline_list2($memberList, $uid);
    //   var_dump($itemList);die;
    $lj_money = 0;

    $is_sf = Db::name('LcUser')->where(['id' => $uid])->value('is_sf');
    //   var_dump($this->userInfo['czmoney']);
    //   var_dump($this->userInfo['is_sf']);die;
    if ($is_sf == 0) {
        //   $all_czmoney=$this->userInfo['czmoney'];
        $lj_money = Db::name('LcUser')->where(['id' => $uid])->value('czmoney');
    }
    foreach ($itemList as $k => $v) {
        $lj_money += $v['czmoney'];

    }
    // 取团队ID
    $team_id = Db::name("LcMemberGrade")->where('id', '>', 1)->order('id desc')->find();
    // 比较等级
    $msg = '用户【' . $member['phone'] . '】直推会员数:' . $tg_num . '<br>';
    $msg .= '用户【' . $member['phone'] . '】直推团长数:' . $tz_num . '<br>';
    $msg .= '用户【' . $member['phone'] . '】下级累计充值:' . $lj_money . '<br>';
    printLog($msg);


    $tid = bjgrade($tg_num, $lj_money, $tz_num);
    $msg1 = '用户【' . $member['phone'] . '】等级比较结果id:' . $tid;
    printLog($msg1);
    // 获取比较后的段对
    $team_data = Db::name("LcMemberGrade")->where("id", $tid)->field('all_activity,title,id,recom_tz,recom_number')->find();
    //团队满足充值升级
    if ($team_data) {
        //团队满足直推会员人数	升级
        if ($tg_num >= $team_data['recom_number']) {
            //团队满足直推团长数	升级
            if ($tz_num >= $team_data['recom_tz']) {
                $tdate['grade_id'] = $tid;

                $tdate['grade_name'] = $team_data['title'];

                Db::name("LcUser")->where("id", $member["id"])->update($tdate);
            }
        }
    } else {
        //团队满足充值升级
        if ($team_id['all_activity'] <= $lj_money) {
            //团队满足直推会员人数	升级
            if ($tg_num >= $team_id['recom_number']) {
                //团队满足直推团长数	升级
                if ($tz_num >= $team_id['recom_tz']) {
                    $tdate['grade_id'] = $team_id['id'];


                    $tdate['grade_name'] = $team_id['title'];

                    Db::name("LcUser")->where("id", $member["id"])->update($tdate);
                }
            }
        }
    }
    //团队升级 上级
    if ($member["recom_id"]) {
        $sj_members = Db::name("LcUser")->where("id", $member["recom_id"])->field('czmoney,grade_id,id')->find();
        //邀请直推会员数
        $sjtg_num = Db::name("LcUser")->where("recom_id", $sj_members["id"])->count();
        //邀请直推团长数
        $sjwhere_find = [
            "grade_id" => ["gt", "1"]
        ];
        $sjtz_num = Db::name("LcUser")->where("recom_id", $sj_members["id"])->where("grade_id > 1")->count();
        $xj_lj = Db::name("LcUser")->where("recom_id", $sj_members["id"])->sum("czmoney");

        //团队充值 本人累计充值+下级直推累计充值

        $sjteam_lj = $sj_members['czmoney'] + $xj_lj;
        $sj_tid = bjgrade($sjtg_num, $sjteam_lj, $sjtz_num);
        $sjteam_data = Db::name("LcMemberGrade")->where("id", $sj_tid)->field('all_activity,title,id,recom_tz,recom_number')->find();

        if ($sjteam_data) {
            //团队满足直推会员人数	升级
            if ($sjtg_num >= $sjteam_data['recom_number']) {
                if ($sjtz_num >= $sjteam_data['recom_tz']) {
                    $sj_tdate['grade_id'] = $sj_tid;
                    $sj_tdate['grade_name'] = $sjteam_data['title'];
                    Db::name("LcUser")->where("id", $sj_members["id"])->update($sj_tdate);
                }
            }
        } else {
            //团队满足充值升级
            if ($team_id['all_activity'] <= $sjteam_lj) {
                //团队满足直推会员人数	升级
                if ($sjtg_num >= $team_id['recom_number']) {
                    //团队满足直推团长数	升级
                    if ($sjtz_num >= $team_id['recom_tz']) {
                        $sj_tdate['grade_id'] = $team_id['id'];


                        $sj_tdate['grade_name'] = $team_id['title'];

                        Db::name("LcUser")->where("id", $sj_members["id"])->update($sj_tdate);
                    }
                }
            }
        }
    }
}

function get_downline_list2($user_list, $telephone, $level = 0)
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
            $arr = array_merge($arr, get_downline_list2($user_list, $v['id'], $level + 1));
        }
        // }

    }
    return $arr;
}

/**
 * 比较等级
 * @param $recom_number 直推人数
 * @param $all_activity 累计充值
 * @param $recom_tz 团长充值
 * @return mixed
 */
function bjgrade($recom_number, $all_activity, $recom_tz)
{
    $aid = Db::name("LcMemberGrade")->where("all_activity", '<=', $all_activity)->order("id desc")->value('id');
    $bid = Db::name("LcMemberGrade")->where("recom_number", '<=', $recom_number)->order("id desc")->value('id');
    $cid = Db::name("LcMemberGrade")->where("recom_tz", '<=', $recom_tz)->order("id desc")->value('id');
    $mid = $aid;
    if ($mid > $bid) {
        $mid = $bid;
    }
    if ($mid > $cid) {
        $mid = $cid;
    }
    return $mid;
}

/**
 * 打印日志
 * $msg 日志内容
 */
function printLog($msg)
{
    if (!is_dir('log')) {
        mkdir('log', 0777, true);
    }
    $path = "log/teamgrade.txt";
    file_put_contents($path, "【" . date('Y-m-d H:i:s') . "】" . $msg . "\r\n\r\n", FILE_APPEND);
}
