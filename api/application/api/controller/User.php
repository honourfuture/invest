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

namespace app\api\controller;

use Endroid\QrCode\QrCode;
use library\Controller;
use think\Db;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Request;

/**
 * 用户中心infomyTeam
 * Class Index
 * @package app\index\controller
 */
class User extends Controller
{
    public function rechargeList()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 41167;
        $page = $this->request->param('page', 1);
        $size = $this->request->param('size', 10);
        $language = $this->request->param('language', 'zh_cn');
        $lists = Db::name('lc_recharge')->where('uid', $uid)
            ->field('id,money,type,status,reason_zh_cn,reason_zh_hk,reason_en_us')
            ->order('id desc')
            ->page($page, $size)
            ->select();
        foreach ($lists as &$item) {
            $item['reason'] = $item['reason_' . $language];
            $item['money'] = vnd_gsh(bcdiv($item['money'], 1, 2)) . "USDT";
        }
        $this->success('获取成功', $lists);
    }

    public function editname()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $username = $this->request->param('username', '');
        if (empty($username)) {
            $this->error('用户名不能为空');
        }
        Db::name('lc_user')->where('id', $uid)->update(['username' => $username]);
        $this->success('修改成功');
    }

    public function service()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user = Db::name('lc_user')->find($uid);
        $agent = Db::name('system_user')->find($user['agent']);
        $this->success('获取成功', ['url' => $agent['service_link']]);
    }

    public function coupon()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 38724;
        $list = Db::name('lc_coupon_list l')
            ->join('lc_coupon c', 'l.coupon_id=c.id')
            ->where('uid', $uid)
            ->where('l.status', 0)
            ->field('l.id,l.expire_time,l.money,l.need_money,c.name')
            ->order('expire_time asc')
            ->select();
        $this->success('获取成功', $list);
    }

    public function asset_balance()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 38616;
        $user = Db::name('lc_user')->field('asset,money')->find($uid);
        $this->success('获取成功', $user);
    }

    public function income()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $id = $this->request->param('id');
        $now = time();
        $invests = Db::name("LcInvestList")
            ->where('iid', $id)
            ->where('uid', $uid)
            ->where("UNIX_TIMESTAMP(time1) <= $now")
            ->order('num asc')
            ->select();

        $today_start_day = date('Y-m-d 00:00:00');
        $today_end_day = date('Y-m-d 23:59:59');
        $invest_list = [];
        foreach ($invests as $invest) {
            if ($invest['status'] == 0) {
                $invest_list[] = $invest;
                break;
            }

            if ($invest['status'] == 1 && ($invest['time2'] >= $today_start_day) && $invest['time2'] <= $today_end_day) {
                break;
            }
        }

        if (!$invest_list) {
            $this->success("领取成功");
        }

        $redisKey = 'LockKey*';
        // $lock = new \app\api\util\RedisLock();
        // $lock->unlock($redisKey);
        $handler = Cache::store('redis')->handler();
        $data = $handler->keys($redisKey);
        foreach ($data as $key) {
            $handler->del($key);
        }
        // 缓存时间 <= 当前时间
        // $invest_list = Db::name("LcInvestList")->where("status = '0'")->where('iid', 2583)->select();//调试结算分红
        // echo json_encode($invest_list);die;
        if (empty($invest_list)) exit('暂无返息计划-' . date("Y-m-d H:i:s") . "|" . $now);
        foreach ($invest_list as $k => $v) {
            // 查询这个用户的投资记录，按期数倒叙
            $max = Db::name("LcInvestList")->field('id')->where(['uid' => $v['uid'], 'iid' => $v['iid']])->order('num desc')->find();
            $is_last = false;
            // 如果当前期数是最大期数
            if ($v['id'] == $max['id']) $is_last = true;
            $data = array('time2' => date('Y-m-d H:i:s'), 'pay2' => $v['pay1'], 'status' => 1);
            if (Db::name("LcInvestList")->where(['id' => $v['id'], 'status' => 0])->update($data)) {
                if ($v['pay1'] > 0) {
                    if ($is_last) {
                        if ($v['pay1'] <= 0) $v['pay1'] = 0;
                        Db::name('LcInvest')->where(['id' => $v['iid']])->update(['status' => 1, 'time2' => date("Y-m-d H:i:s")]);
                    }
                    $LcTips = Db::name('LcTips')->where(['id' => '182']);
                    //获取项目信息
                    $investInfo = Db::name('lc_invest')->where('id', $v['iid'])->find();
                    $itemInfo = Db::name('lc_item')->where('id', $investInfo['pid'])->find();

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
                            if ($item['id'] == $user['member']) {
                                if ($key + 1 == count($memberList)) {
                                    $user['rate'] = $memberList[$key]['rate'];
                                    break;
                                } else {
                                    $user['rate'] = $memberList[$key + 1]['rate'];
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
                        if ($itemInfo['show_home'] == 1) {
                            $next_member = Db::name("LcUserMember")->where('value > ' . $member['value'])->order('value asc')->find();
                            if ($next_member) $member = $next_member;
                        }
                        $rate = $rate + $member['rate'];
                        $user_rate = $member['rate'];
                    }

                    $nums = 1;
                    $addTime = "day";
                    $hour = $itemInfo['hour'];
                    $day = $itemInfo['day'];
                    $indexType = $itemInfo['cycle_type'];
                    // 判断项目投资的返利模式
                    if ($indexType == 1) {
                        // 按小时
                        $nums = $hour;
                        $addTime = "hour";
                    } else if ($indexType == 2) {
                        // 按日 小时 * 24
                        $nums = $hour / 24;
                    } else if ($indexType == 3) {
                        // 每周
                        $nums = ceil(intval($hour / 24 / 7));
                        $addTime = "week";
                    } else if ($indexType == 4) {
                        // 每月返利
                        $nums = ceil(intval($hour / 24 / 30));
                        $addTime = "month";
                    } else if ($indexType == 6) {
                        // 每年返利
                        $nums = ceil(intval($hour / 24 / 365));
                        $addTime = "year";
                    }
                    if ($nums < 1) $nums = 1;
                    $day = $hour / $nums;

                    $money1 = round($investInfo['money'] * $rate / 100, 2);
                    // var_dump($investInfo['money']);
                    // var_dump($indexType);
                    $day = $hour / 24;
                    if ($indexType == 1) {
                        $money1 = round($investInfo['money'] * $rate / 24 / 100, 2);
                    } elseif ($indexType == 2) {
                        $money1 = round($investInfo['money'] * $rate / 100, 2);
                    } elseif ($indexType == 3) {
                        $money1 = round($investInfo['money'] * $rate * 7 / 100, 2);
                    } elseif ($indexType == 4) {
                        $money1 = round($investInfo['money'] * $rate * 30 / 100, 2);
                    } elseif ($indexType == 6) {
                        $money1 = round($investInfo['money'] * $rate * 365 / 100, 2);
                    } elseif ($indexType == 5) {
                        $money1 = round($investInfo['money'] * $rate * $day / 100, 2);
                    }

                    Db::name('lc_invest_list')->where('id', $v['id'])->update(['money1' => $money1, 'user_rate' => $user_rate]);

                    $tempMoney = $money1;
                    // $tempMoney = $v['money1'];
                    $log = vnd_gsh(bcdiv($tempMoney, 1, 2));
                    add_finance($v['uid'], $tempMoney, 1,
                        [
                            'zh_cn' => "《" . $itemInfo['zh_cn'] . "》 " . $LcTips->value("name") . $log,
                            'zh_hk' => "《" . $itemInfo['zh_hk'] . "》 " . $LcTips->value("zh_hk") . $log,
                            'en_us' => "《" . $itemInfo['en_us'] . "》 " . $LcTips->value("en_us") . $log,
                            'vi_vn' => "《" . $itemInfo['vi_vn'] . "》 " . $LcTips->value("vi_vn") . $log,
                            'ja_jp' => "《" . $itemInfo['ja_jp'] . "》 " . $LcTips->value("ja_jp") . $log,
                            'ko_kr' => "《" . $itemInfo['ko_kr'] . "》 " . $LcTips->value("ko_kr") . $log,
                            'ms_my' => "《" . $itemInfo['ms_my'] . "》 " . $LcTips->value("ms_my") . $log,
                        ],
                        "", "", 11, 2, $v['id']
                    );
                    setNumber('LcUser', 'money', $tempMoney, 1, "id = {$v['uid']}");
                    setNumber('LcUser', 'income', $v['money1'], 1, "id = {$v['uid']}");

                    $uid = $v['uid'];

                    //推送
//                    im_send_publish($uid, 'Xin chào, thu nhập ' . $v['money1'] . 'U vào tài khoản!');  // Xin chúc mừng bạn mua《'.$itemInfo['zh_hk'].'》Thu nhập'.$v['money1'].'USDT，Vào tài khoản！

                    // 给上级进行返佣
                    // 先查询用户信息
                    $user = Db::name("LcUser")->where("id = {$uid}")->find();

                    $wait_invest = $v['money1'];
                    $wait_money = 0;

                    //返回本金
                    if ($v['money2'] > 0) {
                        Db::name('LcFinance')->insert([
                            'uid' => $v['uid'],
                            'money' => $v['money2'],
                            'type' => 1,
                            'zh_cn' => "《" . $itemInfo['zh_cn'] . '》，投资完成返还本金',
                            'zh_hk' => "《" . $itemInfo['zh_hk'] . '》，Khoản đầu tư hoàn thành',
                            'en_us' => "《" . $itemInfo['en_us'] . '》，Return of principal upon completion of investment',
                            'th_th' => "《" . $itemInfo['zh_cn'] . '》，投资完成返还本金',
                            'vi_vn' => "《" . $itemInfo['zh_cn'] . '》，投资完成返还本金',
                            'ja_jp' => "《" . $itemInfo['zh_cn'] . '》，投资完成返还本金',
                            'ko_kr' => "《" . $itemInfo['zh_cn'] . '》，投资完成返还本金',
                            'ms_my' => "《" . $itemInfo['zh_cn'] . '》，投资完成返还本金',
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

                    $top = $user['top'];
                    $top2 = $user['top2'];
                    $top3 = $user['top3'];

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
        $this->success("领取成功");
    }

    //余额变动记录
    public function balance_change()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $language = $this->request->param('language');
        $list = Db::name('lc_finance')->where('uid', $uid)
            ->whereNotIn('reason_type', [1, 17, 6, 18])
            ->field('id,money,zh_cn,zh_hk,en_us,reason_type,type,time')
            ->order('id desc')
            ->select();
        foreach ($list as &$item) {
            $item['zh_cn'] = $item[$language];
        }
        $this->success('获取成功', $list);
    }

    //资产变动记录
    public function asset_change()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $language = $this->request->param('language');
        $list = Db::name('lc_finance')->where('uid', $uid)
            ->whereIn('reason_type', [1, 17, 6, 18])
            ->where('zh_cn', 'notlike', '%首次投资%')
            ->field('id,money,zh_cn,zh_hk,en_us,reason_type,type,time')
            ->order('id desc')
            ->select();
        foreach ($list as &$item) {
            $item['zh_cn'] = $item[$language];
        }
        $this->success('获取成功', $list);
    }

    //团队奖励
    public function team_reward()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $language = $this->request->param('language');
        $list = Db::name('lc_finance')->where('zh_cn', 'like', '%下级%')->where('uid', $uid)
            ->field('*')
            ->order('id desc')->select();
        foreach ($list as &$item) {
            $item['zh_cn'] = $item[$language];
        }

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
        $progress = bcdiv(($user['value'] - $member['value']) * 100, $next_member['value'] - $member['value']);
        $list = Db::name('lc_user_member')->order('value asc')->select();
        // foreach($list as &$item){
        //     // $item['rate'] =  $item['rate']."tr";
        // }
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
        $language = $this->request->param('language');
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 38598;
        //总投资额
        $members = Db::name('LcUser')->find($uid);
        $memberList = Db::name('LcUser')->field('id,phone,top,czmoney')->select();
        $itemList = $this->get_downline_list($memberList, $members['id']);
        $ids = [$uid];
        $comIds = [];
        foreach ($itemList as $item) {
            $ids[] = $item['id'];
            $comIds[] = $item['id'];
        }
        $totalInvest = Db::name('lc_invest t')->join('lc_item m', 't.pid = m.id')
            ->where('m.index_type', '<>', 7)
            ->whereIn('t.uid', $comIds)->sum('t.money');

        $tznum = Db::name('LcUser')->where([['top', '=', $uid], ['grade_id', '>', 1]])->count();
        // $huiyuannum = Db::name('LcUser')->where([['top', '=',$uid]])->count();
        // $huiyuannum = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')->where('u.top', $uid)->group('uid')->count();
        // $huiyuannum = Db::name('lc_invest l')->join('lc_item i', 'l.pid=i.id')->join('lc_user u', 'l.uid = u.id')->where('index_type', '<>', 7)->where('u.top', $uid)->group('uid')->count();
        $huiyuannum = Db::name('lc_user u')->join('lc_invest l', 'l.uid = u.id')->join('lc_item i', 'l.pid=i.id')->where('index_type', '<>', 7)->where('top', $uid)->group('l.uid')->count();

        //下一级升级条件
        $grade_info = Db::name('LcMemberGrade')->where("id", $members['grade_id'])->field("id,poundage,title as title_zh_cn,title_zh_hk,title_en_us,recom_number,all_activity,recom_tz")->find();
        $next_grade = Db::name('LcMemberGrade')->where("all_activity", '>', $grade_info['all_activity'])->field("id,poundage,title as title_zh_cn,title_zh_hk,title_en_us,recom_number,all_activity,recom_tz")->order('all_activity asc')->find();
        $finish_arr = [
            'zh_cn' => '已达标',
            'zh_hk' => 'Hoàn thành',
            'en_us' => 'Qualified',
        ];
        //当前团队投资额
        $tzCur = $totalInvest;
        $tzNeed = $next_grade['all_activity'] - $tzCur;
        if ($tzNeed <= 0) $tzNeed = $finish_arr[$language];
        $tzProgress = intval($tzCur / $next_grade['all_activity'] * 100);
        //当前直推数量
        $ztCur = $huiyuannum;
        $ztNeed = $next_grade['recom_number'] - $ztCur;
        if ($ztNeed <= 0) $ztNeed = $finish_arr[$language];
        $ztProgress = intval($ztCur / $next_grade['recom_number'] * 100);
        //团队数量
        $tdCur = $tznum;
        $tdNeed = $next_grade['recom_tz'] - $tdCur;
        if ($tdNeed <= 0) $tdNeed = $finish_arr[$language];
        if ($next_grade['recom_tz'] == 0) {
            $tdNeed = $finish_arr[$language];
            $tdProgress = 100;
        } else {
            $tdProgress = intval($tdCur / $next_grade['recom_tz'] * 100);
            if ($tdProgress == 100) $tdNeed = $finish_arr[$language];
        }

        $data['next'] = [
            'touzi' => ['cur' => $tzCur, 'need' => $tzNeed, 'progress' => $tzProgress],
            'huiyuan' => ['cur' => $ztCur, 'need' => $ztNeed, 'progress' => $ztProgress],
            'tuanzhang' => ['cur' => $tdCur, 'need' => $tdNeed, 'progress' => $tdProgress],
        ];
        $data['cur_name'] = $grade_info['title_' . $language];
        $data['next_name'] = $next_grade['title_' . $language];
        $data['poundage'] = $grade_info['poundage'];
        $data['list'] = Db::name('LcMemberGrade')->field('*,title as title_zh_cn')->order('all_activity asc')->select();
        foreach ($data['list'] as &$item) {
            $item['title'] = $item['title_' . $language];
        }

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
        $asset = bcadd($money, bcdiv($money * $repeat_rate, 100, 2), 2);
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
        $language = $data['language'];
        if (!isset($data['money']) || !$data['money']) {
            $this->error(Db::name('lc_tips')->find(212)[$language]);
        }

        $redisKey = 'LockKeyUserRepeatInvest' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 60, 0)) {
            $this->error(Db::name('LcTips')->field("$language")->find('229'));
        }


        //验证输入金额
        if (!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $data['money'])) $this->error('请输入正确的金额');

        //1分钟内提交不超过三次
        $cash = Db::name('lc_finance')->where('uid', $uid)->where('reason_type', 16)->order('id desc')->limit(4)->field('time')->select();
        if (count($cash) > 3) {
            if (strtotime($cash[2]['time']) > (time() - 60)) {
                $this->error(get_tip(229, $language));
            }
        }


        if (!isset($data['password'])) {
            $this->error(Db::name('lc_tips')->find(212)[$language]);
        }

        //校验密码
        if (md5($data['password']) != $user['password2']) {
            $this->error(Db::name('lc_tips')->find(213)[$language]);
        }
        if ($user['money'] < $data['money']) {
            $this->error(Db::name('lc_tips')->find(65)[$language]);
        }

        $repeat_rate = Db::name('lc_info')->find(1)['repeat_rate'];
        $asset = bcadd($data['money'], bcdiv($data['money'] * $repeat_rate, 100, 2), 2);
        //扣除余额记录
        Db::name('lc_finance')->insert([
            'uid' => $uid,
            'money' => $data['money'],
            'type' => 2,
            'zh_cn' => '复投减去余额钱包',
            'zh_hk' => 'Tổng hợp giảm bớt ví',
            'en_us' => 'Re investment minus balance wallet',
            'before' => $user['money'],
            'time' => date('Y-m-d H:i:s', time()),
            'reason_type' => 16,
            'after_money' => bcsub($user['money'], $data['money'], 2),
            'after_asset' => $user['asset'],
            'before_asset' => $user['asset']
        ]);
        //资产变动记录
        Db::name('lc_finance')->insert([
            'uid' => $uid,
            'money' => $asset,
            'type' => 1,
            'zh_cn' => '复投增加资产钱包',
            'zh_hk' => 'Đầu tư phức hợp làm tăng ví tài sản',
            'en_us' => 'Reinvestment to increase asset wallet',
            'before' => bcsub($user['money'], $data['money'], 2),
            'time' => date('Y-m-d H:i:s', time()),
            'reason_type' => 18,
            'trade_type' => 1,
            'after_money' => bcsub($user['money'], $data['money'], 2),
            'after_asset' => bcadd($user['asset'], $asset, 2),
            'before_asset' => $user['asset']
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
        if ($user) {
            if ($user['mid'] <= 0) {
                while (true) {
                    $mid = rand(111111, 999999);
                    if (!Db::name('LcUser')->where('mid', $mid)->find()) {
                        Db::name('LcUser')->where('id', $uid)->update(['mid' => $mid]);
                        $user['mid'] = $mid;
                        break;
                    }
                }
            }

            $wait_money = Db::name('LcInvestList')->where("uid = $uid AND status = '0' AND money2 > 0")->sum('money2');
            $wait_lixi = Db::name('LcInvestList')->where("uid = $uid AND status = '0' AND money1 > 0")->sum('money1');

            $all_lixi = Db::name('LcInvestList')->where("uid = $uid AND status = '1' AND money1 > 0")->sum('money1');
            if (!empty($user["asset"])) {
                $all_money = $user["asset"];
            }

            $certificate = Db::name('lc_certificate')->where('uid', $user['id'])->where('status', 0)->find();
            if ($certificate) {
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
            $language = 'zh_cn';
            $list = Db::name('LcInvest')->field("$language,id,money,rate,hour,status,time,time2, pid, grouping_num, grouping_income")->where('uid', $uid)->order("id desc")->select();
            $wait_lixi = 0;
            foreach ($list as $k => $v) {
                // item.money * item.rate / 100 * item.hour / 24
                if (!$v['status']) {
                    $wait_lixi += $v['money'] * $v['rate'] / 100 * $v['hour'] / 24;
                }

            }
            // 余额＝下级返佣+团队奖励+首次投资奖励+投资收益+投资本金+充值奖励+投资赠送红包
            //   $money=
            // $total_recharge = Db::name('LcFinance')->where("uid = $uid AND type = '1' AND reason_type in(1,3)")->sum('money');
            $total_recharge = $user['czmoney'];
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
                "money" => bcdiv($user['money'], 1, 2),
                "all_money" => sprintf("%.2f", $all_money),
                "sum_money" => sprintf("%.2f", $sum_money),
                'username' => $user['username'],
                "name" => $user['name'],
                "idcard" => $user['idcard'],
                "uid" => $uid,
                "mid" => $user['mid'],
                "asset" => sprintf("%.2f", $all_money),
                "is_auth" => $user['auth'],
                "user_icon" => $user['avatar'] ? $domain . '/upload/' . $user['avatar'] : '',
                "pointNum" => $user['point_num'],
                "vip_name" => $user['member'] ? getUserMember($user['member']) : '-',
                "member_value" => $member_value,
                'kj_money' => $user['kj_money'],
                'income' => $user['income'],
                'total_recharge' => sprintf("%.2f", $total_recharge),
                'receipt_name' => $user['receipt_name'],
                'receipt_phone' => $user['receipt_phone'],
                'receipt_address' => $user['receipt_address'],
                "cur_value" => $user['value'],
                "is_payPass_init" => $user['mwpassword2'] == '123456' ? true : false,
                "pay_pa" => $user['mwpassword2'],
            );

            if (stripos($user['avatar'], 'http') !== false) $data['user_icon'] = $user['avatar'];

            $max = Db::name('lc_user_member')->max('value');
            if ($user['value'] >= $max) {
                $data['progress'] = 100;
                $data['next_member'] = 0;
            } else {
                $res = Db::name('LcUserMember')->where("value > " . $user['value'] . ' and value >0')->order('value asc')->find();
                $resa = Db::name('LcUserMember')->where("value < " . $res['value'] . ' and value >0')->order('value asc')->find();
                $data['progress'] = (($user['value'] - $resa['value']) / ($res['value'] - $resa['value'])) * 100;
                $data['next_member'] = $res['value'] - $user['value'];
            }
            //项目收益
            $data['project_profit'] = 0;
            $project_list = $list = Db::name('LcInvest')->field("id,money,rate,hour,status,time,time2, pid, grouping_num, grouping_income,uid")->where('uid', $uid)->where('status', 1)->order("id desc")->select();
            foreach ($project_list as $value) {
                $data['project_profit'] += $value['money'] * $value['rate'] / 100 * $value['hour'] / 24;
            }
            //途游宝收益
            $data['ebao_profit'] = $user['ebao_total_income'];
            //盲盒收益
            $data['blind_profit'] = Db::name('LcFinance')->where('uid', $user['id'])->where('zh_cn', 'like', '盲盒产品到期奖励%')->sum('money');
            //数字藏品收益
            $data['figure_collect_profit'] = 0;
            $figure_collect_list = Db::name('LcFigureCollectLog')->where('uid', $user['id'])->where('status', 2)->select();
            foreach ($figure_collect_list as $value) {
                $data['figure_collect_profit'] = bcdiv($value['money'] * $value['sell_rate'], 100, 2);
            }
            //固定收益
            $data['gd_profit'] = bcmul($data['project_profit'] + $data['ebao_profit'] + $data['blind_profit'] + $data['figure_collect_profit'], 1, 2);
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
            $data['system_profit'] = bcadd($data['realauth_profit'] + $data['realauth_profit'] + $data['buy_project_rb'], $data['cj_rb'], 2);
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
                    $data['ebao_wait'] += bcdiv($value['money'] * $product['day_rate'] * $cur_day, 100, 2);
                }
            }
            //待收本金
            $data['wait_money'] = Db::name('LcInvestList')->where("uid = $uid AND status = '0' AND money2 > 0")->sum('money2');
            //待收利息
            $data['wait_lixi'] = 0;
            $wait_list = Db::name('LcInvest')->field("$language,id,money,rate,hour,status,time,time2, pid, grouping_num, grouping_income,uid")->where('uid', $uid)->where('status', 0)->order("id desc")->select();
            foreach ($wait_list as $value) {
                //获取用户信息
                $userInfo = Db::name('lcUser')->where('id', $value['uid'])->find();
                $item = Db::name('lc_item')->where('id', $value['pid'])->find();
                $member = Db::name('lcUserMember')->where('id', $userInfo['member'])->find();
                //获取用户分组信息
                $member = Db::name('lcUserMember')->where('id', $userInfo['member'])->find();
                //获取首页项目加成
                if ($item['show_home'] == 1) {
                    $next_member = Db::name("lcUserMember")->where('value > ' . $member['value'])->order('value asc')->find();
                    if ($next_member) $member = $next_member;
                }
                // $period = $this -> q($item['cycle_type'], $value['hour']);
                $period = 1;
                $nterest = $this->nterest($value['money'], $value['hour'], $item['rate'], $item['cycle_type'], $period);
                $userNterest = $this->nterest($value['money'], $value['hour'], $member['rate'], $item['cycle_type'], $period);

                if (!$value['status']) {
                    $data['wait_lixi'] = bcadd($data['wait_lixi'], bcadd($nterest, $userNterest, 2), 2);
                }
            }
            $data['wait_lixi'] = Db::name('lc_invest_list')->where('uid', $uid)->where('status', 0)->sum('money1');
            ///盲盒本金+收益
            $data['blind_wait'] = 0;
            $blind_list = Db::name('LcBlindBuyLog')->where('uid', $user['id'])->where('status', 0)->select();
            foreach ($blind_list as $value) {
                $data['blind_wait'] += $value['money'] + $value['money'] * $value['rate'] / 100;
            }
            //数字藏品+收益
            $data['figure_collect_wait'] = 0;
            $figure_collect_list = Db::name('LcFigureCollectLog')->where('uid', $user['id'])->whereIn('status', [0, 1])->select();
            foreach ($figure_collect_list as $value) {
                $data['figure_collect_wait'] += $value['money'] + $value['money'] * $value['sell_rate'] / 100;
            }
            //账户余额
            $data['total_asset'] = bcadd($data['ebao'] + $data['ebao_wait'] + $user['money'] + $data['wait_money'] + $data['wait_lixi'] + $data['blind_wait'], $data['figure_collect_wait'], 2);


            $rate_usd = Db::name('lc_info')->find(1)['rate_usd'];
            $data['asset'] = vnd_gsh($data['asset']) . '≈' . bcdiv($data['asset'], $rate_usd, 2);
            $data['all_money'] = vnd_gsh($data['all_money']) . '≈' . bcdiv($data['all_money'], $rate_usd, 2);
            $data['money'] = vnd_gsh($data['money']) . '≈' . bcdiv($data['money'], $rate_usd, 3);
            $data['total_recharge'] = vnd_gsh($data['total_recharge']) . '≈' . bcdiv($data['total_recharge'], $rate_usd, 3);
            $data['wait_money'] = vnd_gsh($data['wait_money']) . '≈' . bcdiv($data['wait_money'], $rate_usd, 3);
            $data['wait_lixi'] = vnd_gsh($data['wait_lixi']) . '≈' . bcdiv($data['wait_lixi'], $rate_usd, 3);

            $this->success("获取成功", $data);
        }

    }

    /**
     * Describe:会员分享
     * DateTime: 2020/5/17 14:03
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function share()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $phone = Db::name('LcUser')->where(['id' => $uid])->value('phone');
        $share_user = Db::name('LcUser')->where(['top' => $uid])->field('phone,auth,time')->select();
        $reward = Db::name('LcReward')->field("register2")->find(1);
        $qrCode = new QrCode();
        $qrCode->setText(getInfo('domain') . '/#/register?m=' . $phone);
        $qrCode->setSize(300);
        $shareCode = $qrCode->getDataUri();
        $shareLink = getInfo('domain') . '/#/register?m=' . $phone;
        $data = array(
            'share_user' => $share_user,
            'share_image_url' => $shareCode,
            'share_link' => $shareLink,
            'reward' => $reward['register2'],
            'user_icon' => getInfo('logo_img'),
            'phone' => $phone
        );
        $this->success("获取成功", $data);
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
        $language = $this->request->param('language');
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
        $top1 = Db::name('LcUser')->where(['top' => $uid])->field('id,top,phone,name,time, auth, czmoney')->order("czmoney desc")->select();
        // $top2 = Db::name('LcUser')->where(['top2' => $uid])->field('id,phone,name,time, auth, czmoney')->order("czmoney desc")->select();
        // $top3 = Db::name('LcUser')->where(['top3' => $uid])->field('id,phone,name,time, auth, czmoney')->order("czmoney desc")->select();

        $top1Ids = Db::name('lc_user')->where('top', $uid)->column('id');
        $top1Ids = empty($top1Ids) ? [9999999] : $top1Ids;
        $top2 = Db::name('LcUser')->where('top', 'in', $top1Ids)->field('id,top,phone,name,time, auth, czmoney')->order("czmoney desc")->select();
        $top2Ids = Db::name('LcUser')->where('top', 'in', $top1Ids)->column('id');
        $top2Ids = empty($top2Ids) ? [9999999] : $top2Ids;
        $top3 = Db::name('LcUser')->where('top', 'in', $top2Ids)->field('id,top,phone,name,time, auth, czmoney')->order("czmoney desc")->select();
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
                $top2[$key]['phone'] = $aes->decrypt($value['phone']);
            }
        }
        if (!empty($top3)) {
            foreach ($top3 as $key => $value) {
                $top3[$key]['time'] = date("Y/m/d H:i:s", strtotime($value['time']));
                $top3[$key]['phone'] = $aes->decrypt($value['phone']);
            }
        }
        $memberList = Db::name('LcUser')->field('id, phone, top,czmoney,name,time, auth')->select();

        $itemList = $this->get_downline_list($memberList, $uid);

        //投资人数
        $totalInvestNum = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')->whereIn('l.uid', array_column($itemList, 'id'))->group('uid')->count();

        //   var_dump($itemList);die;
        $all_czmoney = 0;
        $top4 = [];
        $top5 = [];
        $top6 = [];
        $top7 = [];
        $top8 = [];
        $top9 = [];
        $top10 = [];
        $is_sf = Db::name('LcUser')->where(['id' => $uid])->value('is_sf');
        //   var_dump($this->userInfo['czmoney']);
        //   var_dump($this->userInfo['is_sf']);die;
        if ($is_sf == 0) {
            //   $all_czmoney=$this->userInfo['czmoney'];
            $all_czmoney = Db::name('LcUser')->where(['id' => $uid])->value('czmoney');
        }
        foreach ($itemList as $k => $v) {
            $v['phone'] = $aes->decrypt($v['phone']);
            $all_czmoney += $v['czmoney'];
            if ($v['level'] == 4) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top4[] = $v;
                //   $top4=array_merge($top4,$arr);
            }
            if ($v['level'] == 5) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top5[] = $v;
            }
            if ($v['level'] == 6) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top6[] = $v;
            }
            if ($v['level'] == 7) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top7[] = $v;
            }
            if ($v['level'] == 8) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top8[] = $v;
            }
            if ($v['level'] == 9) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top9[] = $v;
            }
            if ($v['level'] == 10) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top10[] = $v;
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
        $top1Project = Db::name('LcInvest r , lc_user u')->where("r.uid = u.id AND u.top = $uid")->sum('r.money');
        $top2Project = Db::name('LcInvest r , lc_user u')->where("r.uid = u.id AND u.top2 = $uid")->sum('r.money');
        $top3Project = Db::name('LcInvest r , lc_user u')->where("r.uid = u.id AND u.top3 = $uid")->sum('r.money');
        $countProject = $myProject + $top1Project + $top2Project + $top3Project;

        $info = Db::name('LcInfo')->find(1);


        //总投资额
        $members = Db::name('LcUser')->find($uid);
        $memberList = Db::name('LcUser')->field('id,phone,top,czmoney')->select();
        $itemList = $this->get_downline_list($memberList, $members['id']);
        $ids = [$uid];
        $comIds = [];
        $teamIds = [$uid];
        $teamIdss = [];

        $grade_id = Db::name('lc_user')->find($uid)['grade_id'];
        $currentMemberGrade = Db::name("LcMemberGrade")->where(['id' => $grade_id])->find();
        $team_total_cz = 0;
        foreach ($itemList as $item) {
            $ids[] = $item['id'];
            $comIds[] = $item['id'];
            if ($item['level'] > $currentMemberGrade['statistics'] && $currentMemberGrade['statistics'] != 'n') {
                continue;
            }
            $teamIds[] = $item['id'];
            $teamIdss[] = $item['id'];
            $czmoney = Db::name("lc_user")->where('id', $item['id'])->find()['czmoney'];
            $team_total_cz = bcadd($czmoney, $team_total_cz, 2);
        }


        $totalInvest = Db::name('lc_invest t')->join('lc_item m', 't.pid = m.id')
            // ->where('m.index_type', '<>', 7)
            ->whereIn('t.uid', $teamIdss)->sum('t.money');

        // $totalInvest = Db::name('lc_invest t')
        // ->whereIn('t.uid', $teamIdss)->sum('t.money');

        //投资人数
        $totalInvestNum = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')->whereIn('l.uid', $teamIdss)->group('uid')->count();


        //团队奖
        // $countCommission = Db::name('LcFinance')->where('zh_cn', 'like', '%奖励，投资%')->whereIn('uid', $teamIds)->sum('money');
        $countCommission = Db::name('LcFinance')->where('zh_cn', 'like', '%奖励')->whereIn('uid', $teamIds)->sum('money');

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
            'aap_down' => $aap_down,
            'appDownCode' => $appDownCode,
            'count_recharge' => $countRecharge,
            'count_project' => $countProject,
            'countCommission' => $countCommission,
            'is_see' => $info['is_see'],
            // 'myTeanNum'=>count($itemList),
            'myTeanNum' => count($teamIdss),
            'all_czmoney' => sprintf("%.2f", $all_czmoney),
            'total_invest' => $totalInvest,
            'total_invest_num' => $totalInvestNum,
            'team_total_cz' => $team_total_cz
        );
        $grade_info = Db::name('LcMemberGrade')->where("id", $user_info['grade_id'])->field("id,recom_number,all_activity,recom_tz")->find();
        $next_grade = Db::name('LcMemberGrade')->where("all_activity", '>', $grade_info['all_activity'])->field("id,recom_number,all_activity,recom_tz")->order('all_activity asc')->find();

        $tznum = Db::name('LcUser')->where([['top', '=', $uid], ['grade_id', '>', 1]])->count();
        // $huiyuannum = Db::name('LcUser')->where([['top', '=',$uid]])->count();
        // $huiyuannum = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')->where('u.top', $uid)->group('uid')->count();
        $huiyuannum = Db::name('lc_user u')->join('lc_invest l', 'l.uid = u.id')->join('lc_item i', 'l.pid=i.id')->where('index_type', '<>', 7)->where('top', $uid)->group('l.uid')->count();

        $finish_arr = [
            'zh_cn' => lang('text7'),
            'zh_hk' => lang('text7'),
            'en_us' => lang('text7'),
        ];

        if (!$next_grade) {
            $data['next'] = [
                'touzi' => ['cur' => $totalInvest, 'need' => $finish_arr[$language], 'progress' => 100],
                'huiyuan' => ['cur' => $huiyuannum, 'need' => $finish_arr[$language], 'progress' => 100],
                'tuanzhang' => ['cur' => $tznum, 'need' => $finish_arr[$language], 'progress' => 100],
            ];
        } else {
            //当前团队投资额
            $tzCur = $totalInvest;
            $tzNeed = $next_grade['all_activity'] - $tzCur;
            if ($tzNeed <= 0) $tzNeed = $finish_arr[$language];
            if ($next_grade['all_activity']) {
                $tzProgress = intval($tzCur / $next_grade['all_activity'] * 100);
            } else {
                $tzProgress = 100;
            }
            //当前直推数量
            $ztCur = $huiyuannum;
            $ztNeed = $next_grade['recom_number'] - $ztCur;
            if ($ztNeed <= 0) $ztNeed = $finish_arr[$language];
            if ($next_grade['recom_number']) {
                $ztProgress = intval($ztCur / $next_grade['recom_number'] * 100);
            } else {
                $ztProgress = 100;
            }

            //团队数量
            $tdCur = $tznum;
            $tdNeed = $next_grade['recom_tz'] - $tdCur;
            if ($tdNeed <= 0) $tdNeed = $finish_arr[$language];
            if ($next_grade['recom_tz'] == 0) {
                $tdNeed = $finish_arr[$language];
                $tdProgress = 100;
            } else {
                $tdProgress = intval($tdCur / $next_grade['recom_tz'] * 100);
                if ($tdProgress == 100) $tdNeed = $finish_arr[$language];
            }

            $data['next'] = [
                'touzi' => ['cur' => $tzCur, 'need' => $tzNeed, 'progress' => $tzProgress],
                'huiyuan' => ['cur' => $ztCur, 'need' => $ztNeed, 'progress' => $ztProgress],
                'tuanzhang' => ['cur' => $tdCur, 'need' => $tdNeed, 'progress' => $tdProgress],
            ];
        }
        $teamName = Db::name('LcUser a , lc_member_grade b')->where("a.grade_id = b.id AND a.id = $uid")->field('b.title as title_zh_cn,b.title_zh_hk,b.title_en_us')->find();
        $data['team_name'] = $teamName['title_' . $language];
        $data['recommend_reward'] = Db::name('lc_finance')->where('zh_cn', 'like', '%下级%会员返佣%')->where('uid', $uid)->sum('money');
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
        $aap_down = Db::name('LcVersion')->where(['id' => 1])->value('android_app_down_url');
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

        $itemList = $this->get_downline_list($memberList, $uid);
        //   var_dump($itemList);die;
        $all_czmoney = 0;
        $top4 = [];
        $top5 = [];
        $top6 = [];
        $top7 = [];
        $top8 = [];
        $top9 = [];
        $top10 = [];
        foreach ($itemList as $k => $v) {
            $all_czmoney += $v['czmoney'];
            if ($v['level'] == 4) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top4[] = $v;
                //   $top4=array_merge($top4,$arr);
            }
            if ($v['level'] == 5) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top5[] = $v;
            }
            if ($v['level'] == 6) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top6[] = $v;
            }
            if ($v['level'] == 7) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top7[] = $v;
            }
            if ($v['level'] == 8) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top8[] = $v;
            }
            if ($v['level'] == 9) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top9[] = $v;
            }
            if ($v['level'] == 10) {
                $v['time'] = date("Y/m/d H:i:s", strtotime($v['time']));
                $top10[] = $v;
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
            'all_czmoney' => $all_czmoney,
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
    public function nterest($money, $hour, $rate, $type, $period)
    {
        $rate = $rate / 100;
        $total = 0;
        switch ($type) {
            case 1:
                $total = $money * $rate * $hour / $period;
                break;
            case 2:
                $q = ($hour / 24) < 1 ? 1 : ($hour / 24);
                $total = $money * $rate * $q / $period;
                break;
            case 3:
                $q = ($hour / 24 / 7) < 1 ? 1 : ($hour / 24 / 7);
                $total = $money * $rate * $q / $period;
                break;
            case 4:
                $q = ($hour / 24 / 30) < 1 ? 1 : ($hour / 24 / 30);
                $total = $money * $rate * $q / $period;
                break;
            case 5:
                $text = '到期返本返利';
                $total = $money * $rate / $period;
                break;
            case 6:
                $q = ($hour / 24 / 365) < 1 ? 1 : ($hour / 24 / 365);
                $total = $money * $rate * $q / $period;
                break;
            default:
                $text = '未设置类型';
                break;
        }
        return round($total, 2);
    }

    public function order()
    {
        $rate_usd = Db::name('lc_info')->find(1)['rate_usd'];
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $page = $this->request->param('page', 1);
        $size = $this->request->param('size', 5);
        $language = $params["language"];
        $user = Db::name('lc_user')->find($uid);
        $wait_money = 0;
        $wait_lixi = 0;
        $list = Db::name('LcInvest')->field("*")->where('uid', $uid)->page($page, $size)->order(['status' => 'ASC', 'id' => 'DESC'])->select();
        $invest_ids = array_column($list, 'id');
        $invest_lists = Db::name('lc_invest_list')
            ->whereIn('iid', $invest_ids)
            ->select();
        $today_start_day = date('Y-m-d 00:00:00');
        $today_end_day = date('Y-m-d 23:59:59');
        $invest_status = [];
        $id_count = [];
        foreach ($invest_lists as $invest) {
            $id_count[$invest['iid']] = 1;
            $day = date('Y-m-d', strtotime($invest['time1']));
            if (!isset($invest_status[$invest['iid']])) {
                $invest_status[$invest['iid']] = [
                    'income' => 0,
                    'is_income' => 0,
                    'num' => 1,
                    'start_day' => false,
                ];
            }
            $invest_status[$invest['iid']]['income'] = bcadd($invest_status[$invest['iid']]['income'], $invest['money1'], 2);
            if ($invest['status'] == 1 && ($invest['time2'] >= $today_start_day) && $invest['time2'] <= $today_end_day) {
                $invest_status[$invest['iid']]['is_income'] = 1;
                $invest_status[$invest['iid']]['num'] = $invest['num'];
            }

            if ($invest['status'] == 0 && !$invest_status[$invest['iid']]['start_day']) {
                $invest_status[$invest['iid']]['start_day'] = $day;
                $id_count[$invest['iid']]++;
                $invest_status[$invest['iid']]['num'] = $invest['num'];
                continue;
            }

            if ($invest['status'] == 0 && $day < $invest_status[$invest['iid']]['start_day']) {
                $invest_status[$invest['iid']]['start_day'] = $day;
            }
            $id_count[$invest['iid']]++;
        }
        $today = date('Y-m-d');
        foreach ($list as &$item) {
            $item['sy'] = 0;
            $item['is_income'] = 0;
            if (isset($invest_status[$item['id']])) {
                $item['num'] = $invest_status[$item['id']]['num'];

                $item['sy'] = $invest_status[$item['id']]['income'];
                $item['is_income'] = $invest_status[$item['id']]['is_income'];
                if ($item['is_income'] == 0 && $invest_status[$item['id']]['start_day']) {
                    if ($today < $invest_status[$item['id']]['start_day']) {

                        $item['is_income'] = 1;
                    }
                }
            }

            $wait_money = bcadd($wait_money, Db::name('lc_invest_list')->where('iid', $item['id'])->where('status', 0)->sum('money2'), 3);
            $wait_lixi = bcadd($wait_lixi, Db::name('lc_invest_list')->where('iid', $item['id'])->where('status', 0)->sum('money1'), 3);
            $item['money'] = bcdiv($item['money'], $rate_usd, 2);
            $item['sy'] = bcdiv($item['sy'], $rate_usd, 2);
            $item['title'] = $item[$language];
        }
        $yesterday_profit = Db::name('lc_invest_list')->where('uid', $uid)->where('status', 1)->where("TO_DAYS(NOW( ) ) - TO_DAYS( time1) <= 1  ")->sum('money1');
        $all_money = Db::name('lc_invest_list')->where('uid', $uid)->where('status', 1)->sum('money1');
        $asset_usdt = bcdiv($user['asset'], $rate_usd, 2);
        // $yesterday_profit = bcdiv($yesterday_profit,1,2);
        $yesterday_profit_usdt = bcdiv($yesterday_profit, $rate_usd, 6);
        //累计收益
        $ok_apr_money_usdt = bcdiv($all_money, $rate_usd, 2);
        $on_apr_money_usdt = bcdiv($wait_lixi, $rate_usd, 2);
        $on_money_usdt = bcdiv($wait_money, $rate_usd, 2);
        $this->success("获取成功", ['uid' => $uid, 'list' => $list, 'on_money' => vnd_gsh(sprintf("%.2f", $wait_money)), 'on_apr_money' => vnd_gsh(sprintf("%.2f", $wait_lixi)), 'ok_apr_money' => vnd_gsh(sprintf("%.2f", $all_money)), 'asset' => vnd_gsh(bcdiv($user['asset'], 1, 2)), 'asset_usdt' => $asset_usdt, 'yestday_profit' => $yesterday_profit, 'yestday_profit_usdt' => $yesterday_profit_usdt, 'ok_apr_money_usdt' => $ok_apr_money_usdt, 'on_apr_money_usdt' => $on_apr_money_usdt, 'on_money_usdt' => $on_money_usdt]);
    }

    /**
     * Describe:订单列表
     * DateTime: 2020/9/5 13:41
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function order1()
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
        $wait_lixi = 0;
        $all_money = 0;
        foreach ($list as $k => $v) {
            // item.money * item.rate / 100 * item.hour / 24
            if (!$v['status']) {
                $wait_lixi += $v['money'] * $v['rate'] / 100 * $v['hour'] / 24;
            } else {
                $all_money += $v['money'] * $v['rate'] / 100 * $v['hour'] / 24;
            }
        }
        $wait_lixi = 0;
        for ($i = 0; $i < count($list); $i++) {
            //获取用户信息
            $userInfo = Db::name('lcUser')->where('id', $list[$i]['uid'])->find();
            //获取项目信息
            $item = Db::name('lc_item')->where('id', $list[$i]['pid'])->find();
            //获取用户分组信息
            $member = Db::name('lcUserMember')->where('id', $userInfo['member'])->find();
            //获取首页项目加成
            if ($item['show_home'] == 1) {
                $next_member = Db::name("lcUserMember")->where('value > ' . $member['value'])->order('value asc')->find();
                if ($next_member) $member = $next_member;
            }
            //$list[$i]['sy'] = $list[$i]['money'] * ($list[$i]['money'] / 100) + $list[$i]['money'] * ($member['rate'] / 100);
            //$list[$i]['sy'] = $list[$i]['money'] * ($list[$i]['rate'] / 100) + $list[$i]['money'] * ($member['rate'] / 100);

            // $period = $this -> q($item['cycle_type'], $list[$i]['hour']);
            $period = 1;
            if ($item['add_rate'] == 0) {
                $member['rate'] = 0;
            }
            $nterest = $this->nterest($list[$i]['money'], $list[$i]['hour'], $item['rate'], $item['cycle_type'], $period);
            $userNterest = $this->nterest($list[$i]['money'], $list[$i]['hour'], $member['rate'], $item['cycle_type'], $period);

            $list[$i]['sy'] = bcadd($nterest, $userNterest, 2);
            if (!$list[$i]['status']) {
                $wait_lixi = bcadd($wait_lixi, bcadd($nterest, $userNterest, 2), 2);
            }

        }
        // $all_money = Db::name('LcInvestList')->where("uid = $uid AND status = '1' AND money1 > 0")->sum('money1');
        $user = Db::name('lc_user')->find($uid);
        $yesterday_profit = Db::name('lc_invest_list')->where('uid', $uid)->where('status', 1)->where("TO_DAYS(NOW( ) ) - TO_DAYS( time1) <= 1  ")->sum('money1');
        $all_money = Db::name('lc_invest_list')->where('uid', $uid)->where('status', 1)->sum('money1');
        $rate_usd = Db::name('lc_info')->find(1)['rate_usd'];
        $asset_usdt = bcdiv($user['asset'], $rate_usd, 2);
        $yesterday_profit_usdt = bcdiv($yesterday_profit, $rate_usd, 2);
        // $asset = $user['asset'].'≈'.bcdiv($user['asset'], $rate_usd, 2);
        // $yesterdayProfit = $yesterdayProfit.'≈'.bcdiv($yesterdayProfit, $rate_usd, 2);
        Log::record($all_money, 'error');
        $this->success("获取成功", ['uid' => $uid, 'list' => $list, 'on_money' => sprintf("%.2f", $wait_money), 'on_apr_money' => sprintf("%.2f", $wait_lixi), 'ok_apr_money' => sprintf("%.2f", $all_money), 'asset' => $user['asset'], 'asset_usdt' => $asset_usdt, 'yestday_profit' => $yesterday_profit, 'yestday_profit_usdt' => $yesterday_profit_usdt]);
    }

    public function q($type, $hour)
    {
        $q = 0;
        switch ($type) {
            case 1:
                $q = $hour;
                break;
            case 2:
                $q = ($hour / 24) < 1 ? 1 : ($hour / 24);
                break;
            case 3:
                $q = ($hour / 24 / 7) < 1 ? 1 : ($hour / 24 / 7);
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
        $banks = Db::name('LcBank')->where('uid', $userInfo['id'])->select();

        $intPwd = $this->user['mwpassword2'] == '123456' ? true : false;
        $bank = Db::name('LcBank bank,lc_withdrawal_wallet wallet')->field("bank.account as account,bank.bank as bank,bank.id as id,wallet.charge as charge,wallet.type as type,wallet.rate as rate,wallet.mark as mark, bank.bank_type as bankType")->where('bank.wid=wallet.id AND wallet.show=1')->where(['bank.uid' => $userInfo['id']])->order('bank.id desc')->select();
        foreach ($bank as $k => $v) {
            if (strlen($v['account']) > 6) {
                $dataDesensitization = dataDesensitization($v['account'], 2, strlen($v['account']) - 6);
                $bank[$k]['account'] = $dataDesensitization ?: $v['account'];
            }
        }

        // 查询矿币兑换比例
        $machines = Db::name("LcMachines")->find();

        //免费额度
        $free_quota = 100000;
        $total_cash = Db::name('lc_cash')->where('uid', $userInfo['id'])->whereIn('status', [0, 1])->sum('money');
        if ($free_quota > $total_cash) {
            $differ_quota = bcsub($free_quota, $total_cash, 2);
        } else {
            $differ_quota = 0;
        }
        $data = array(
            'count' => count($bank),
            'bank' => $bank,
            'money' => vnd_gsh(bcdiv($this->user['money'], 1, 2)),
            'kjMoney' => bcdiv($this->user['kj_money'], 1, 2),
            'intPwd' => $intPwd,
            'asset' => vnd_gsh(bcdiv($this->user['asset'], 1, 2)),
            'machines_rate' => $machines['rate'],
            'banksid' => $banks,
            'free_quota' => $free_quota,
            'differ_quota' => $differ_quota
        );
        $this->success("获取成功", $data);
    }

    //银行卡验证
    public function verify_bank($name, $bankcard_number)
    {
        $host = "https://jumbank2ck.market.alicloudapi.com";
        $path = "/bankcard/2-check";
        $method = "POST";
        $appcode = "fe1e1fb261c14ac68244483b1938e8d8";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        array_push($headers, "Content-Type" . ":" . "application/x-www-form-urlencoded; charset=UTF-8");
        $querys = "";
        $bodys = "bankcard_number=" . $bankcard_number . "&name=" . $name;
        $url = $host . $path;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        $result = json_decode(curl_exec($curl), true);
        if ($result['code'] != 200) {
            return ['status' => 0, 'msg' => $result['msg']];
        }
        if ($result['data']['result'] != 0) {
            return ['status' => 0, 'msg' => $result['data']['msg']];
        }

        return ['status' => 1, 'msg' => '验证成功'];
        //6228480921560919315
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
        // if (empty($params['t_txyzm'])) {
        //     $this->error(array(
        //         'zh_cn' => '请填写图形验证码'
        //     ));
        // }
        // //图形验证码
        // $txyzm = Cache::store('redis')->get('api_make_code_'.$params["uuid"]);
        // if(!$txyzm||$txyzm!=$params['t_txyzm']){
        //     $this->error(array(
        //         'zh_cn' => '图形验证码不正确'
        //     ));
        // }
        //判断是否添加过
        $bankRes = DB::name('lc_bank')->where(['uid' => $uid, 'type' => $type])->find();
        if (!empty($bankRes['id'])) $this->error(Db::name('LcTips')->field("$language")->find('210'));

        //判断是否为实名名字
        // if($type == 4 && (empty($params['name']) || $this->user['name'] != $params['name'])) $this->error(Db::name('LcTips')->field("$language")->find('211'));


        //银行卡验证
        if ($type == 4) {
            // $authBank = $this->verify_bank($params['name'], $params['account']);
            // if (!$authBank['status']) $this->error($authBank['msg']);
        }


        $user = Db::name('LcUser')->find($uid);
        // if (!$params['code']) $this->error(Db::name('LcTips')->field("$language")->find('44'));

        // 验证验证码是否正确
        // $codeResult = check_code($user['phone'], $params['code'], $language);
        // if (!$codeResult['status']) {
        //     $this->error($codeResult['msg']);
        // }
        // $sms_code = Db::name("LcSmsList")->where("date_sub(now(),interval 5 minute) < time")->where("phone = '{$user['phone']}'")->order("id desc")->value('ip');
        // if ($params['code'] != $sms_code) $this->error(Db::name('LcTips')->field("$language")->find('45'));

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
            $auth_check = bankAuth($this->user['name'], $card, $this->user['idcard']);
            if ($auth_check['code'] == 0) $this->error($auth_check['msg']);
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
        $this->error("Thao tác thất bại");
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
        if (isSimplePayPassword($params['npassword'])) {
            $this->error(lang('text10'));
        }

        // if (empty($params['t_txyzm'])) {
        //     $this->error(array(
        //         'zh_cn' => '请填写图形验证码'
        //     ));
        // }
        // //图形验证码
        // $txyzm = Cache::store('redis')->get('api_make_code_'.$params["uuid"]);
        // if(!$txyzm||$txyzm!=$params['t_txyzm']){
        //     $this->error(array(
        //         'zh_cn' => '图形验证码不正确'
        //     ));
        // }
        if (!$user) $this->error(Db::name('LcTips')->field("$language")->find('46'));
        // if (!$params['code']) $this->error(Db::name('LcTips')->field("$language")->find('119'));


        // 验证验证码是否正确
        $guo = $user['guo'];
        $aes = new Aes();
        $phone = $aes->decrypt($user['phone']);
        $mobile = $aes->encrypt($guo . $phone);

        // if($params['code'] != 502231){
        //     // 验证验证码是否正确
        //     $codeResult = check_code($mobile, $params['code'], $language);
        //     if (!$codeResult['status']) {
        //         $this->error($codeResult['msg']);
        //     }
        // }


        // $sms_code = Db::name("LcSmsList")->where("phone = '{$user['phone']}'")->order("id desc")->value('ip');
        // if ($params['code'] != $sms_code) $this->error(Db::name('LcTips')->field("$language")->find('120'));


        // if (payPassIsContinuity($params['npassword'])) $this->error(Db::name('LcTips')->field("$language")->find('122'));
        $data = ['password2' => md5($params['npassword']), 'mwpassword2' => $params['npassword']];
        //开启事务
        Db::startTrans();
        if ($params['old_password']) {
            $res = Db::name('LcUser')->where('id', $uid)->where('mwpassword2', $params['old_password'])->find();
            if (!$res) {
                $this->error(Db::name('LcTips')->field("$language")->find('116'));
            }
        } else {
            $res = Db::name('LcUser')->where('id', $uid)->update($data);
        }
        if ($res) {
            Db::commit();
            $this->success(Db::name('LcTips')->field("$language")->find('112'));
        } else {
            Db::rollback();
            $this->error(Db::name('LcTips')->field("$language")->find('113'));
        }
    }

    public function calc_withdrawals()
    {
        //已提现金额
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $free_money = 100000;
        // $total_money = Db::name('lc_cash')->where(['uid' => $uid])->whereIn('status', [0,1])->sum('money');
        // if ($total_money <= $free_money) {
        //     $differ_money = bcsub($free_money, $total_money, 2);
        // } else {
        //   $differ_money = 0; 
        // }
        $params = $this->request->param();
        $bank = Db::name('lc_bank')->find($params['bank_id']);
        $wallet = Db::name('lc_withdrawal_wallet')->where('id', $bank['wid'])->find();
        $fee = $this->getFee($uid, $wallet, $params['money']);
        // if ($differ_money > 0) {
        //     if ($differ_money >= $params['money']) {
        //         $fee = 0;
        //     } else {
        //         $fee = bcdiv(bcmul($params['money']-$differ_money, $wallet['charge'], 2), 100, 2);
        //     }
        // } else {
        //     $fee = bcdiv(bcmul($params['money'], $wallet['charge'], 2), 100, 2);
        // }
        // $need_money = $params['money'] - $differ_money;
        // $fee = bcdiv(bcmul($params['money'], $wallet['charge'], 2), 100, 2);
        // $fee = bcdiv(bcmul($need_money, $wallet['charge'], 2), 100, 2);
        $data = [
            'charge' => $wallet['charge'],
            'fee' => $fee,
            'money' => bcsub($params['money'], $fee, 2)
        ];
        $this->success('获取成功', $data);
    }

    public function getFee($uid, $wallet, $cash_money)
    {
        $free_money = 0;   //免费额度
        $total_money = Db::name('lc_cash')->where(['uid' => $uid])->whereIn('status', [0, 1])->sum('money');
        if ($total_money <= $free_money) {
            $differ_money = bcsub($free_money, $total_money, 2);
        } else {
            $differ_money = 0;
        }
        if ($differ_money > 0) {
            if ($differ_money >= $cash_money) {
                $fee = 0;
            } else {
                $fee = bcdiv(bcmul($cash_money - $differ_money, $wallet['charge'], 2), 100, 2);
            }
        } else {
            $fee = bcdiv(bcmul($cash_money, $wallet['charge'], 2), 100, 2);
        }
        return $fee;
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
        if (!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $params['money'])) $this->error('请输入正确的金额');
        //充值提现时间为：：9：00——24：00
        // date_default_timezone_set("Asia/Shanghai");

        if (date('G') < 9 || date('G') >= 22) {
            //$this->error("提现时间为：9：00——22：00");die;
        }
        $language = $params["language"];
        $uid = $this->userInfo['id'];

        $this->user = Db::name('LcUser')->find($uid);
        $this->min_cash = getInfo('cash');
        $this->withdraw_num = getInfo('withdraw_num');
        $this->bank = Db::name('LcBank')->where('uid', $uid)->order("id desc")->select();

        $redisKey = 'LockKeyUserCostApply' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 10, 0)) {
            $this->error(Db::name('LcTips')->field("$language")->find('229'));
        }


        //判断当前是否绑定银行卡
        // if (!Db::name('LcBank')->where(['uid' =>  $uid, 'bank' => '银行卡'])->find()) {
        //     $this->error('请先绑定银行卡');die;
        // }

        if ($this->user['money'] == 0) {
            $this->error('你的账户余额不足');
            die;
        }

        //1分钟内提交不超过五次
        $cash = Db::name('lc_cash')->where('uid', $uid)->order('id desc')->limit(6)->select();
        if (count($cash) > 5) {
            if (strtotime($cash[4]['time']) > (time() - 60)) {
                $this->error(get_tip(229, $language));
            }
        }

        //usdt验证

        $lastCash = Db::name('lc_cash')->where('uid', $uid)->where('bank', 'USDT(TRC-20)')->order('id desc')->find();
        $bank = Db::name('lc_bank')->find($params['bank_id']);
        if (empty($bank)) {
            return;
        }
        $cash_type = $bank['wid'];

        if ($lastCash && $bank['bank'] == 'USDT(TRC-20)') {
            if ($lastCash['account'] != $bank['account']) {
                $this->error('提现地址不一致，请联系客服');
            }
        }

        //提现金额
        $wallet = Db::name('lc_withdrawal_wallet')->where('id', $bank['wid'])->find();
        if ($params['money'] < $wallet['min_withdrawals']) {
            $this->error(lang('text9') . $wallet['min_withdrawals']);
        }
        if ($params['money'] > $wallet['max_withdrawals']) {
            $this->error(lang('text9') . $wallet['max_withdrawals']);
        }

        $user = Db::name('lc_user')->find($uid);


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
            // if ($data['money'] < $this->min_cash) {
            //     $returnData = array(
            //         "$language" => Db::name('LcTips')->where(['id' => '131'])->value("$language") . $this->min_cash . Db::name('LcTips')->where(['id' => '180'])->value("$language")
            //     );
            //     $this->error($returnData);
            // }
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
                // $chargeMoney = round($data['money'] * $wallet['charge'] / 100, 2);
                $chargeMoney = $this->getFee($uid, $wallet, $data['money']);
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
                }
                $add = array('uid' => $uid, 'name' => $bank['name'], 'bid' => $data['bank_id'], 'bank' => $bank['bank'], 'area' => $bank['area'] ?: 0, 'account' => $bank['account'], 'img' => $bank['img'], 'money' => $data['money'] - $chargeMoney, 'money2' => $money2, 'charge' => $chargeMoney, 'status' => 0, 'time' => date('Y-m-d H:i:s'), 'time2' => '0000-00-00 00:00:00');
            }
            //内部账号提现自动审核
            if ($this->user['is_sf'] == 1 || $this->user['is_sf'] == 2) {
                // if ($data['bank_id'] == 0) {
                //     $add = array('uid' => $uid, 'name' => $bank['name'], 'bid' => $data['bank_id'], 'bank' => "Alipay", 'area' => 0, 'account' => $this->user['alipay'], 'money' => $data['money'], 'charge' => $chargeMoney, 'status' => 1, 'time' => date('Y-m-d H:i:s'), 'time2' => '0000-00-00 00:00:00');
                // } else {
                //     $money2 = round($data['money'] / $wallet['rate'], $num11) - $chargeMoney;;
                // if ($wallet['type'] == 1) { //改过
                //     $money2 = round($data['money'] / $wallet['rate'], $num11) - $chargeMoney;
                // } else if ($wallet['type'] == 4) {
                //     $money2 = round($data['money'], $num11) - $chargeMoney;
                // }
                //     $add = array('uid' => $uid, 'name' => $bank['name'], 'bid' => $data['bank_id'], 'bank' => $bank['bank'], 'area' => $bank['area'] ?: 0, 'account' => $bank['account'], 'img' => $bank['img'], 'money' => $data['money'] - $chargeMoney, 'money2' => $money2, 'charge' => $chargeMoney, 'status' => 1, 'time' => date('Y-m-d H:i:s'), 'time2' => '0000-00-00 00:00:00');
                // }
                $add['status'] = 1;
                //内部号增加提现金额
                Db::name('lc_user')->where('id', $uid)->update(['cash_sum' => bcadd($user['cash_sum'], $data['money'], 2)]);
            }
            $add['money2'] = round($add['money2'], 0);
            $add['cash_type'] = $cash_type;
            if ($cash_type == 10) {
                // 需要走代付的
                $orderid = 'DY' . date('His') . rand(1, 999);
                $add['order_no'] = $orderid;
            }
            // var_dump($charge);exit;
            if (Db::name('LcCash')->insert($add)) {
                //标记
                $user = Db::name('lc_user')->find($uid);
                //提现时间
                $cashTime = Db::name('lc_cash')->where('uid', $uid)->order('id desc')->limit(3)->select();
                if (count($cashTime) == 3) {
                    $rechargeTime = Db::name('lc_recharge')->where('uid', $uid)->where('status', 1)->order('id desc')->find();
                    if (!$rechargeTime || strtotime($cashTime[2]['time']) > strtotime($rechargeTime['time'])) {
                        Db::name('lc_user')->where('id', $uid)->update(['sign_status' => 1]);
                    }
                }

                //手续费
                $withdrawMoney = $data['money'];
                if ($wallet['charge'] > 0) {
                    // $charge = round($data['money'] * $wallet['charge'] / 100, 2);
                    $charge = $this->getFee($uid, $wallet, $data['money']);
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
                    $desc = "余额提现至USDT ";
                    $desc_zh_hk = "Rút số dư đến USDT ";
                    $desc_en_us = "Withdrawal of balance to USDT ";
                } else {
                    $desc = "余额提现至银行卡 ";
                    $desc_zh_hk = "Số dư rút tiền vào thẻ ngân hàng ";
                    $desc_en_us = "Withdrawal of balance to bank card ";
                }

                //提现流水
                $LcTips = Db::name('LcTips')->where(['id' => '136']);
                addFinance($uid, $withdrawMoney, 2,
                    $desc . $withdrawMoney,
                    $desc_zh_hk . $withdrawMoney,
                    $desc_en_us . $withdrawMoney,
                    $desc . $withdrawMoney,
                    $desc . $withdrawMoney,
                    $desc . $withdrawMoney,
                    $desc . $withdrawMoney,
                    $desc . $withdrawMoney,
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
        $language = $this->request->param('language');
        $payment = Db::name('LcPayment')->field('*,bank as bank_zh_cn,bank_name as bank_name_zh_cn,min_recharge,max_recharge,min_withdrawals')->where(['show' => 1])->where("level <= " . $user['value'])->order("sort asc,id desc")->select();
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
                $list[$k]["min_recharge"] = $v["min_recharge"];
                $list[$k]["max_recharge"] = $v["max_recharge"];
                $list[$k]["min_withdrawals"] = $v["min_withdrawals"];
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
                        $list[$k]["name"] = $v["bank_" . $language];
                        $list[$k]["user"] = $v["bank_name_" . $language];
                        $list[$k]["account"] = $v["bank_account"];
                        break;
                    default:
                }
            }
        }
        $info = Db::name('lc_info')->find(1);
        $rate_usd = $info['rate_usd'];
        $recharge_range = $info['recharge_range'];
        $start_time = explode(' - ', $recharge_range)[0] ?? "00:00";
        $end_time = explode(' - ', $recharge_range)[1] ?? "23:59";

        $data = array(
            'money' => bcdiv($user['money'], $rate_usd, 3),
            'min_recharge' => $info['min_recharge'],
            'payment' => $list,
            'asset' => bcdiv($user['asset'], $rate_usd, 3),
            'kj_money' => $user['kj_money'],
            'start_time' => $start_time,
            'end_time' => $end_time,
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


    public function generateSignature(array $returnArray, string $md5key): string
    {

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
     * Describe:充值（这里改成在线支付）
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
        if (!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $money)) $this->error('请输入正确的金额');
        //充值提现时间为：：9：00——24：00
        // date_default_timezone_set("Asia/Shanghai");

        if (date('G') < 9) {
            //   $this->error("充值时间为：9：00——24：00");die;
        }
        //var_dump($this->userInfo);exit;
        $language = $params["language"];
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);

        //1分钟内提交不超过五次
        $recharge = Db::name('lc_recharge')->where('uid', $uid)->order('id desc')->limit(6)->select();
        if (count($recharge) > 5) {
            if (strtotime($recharge[4]['time']) > (time() - 60)) {
                $this->error(get_tip(229, $language));
            }
        }

        $paymentId = $params["id"];
        $info = Db::name('LcInfo')->find(1);
        if ($money < $info['min_recharge']) {
            $returnData = array(
                "$language" => Db::name('LcTips')->where(['id' => '140'])->value("$language") . $info['min_recharge']
            );
            $this->error($returnData);
        }
        $payment = Db::name('LcPayment')->field('*,bank as bank_zh_cn,bank_name as bank_name_zh_cn')->find($paymentId);

        if ($payment['min_recharge'] > $money) {
            if ($language == 'zh_cn') {
                $this->error('min：' . $money);
            } elseif ($language == 'zh_hk') {
                $this->error('Số lượng tối thiểu：' . $money);
            } elseif ($language == 'en_us') {
                $this->error('Minimum  recharge amount ' . $money);
            }
        }
        if ($payment['max_recharge'] < $money) {
            if ($language == 'zh_cn') {
                $this->error('max：' . $money);
            } elseif ($language == 'zh_hk') {
                $this->error('Số lượng tối đa：' . $money);
            } elseif ($language == 'en_us') {
                $this->error('Maximum recharge amount ' . $money);
            }
        }
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
                    $paymentArr["name"] = $payment["bank_" . $language] . "-" . $payment["bank_name_" . $language];
                    $paymentArr["bank"] = $payment["bank_" . $language];
                    $paymentArr["username"] = $payment["bank_name_" . $language];
                    $paymentArr["account"] = $payment["bank_account"];
                    break;
                default:
            }
        }
        //if ($this->user['auth'] != 1) $this->error(Db::name('LcTips')->field("$language")->find('142'), "", 405);
        $orderid = date('YmdHis') . rand(1, 999);
        $num11 = 0; // 
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
            'add_time' => time(),
            'time2' => '0000-00-00 00:00:00'
        );
        if ($payment["type"] == 4) {
            $add['type'] = $payment["bank_zh_cn"] . "-" . $payment["bank_name_zh_cn"];
            $add['type_zh_hk'] = $payment["bank_zh_hk"] . "-" . $payment["bank_name_zh_hk"];
            $add['type_en_us'] = $payment["bank_en_us"] . "-" . $payment["bank_name_en_us"];
            $add['username'] = $paymentArr['username'];
            $add['account'] = $paymentArr['account'];
            $add['bank'] = $paymentArr['bank'];
        } else {
            $add['type'] = $paymentArr['name'];
            $add['type_zh_hk'] = $paymentArr['name'];
            $add['type_en_us'] = $paymentArr['name'];
            $add['address'] = $paymentArr["address"];
        }

        // 如果是在线支付
        // if($paymentId == 22){
        //     // 需要判断上一笔订单支付是否完成，也需要判断是否为相同金额
        //     $re = Db::name('LcRecharge')->where([
        //         'uid' => $uid, 
        //         'pid' => $paymentId, 
        //         'money' => $money, 
        //         'status' => '0',
        //         ])->where('add_time', '>' , (time() - 300))->find();
        //     if($re && !empty($re['pay_url'])){
        //         $data = array(
        //             'payment' => $paymentArr,
        //             'orderId' => $re['id'],
        //         );
        //         $data['pay_url'] = $re['pay_url'];
        //         // 直接返回之前的
        //         $this->success('获取成功！', $data);
        //     }
        // }

        $re = Db::name('LcRecharge')->insertGetId($add);
        //如果是内部自动审核通过
        if ($this->user['is_sf'] == 1 || $this->user['is_sf'] == 2) {

            //   $this->autoSh($uid,$re);

        }
        $data = array(
            'payment' => $paymentArr,
            'orderId' => $re
        );
        $domain = request()->domain();
        // 判断是不是$paymentId == 22，如果是，则采用在线支付
        // if($paymentId == 22){
        //     $requestData = [
        //         'member_id'  => '10007',
        //         'code'  => '803',
        //         'order_id' => $orderid,
        //         'us_id' => $uid,
        //         'amount' => $add['money2'],
        //         'notify_url' => $domain. '/index/index/pay_notify',
        //         'type' => 'json',
        //     ];
        //     $requestData['amount'] = $requestData['amount'];
        //     $sign = $this->generateSignature($requestData, 'dieezvo6ewmade5l1nwbs48jjgo53aq7');
        //     $requestData['sign'] = $sign;
        //     $rel = httpRequest('https://vn168.xyzf888.com/Pay_Index.html', $requestData, 'POST', ['Content-type:application/x-www-form-urlencoded; charset=utf-8']);
        //     $rel = json_decode($rel, true);
        //     if($rel['status'] != 'success'){
        //         Db::name('LcRecharge')->where('id', $re)->delete();
        //         Log::error('支付拉单失败：'. json_encode($rel));
        //         $this->error("Thao tác thất bại ERR001");
        //     }
        //     Db::name('LcRecharge')->where(['uid' => $uid, 'id' => $re])->update(['pay_url'=> $rel['pay_url'],'yun_order_id' => $rel['order_no'], 'status'=> '0', 'address'=> '本单在线支付，请勿审核！']);
        //     $data['pay_url'] = $rel['pay_url'];
        // }

        if ($re) $this->success('获取成功！', $data);
        $this->error("Thao tác thất bại");
    }

    /*
        *充值自动审核
        *
       */
    public function autoSh($uid, $oid)
    {
        $recharge = Db::name('LcRecharge')->find($oid);
        if ($recharge && $recharge['status'] == 0 || $recharge['status'] == 3) {
            $money = $recharge['money'];
            $money2 = $recharge['money2'];
            $uid = $recharge['uid'];
            $type = $recharge['type'];

            $LcTips152 = Db::name('LcTips')->where(['id' => '152']);
            $LcTips153 = Db::name('LcTips')->where(['id' => '153']);

            if ($recharge['pid'] == 21) {
                $money = $money2;
            }
            addFinance($uid, $money, 1,
                $type . $LcTips152->value("zh_cn") . $money,
                $type . $LcTips152->value("zh_hk") . $money,
                $type . $LcTips152->value("en_us") . $money,
                $type . $LcTips152->value("th_th") . $money,
                $type . $LcTips152->value("vi_vn") . $money,
                $type . $LcTips152->value("ja_jp") . $money,
                $type . $LcTips152->value("ko_kr") . $money,
                $type . $LcTips152->value("ms_my") . $money,
                "", "", 1
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

            $memberId = setUserMember($uid, $user['value']);

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
            $top = $user['top'];
            $top2 = $user['top2'];
            $top3 = $user['top3'];
            //一级
            $member_rate = Db::name("LcUserMember")->where(['id' => $user['member']])->value("member_rate");

            setRechargeRebate1($uid, $money, $member_rate, '个人充值奖励');
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
        $language = $data['language'];
        // Log::record($data, 'log');

        $redisKey = 'LockKeyUserRechargeApply' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 5, 0)) {
            $this->error(Db::name('LcTips')->field("$language")->find('229'));
        }

        $ifc = Db::name('LcRecharge')->where(['uid' => $uid, 'status' => '0'])->find();
        if ($ifc) {
            $this->error(Db::name('LcTips')->field("$language")->find('228'));
        }

        // var_dump($data);exit;
        $update = array('status' => '0',
            'warn' => '0',
            'bank_name' => isset($data['bankName']) ? $data['bankName'] : '',
            'card_name' => isset($data['cardName']) ? $data['cardName'] : '',
            'card_no' => isset($data['cardNo']) ? $data['cardNo'] : '',
            'image' => $data['image']);
        $this->user = Db::name('LcUser')->find($uid);
        //如果是内部自动审核通过
        // if( $this->user['is_sf']==1|| $this->user['is_sf']==2 ){
        //     $update['status']=1;
        //     $this->autoSh($uid,$data['id']);
        // }
        $re = Db::name('LcRecharge')->where(['uid' => $uid, 'status' => 3, 'id' => $data['id']])->update($update);
        if ($re) $this->success('获取成功！');
        $this->error("Thao tác thất bại");
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
        $language = $data['language'];

        $ifc = Db::name('LcRecharge')->where(['uid' => $uid, 'status' => '0'])->find();
        if ($ifc) {
            $this->error(Db::name('LcTips')->field("$language")->find('228'));
        }

        $update = array('status' => '0',
            'warn' => '0',
            'reason' => '付款人：' . $data['name'] . '<br/>转账附言：' . $data['remark']);
        $this->user = Db::name('LcUser')->find($uid);
        //如果是内部自动审核通过
        if ($this->user['is_sf'] == 1 || $this->user['is_sf'] == 2) {
            $update['status'] = 1;


        }
        $re = Db::name('LcRecharge')->where(['uid' => $uid, 'status' => 3, 'id' => $data['id']])->update($update);
        if ($re) $this->success('获取成功！');
        $this->error("Thao tác thất bại");
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

        $redisKey = 'LockKeyUserSign' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 10, 0)) {

            $this->error(Db::name('LcTips')->field("$language")->find('229'));
        }

        // 获取签到次数和签到时间
        $user = Db::name('LcUser')->field("qiandao,qdnum,member, point_num,auth")->find($uid);

        if (!$user['auth']) $this->error(get_tip(237, $language));

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
                'title' => '挖矿日',
                'title_zh_hk' => '挖礦日',
                'title_en_us' => 'Mining Day',
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
                'zh_hk' => "簽到贈送積分",
                'en_us' => "Sign in and give away points",
                'th_th' => "签到赠送积分",
                'vi_vn' => "签到赠送积分",
                'ja_jp' => "签到赠送积分",
                'ko_kr' => "签到赠送积分",
                'ms_my' => "签到赠送积分",
                'time' => date('Y-m-d H:i:s'),
                'before' => $user['point_num']
            );
            $id = Db::name('LcPointRecord')->insertGetId($pointRecord);
            add_finance($uid, $num, 1,
                [
                    'zh_cn' => "签到赠送",
                    'zh_hk' => "簽到贈送",
                    'en_us' => "Sign in and give away points",
                    'th_th' => "签到赠送",
                    'vi_vn' => "签到赠送",
                    'ja_jp' => "签到赠送",
                    'ko_kr' => "签到赠送",
                    'ms_my' => "签到赠送",
                ],
                "", "", 31,
                2, $id
            );
            setNumber('LcUser', 'money', $num, 1, "id = {$uid}");
        }

        Db::name('LcUser')->where(['id' => $uid])->update(['qiandao' => date('Y-m-d H:i:s')]);
        Db::name("LcUserSignLog")->insert(['date' => date("Y-m-d"), 'uid' => $uid]);
        setNumber('LcUser', 'qdnum', 1, 1, "id=$uid");

        $this->success(Db::name('lc_tips')->find(214)[$language], ['type' => $type, 'num' => $num]);

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
        $language = $this->request->param('language');
        $list = Db::name('lc_msg')->order('id desc')->select();
        $ok_read_num = 0;
        foreach ($list as &$item) {
            $item['title'] = $item['title_' . $language];
            $item['content'] = $item['content_' . $language];
            if (Db::name('lc_msg_is')->where('uid', $uid)->where('mid', $item['id'])->find()) {
                $item['is_read'] = true;
                $ok_read_num++;
            } else {
                $item['is_read'] = false;
            }
        }
        $this->success("获取成功", ['list' => $list, 'ok_read_num' => $ok_read_num]);
        // $this->success("获取成功", ['list' => $list, 'ok_read_num' => count($msgtop)]);

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
        $language = $this->request->param('language');
        foreach ($list as &$item) {
            $item['title'] = $item['title_' . $language];
            $item['content'] = $item['content_' . $language];
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
        $language = $this->request->param('language');
        $notice['title'] = $notice['title_' . $language];
        $notice['content'] = $notice['content_' . $language];
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
        // $reason_id = $this->request->param('reason_id');
        $type = $this->request->param('type', 0);
        $item_id = $this->request->param('item_id', 0);
        //0=全部 1=充值记录 2=提现记录 3=途游宝 4=资产 5=余额
        switch ($type) {
            case 1:
                $where[] = ['reason_type', 'eq', 1];
                break;
            case 2:
                $where[] = ['reason_type', 'eq', 2];
                break;
            case 3:
                $where[] = ['reason_type', 'in', [12, 19, 20]];
                break;
            case 4:
                $where[] = ['reason_type', 'in', [1, 6, 18]];
                break;
            case 5:
                $where[] = ['reason_type', 'not in', [1, 17, 6, 18]];
                break;
            case 0:
                if ($item_id) {

                    $invest_income_ids = Db::name('lc_invest_list')
                        ->whereIn('iid', $item_id)
                        ->column('id');
                    $where[] = ['reason_type', 'in', [11]];
                    if ($invest_income_ids) {
                        $where[] = ['orderid', 'in', $invest_income_ids];
                    }
                }
                break;
        }
        // $reason = array(
        //     "1" => "充值",
        //     "2" => "提现",
        //     "3" => "赠送",
        //     "4" => "签到",
        //     "5" => "分享奖励",
        //     "6" => "购买商品",
        //     "7" => "红包",
        //     "8" => "奖励",
        //     "9" => "新人福利",
        //     "10" => "推荐",
        //     "11" => "收益",
        // );
        $date = $this->request->param('data', date('Y-m', time()));
        // var_dump($date);exit;
        $where[] = ['time', 'like', $date . '%'];

        $user = Db::name("LcUser")->find($uid);
        $where[] = ['uid', 'eq', $uid];
        // if ($reason_id) $where[] = ['reason_type', 'eq', "$reason_id"];
        $data = Db::name('LcFinance')->field("*,id,money,type,reason,before,time,remark,reason_type")->where($where)->order("id desc")->select();
        foreach ($data as &$item) {
            $item['zh_cn'] = $item[$language];
        }

        //总收入
        $total_income = Db::name('LcFinance')->field("*,id,money,type,reason,before,time,remark,reason_type")->where($where)->where('type', 1)->sum('money');
        //总支出
        $total_expend = Db::name('LcFinance')->field("*,id,money,type,reason,before,time,remark,reason_type")->where($where)->where('type', 2)->sum('money');

        $rate_usd = Db::name('lc_info')->find(1)['rate_usd'];

        foreach ($data as &$value) {
            $value['money'] = bcdiv($value['money'], $rate_usd, 2) . lang("text6");
        }
        $money = array_column($data, "money");
        $this->success("获取成功", ['list' => $data, 'asset' => $user['asset'], 'money' => $user['money'], 'username' => $user['name'] ?: $user['phone'], 'share_reward' => array_sum($money), 'total_income' => vnd_gsh(bcdiv($total_income, 1, 2)), 'total_expend' => vnd_gsh(bcdiv($total_expend, 1, 2))]);
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

        $ebao_total_income = Db::name('LcEbaoRecord')->where('uid', $uid)->where('title', 'like', '%收益%')->sum('money');
        $ebao_last_income = Db::name('LcEbaoRecord')->where('uid', $uid)->where('title', 'like', '%收益%')->whereTime('time', 'today')->sum('money');
        $data = array(
            "ebao" => $user['ebao'],
            'ebao_total_income' => $ebao_total_income,
            'ebao_last_income' => $ebao_last_income,
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


        // if(think\facade\){

        // }


        $redisKey = 'LockKeyUserAddEbao' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 10, 0)) {
            $this->error(Db::name('LcTips')->field("$language")->find('229'));
        }
        // var_dump($lock->_redis);
        // exit;
        // var_dump($lock);exit;
        // $redisLockTime = 60;
        // $result = Cache::store("redis")->setnx($redisKey,1);
        // if($result==false){
        //   if(Cache::store("redis")->ttl($redisKey)==-1){
        //       Cache::store("redis")->expire($redisKey,1);
        //     }
        //     $this->error(Db::name('LcTips')->field("$language")->find('229'));
        // }
        // Cache::store("redis")->setnx($redisKey,1,60);


        $this->user = Db::name('LcUser')->find($uid);
        $params = $this->request->param();
        $money = floatval($params["money"]);
        $language = $this->request->param('language');
        if (!isset($params['password']) || empty($params['password'])) {
            $this->error(get_tip(232, $language));
        }

        //一分钟内只能操作一次
        // $lastLog = Db::name('LcEbaoRecord')->where('uid', $uid)->order('id desc')->find();
        // if ($lastLog && strtotime($lastLog['time']) > (time() - 60)) {
        //     $this->error(get_tip(229, $language));
        // }

        //验证支付密码
        if (md5($params['password']) != $this->user['password2']) {
            $this->error(get_tip(213, $language));
        }

        if ($money <= 0) $this->error(get_tip(215, $language));
        if ($this->user['money'] <= 0) $this->error(get_tip(65, $language));

        //$language = $params["language"];
        //$language = 'en_us';
        if (!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $money)) $this->error(get_tip(215, $language));
        // 余额够不够
        if ($this->user['money'] < $money)
            $this->error(get_tip(65, $language));

        // 查询最低转入限制是否达标
        $reward = Db::name("LcReward")->find(1);

        if ($money < $reward['ebao_min']) {
            $min = [
                'zh_cn' => "转入金额没有达到途游宝最低限制金额：" . $reward['ebao_min'] . " USDT",
                'zh_hk' => "轉入金額沒有達到途遊寶最低限製金額：" . $reward['ebao_min'] . " USDT",
                'en_us' => "The transfer amount has not reached the minimum limit of Tuyoubao：" . $reward['ebao_min'] . " USDT",
            ];
            $this->error(array(
                'zh_cn' => $min[$language]
            ));
        } else if ($money > $reward['ebao_max']) {
            $max = [
                'zh_cn' => "转入金额超过途游宝最高限制金额：" . $reward['ebao_max'] . " USDT",
                'zh_hk' => "轉入金額超過途遊寶最高限製金額：" . $reward['ebao_max'] . " USDT",
                'en_us' => "The transfer amount exceeds the maximum limit on Tuyoubao：" . $reward['ebao_max'] . " USDT",
            ];
            $this->error(array(
                'zh_cn' => $max[$language]
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
            $LcTips73->value("zh_cn") . '《' . get_tip(218, 'zh_cn') . '》，' . $money,
            $LcTips73->value("zh_hk") . '《' . get_tip(218, 'zh_hk') . '》，' . $money,
            $LcTips73->value("en_us") . '《' . get_tip(218, 'en_us') . '》，' . $money,
            $LcTips73->value("th_th") . '《' . get_tip(218, $language) . '》，' . $money,
            $LcTips73->value("vi_vn") . '《' . get_tip(218, $language) . '》，' . $money,
            $LcTips73->value("ja_jp") . '《' . get_tip(218, $language) . '》，' . $money,
            $LcTips73->value("ko_kr") . '《' . get_tip(218, $language) . '》，' . $money,
            $LcTips73->value("ms_my") . '《' . get_tip(218, $language) . '》，' . $money,
            "", "", 19
        );
        setNumber('LcUser', 'money', $money, 2, "id = $uid");


        // 增加途游宝流水
        $ebaoRecord = array(
            'uid' => $uid,
            'money' => $money,
            'type' => 1,
            'title' => '途游宝转入 ' . $money,
            'title_zh_hk' => '途遊寶轉入 ' . $money,
            'title_en_us' => 'Tuyoubao transfer in ' . $money,
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
        if (!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $money)) $this->error('请输入正确的金额');

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
        $data = Db::name('LcEbaoRecord')->field("title as title_zh_cn,title_zh_hk,title_en_us,id,money,type,time")->where('title', 'notlike', '充值%奖励%')->where($where)->order("id desc")->select();
        foreach ($data as &$item) {
            $item['title'] = $item['title_' . $language];
        }
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
        $language = $params['language'];
        $money = floatval($params["money"]);
        if (!isset($params['password']) || empty($params['password'])) {
            $this->error(get_tip(232, $language));
        }


        $redisKey = 'LockKeyUserSubEbao' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 10, 0)) {
            $this->error(Db::name('LcTips')->field("$language")->find('229'));
        }

        //一分钟内只能操作一次
        // $lastLog = Db::name('LcEbaoRecord')->where('uid', $uid)->order('id desc')->find();
        // if ($lastLog && strtotime($lastLog['time']) > (time() - 60)) {
        //     $this->error(get_tip(229, $language));
        // }

        //验证支付密码
        if (md5($params['password']) != $this->user['password2']) {
            $this->error(get_tip(213, $language));
        }


        if ($money <= 0) $this->error(get_tip(215, $language));
        if ($this->user['ebao'] <= 0) $this->error(get_tip(65, $language));

        if (!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $money)) $this->error(get_tip(215, $language));
        if ($money < 0) {
            $this->error(get_tip(216, $language));
        }
        // 余额够不够
        if ($this->user['ebao'] < $money) $this->error(get_tip(65, $language));

        $LcTips73 = Db::name('LcTips')->where(['id' => '73']);


        addFinance($uid, $money, 1,
            $LcTips73->value("zh_cn") . '《' . get_tip(217, 'zh_cn') . '》，' . $money,
            $LcTips73->value("zh_hk") . '《' . get_tip(217, 'zh_hk') . '》，' . $money,
            $LcTips73->value("en_us") . '《' . get_tip(217, 'en_us') . '》，' . $money,
            $LcTips73->value("th_th") . '《' . get_tip(217, $language) . '》，' . $money,
            $LcTips73->value("vi_vn") . '《' . get_tip(217, $language) . '》，' . $money,
            $LcTips73->value("ja_jp") . '《' . get_tip(217, $language) . '》，' . $money,
            $LcTips73->value("ko_kr") . '《' . get_tip(217, $language) . '》，' . $money,
            $LcTips73->value("ms_my") . '《' . get_tip(217, $language) . '》，' . $money,
            "", "", 20
        );
        setNumber('LcUser', 'money', $money, 1, "id = $uid");


        // 增加途游宝流水
        $ebaoRecord = array(
            'uid' => $uid,
            'money' => $money,
            'type' => 2,
            'title' => '途游宝转出 ' . $money,
            'title_zh_hk' => '途遊寶轉出 ' . $money,
            'title_en_us' => 'Tuyoubao transfer out ' . $money,
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
        $language = $this->request->param('language');
        $uid = $this->userInfo['id'];
        $data = Db::name('LcMechinesFinance')->field("*,title title_zh_cn,id,amount money,type,add_time time")->where("uid = ${uid} ")->order("id desc")->select();
        foreach ($data as &$item) {
            $item['title'] = $item['title_' . $language];
        }
        $this->success("获取成功", ['list' => $data]);
    }

    public function identity($realname, $idcard)
    {
        $host = "https://zidv2.market.alicloudapi.com";
        $path = "/idcard/VerifyIdcardv2";
        $method = "GET";
        $appcode = "6212698ed424408cb6a282c97f783740";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "cardNo=$idcard&realName=" . urlencode($realname);
        $bodys = "";
        $url = $host . $path . "?" . $querys;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $result = curl_exec($curl);
        return $result;

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
    }

    public function setCard()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $name = $this->request->param('name');
        $cardNo = $this->request->param('cardNo');
        $cardFront = $this->request->param('cardFront');
        $cardBack = $this->request->param('cardBack');
        $type = $this->request->param('type');

        // 检查是否已完成认证
        $this->user = Db::name('LcUser')->find($uid);
        if ($this->user['auth'] == 1) {
            $this->error(array(
                'zh_cn' => lang("text5")
            ));
        }

        $info = Db::name('lc_certificate')->where('uid', $uid)->where('status', 'in', [0, 1])->find();
        if ($info) {
            $this->error(array(
                'zh_cn' => lang("text5")
            ));
        }

        $redisKey = 'LockKeyUserCostApply' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 10, 0)) {
            $this->error(Db::name('LcTips')->field("$language")->find('229'));
        }

        $cardNo = strtoupper($cardNo);
        // 检查该身份证是否已认证过
        $iscount = Db::name('LcUser')->where(["idcard" => "{$cardNo}"])->count();
        if ($iscount >= 1) {
            $this->error(lang("text4"));
        }

        // 请求认证（天眼数聚）
        // $host = "https://eid.shumaidata.com";
        // $path = "/eid/check";
        // $method = "POST";
        // // $appcode = "aff7cfa728344dab9180c500d73e07ae";
        // $appcode = "6212698ed424408cb6a282c97f783740";
        // $headers = array();
        // array_push($headers, "Authorization:APPCODE " . $appcode);
        // $querys = "idcard=" . $cardNo . "&name=" . urlencode($name);
        // $bodys = "";
        // $url = $host . $path . "?" . $querys;

        // $curl = curl_init();
        // curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        // curl_setopt($curl, CURLOPT_URL, $url);
        // curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        // curl_setopt($curl, CURLOPT_FAILONERROR, false);
        // curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // //设定返回信息中是否包含响应信息头，启用时会将头文件的信息作为数据流输出，true 表示输出信息头, false表示不输出信息头
        // //如果需要将字符串转成json，请将 CURLOPT_HEADER 设置成 false
        // curl_setopt($curl, CURLOPT_HEADER, false);
        // if (1 == strpos("$" . $host, "https://")) {
        //     curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        //     curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        // }
        // $result = curl_exec($curl);

        // // 开始解析数据
        // $resultObj = json_decode($result);
        // if (0 != $resultObj->code) {
        //     // 错误
        //     $this->error(array(
        //         'zh_cn' => $resultObj->message
        //     ));
        // }
        // $result = json_decode(json_encode($resultObj->result), true);
        // if ($result['res'] == 2) {
        //     $this->error('姓名身份证不匹配');
        // }

        //  $checkRes = (new Util())->validation_filter_id_card($cardNo);
        //  if (!$checkRes) {
        //      $this->error('身份证号异常，请检查');
        //  }
        //  $pattern = '/^[\x{4e00}-\x{9fa5}]{2,4}$/u';
        //  if (!preg_match($pattern, $name)) {
        //      $this->error('非法姓名');
        //  }

        //  $result = $this->identity($name, $cardNo);
        //  $result = json_decode($result, true);
        // if ($result['result']['isok'] != 'true') {
        //     $this->error('身份证姓名不匹配，请检查');
        // }

        // Log::record($resultObj, 'error');
        // Log::record($result, 'error');
        // var_dump($cardFront);
        // var_dump($cardBack);

        Db::name('lc_certificate')->insert([
            'uid' => $uid,
            'name' => $name,
            'type' => $type,
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
        $iscount = Db::name('LcUser')->where(["idcard" => "{$cardNo}"])->count();
        if ($iscount >= 1) {
            $this->error(array(
                'zh_cn' => "1 CCCD chỉ có thể xác minh 1 tài khoản！"
            ));
        }

        // 请求认证
        $host = "https://eid.shumaidata.com";
        $path = "/eid/check";
        $method = "POST";
        // $appcode = "aff7cfa728344dab9180c500d73e07ae";
        $appcode = "fe1e1fb261c14ac68244483b1938e8d8";
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

        $rsd = Db::name('LcRecharge')->where(['uid' => $uid, 'type' => '实名奖励'])->find();
        if (!$rsd) {
            $dd = Db::name('LcReward')->where(['id' => 1])->find();

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
        if (stripos($avatar, 'http') !== false) {
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
        $password = $this->request->param('password');
        $language = $this->request->param('language');
        if ($amount <= 0) $this->error('请输入正确的金额');
        if (!isset($password) || empty($password)) {
            $this->error(get_tip(232, $language));
        }


        $redisKey = 'LockKeykjOut' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 60, 0)) {
            $this->error(Db::name('LcTips')->field("$language")->find('229'));
        }

        if (!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $amount)) $this->error('请输入正确的金额');

        //一分钟内只能操作一次
        $lastLog = Db::name('LcMechinesFinance')->where('uid', $uid)->where('type', 2)->where('title', 'MMH兑换余额')->order('id desc')->find();
        if ($lastLog && strtotime($lastLog['add_time']) > (time() - 60)) {
            $this->error(get_tip(229, $language));
        }
        // 判断矿机余额是否足够
        $this->user = Db::name('LcUser')->find($uid);
        //验证支付密码
        if (md5($password) != $this->user['password2']) {
            $this->error(get_tip(213, $language));
        }
        if ($this->user['kj_money'] < $amount) {
            $this->error(array(
                'zh_cn' => "MMH余额不足！！"
            ));
        }
        if ($this->user['kj_money'] <= 0) $this->error('余额不足');

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
            '《MMH兑换余额》' . $income,
            '《MMH兑换余额》' . $income,
            '《MMH兑换余额》' . $income,
            '《MMH兑换余额》' . $income,
            '《MMH兑换余额》' . $income,
            '《MMH兑换余额》' . $income,
            '《MMH兑换余额》' . $income,
            '《MMH兑换余额》' . $income,
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
            // 'auto_out_ebao' => $this->user['auto_out_ebao'],
            // 'in_ebao_start' => $this->user['in_ebao_start'],
            // 'in_ebao_end' => $this->user['in_ebao_end'],
            // 'out_ebao_start' => $this->user['out_ebao_start'],
            // 'out_ebao_end' => $this->user['out_ebao_end'],
        );

        Db::name('lc_user')->update($data);
        $data['ebao'] = $this->user['ebao'];
        $data['ebao_total_income'] = Db::name('LcEbaoRecord')->where('uid', $uid)->where('title', 'like', '%收益%')->sum('money');

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
        $this->error("暂未开通转账功能");
        die;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $amount = floatval($this->request->param('amount'));
        $account = intval($this->request->param('account'));
        $password = $this->request->param('password');
        $language = $this->request->param('language');
        if ($amount <= 0) $this->error('请输入正确的金额');
        if (!isset($password) || empty($password)) {
            $this->error(get_tip(232, $language));
        }

        if (!preg_match('/^[1-9]\d*(\.\d{1,2})?$/', $amount)) $this->error(get_tip(215, $language));
        // 查询目标账户是否存在
        //$aes = new Aes();
        //$account = $aes->encrypt($account);
        if ($account < 111111 || $account > 999999) {
            $this->error(get_tip(223, $language));
        }
        $tUser = Db::name("LcUser")->where('mid', $account)->find();
        if (!$tUser) {
            $this->error(array(
                'zh_cn' => get_tip(224, $language)
            ));
        }

        //一分钟内只能操作一次
        $lastLog = Db::name('LcMechinesFinance')->where('uid', $uid)->where('type', 2)->where('title', 'MMH转账支出')->order('id desc')->find();
        if ($lastLog && strtotime($lastLog['add_time']) > (time() - 60)) {
            $this->error(get_tip(229, $language));
        }
        // 判断矿机余额是否足够
        $this->user = Db::name('LcUser')->find($uid);
        if ($account == $this->user['mid']) {
            $this->error(get_tip(225, $language));
        }
        //验证支付密码
        if (md5($password) != $this->user['password2']) {
            $this->error(get_tip(213, $language));
        }


        if ($this->user['kj_money'] < $amount) {
            $this->error(array(
                'zh_cn' => get_tip(225, $language)
            ));
        }
        // if($this->user['kj_money']<=0) $this->error('余额不足');

        // 扣除矿币数量
        Db::name('LcUser')->where(['id' => $uid])->setDec('kj_money', $amount);
        // 扣除矿机流水
        $finance = array(
            'uid' => $uid,
            'type' => 2,
            'title' => "MMH转账支出",
            'title_zh_hk' => 'MMH轉賬支出',
            'title_en_us' => 'MMH transfer expenses',
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
            'title_zh_hk' => '收到MMH轉賬',
            'title_en_us' => 'Received MMH transfer',
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
            ->leftJoin('lc_item m', "m.id = i.pid")
            ->field("i.* , u.name userName, u.phone,i.pid as id,m.img")
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
            ->leftJoin('lc_item m', "m.id = i.pid")
            ->field("u.phone, i.money, il.time, i.zh_cn, il.money as reward,i.pid as id,m.img")
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
        $xjlj_money = Db::name("LcUser")->where("recom_id", $uid)->where('is_sf', 0)->sum("czmoney");

        $xjlj_money += Db::name("LcUser")->where("top2", $uid)->where('is_sf', 0)->sum("czmoney");
        $xjlj_money += Db::name("LcUser")->where("top3", $uid)->where('is_sf', 0)->sum("czmoney");


        $memberList = Db::name('LcUser')->field('id, phone, top,czmoney,name,time, auth')->select();

        $itemList = $this->get_downline_list($memberList, $uid);
        //   var_dump($itemList);die;
        $all_czmoney = 0;

        $is_sf = Db::name('LcUser')->where(['id' => $uid])->value('is_sf');
        //   var_dump($this->userInfo['czmoney']);
        //   var_dump($this->userInfo['is_sf']);die;
        if ($is_sf == 0) {
            //   $all_czmoney=$this->userInfo['czmoney'];
            $all_czmoney = Db::name('LcUser')->where(['id' => $uid])->value('czmoney');
        }
        foreach ($itemList as $k => $v) {
            $all_czmoney += $v['czmoney'];

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
            'czmoney' => sprintf('%.2f', $all_czmoney),
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
        $language = $this->request->param('language');
        $list = Db::name("LcEbaoProductRecord")->where("uid = {$uid}")->field('*,title as title_zh_cn')->order("id desc")->select();
        foreach ($list as &$item) {
            $item['title'] = $item['title_' . $language];
            $item['day_rate'] = Db::name('lc_ebao_product')->find($item['product_id'])['day_rate'];
        }
        $this->success("操作成功", array(
            'list' => $list
        ));
    }


}
