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


use library\Controller;
use Endroid\QrCode\QrCode;
use think\Db;
use library\File;
use think\facade\Session;
use think\facade\Cache;
use think\facade\Log;
use library\tools\Data;
use think\Image;

/**
 * 首页
 * Class Index
 * @package app\index\controller
 */
class Task extends Controller
{
    //邀请任务
    public function invite()
    {
        // $uid = 38718;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $userinfo = Db::name('lc_user')->find($uid);
        $language = $this->request->param('language');
        $list = Db::name('lc_task_invite')->where('status', 1)->field('*,name as name_zh_cn')->order('sort asc')->select();
        //当天邀请并投资用户数量
        // $inviteNum = Db::name('lc_invest i')->join('lc_user u', 'i.uid = u.id')->where('top', $uid)->where("to_days(i.time) = to_days(now())")->where("to_days(u.time) = to_days(now())")->count();
        $inviteNum = Db::name('lc_user')->where('auth', 1)->where('top', $uid)->where("to_days(time) = to_days(now())")->where("to_days(auth_time) = to_days(now())")->count();
        foreach ($list as &$item) {
            $item['name'] = $item['name_'.$language];
            if ($inviteNum >= $item['num']) {
                //是否已经领取过当日奖励
                $reward = Db::name('lc_task_reward')->where('uid', $uid)->where('type', 'invite')->where('task_id', $item['id'])->where("to_days(time) = to_days(now())")->find();
                if ($reward) {
                    $item['status'] = 2;
                } else {
                    $item['status'] = 1;
                }
            } else {
                $item['status'] = 0;
            }
            $item['cur_invite_num'] = $inviteNum;
        }
        $this->success('获取成功', $list);
    }
    
    //充值任务
    public function recharge()
    {
        // $uid = 38718;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $userinfo = Db::name('lc_user')->find($uid);
        $language = $this->request->param('language');
        $list = Db::name('lc_task_recharge')->where('status', 1)->field('*,name as name_zh_cn')->order('sort asc')->select();
        $rechargeNum = Db::name('lc_recharge')->where('uid', $uid)->where('status', 1)->where("to_days(time) = to_days(now())")->sum('money');
        foreach ($list as &$item) {
            $item['name'] = $item['name_'.$language];
            if ($rechargeNum >= $item['money']) {
                //是否已经领取过当日奖励
                $reward = Db::name('lc_task_reward')->where('uid', $uid)->where('type', 'recharge')->where('task_id', $item['id'])->where("to_days(time) = to_days(now())")->find();
                if ($reward) {
                    $item['status'] = 2;
                } else {
                    $item['status'] = 1;
                }
            } else {
                $item['status'] = 0;
            }
        }
        $this->success('获取成功', $list);
    }
    
    //投资任务
    public function invest()
    {
        // $uid = 38718;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $userinfo = Db::name('lc_user')->find($uid);
        $language = $this->request->param('language');
        $list = Db::name('lc_task_invest')->where('status', 1)->field('*,name as name_zh_cn')->order('sort asc')->select();
        $investNum = Db::name('lc_invest')->where('uid', $uid)->where("to_days(time) = to_days(now())")->count();
        foreach ($list as &$item) {
            $item['name'] = $item['name_'.$language];
            if ($investNum >= $item['num']) {
                //是否已经领取过当日奖励
                $reward = Db::name('lc_task_reward')->where('uid', $uid)->where('type', 'invest')->where('task_id', $item['id'])->where("to_days(time) = to_days(now())")->find();
                if ($reward) {
                    $item['status'] = 2;
                } else {
                    $item['status'] = 1;
                }
            } else {
                $item['status'] = 0;
            }
        }
        $this->success('获取成功', $list);
    }
    
    //领取邀请奖励
    public function reward()
    {
        // $uid = 38718;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user = Db::name('lc_user')->find($uid);
        $language = $this->request->param('language');
        $task_id = $this->request->param('task_id');
        $type = $this->request->param('type');
        
        if (!in_array($type, [0,1,2])) {
                $this->error(Db::name('lc_tips')->find(236)[$language]);
        }
        $table = ['LcTaskInvite', 'LcTaskRecharge', 'LcTaskInvest'];
        $arr = ['invite','recharge','invest'];
        if (!$task = Db::name($table[$type])->find($task_id)) {
                $this->error(Db::name('lc_tips')->find(235)[$language]);
        }
        
        
        if ($type == 0) {
            //今日邀请并投资人数
            // $inviteNum = Db::name('lc_invest i')->join('lc_user u', 'i.uid = u.id')->where('top', $uid)->where("to_days(i.time) = to_days(now())")->where("to_days(u.time) = to_days(now())")->count();
            $inviteNum = Db::name('lc_user')->where('auth', 1)->where('top', $uid)->where("to_days(time) = to_days(now())")->where("to_days(auth_time) = to_days(now())")->count();
            if ($inviteNum < $task['num']) {
                $this->error(Db::name('lc_tips')->find(233)[$language]);
            }
            $reward = Db::name('lc_task_reward')->where('uid', $uid)->where('type', 'invite')->where('task_id', $task['id'])->where("to_days(time) = to_days(now())")->find();
            if ($reward) {
                $this->error(Db::name('lc_tips')->find(234)[$language]);
            }
        } elseif ($type == 1) {
            //是否已经领取过当日奖励
            $rechargeNum = Db::name('lc_recharge')->where('uid', $uid)->where('status', 1)->where("to_days(time) = to_days(now())")->sum('money');
            if ($rechargeNum < $task['money']) {
                $this->error(Db::name('lc_tips')->find(233)[$language]);
            }
            $reward = Db::name('lc_task_reward')->where('uid', $uid)->where('type', 'recharge')->where('task_id', $task['id'])->where("to_days(time) = to_days(now())")->find();
            if ($reward) {
                $this->error(Db::name('lc_tips')->find(234)[$language]);
            }
        } elseif ($type == 2) {
            $investNum = Db::name('lc_invest')->where('uid', $uid)->where("to_days(time) = to_days(now())")->count();
            if ($investNum < $task['num']) {
                $this->error(Db::name('lc_tips')->find(233)[$language]);
            }
            $reward = Db::name('lc_task_reward')->where('uid', $uid)->where('type', 'invest')->where('task_id', $task['id'])->where("to_days(time) = to_days(now())")->find();
            if ($reward) {
                $this->error(Db::name('lc_tips')->find(234)[$language]);
            }
        }
        
        
        //赠送对应奖励
        $reward = $task['reward'];
        
        Db::name('lc_finance')->insert([
            'uid' => $uid,
            'money' => $reward,
            'type' => 1,
            'zh_cn' => $task['name'],
            'zh_hk' => $task['name_zh_hk'],
            'en_us' => $task['name_en_us'],
            'before' => $user['money'],
            'time' => date('Y-m-d H:i:s', time()),
            'reason_type' => 16,
            'after_money' => bcadd($user['money'], $reward, 2),
            'after_asset' => $user['asset'],
            'before_asset' => $user['asset']
        ]);
        
        //增加余额
        Db::name('lc_user')->where('id', $uid)->update(['money' => bcadd($user['money'], $reward, 2)]);
        
        //奖励记录
        Db::name('lc_task_reward')->insert([
            'uid' =>$uid,
            'name' => $task['name'],
            'type' => $arr[$type],
            'reward' => $reward,
            'task_id' => $task['id'],
            'time' => date('Y-m-d H:i:s', time())
        ]);
        
        $this->success('领取成功');
    }
}