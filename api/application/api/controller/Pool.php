<?php

namespace app\api\controller;

use library\Controller;
use think\Db;

class Pool extends Controller
{
    //奖池列表
    public function lists()
    {
        $list = Db::name('lc_reward_pool')->where('status', 1)->select();
        $this->success('获取成功', $list);
    }
    
    //最近一期中奖用户
    public function history_lottery()
    {
        $list = Db::name('lc_reward_pool_period p')
            ->join('lc_user u', 'p.lottery_uid = u.id')
            ->where('p.status', 2)
            ->field('p.sn,u.phone')
            ->order('p.sn desc')
            ->limit(5)
            ->select();
        $aes = new Aes();
        foreach ($list as &$item) {
            $item['msg'] = '尾号'.substr($aes->decrypt($item['phone']), -4).'获得本期幸运用户';
        }
        // $info = Db::name('lc_reward_pool_period')->where('status', 2)->order('id desc')->limit(5)->select();
        // $user = Db::name('lc_user')->find($info['lottery_uid']);
        // $aes = new Aes();
        // $mobile = $aes->decrypt($user['phone']);
        // $data['msg'] = '尾号'.substr($mobile, -4).'用户已兑换成功';
        $this->success('获取成功', $list);
    }
    
    //全部中奖用户
    public function all_history_lottery()
    {
        $page = $this->request->param('page', 1);
        $size = $this->request->param('size', 10);
        $count = Db::name('lc_reward_pool_period p')->join('lc_user u', 'p.lottery_uid = u.id')->where('p.status', 2)->count();
        $list = Db::name('lc_reward_pool_period p')
            ->join('lc_user u', 'p.lottery_uid = u.id')
            ->where('p.status', 2)
            ->page($page,$size)
            ->field('p.sn,u.phone')
            ->order('p.sn desc')
            ->select();
        $aes = new Aes();
        foreach ($list as &$item) {
            $item['msg'] = '尾号'.substr($aes->decrypt($item['phone']), -4).'获得本期幸运用户';
        }
        $data = [
            'page' => $page,
            'size' => $size,
            'count' => $count,
            'list' => $list
        ];
        
        $this->success('获取成功', $data);
    }
    
    //投注记录
    public function join_list()
    {
        // $uid = 38724;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $pool_id = $this->request->param('pool_id');
        $page = $this->request->param('page', 1);
        $size = $this->request->param('size', 10);
        $count = Db::name('lc_reward_pool_log l')->join('lc_reward_pool_period p', 'l.period_id = p.id')->where('l.uid', $uid)->count();
        $list = Db::name('lc_reward_pool_log l')->join('lc_reward_pool_period p', 'l.period_id = p.id')
            ->field('p.sn,l.score,l.createtime')
            ->order('createtime desc')
            ->where('l.uid', $uid)->page($page,$size)->select();
        $data = [
            'page' => $page,
            'size' => $size,
            'count' => $count,
            'list' => $list
        ];
        
        $this->success('获取成功', $data);
    }
    
    //奖池期数
    public function periods()
    {
        $pool_id = $this->request->param('pool_id');
        $page = $this->request->param('page', 1);
        $size = $this->request->param('size', 10);
        $count = Db::name('lc_reward_pool_period')->where('pool_id', $pool_id)->count();
        $list = Db::name('lc_reward_pool_period p')
            ->join('lc_reward_pool r', 'p.pool_id = r.id')
            ->where('pool_id', $pool_id)
            ->field('p.id,r.id as pool_id,r.name,r.money,r.score,p.quota')
            ->order('sn desc')
            ->select();
        foreach ($list as &$item) {
            //累计投注积分
            $total_score = Db::name('lc_reward_pool_log')->where('period_id', $item['id'])->sum('score');
            $item['rate'] = bcdiv($total_score*100,$item['quota'], 2);
        }
        
        $data = [
            'page' => $page,
            'size' => $size,
            'count' => $count,
            'list' => $list
        ];
        $this->success('获取成功', $data);
    }
    
    public function time()
    {
        $pool_id= $this->request->param('pool_id');
        $info = Db::name('lc_reward_pool_period')->where('pool_id', $pool_id)->order('id desc')->find();
        if ($info['status'] == 1) {
            $differ_time = strtotime($info['end_time']) + 60 - time();
            $this->success('获取成功', ['differ_second' => $differ_time]);
        } else {
            $this->success('获取成功', ['differ_second' => 0]);
        }
    }
    
    public function lottery()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $pool_id= $this->request->param('pool_id', 1);
        $info = Db::name('lc_reward_pool_period')->where('pool_id', $pool_id)->where('status', 2)->order('id desc')->find();
        
        if ($uid == $info['lottery_uid']) {
            $data = ['status' => 1, 'msg' => '恭喜您'.$info['sn'].'期中奖获得'.$info['money'].'元，现已返还到您账户余额', 'data' => $info];
        } else {
            $data = ['status' => 0, 'msg' => '很遗憾本期未中奖，再接再厉', 'data' => $info];
        }
        $this->success('获取成功',$data);
    }
    
    public function percent()
    {
        $pool_id= $this->request->param('pool_id');
        $info = Db::name('lc_reward_pool_period')->where('pool_id', $pool_id)->order('id desc')->find();
        if (empty($info)) {
        $this->success('获取成功', ['rate' => 0]);
        }
        if ($info['status'] > 0) {
            $rate = 100;
        } else {
            $total_score = Db::name('lc_reward_pool_log')->where('period_id', $info['id'])->sum('score');
            $rate = bcdiv($total_score*100, $info['quota'], 2);
        }
        $this->success('获取成功', ['rate' => $rate]);
    }
    
    //参与奖池
    public function join()
    {
        $pool_id= $this->request->param('pool_id');
        // $score = $this->request->param('score');
        
        // if (!$info = Db::name('lc_reward_pool_period')->find($id)) {
        //     $this->error('期号不存在');
        // } elseif ($info['status'] > 0) {
        //     $this->error('当期已结束');
        // }
        
        // $uid = 38724;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user = Db::name('lc_user')->find($uid);
        $pool = Db::name('lc_reward_pool')->find($pool_id);
        
        if ($user['point_num'] < $pool['score']) {
            $this->error('积分不足');
        }
        
        //是否购买过产品
        if (!Db::name('lc_invest')->where('uid', $uid)->find()) {
            $this->error('未参与购买产品，暂未获得参与资格！');
        }
        
        $redisKey = 'LockKeyUserItemApply'.$uid;
        $lock = new \app\api\util\RedisLock();
        if(!$lock->lock($redisKey,10,0)){
            $this->error("请勿重复提交,稍后重试");
        }
        
        // if ($score < $pool['score']) {
        //     $this->error('最低投入'.$pool['score'].'积分');
        // }
        
        //当前期
        $info = Db::name('lc_reward_pool_period')->where('pool_id', $pool_id)->order('id desc')->find();
        if ($info['status'] > 0) {
            $differ_time = strtotime($info['end_time']) + 60 - time();
            $this->success('获取成功', ['differ_time' => $differ_time]);
        }
        
        //生成参与记录
        Db::name('lc_reward_pool_log')->insert([
            'period_id' => $info['id'],
            'uid' => $uid,
            'score' => $pool['score'],
            'createtime' => date('Y-m-d H:i:s', time())
        ]);
        //积分明细
        Db::name('lc_point_record')->insert([
            'uid' => $uid,
            'num' => $pool['score'],
            'type' => 2,
            'zh_cn' => '参与奖池抽奖',
            'zh_hk' => '參與獎池抽獎',
            'en_us' => 'Participate in the prize pool draw',
            'th_th' => '参与奖池抽奖',
            'vi_vn' => '参与奖池抽奖',
            'ja_jp' => '参与奖池抽奖',
            'ko_kr' => '参与奖池抽奖',
            'ms_my' => '参与奖池抽奖',
            'before' => $user['point_num'],
            'time' => date('Y-m-d H:i:s', time()),
            'reason' => '',
            'remark' => ''
        ]);
        //扣除积分
        Db::name('lc_user')->where('id', $uid)->update(['point_num' => bcsub($user['point_num'], $pool['score'])]);
        
        //统计当期已投数量
        $total_quota = Db::name('lc_reward_pool_log')->where('period_id', $info['id'])->sum('score');
        if ($total_quota >= $info['quota']) {
            Db::name('lc_reward_pool_period')->where('id', $info['id'])->update(['status' => 1, 'end_time' => date('Y-m-d H:i:s', time())]);
        }
        
        $this->success('成功参与');
    }
    
    
    //弹窗中奖信息
    public function popup()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $data = Db::name('lc_pool_lottery_msg')->where('uid', $uid)->where('status', 0)->find();
        $this->success('获取成功', $data);
    }
    
    //确认中奖弹窗
    public function confirm()
    {
        $id = $this->request->param('id');
        Db::name('lc_pool_lottery_msg')->where('id', $id)->update(['status' => 1]);
        $this->success('操作成功');
    }
    
    
    
    
    
    
}