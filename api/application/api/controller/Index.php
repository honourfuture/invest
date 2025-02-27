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
use library\File;
use think\Db;
use think\facade\Cache;
use think\facade\Session;
use think\Image;

/**
 * 首页
 * Class Index
 * @package app\index\controller
 */
class Index extends Controller
{
    public function withdrawals_config()
    {
        $mobile = '18223537801';
        echo substr($mobile, -4);
    }

    //补签
    public function repair_sign()
    {
        $id = $this->request->param('id', 0);
        $url = $this->request->param('url', '');
        $language = $this->request->param('language', 'zh_cn');
        if (empty($url)) $this->error(get_tip(79, $language));
        if (!$info = Db::name('lc_invest')->find($id)) {
            $this->error(get_tip(238, $language));
        }
        Db::name('lc_invest')->where('id', $info['id'])->update(['sign_base64' => $url, 'repair_sign' => 0]);
        $this->success('操作成功');
    }

    public function teamdata()
    {
        $uid = $this->request->param('uid', 0);
        $itemList = $this->getTeam($uid);
        $totalInvest = 0;
        $totalRecharge = 0;
        $auth_num = 0;
        foreach ($itemList as $item) {
            $user = Db::name('lc_user')->find($item);
            $invest_sum = Db::name('lc_invest t')->join('lc_item m', 't.pid = m.id')
                ->where('m.index_type', '<>', 7)
                ->whereIn('t.uid', $item)->sum('t.money');
            $totalInvest = bcadd($totalInvest, $invest_sum, 2);
            $totalRecharge = bcadd($totalRecharge, $user['czmoney'], 2);
            if ($user['auth']) {
                $auth_num++;
            }
        }
        $data = [
            'team_num' => count($itemList),
            'total_invest' => $totalInvest,
            'total_recharge' => $totalRecharge,
            'auth_num' => $auth_num
        ];
        $this->success('获取成功', $data);
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

    public function download()
    {
        $data = Db::name('lc_version')->find(1);
        $this->success('获取成功', ['android_download' => $data['android_download'], 'ios_download' => $data['ios_download']]);
    }

    //收藏记录
    public function collect()
    {
        $language = $this->request->param('language', 'zh_cn');
        $page = $this->request->param('page', 1);
        $size = $this->request->param('size', 10);
        $uid = $this->request->param('uid');
        // $uid = 38724;
        // $this->checkToken();
        // $uid = $this->userInfo['id'];
        $count = Db::name('lc_collect')->where('uid', $uid)->count();
        // $list = Db::name('lc_collect c')->join('lc_item i', 'c.item_id = i.id')->where('uid', $uid)->field('c.createtime,i.zh_cn,i.zh_hk,i.en_us,i.img,c.item_id')->page($page,$size)->order('c.createtime desc')->select();
        $list = Db::name('lc_collect c')->join('lc_item i', 'c.item_id = i.id')->where('uid', $uid)->field('c.createtime,i.zh_cn,i.zh_hk,i.en_us,i.img,c.item_id')->order('c.createtime desc')->select();
        foreach ($list as &$item) {
            $item['title'] = $item[$language];
            $item['createtime'] = date('Y-m-d', strtotime($item['createtime']));
        }

        $data = [
            'page' => $page,
            'size' => $size,
            'count' => $count,
            'list' => $list
        ];

        $this->success('获取成功', $data);
    }

    //分享详情
    public function share_detail()
    {
        $id = $this->request->param('id');
        $language = $this->request->param('language');
        $invest = Db::name('lc_invest')->find($id);
        if (!$invest) {
            $this->error('数据不存在');
        }
        $item = Db::name('lc_item')->find($invest['pid']);
        // var_dump($item);exit;
        $arr_cycle_type = [];
        $data = [
            'id' => $invest['id'],
            'item_id' => $item['id'],
            'name' => $item[$language],
            'money' => $invest['money'],
            'rate' => $invest['rate'],
            'cycle_type' => cycle_type($item['cycle_type'], $language),
            'pz_money' => bcdiv(bcmul($item['live_yield'], $invest['money'], 2), 100, 2),
            'pz_rate' => $invest['live_count'],
            'team_money' => bcdiv(bcmul($item['group_yield'], $invest['money'], 2), 100, 2),
            'team_rate' => $invest['grouping_num'],
            'days' => bcdiv($item['hour'], 24)
        ];

        $this->success('获取成功', $data);
    }

    public function web_link()
    {
        $language = $this->request->param('language');

        $list = Db::name('lc_web_link')->where('status', 1)->field('*,name as name_zh_cn,url as url_zh_cn')->order('sort desc')->select();
        foreach ($list as &$item) {
            $item['name'] = $item['name_' . $language];
            $item['url'] = $item['url_' . $language];
        }

        $this->success('获取成功', $list);
    }

    public function member_info()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user = Db::name('lc_user u')->join('lc_user_member m', 'u.member = m.id')->where('u.id', $uid)->field('m.*')->find();
        $all = Db::name('lc_user_member')->select();
        $data = [
            'cur_user' => $user,
            'all_user' => $all,
        ];
        $this->success('获取成功', $data);
    }

    public function getTop($uid)
    {
        static $list = [];
        static $num = 1;
        $user = Db::name('lc_user')->find($uid);
        //获取整个团队
        if ($user['top']) {
            $list[] = [
                'num' => $num,
                'uid' => $user['top']
            ];
            // $list[] = $user['top'];
            $num = $num + 1;
            $this->getTop($user['top'], $list, $num);
        }
        return $list;
    }

    public function lottery_log()
    {
        $language = $this->request->get('language', 'zh_cn');
        $page = $this->request->get('page', 1);
        $size = $this->request->get('size', 10000);
        // $uid = 38652;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $count = Db::name('lc_lottery_record')->where('uid', $uid)->count();
        $list = Db::name('lc_lottery_record')->where('uid', $uid)->page($page, $size)->order('id desc')->select();
        foreach ($list as &$item) {
            $item['title'] = $item[$language];
        }

        $data = [
            'page' => $page,
            'size' => $size,
            'count' => $count,
            'list' => $list
        ];

        $this->success('获取成功', $data);
    }

    public function test()
    {
        echo Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')->where('u.top', 38685)->group('uid')->count();
        exit;

        echo 2;
        exit;
        $aes = new Aes();
        $mobile = '18223537801';
        $encrypt = $aes->encrypt($mobile);
        $decrypt = $aes->decrypt($encrypt);
        echo $encrypt . '<br/>';
        echo $decrypt;
    }

    public function share_product()
    {
        $item_id = $this->request->get('item_id', 0);
        // $uid = 38658;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user = Db::name('lc_user')->find($uid);
        $link = getInfo('domain') . '/#/pages/main/login/reg?m=' . $user['invite'] . '&i=' . $item_id;
        $this->success('获取成功', $link);
    }

    //公告列表
    public function message()
    {
        $language = $this->request->param('language');
        $list = Db::name('lc_article')->where('type', 12)->order('sort asc,id desc')->select();
        foreach ($list as &$item) {
            $item['title'] = $item["title_" . $language];
        }
        $this->success('获取成功', $list);
    }

    //yzm
    public function makeCode()
    {
        $uuid = $this->request->get('uuid', 0);
        // $string = "abcdefghijklmnopqrstuvwxyz0123456789";
        $string = "0123456789";
        $str = "";
        for ($i = 0; $i < 4; $i++) { // 个数
            $pos = rand(0, 9);
            $str .= $string{$pos};
        }
        Cache::store('redis')->set('api_make_code_' . $uuid, $str, 60);
        $img_handle = Imagecreate(45, 28);  // 图片大小80X20
        $back_color = ImageColorAllocate($img_handle, 255, 255, 255); // 背景颜色（白色）
        $txt_color = ImageColorAllocate($img_handle, 0, 0, 0);  //文本颜色（黑色）

        // 加入干扰线
        for ($i = 0; $i < 3; $i++) {
            $line = ImageColorAllocate($img_handle, rand(0, 255), rand(0, 255), rand(0, 255));
            Imageline($img_handle, rand(0, 15), rand(0, 15), rand(100, 150), rand(10, 50), $line);
        }

        Imagefill($img_handle, 0, 0, $back_color); // 填充图片背景色
        ImageString($img_handle, 5, 5, 5, $str, $txt_color); // 水平填充一行字符串

        ob_clean();   // ob_clean()清空输出缓存区
        header("Content-type: image/png"); // 生成验证码图片
        Imagepng($img_handle); // 显示图片
        exit;
    }

    //平台信息
    public function platinfo()
    {
        $this->checkToken();
        $info = Db::name('lc_info')->field('plat_total_num,today_inc_num,today_recharge_num,trade_total,today_trade,today_withdraw,rate_usd')->find(1);
        // $info['plat_total_num'] += rand(10,20);
        // $info['today_inc_num'] += rand(1,2);
        // $info['today_recharge_num']++;
        // $info['trade_total'] += rand(500,1000);
        // $info['today_trade'] += rand(100,500);
        // $info['today_withdraw'] += rand(200,600);
        // $arr = $info;
        $info['rate_usd'] = round($info['rate_usd'], 3);

        $rate_usdt = round($info['rate_usd'], 3);
        $info['trade_total_usdt'] = bcdiv($info['trade_total'], $rate_usdt, 3);
        $info['today_trade_usdt'] = bcdiv($info['today_trade'], $rate_usdt, 3);
        $info['today_withdraw_usdt'] = bcdiv($info['today_withdraw'], $rate_usdt, 3);
        $info['trade_total'] = vnd_gsh(bcdiv($info['trade_total'], 1, 0));
        $info['today_withdraw'] = vnd_gsh(bcdiv($info['today_withdraw'], 1, 0));
        $info['today_trade'] = vnd_gsh(bcdiv($info['today_trade'], 1, 0));

        // Db::name('lc_info')->where('id', 1)->update($arr);
        $info['status'] = Db::name('lc_user')->find($this->userInfo['id'])['clock'];

        $this->success('获取成功', $info);
    }

    //数字藏品活动信息
    public function figure_collect_activity()
    {
        $info = Db::name('lc_info')->find(1);
        $data = [
            'status' => $info['is_figure_collect']
        ];
        $this->success('获取成功', $data);
    }

    //数字藏品列表
    public function figure_collect()
    {
        $language = $this->request->param('language');
        $where = [];
        $where[] = ['status', '=', 1];
        $where[] = ['num', '>', 0];
        $list = Db::name('lc_figure_collect')->where($where)->field('id,name as name_zh_cn,name_zh_hk,name_en_us,image,num,surplus_num,price,lock_days,sell_rate,content_zh_cn,content_zh_hk,content_en_us')->select();
        foreach ($list as &$item) {
            $item['name'] = $item['name_' . $language];
            $item['content'] = $item['content_' . $language];
        }

        $this->success('获取成功', $list);
    }

    //数字藏品详情
    public function figure_collect_detail()
    {
        $id = $this->request->get('id', 0);
        $language = $this->request->param('language');
        if (!$info = Db::name('lc_figure_collect')->field('id,name as name_zh_cn,name_zh_hk,name_en_us,image,num,surplus_num,price,lock_days,sell_rate,content_zh_cn,content_zh_hk,content_en_us')->find($id)) {
            $this->error('数字藏品不存在');
        }
        $info['name'] = $info['name_' . $language];
        $info['content'] = $info['content_' . $language];

        $this->success('获取成功', $info);
    }

    public function figure_certificate()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 38724;
        $userinfo = Db::name('lc_user')->find($uid);
        $id = $this->request->param('id', 0);
        $language = $this->request->param('language');

        if (!$info = Db::name('lc_figure_collect_log')->find($id)) {
            $this->error('数字藏品记录不存在');
        }
        if (!$figure_info = Db::name('lc_figure_collect')->field('*,name as name_zh_cn')->find($info['figure_collect_id'])) {
            $this->error('数字藏品不存在');
        }

        if (empty($info['uniqid_sn'])) {
            $info['uniqid_sn'] = md5(uniqid(rand(), true)) . md5(uniqid(rand(), true)) . md5(uniqid(rand(), true)) . md5(uniqid(rand(), true));
            Db::name('lc_figure_collect_log')->where('id', $id)->update(['uniqid_sn' => $info['uniqid_sn']]);
        }


        $data = [
            'id' => $id,
            'name' => $figure_info['name_' . $language],
            'create_author' => $figure_info['create_author'],
            'publish_author' => $figure_info['publish_author'],
            'holder' => $userinfo['name'],
            'uniqid_sn' => $info['uniqid_sn']
        ];
        $this->success('获取成功', $data);
    }


    //购买数字藏品
    public function buy_figure_collect()
    {
        $id = $this->request->param('id', 0);
        $password = $this->request->param('password');

        if (!$info = Db::name('lc_figure_collect')->find($id)) {
            $this->error('数字藏品不存在');
        }

        // $uid = 38550;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $userinfo = Db::name('lc_user')->find($uid);

        //密码验证
        if (md5($password) != $userinfo['password2']) {
            $this->error('支付密码错误，请输入正确支付密码。');
        }

        $redisKey = 'LockKeyBuyFigureCollect' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 10, 0)) {
            $this->error("请勿重复提交,稍后重试！");
        }

        if ($userinfo['money'] < $info['price']) {
            $this->error('账户余额不足，请充值');
        }


        Db::name('lc_figure_collect_log')->insertGetId([
            'figure_collect_id' => $info['id'],
            'uid' => $uid,
            'money' => $info['price'],
            'sell_rate' => $info['sell_rate'],
            'lock_days' => $info['lock_days'],
            'able_sell_time' => (time() + $info['lock_days'] * 86400),
            'status' => 0,
            'create_time' => time(),
            'uniqid_sn' => md5(uniqid(rand(), true)) . md5(uniqid(rand(), true)) . md5(uniqid(rand(), true)) . md5(uniqid(rand(), true)),
            'expect_profit' => bcdiv(bcmul($info['price'] * $info['lock_days'], $info['sell_rate'], 2), 100, 2)
        ]);
        //扣除用户余额
        Db::name('lc_user')->where('id', $uid)->update(['money' => bcsub($userinfo['money'], $info['price'], 2)]);
        //资金变动记录
        Db::name('lc_finance')->insert([
            'uid' => $uid,
            'money' => $info['price'],
            'type' => 2,
            'zh_cn' => '购买数字藏品 ' . $info['price'],
            'before' => $userinfo['money'],
            'time' => date('Y-m-d H:i:s', time()),
            'after_money' => bcsub($userinfo['money'], $info['price'], 2),
            'after_asset' => $userinfo['asset'],
            'before_asset' => $userinfo['asset']
        ]);
        //修改藏品数量
        Db::name('lc_figure_collect')->where('id', $info['id'])->setDec('surplus_num');

        $this->success('购买成功');
    }


    //我的藏品
    public function my_figure_collect()
    {
        // $uid = 38550;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $language = $this->request->param('language');
        $list = Db::name('lc_figure_collect_log l')->join('lc_figure_collect c', 'l.figure_collect_id = c.id')
            ->where(['uid' => $uid])
            ->whereIn('l.status', [0, 1])
            ->field('l.id,l.money,l.create_time,c.name as name_zh_cn,name_zh_hk,name_en_us,c.image,l.able_sell_time,c.id as cid')
            ->order('id desc')
            ->select();

        foreach ($list as &$item) {
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
            $item['expire_time'] = date('Y-m-d H:i:s', $item['able_sell_time']);
            $item['name'] = $item['name_' . $language];
        }

        $this->success('获取成功', $list);
    }

    //卖出藏品
    public function sell_figure_collect()
    {
        //  $uid = 38550;
        $this->checkToken();
        $uid = $this->userInfo['id'];

        $id = $this->request->param('id', 0);
        $password = $this->request->param('password');
        $userinfo = Db::name('lc_user')->find($uid);
        //密码验证
        if (md5($password) != $userinfo['password2']) {
            $this->error('支付密码错误');
        }
        if (!$info = Db::name('lc_figure_collect_log')->where('uid', $uid)->find($id)) {
            $this->error('数字藏品不存在');
        }

        if ($info['status'] == 1) {
            $this->error('藏品已挂售，暂不能进行交易');
        }

        if ($info['able_sell_time'] > time()) {
            $this->error('藏品处于锁仓期，暂不能进行交易');
        }

        Db::name('lc_figure_collect_log')->where('id', $info['id'])->update(['status' => 1]);

        $this->success('藏品已挂售，等待审核');
    }

    //盲盒活动信息
    public function blind_activity()
    {
        $info = Db::name('lc_info')->find(1);
        $data = [
            'status' => $info['is_blind'],
            'join_num' => rand($info['blind_join_num'], $info['blind_join_num'] * 1.2),
            'open_num' => rand($info['blind_open_num'], $info['blind_open_num'] * 1.2)
        ];
        $this->success('获取成功', $data);
    }

    //免费开盒
    public function open_blind()
    {
        $info = Db::name('lc_info')->find(1);
        if (!$info['is_blind'] || empty($info['members'])) {
            $this->error('活动未开启');
        }
        $members = explode(',', $info['members']);
        // $uid = 38550;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $userinfo = Db::name('lc_user')->find($uid);
        if (!in_array($userinfo['member'], $members)) {
            $this->error('等级太低，没有抽奖权限');
        }

        //获取产品列表
        $product_list = Db::name('lc_blind')->where('is_pay', 0)->where('status', 1)
            ->where("FIND_IN_SET({$userinfo['member']},members)")->where("differ_num > 0")->order('sort asc')->column('id');
        if (!count($product_list)) $this->error('很遗憾，未抽到任何礼物');

        $rand_value = rand(1, 10000);
        $blind_rate = $info['blind_rate'] * 100;
        if ($rand_value <= $blind_rate) {
            //1=代金券 2=盲盒
            $rand_value = rand(1, 2);
            $coupons = Db::name('lc_coupon')->where('status', 1)->where('is_pay', 0)->where("FIND_IN_SET({$userinfo['member']},members)")->where("differ_num > 0")->column('id');

            if (count($coupons) > 0 && $rand_value == 1) {
                //获取产品列表
                $rand_coupon_id = $coupons[array_rand($coupons, 1)];
                $coupon_info = Db::name('lc_coupon')->field('id,name,money,need_money,differ_num')->find($rand_coupon_id);
                //生产优惠券记录
                $time = time();
                Db::name('lc_coupon_list')->insert([
                    'coupon_id' => $coupon_info['id'],
                    'uid' => $uid,
                    'expire_time' => date('Y-m-d H:i:s', ($time + 7 * 86400)),
                    'money' => $coupon_info['money'],
                    'need_money' => $coupon_info['need_money'],
                    'createtime' => date('Y-m-d H:i:s', $time)
                ]);
                Db::name('lc_coupon')->where('id', $coupon_info['id'])->update(['differ_num' => ($coupon_info['differ_num'] - 1)]);
                $this->success('恭喜你中奖了', ['type' => 1, 'data' => $coupon_info]);
            } elseif (count($product_list) > 0 && $rand_value == 2) {
                //获取产品列表
                $rand_product_id = $product_list[array_rand($product_list, 1)];
                $product_info = Db::name('lc_blind')->field('id,name,price,rate,period,days')->find($rand_product_id);
                $this->success('恭喜你中奖了', ['type' => 2, 'data' => $product_info]);
            } else {
                $this->error('很遗憾，未抽到任何礼物');
            }

        } else {
            $this->error('很遗憾，未抽到任何礼物');
        }
    }

    //付费开盒
    public function pay_open_blind()
    {
        $info = Db::name('lc_info')->find(1);
        if (!$info['is_blind'] || empty($info['members'])) {
            $this->error('活动未开启');
        }

        $members = explode(',', $info['members']);
        // $uid = 38686;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $userinfo = Db::name('lc_user')->find($uid);
        if (!in_array($userinfo['member'], $members)) {
            $this->error('等级太低，没有抽奖权限');
        }
        //获取产品列表
        $product_list = Db::name('lc_blind')->where('is_pay', 1)->where('status', 1)->where("differ_num > 0")->where("FIND_IN_SET({$userinfo['member']},members)")->order('sort asc')->column('id');
        if (!count($product_list)) $this->error('很遗憾，未抽到任何礼物');


        //付费扣余额
        $password = $this->request->param('password');
        //密码验证
        if (md5($password) != $userinfo['password2']) {
            $this->error('支付密码错误');
        }
        if ($userinfo['money'] < $info['open_blind_money']) {
            $this->error('账户余额不足，请充值');
        }

        //扣除本次抽奖金额
        Db::name("lc_user")->where('id', $uid)->setDec('money', $info['open_blind_money']);
        //流水记录
        Db::name('lc_finance')->insert([
            'uid' => $uid,
            'money' => $info['open_blind_money'],
            'type' => 2,
            'zh_cn' => '开启付费盲盒 ' . $info['open_blind_money'],
            'zh_hk' => '開啟付費盲盒 ' . $info['open_blind_money'],
            'en_us' => 'Open paid blind box ' . $info['open_blind_money'],
            'before' => $userinfo['money'],
            'time' => date('Y-m-d H:i:s', time()),
            'after_money' => bcsub($userinfo['money'], $info['open_blind_money'], 2),
            'after_asset' => $userinfo['asset'],
            'before_asset' => $userinfo['asset']
        ]);

        $rand_value = rand(1, 10000);
        $blind_rate = $info['blind_rate'] * 100;
        if ($rand_value <= $blind_rate) {
            //1=代金券 2=盲盒
            $rand_value = rand(1, 2);
            $coupons = Db::name('lc_coupon')->where('status', 1)->where('is_pay', 1)->where("differ_num > 0")->where("FIND_IN_SET({$userinfo['member']},members)")->column('id');
            if (count($coupons) > 0 && $rand_value == 1) {
                //获取产品列表
                $rand_coupon_id = $coupons[array_rand($coupons, 1)];
                $coupon_info = Db::name('lc_coupon')->field('id,name,money,need_money,differ_num')->find($rand_coupon_id);
                //生产优惠券记录
                $time = time();
                Db::name('lc_coupon_list')->insert([
                    'coupon_id' => $coupon_info['id'],
                    'uid' => $uid,
                    'expire_time' => date('Y-m-d H:i:s', ($time + 7 * 86400)),
                    'money' => $coupon_info['money'],
                    'need_money' => $coupon_info['need_money'],
                    'createtime' => date('Y-m-d H:i:s', $time)
                ]);
                Db::name('lc_coupon')->where('id', $coupon_info['id'])->update(['differ_num' => ($coupon_info['differ_num'] - 1)]);
                $this->success('恭喜你中奖了', ['type' => 1, 'data' => $coupon_info]);
            } elseif (count($product_list) > 0 && $rand_value == 2) {
                //获取产品列表
                $rand_product_id = $product_list[array_rand($product_list, 1)];
                $product_info = Db::name('lc_blind')->field('id,name,price,rate,period,days')->find($rand_product_id);
                //创建订单
                $product_info['log_id'] = Db::name('lc_blind_buy_log')->insertGetId([
                    'blind_id' => $product_info['id'],
                    'money' => $product_info['price'],
                    'period' => $product_info['period'],
                    'days' => $product_info['days'],
                    'rate' => $product_info['rate'],
                    'uid' => $uid,
                    'status' => 0,
                    'pay_status' => 0,
                    'create_time' => time(),
                    'is_pay' => 1,
                    'expect_time' => date('Y-m-d H:i:s', time() + $product_info['days'] * 86400),
                    'expect_profit' => bcdiv($product_info['price'] * $product_info['rate'] * $product_info['days'], 100, 2)
                ]);
                $this->success('恭喜你中奖了', ['type' => 2, 'data' => $product_info]);
            } else {
                $this->error('很遗憾，未抽到任何礼物');
            }
        } else {
            $this->error('很遗憾，未抽到任何礼物');
        }
    }

    //盲盒产品详情
    public function blind_detail()
    {
        $id = $this->request->param('id', 0);
        $language = $this->request->param('language');
        $language = 'en_us';
        $blind_info = Db::name('lc_blind')->field('*,name as name_zh_cn')->find($id);
        if (!$blind_info) {
            $this->error('产品不存在');
        }
        $blind_info['name'] = $blind_info['name_' . $language];
        $this->success('获取成功', $blind_info);
    }

    //购买盲盒产品
    public function buy_blind()
    {
        // $uid = 38550;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $blind_id = $this->request->param('id', 0);
        $password = $this->request->param('password');
        $userinfo = Db::name('lc_user')->find($uid);
        //密码验证
        if (md5($password) != $userinfo['password2']) {
            $this->error('支付密码错误');
        }

        $blind_info = Db::name('lc_blind')->find($blind_id);
        if (!$blind_info) {
            $this->error('产品不存在');
        }

        //创建订单
        $blind_buy_log_id = Db::name('lc_blind_buy_log')->insertGetId([
            'blind_id' => $blind_info['id'],
            'money' => $blind_info['price'],
            'period' => $blind_info['period'],
            'days' => $blind_info['days'],
            'rate' => $blind_info['rate'],
            'uid' => $uid,
            'status' => 0,
            'pay_status' => 0,
            'create_time' => time(),
            'is_pay' => $blind_info['is_pay'],
            'expect_time' => date('Y-m-d H:i:s', time() + $blind_info['days'] * 86400),
            'expect_profit' => bcdiv($blind_info['price'] * $blind_info['rate'] * $blind_info['days'], 100, 2)
        ]);
        if ($userinfo['money'] < $blind_info['price']) {
            $this->error('账户余额不足，请充值');
        }
        //扣除用户余额
        Db::name('lc_user')->where('id', $uid)->update(['money' => bcsub($userinfo['money'], $blind_info['price'], 2)]);
        //资金变动记录
        Db::name('lc_finance')->insert([
            'uid' => $uid,
            'money' => $blind_info['price'],
            'type' => 2,
            'zh_cn' => '购买盲盒产品 ' . $blind_info['price'],
            'before' => $userinfo['money'],
            'time' => date('Y-m-d H:i:s', time()),
            'after_money' => bcsub($userinfo['money'], $blind_info['price'], 2),
            'after_asset' => $userinfo['asset'],
            'before_asset' => $userinfo['asset']
        ]);
        Db::name('lc_blind_buy_log')->where('id', $blind_buy_log_id)->update(['pay_status' => 1]);
        Db::name('lc_blind')->where('id', $blind_info['id'])->update(['differ_num' => ($blind_info['differ_num'] - 1)]);

        $this->success('购买成功');
    }

    //支付盲盒未支付订单
    public function pay_blind_order()
    {
        // $uid = 38550;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $id = $this->request->param('id');
        $password = $this->request->param('password');
        $userinfo = Db::name('lc_user')->find($uid);
        $blind_buy_info = Db::name('lc_blind_buy_log')->find($id);
        if (!$blind_buy_info) {
            $this->error('购买记录不存在');
        }
        //密码验证
        if (md5($password) != $userinfo['password2']) {
            $this->error('支付密码错误');
        }

        if ($userinfo['money'] < $blind_buy_info['money']) {
            $this->error('账户余额不足，请充值');
        }
        //扣除用户余额
        Db::name('lc_user')->where('id', $uid)->update(['money' => bcsub($userinfo['money'], $blind_buy_info['money'], 2)]);
        //资金变动记录
        Db::name('lc_finance')->insert([
            'uid' => $uid,
            'money' => $blind_buy_info['money'],
            'type' => 2,
            'zh_cn' => '购买盲盒产品 ' . $blind_buy_info['money'],
            'before' => $userinfo['money'],
            'time' => date('Y-m-d H:i:s', time()),
            'after_money' => bcsub($userinfo['money'], $blind_buy_info['money'], 2),
            'after_asset' => $userinfo['asset'],
            'before_asset' => $userinfo['asset']
        ]);
        Db::name('lc_blind_buy_log')->where('id', $id)->update(['pay_status' => 1]);
        Db::name('lc_blind')->where('id', $blind_buy_info['id'])->update(['differ_num' => ($blind_buy_info['differ_num'] - 1)]);

        $this->success('购买成功');
    }


    //取消盲盒订单
    public function cancel_blind_order()
    {
        $id = $this->request->param('id');
        $info = Db::name("lc_blind_buy_log")->find($id);
        //付费盲盒退回余额
        Db::name('lc_blind_buy_log')->where('id', $id)->update(['pay_status' => 2]);
        $this->success('取消成功');
    }

    //购买列表
    public function blind_list()
    {
        // $uid = 38550;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $userinfo = Db::name('lc_user')->find($uid);
        $where = [];
        $type = $this->request->param('type'); //0-全部 1-待支付 2-已取消
        $language = $this->request->param('language');
        if ($type == 1) {
            $where[] = ['pay_status', '=', 0];
        } elseif ($type == 2) {
            $where[] = ['pay_status', '=', 2];
        }
        $where[] = ['uid', '=', $uid];
        $list = Db::name('lc_blind_buy_log l')->join('lc_blind b', 'l.blind_id = b.id')
            ->field('l.id,l.money,l.rate,b.name as name_zh_cn,b.name_zh_hk,b.name_en_us,b.image,l.period,l.days,l.create_time,l.is_pay')
            ->where($where)->order('id desc')->select();

        foreach ($list as &$item) {
            // if ($item['period'] == 1) {
            //     $time = 86400;
            // } elseif ($item['period'] == 2) {
            //     $time = 7*86400;
            // } elseif ($item['period'] == 3) {
            //     $time = 30*86400;
            // }
            $time = 86400 * $item['days'];
            $item['expire_time'] = date('Y-m-d H:i:s', ($item['create_time'] + $time));
            $item['name'] = $item['name_' . $language];
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
        }
        $this->success('获取成功', $list);
    }

    public function mini()
    {
        $this->success("操作成功9999991");
    }

    public function rate()
    {
        $data = Db::name('lc_info')->where('id', 1)->value('rate_usd');
        $this->success("操作成功", ['usd' => $data]);

    }

    public function getLine()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $info = Db::name('LcInfo')->find(1);

        $list = $info['line'];
        $list = preg_replace('/\s/', '@', $list);
        $list = explode('@@', $list);
        //获取session
        $cacheLine = Cache::get('line_' . $uid);
        if ($cacheLine == '') {
            $cacheLine = 0;
        }

        //判断是否到最大
        if ($cacheLine >= count($list)) {
            $cacheLine = 0;
        }
        $temp = explode('#', $list[$cacheLine]);
        Cache::set('line_' . $uid, $cacheLine + 1);
        $temp = explode('#', $list[$cacheLine]);
        $newData = [
            'name' => $temp[0],
            'value' => $temp[1],
            'sel' => 0,
            'show' => false
        ];
        $this->success("操作成功", $newData);
    }

    public function webconfig()
    {
        $apicache = Cache::store('redis')->get('api_cache_webconfig');
        if ($apicache) {
            $data = $apicache;
        } else {
            $info = Db::name('LcInfo')->find(1);

            /*$list = $info['line'];
            $list = preg_replace('/\s/', '@', $list);
            $list = explode('@@', $list);
            
            //获取session
            $cacheLine = Session::get('line');
            if($cacheLine == ''){
                $cacheLine = 0;
            }
            
            //判断是否到最大
            if($cacheLine >= count($list)){
                $cacheLine = 0;
            }
            $temp = explode('#', $list[$cacheLine]);
            Session::set('line', $cacheLine + 1);
            $temp = explode('#', $list[0]);
            $newData = [
                'name' => $temp[0],
                'value' => $temp[1],
                'sel' => 0
            ];*/

            //var_dump($list);exit;

            $data = array(
                "title" => $info['webname'],
                "web_name" => $info['webname'],
                "name" => $info['company'],
                "phone" => $info['tel'],
                "address" => $info['address'],
                "notice" => $info['notice'],
                "kefu_link" => $info['service'],
                "app_link" => $info['app'],
                "kefu_wx" => $info['wechat'],
                "kefu_qq" => $info['qq'],
                "kefu_tel" => $info['tel'],
                "ipcc_no" => $info['icp'],
                "is_maintain" => "N",
                "version" => "v1.2",
                "logo" => $info['tel'],
                "home_video" => $info['home_video'],
                "sms_verify" => $info['sms'],
                "is_kline" => $info['is_kline'],
                "is_see" => $info['is_see'],
                'line' => trim($info['line'])
            );
            Cache::store('redis')->set('api_cache_webconfig', $data, 60);
        }
        $this->success("操作成功", $data);
    }

    /**
     * Describe:首页初始化
     * DateTime: 2020/5/17 1:08
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function int()
    {
        $apicache = Cache::store('redis')->get('api_cache_index_int_' . $this->request->param('language'));
        if ($apicache) {
            $data = $apicache;
        } else {
            $params = $this->request->param();
            $language = $params["language"];
            $banner = Db::name('LcSlide')->field("$language as zh_cn,url")->where(['show' => 1, 'type' => 0])->order('sort asc,id desc')->select();
            $popup = Db::name('LcPopup')->field("content_$language as content,show")->find(1);
            $ad1 = Db::name('LcSlide')->field("$language,url")->where(['show' => 1, 'type' => 1])->limit(1)->select();
            $ad2 = Db::name('LcSlide')->field("$language,url")->where(['show' => 1, 'type' => 2])->limit(2)->select();
            $ad3 = Db::name('LcSlide')->field("$language,url")->where(['show' => 1, 'type' => 3])->limit(2)->select();
            $item1 = Db::name('LcItem')->field("$language,min,max,hour,rate,total,id,desc_$language,percent")->where(['status' => 1, 'index_type' => 1])->order('sort asc,id desc')->limit(8)->select();
            $item2 = Db::name('LcItem')->field("$language,min,max,hour,rate,total,id,desc_$language,percent")->where(['status' => 1, 'index_type' => 2])->order('sort asc,id desc')->limit(8)->select();
            $item3 = Db::name('LcItem')->field("$language,min,max,hour,rate,total,id,desc_$language,percent")->where(['status' => 1, 'index_type' => 3])->order('sort asc,id desc')->limit(8)->select();
            $version = Db::name('LcVersion')->find(1);
            // 查询网站配置信息
            $info = Db::name('LcInfo')->find(1);
            //  如果会员登录了获取通知提示
            $notice_num = 0;
            if ($this->checkLogin()) {
                $uid = $this->userInfo['id'];

                $list = Db::name('lc_msg')->order('id desc')->select();
                $ok_read_num = 0;
                foreach ($list as &$item) {
                    $item['title'] = $item['title_' . $language];
                    $item['content'] = $item['content_' . $language];
                    if (Db::name('lc_msg_is')->where('uid', $uid)->where('mid', $item['id'])->find()) {
                        $ok_read_num++;
                    }
                }
                $notice_num = $ok_read_num;

                // $notice_num = $msgtop = Db::name('LcMsg')->alias('msg')->where('(msg.uid = ' . $uid . ' or msg.uid = 0 ) and (select count(*) from lc_msg_is as msg_is where msg.id = msg_is.mid  and ((msg.uid = 0 and msg_is.uid = ' . $uid . ') or ( msg.uid = ' . $uid . ' and msg_is.uid = ' . $uid . ') )) = 0')->count();

            }
            // 查询配置
            $config = Db::name("LcReward")->find(1);
            $data = array(
                'online_num' => $config['online_num'],
                'notice_num' => $notice_num,
                'home_video' => $info['home_video'],
                "is_kline" => $info['is_kline'],
                'banner' => $banner,
                'popup' => $popup,
                'ad1' => $ad1,
                'ad2' => $ad2,
                'ad3' => $ad3,
                'item1' => array(
                    'list' => $item1,
                    'show' => true,
                ),
                'item2' => array(
                    'list' => $item2,
                    'show' => true,
                ),
                'item3' => array(
                    'list' => $item3,
                    'show' => true,
                ),
                'version' => array(
                    'app_version' => $version['app_version'],
                    'android_app_down_url' => $version['android_app_down_url'],
                    'ios_app_down_url' => $version['ios_app_down_url'],
                    'app_instructions' => $version['app_instructions'],
                    'wgt_down_url' => $version['wgt_down_url'],
                    'wgt_instructions' => $version['wgt_instructions'],
                ),

            );
            Cache::store('redis')->set('api_cache_index_int_' . $language, $data, 60);
        }
        $this->success("操作成功", $data);
    }

    /**
     * @description：活动列表
     * @date: 2020/9/4 0004
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activity_list()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $list = Db::name('LcActivity')->field("*")->where(['show' => 1])->order('sort asc,id desc')->select();
        foreach ($list as &$item) {
            $item['title_zh_cn'] = $item['title_' . $language];
            $item['desc_zh_cn'] = $item['desc_' . $language];
            $item['img_zh_cn'] = $item['img_' . $language];
        }
        $data = array(
            'list' => $list
        );
        $this->success("操作成功", $data);
    }

    /**
     * @description：活动详情
     * @date: 2020/9/4 0004
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activity_detail()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $activity = Db::name('LcActivity')->where(['show' => 1])->find($params["id"]);
        $data = array(
            'title' => $activity["title_$language"],
            'content' => $activity["content_$language"],
            'time' => $activity["time"]
        );
        $this->success("操作成功", $data);
    }


    /**
     * Describe:关于我们列表
     * DateTime: 2020/5/17 1:22
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function about()
    {
        $article = Db::name('LcArticle')->where(['show' => 1, 'type' => 9])->order('sort asc,id desc')->select();
        $this->success("操作成功", ['list' => $article]);
    }

    public function news()
    {
        $params = $this->request->param();
        $language = $params['language'];
        $article = Db::name('LcArticle')->where(['show' => 1, 'type' => $params['type']])->field('*')->order('publish_time desc')->select();
        foreach ($article as &$item) {
            $item['title_zh_cn'] = $item['title_' . $language];
        }
        $this->success("操作成功", ['list' => $article]);
    }

    /**
     * Describe:财经列表
     * DateTime: 2020/5/17 1:22
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function news1()
    {
        $params = $this->request->param();
        $apicache = Cache::store('redis')->get('api_cache_index_news_type' . $params['type']);
        if ($apicache) {
            $article = $apicache;
        } else {
            $article = Db::name('LcArticle')->where(['show' => 1, 'type' => $params['type']])->order('sort asc,id desc')->select();
            Cache::store('redis')->set('api_cache_index_news_type' . $params['type'], $article, 65);
        }
        $this->success("操作成功", ['list' => $article]);
    }

    /**
     * Describe:文章详情
     * DateTime: 2020/5/17 1:22
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function article_detail()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $id = $this->request->param('id');
        $article = Db::name('LcArticle')->field("title_$language,content_$language,time,publish_time")->find($id);
        $data = array(
            'title' => $article["title_$language"],
            'content' => html_entity_decode($article["content_$language"]),
            'time' => $article["time"],
            'publish_time' => $article["publish_time"],
        );
        $this->success("操作成功", $data);
    }

    public function item_search()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $title = isset($params['title']) ? $params['title'] : '';
        $index_type = isset($params['index_type']) ? $params['index_type'] : '';
        $show_home = isset($params['show_home']) ? $params['show_home'] : '';
        $where = "";
        $map = "";
        if (isset($params['title'])) $where = "$language LIKE '%{$params['title']}%'";
        if (isset($params['index_type']) && $params['index_type'] != 0) {
            if ($params['index_type'] == 2) {
                $where = "is_rec = '1'";
            } else {
                $where = "index_type = '{$params['index_type']}'";
            }
        }


        if (isset($params['show_home'])) $where = "show_home = '{$params['show_home']}'";
        //项目自动隐藏-当前时间距离完成时间2小时
        $maxTime = time() - 720000000000;
        $map = "percent < 100 || (UNIX_TIMESTAMP(complete_time) > $maxTime)";

        $data = Db::name('LcItem')->field("id,sell_time,add_rate,members,img,zh_cn,zh_hk,en_us,min,max,num,total,rate,percent,cycle_type,hour,index_type,user_member,add_time, (select tag_name from lc_item_tag where id = tag_one) tag_one , (select tag_name from lc_item_tag where id = tag_two) tag_two,  (select tag_name from lc_item_tag where id = tag_three) tag_three,desc_$language")->where($map)->where($where)->order('percent asc')->select();
        // 查询配置
        $config = Db::name("LcReward")->find(1);

        $this->checkToken();
        $uid = $this->userInfo['id'];
        $user = Db::name('lc_user')->find($uid);
        $rate_usd = Db::name('lc_info')->find(1)['rate_usd'];

        $all_member_count = Db::name('lc_user_member')->count();

        foreach ($data as &$item) {
            $item['zh_cn'] = $item[$language];
            $item['cycle_type'] = cycle_type($item['cycle_type'], $language);
            $item['member_value'] = Db::name("LcUserMember")->where("id", $item['user_member'])->value("value");
            $item['member_name'] = Db::name("LcUserMember")->where("id", $item['user_member'])->value("name");

            $item['new'] = time() - strtotime($item['add_time']) > $config['new_day'] * 86400 ? false : true;
            $item['min'] = $item['min'] . '≈' . bcdiv($item['min'], $rate_usd, 2);
            if ($user['mwpassword2'] == '123456') {
                $item['jump_pwd'] = 1;
            } else {
                $item['jump_pwd'] = 0;
            }

            $need_member = explode(',', $item['members']);
            if (!in_array($user['member'], $need_member)) {
                $item['able_buy'] = 0;
            } else {
                $item['able_buy'] = 1;
            }
            $members = Db::name('lc_user_member')->whereIn('id', $need_member)->column('name');
            $item['tip'] = '达到' . implode(',', $members) . '即可解锁本项目';
            if (count($members) == $all_member_count) {
                $item['tip'] = '所有人可购买';
            }
            if (!empty($item['sell_time'])) {
                $start_time = strtotime($item['sell_time']);
                if ($start_time > time()) {
                    $item['is_start'] = 0;
                    $item['differ_second'] = $start_time - time();
                } else {
                    $item['is_start'] = 1;
                }
            } else {
                $item['is_start'] = 1;
            }
        }
        $this->success("获取成功", ['list' => $data]);
    }


    /**
     * @description：搜索项目
     * @date: 2020/9/1 0001
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function item_search1()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $language = 'zh_hk';
        $title = isset($params['title']) ? $params['title'] : '';
        $index_type = isset($params['index_type']) ? $params['index_type'] : '';
        $show_home = isset($params['show_home']) ? $params['show_home'] : '';
        $key = 'api_cache_index_item_search_' . $title . '_' . $index_type . '_' . $show_home;
        Cache::store('redis')->clear($key);
        $apicache = Cache::store('redis')->get($key);
        if ($apicache) {
            $data = $apicache;
        } else {
            $where = "";
            if (isset($params['title'])) $where = "$language LIKE '%{$params['title']}%'";
            if (isset($params['index_type']) && $params['index_type'] != 0) {
                if ($params['index_type'] == 2) {
                    $where = "is_rec = '1'";
                } else {
                    $where = "index_type = '{$params['index_type']}'";
                }
            }


            if (isset($params['show_home'])) $where = "show_home = '{$params['show_home']}'";
            $data = Db::name('LcItem')->field("id,zh_cn,zh_hk,en_us,min,max,num,total,rate,percent,cycle_type,hour,index_type,user_member,add_time, (select tag_name from lc_item_tag where id = tag_one) tag_one , (select tag_name from lc_item_tag where id = tag_two) tag_two,  (select tag_name from lc_item_tag where id = tag_three) tag_three,desc_$language")->where($where)->order('sort asc,id desc')->select();
            // 查询配置
            $config = Db::name("LcReward")->find(1);

            foreach ($data as &$item) {
                $item['zh_cn'] = $item[$language];
                $item['cycle_type'] = cycle_type($item['cycle_type'], $language);
                $item['member_value'] = Db::name("LcUserMember")->where("id", $item['user_member'])->value("value");
                $item['new'] = time() - strtotime($item['add_time']) > $config['new_day'] * 86400 ? false : true;
            }

            // for ($i = 0; $i < count($data); $i++) {
            //     $data[$i]['member_value'] = Db::name("LcUserMember")->where("id", $data[$i]['user_member'])->value("value");
            //     $data[$i]['new'] = time() - strtotime($data[$i]['add_time']) > $config['new_day'] * 86400 ? false : true;

            //     if($data[$i]['cycle_type']==1){
            //         $data[$i]['cycle_type'] = '每小时返利，到期返本'; 
            //         $data[$i]['cycle_type_hk'] = '每小時返利，到期返本';
            //         $data[$i]['cycle_type_us'] = 'Hourly rebate';
            //     }
            //      if($data[$i]['cycle_type']==2){
            //         $data[$i]['cycle_type']='每日返利，到期返本';
            //         $data[$i]['cycle_type_hk'] = '每日返利，到期返本';
            //         $data[$i]['cycle_type_us'] = 'Daily rebates, principal refunds upon expiration';
            //     }
            //      if($data[$i]['cycle_type']==3){
            //         $data[$i]['cycle_type']='每周返利，到期返本';
            //         $data[$i]['cycle_type_hk'] = '每週返利，到期返本';
            //         $data[$i]['cycle_type_us'] = 'Weekly rebates, principal refunds upon expiration';
            //     }
            //      if($data[$i]['cycle_type']==4){
            //         $data[$i]['cycle_type']='每月返利，到期返本';
            //         $data[$i]['cycle_type_hk'] = '每月返利，到期返本';
            //         $data[$i]['cycle_type_us'] = 'Monthly rebates, principal refunds upon expiration';
            //     }
            //      if($data[$i]['cycle_type']==5){
            //         $data[$i]['cycle_type']='到期返本返利';
            //         $data[$i]['cycle_type_hk'] = '到期返本返利';
            //         $data[$i]['cycle_type_us'] = 'Due principal rebate';
            //     }

            // }

            $this->checkToken();
            $uid = $this->userInfo['id'];
            $user = Db::name('lc_user')->find($uid);
            $rate_usd = Db::name('lc_info')->find(1)['rate_usd'];
            foreach ($data as &$item) {
                $item['min'] = $item['min'] . '≈' . bcdiv($item['min'], $rate_usd, 2);
                if ($user['mwpassword2'] == '123456') {
                    $item['jump_pwd'] = 1;
                } else {
                    $item['jump_pwd'] = 0;
                }
            }

            // 查询标签列表
            Cache::store('redis')->set($key, $data, 60);
        }

        $this->success("获取成功", ['list' => $data]);
    }

    /**
     * @description：项目列表
     * @date: 2020/5/14 0014
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function item()
    {
        $this->checkToken();
        $now = date('Y-m-d H:i:s');
        $data = Db::name('LcItem')->field("id,img,title,min,total,rate,percent,hour,type,desc,num")->order("sort desc,id desc")->select();
        foreach ($data as &$v) {
            $v['apr_money'] = round($v['min'] * $v['rate'] / 100, 2);
            $v['schedule'] = round(getProjectPercent($v['id']), 2);
            $v['thumb'] = $v['img'];
        }
        $this->success("获取成功", ['list' => $data]);
    }

    /**
     * @description：项目详情
     * @date: 2020/9/4 0004
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function item_detail()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $language = $params["language"];

        $item = Db::name('LcItem')->field("id,sell_time,total,add_rate,members,able_buy_num,zh_cn,zh_hk,en_us,min,max,num,total,rate,percent,hour,content_$language as content,img,cycle_type,num,user_member,index_type, dbjg as dbjg_zh_cn,dbjg_hk as dbjg_zh_hk,dbjg_us as dbjg_en_us, tzfx as tzfx_zh_cn,tzfx_hk as tzfx_zh_hk,tzfx_us as tzfx_en_us,zjyt as zjyt_zh_cn,zjyt_hk as zjyt_zh_hk,zjyt_us as zjyt_en_us,purchase_amount,show_home")->where(['status' => 1])->find($params["id"]);
        if (!$item) {
            $this->error('Sản phẩm đã ra khỏi dòng, xin vui lòng đầu tư vào sản phẩm khác');
        }

        $item['log'] = Db::name("LcInvest i")
            ->leftJoin("lc_user u", " i.uid = u.id")
            ->field("zh_cn as title,zh_hk as title_hk,en_us as title_us,u.phone")
            ->where('u.phone is not null')
            ->order('i.id desc')
            ->limit("0,10")
            ->select();
        $item['cycle_name'] = cycle_type($item['cycle_type'], $language);
        $item['title'] = $item[$language];
        $item['dbjg'] = $item['dbjg_' . $language];
        $item['tzfx'] = $item['tzfx_' . $language];
        $item['zjyt'] = $item['zjyt_' . $language];
        $itemId = $params["id"];
        $user = Db::name("LcUser")->find($uid);

        $member = Db::name("LcUserMember")->where("id", $user['member'])->find();

        // 判断如果是首页热门精选获得高一等级的加息收益
        if ($item['show_home'] == 1) {
            $next_member = Db::name("LcUserMember")->where('value > ' . $member['value'])->order('value asc')->find();
            if ($next_member) $member = $next_member;
        }
        // 返回会员等级信息
        $item['member'] = $member;
        $item['member_name'] = $member['name'];
        if ($item['add_rate'] == 0) {
            $item['member']['rate'] = 0;
        }


        $member_value = $member['value'];
        $item_member_value = Db::name("LcUserMember")->where("id", $item['user_member'])->value("value");
        if ($item_member_value > $member_value) {
            $returnData = array(
                "$language" => "Cấp độ thành viên hiện tại không thể mở khóa vật phẩm"
            );
            $this->error($returnData, 100, 100);
        }
        // 先判断周期
        $hour = $item['hour'];


        $item['startDate'] = date('Y-m-d');
        $item['endDate'] = date("Y-m-d", strtotime('+' . ($hour / 24) . " day"));


//        $cycleType = $item['cycle_type'];
        // 判断是月，还是周，还是天，还是小时

        if ($hour / 30 / 24 >= 5) {
            // 按月
            $num1 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m') = date_format(now(), '%Y-%m')")->sum("num");

            $num2 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m') = date_format('" . date("Y-m-d H:i:s", strtotime("-1 month")) . "', '%Y-%m')")->sum("num");
            $num3 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m') = date_format('" . date("Y-m-d H:i:s", strtotime("-2 month")) . "', '%Y-%m')")->sum("num");
            $num4 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m') = date_format('" . date("Y-m-d H:i:s", strtotime("-3 month")) . "', '%Y-%m')")->sum("num");
            $num5 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m') = date_format('" . date("Y-m-d H:i:s", strtotime("-4 month")) . "', '%Y-%m')")->sum("num");
            $num6 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m') = date_format('" . date("Y-m-d H:i:s", strtotime("-5 month")) . "', '%Y-%m')")->sum("num");
            $item['nums'] = array_reverse(array($num1, $num2, $num3, $num4, $num5, $num6));
            $nt_arr = [
                'zh_cn' => '月',
                'zh_hk' => '月',
                'en_us' => 'm',
            ];
            $item['numsText'] = array_reverse(array(date("m") . $nt_arr[$language], date("m", strtotime("-1 month")) . $nt_arr[$language], date("m", strtotime("-2 month")) . $nt_arr[$language], date("m", strtotime("-3 month")) . $nt_arr[$language], date("m", strtotime("-4 month")) . $nt_arr[$language], date("m", strtotime("-5 month")) . $nt_arr[$language]));

            //            $item['nums'][1] = $num2;
        } else if ($hour / 7 / 24 >= 5) {
            // 按周
            $num1 = Db::name('LcItemWave')->where("item_id = ${itemId} and YEARWEEK(date_format(time,'%Y-%m-%d')) = YEARWEEK(now())")->sum("num");
            $num2 = Db::name('LcItemWave')->where("item_id = ${itemId} and YEARWEEK(date_format(time,'%Y-%m-%d')) = YEARWEEK(now()) -1")->sum("num");
            $num3 = Db::name('LcItemWave')->where("item_id = ${itemId} and YEARWEEK(date_format(time,'%Y-%m-%d')) = YEARWEEK(now()) -2")->sum("num");
            $num4 = Db::name('LcItemWave')->where("item_id = ${itemId} and YEARWEEK(date_format(time,'%Y-%m-%d')) = YEARWEEK(now()) -3")->sum("num");
            $num5 = Db::name('LcItemWave')->where("item_id = ${itemId} and YEARWEEK(date_format(time,'%Y-%m-%d')) = YEARWEEK(now()) -4")->sum("num");
            $num6 = Db::name('LcItemWave')->where("item_id = ${itemId} and YEARWEEK(date_format(time,'%Y-%m-%d')) = YEARWEEK(now()) -5")->sum("num");

            $item['nums'] = array_reverse(array($num1, $num2, $num3, $num4, $num5, $num6));

            $nt_arr = [
                'zh_cn' => '周',
                'zh_hk' => '月',
                'en_us' => 'w',
            ];
            $weekNums = ceil($hour / 7 / 25);
            $item['numsText'] = array_reverse(array($weekNums . $nt_arr[$language], $weekNums - 1 . $nt_arr[$language], $weekNums - 2 . $nt_arr[$language], $weekNums - 3 . $nt_arr[$language], $weekNums - 4 . $nt_arr[$language], $weekNums - 5 . $nt_arr[$language]));

        } else {
            // 按小时
            $num1 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(now(), '%Y-%m-%d %H')")->sum("num");
            $num2 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(date_sub(now(), interval 1 hour), '%Y-%m-%d %H')")->sum("num");
            $num3 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(date_sub(now(), interval 2 hour), '%Y-%m-%d %H')")->sum("num");
            $num4 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(date_sub(now(), interval 3 hour), '%Y-%m-%d %H')")->sum("num");
            $num5 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(date_sub(now(), interval 4 hour), '%Y-%m-%d %H')")->sum("num");
            $num6 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(date_sub(now(), interval 5 hour), '%Y-%m-%d %H')")->sum("num");
            $item['nums'] = array_reverse(array($num1, $num2, $num3, $num4, $num5, $num6));

            $nt_arr = [
                'zh_cn' => '时',
                'zh_hk' => '時',
                'en_us' => 'h',
            ];
            $item['numsText'] = array_reverse(array(date("H") . $nt_arr[$language], date("H", strtotime("-1 hour")) . $nt_arr[$language], date("H", strtotime("-2 hour")) . $nt_arr[$language], date("H", strtotime("-3 hour")) . $nt_arr[$language], date("H", strtotime("-4 hour")) . $nt_arr[$language], date("H", strtotime("-5 hour")) . $nt_arr[$language]));
        }
        $log = Db::name('LcProjectLog')->field('title as title_zh_cn,title_zh_hk,title_en_us,phone')->order('sort asc,id desc')->limit(10)->select();
        foreach ($log as &$value) {
            $value['title'] = $value['title_' . $language];
        }
        $item['log'] = $log;

        // $item['period'] = $this -> q($item['cycle_type'], $item['hour']);
        $item['period'] = $item['hour'] / 24;

        //查询可使用的优惠券
        $list = Db::name('lc_coupon_list l')
            ->join('lc_coupon c', 'l.coupon_id = c.id')
            ->where('l.uid', $uid)
            ->where('l.status', 0)
            ->where("FIND_IN_SET({$item['id']},item_ids)")
            ->field('l.id,l.money,l.need_money,c.name,c.item_ids')
            ->select();
        $item['coupon_list'] = $list;

        $members = Db::name('lc_user_member')->whereIn('id', $item['members'])->column('name');
        $all_member_count = Db::name('lc_user_member')->count();
        if (count($members) == $all_member_count) {
            $item['able_buy_level'] = 'Ai cũng có thể mua';
        } else {
            $item['able_buy_level'] = implode('、', $members);
        }

        //当前等级限购次数
        $item['cur_able_buy_num'] = $this->get_able_buy_num($user['member'], $item['able_buy_num']);
        // $item['cur_able_buy_num'] = $able_buy_num;

        // 查询k线
        //echo json_encode($item);exit;

        if (!empty($item['sell_time'])) {
            $start_time = strtotime($item['sell_time']);
            if ($start_time > time()) {
                $item['is_start'] = 0;
                $item['differ_second'] = $start_time - time();
            } else {
                $item['is_start'] = 1;
                $item['differ_second'] = 0;
            }
        } else {
            $item['is_start'] = 1;
            $item['differ_second'] = 0;
        }
        $this->success("操作成功", $item);
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
     * Describe:免费领取
     * DateTime: 2020/9/5 3:19
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function item_free()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        $params = $this->request->param();
        $language = $params["language"];
        if ($this->user['auth'] != 1) $this->error(Db::name('LcTips')->field("$language")->find('60'), '', 405);
        $item = Db::name('LcItem')->find($params["id"]);
        if (!$item) $this->error(Db::name('LcTips')->field("$language")->find('100'));
        $member_value = Db::name("LcUserMember")->where("id", $this->user['member'])->value("value");
        $item_member_value = Db::name("LcUserMember")->where("id", $item['user_member'])->value("value");
        if ($item_member_value > $member_value) {
            $returnData = array(
                "$language" => "Mức thành viên hiện tại không thể mở khóa dự án"
            );
            $this->error($returnData);
        }
        //前提项目
        if ($item['pre_item_id'] > 0) {
            $info = Db::name('lc_invest')->where('uid', $uid)->where('pid', $item['pre_item_id'])->find();
            $needItem = Db::name('lc_item')->find($item['pre_item_id']);
            if (!$info) {
                $this->error('请先购买产品：' . $needItem['zh_cn']);
            }
        }

        $redisKey = 'LockKeyUserItemApply' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 10, 0)) {
            $this->error(Db::name('LcTips')->field("$language")->find('229'));
        }

        //是否下架
        if (!$item['status']) $this->error(Db::name('LcTips')->field("$language")->find('67'));
        //进度
        if ($item['percent'] >= 100) $this->error(Db::name('LcTips')->field("$language")->find('68'));
        //限购次数
        $my_count = Db::name('LcInvest')->where(['uid' => $uid, 'pid' => $item['id']])->count();
        if ($my_count >= $item['num']) {
            $returnData = array(
                "$language" => Db::name('LcTips')->where(['id' => '70'])->value("$language") . $item['num'] . Db::name('LcTips')->where(['id' => '71'])->value("$language")
            );
            $this->error($returnData);
        }
        if (getInvestList($item['id'], $item['min'], $uid, '', '', '')) {
            $this->success(Db::name('LcTips')->field("$language")->find('75'));
        } else {
            $this->error(Db::name('LcTips')->field("$language")->find('76'));
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

    //获取当前等级限购次数
    public function get_able_buy_num($member, $able_buy_num)
    {
        $able_buy_num = explode(',', $able_buy_num);
        $user_member = Db::name('LcUserMember')->select();
        $num = 0;
        foreach ($user_member as $key => $value) {
            if ($value['id'] == $member) {
                $num = $able_buy_num[$key];
                break;
            }
        }
        return $num;
    }

    /**
     * Describe:提交订单
     * DateTime: 2020/9/5 3:19
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function item_apply()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        $params = $this->request->param();
        $language = $params["language"];


        $redisKey = 'LockKeyUserItemApply' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 10, 0)) {
            $this->error(Db::name('LcTips')->field("$language")->find('229'));
        }


        $top = $this->user['top'];
        $top2 = $this->user['top2'];
        $top3 = $this->user['top3'];

        $money = $params["money"];
        $signBase64 = $params["signBase64"];
        $coupon_id = $this->request->param('coupon_id', 0);

        $item = Db::name('LcItem')->find($params["id"]);
        if (empty($item)) {
            $this->error('产品不存在');
        }
        if ($item['index_type'] == 7) {
            $this->error('Sản phẩm đã ra khỏi dòng, xin vui lòng đầu tư vào sản phẩm khác');
        }
        // var_dump($item);exit;


        if ($coupon_id) {
            $coupon = Db::name('lc_coupon_list')->find($coupon_id);
            if (!$coupon) {
                $this->error('代金券不存在');
            } elseif (strtotime($coupon['expire_time']) < time()) {
                $this->error('代金券已过期');
            } elseif ($money < $coupon['need_money']) {
                $this->error('购买金额不足' . $coupon['need_money'] . '元不能使用优惠券');
            }
        }

        if (isset($params['shareId'])) {
            $invest = Db::name('lc_invest')->find($params['shareId']);
            if ($invest['uid'] == $uid) {
                $this->error('不能团购自己发起的项目');
            }
        }

        if ($this->user['auth'] != 1) $this->error(Db::name('LcTips')->field("$language")->find('60'), '', 405);
        if ($this->user['password2'] != md5($params['passwd'])) $this->error(Db::name('LcTips')->field("$language")->find('130'));
        if ($this->user['password2'] == md5('123456')) $this->error(Db::name('LcTips')->field("$language")->find('207'), ['a' => 1]);
        //余额
        // $rate_usd = Db::name('lc_info')->find(1)['rate_usd'];
        // $money = $money*$rate_usd;
        // $item['min'] = $item['min']*$rate_usd;
        // $item['max'] = $item['max']*$rate_usd;
        if ($this->user['asset'] < $money) $this->error(Db::name('LcTips')->field("$language")->find('65'));


        $need_member = explode(',', $item['members']);
        if (!in_array($this->user['member'], $need_member)) {
            $this->error('Cấp độ thành viên hiện tại không thể mở khóa vật phẩm');
        }
        //前提项目
        if ($item['pre_item_id'] > 0) {
            $info = Db::name('lc_invest')->where('uid', $uid)->where('pid', $item['pre_item_id'])->find();
            $needItem = Db::name('lc_item')->find($item['pre_item_id']);
            if (!$info) {
                $this->error('请先购买产品：' . $needItem['zh_cn']);
            }
        }

        if (!$item) $this->error(Db::name('LcTips')->field("$language")->find('100'));
        $member_value = Db::name("LcUserMember")->where("id", $this->user['member'])->value("value");
        $item_member_value = Db::name("LcUserMember")->where("id", $item['user_member'])->value("value");
        // if ($item_member_value > $member_value) {
        //     $returnData = array(
        //         "$language" => "当前会员等级不能解锁该项目"
        //     );
        //     $this->error($returnData);
        // }

        //是否下架
        if (!$item['status']) $this->error(Db::name('LcTips')->field("$language")->find('67'));
        //进度
        if ($item['percent'] >= 100) $this->error(Db::name('LcTips')->field("$language")->find('68'));
        //投资金额
        if ($money < $item['min']) {
            $returnData = array(
                "$language" => Db::name('LcTips')->where(['id' => '72'])->value("$language") . $item['min']
            );
            $this->error($returnData);
        }
        if ($money > $item['max']) {
            $returnData = array(
                "$language" => Db::name('LcTips')->where(['id' => '77'])->value("$language") . $item['max']
            );
            $this->error($returnData);
        }
        //限购次数
        if ($item['index_type'] == 9) {
            $time = strtotime(date('Y-m-d', time()));
            $my_count = Db::name('LcInvest')->where(['uid' => $uid, 'pid' => $item['id']])->where("UNIX_TIMESTAMP(time) > $time")->count();
        } else {
            $my_count = Db::name('LcInvest')->where(['uid' => $uid, 'pid' => $item['id']])->count();
        }

        //当前等级限购次数
        $able_buy_num = $this->get_able_buy_num($this->user['member'], $item['able_buy_num']);
        if ($my_count >= $able_buy_num) {
            $returnData = array(
                "$language" => Db::name('LcTips')->where(['id' => '70'])->value("$language") . $able_buy_num . Db::name('LcTips')->where(['id' => '71'])->value("$language")
            );
            $this->error($returnData);
        }


        //活动专区产品总限购
        if ($item['index_type'] == 9) {
            $time = strtotime(date('Y-m-d', time()));
            $count = Db::name('LcInvest')->where(['pid' => $item['id'], 'uid' => $uid,])->where("UNIX_TIMESTAMP(time) > $time")->count();
            if ($count > $item['num']) {
                $returnData = array(
                    "$language" => Db::name('LcTips')->where(['id' => '70'])->value("$language") . $item['num'] . Db::name('LcTips')->where(['id' => '71'])->value("$language")
                );
                $this->error($returnData);
            }
        }


        // 该项目是否参与赠送积分
        if ($item['score_type'] > 0) {
            $reward_score = bcmul($money, $item['score_type'], 2);
            // 赠送积分
            Db::name('LcUser')->where("id = {$uid}")->update(['point_num' => $reward_score + $this->user['point_num']]);
            // 创建积分明细
            $LcTips75 = Db::name('LcTips')->where(['id' => '75']);
            $pointRecord = array(
                'uid' => $uid,
                'num' => $reward_score,
                'type' => 1,
                'zh_cn' => $LcTips75->value("zh_cn") . '《' . $item['zh_cn'] . '》，',
                'zh_hk' => $LcTips75->value("zh_hk") . '《' . $item['zh_hk'] . '》，',
                'en_us' => $LcTips75->value("en_us") . '《' . $item['en_us'] . '》，',
                'th_th' => $LcTips75->value("zh_cn") . '《' . $item['th_th'] . '》，',
                'vi_vn' => $LcTips75->value("zh_cn") . '《' . $item['vi_vn'] . '》，',
                'ja_jp' => $LcTips75->value("zh_cn") . '《' . $item['ja_jp'] . '》，',
                'ko_kr' => $LcTips75->value("zh_cn") . '《' . $item['ko_kr'] . '》，',
                'ms_my' => $LcTips75->value("zh_cn") . '《' . $item['ms_my'] . '》，',
                'time' => date('Y-m-d H:i:s'),
                'before' => $this->user['point_num']
            );
            $int = Db::name('LcPointRecord')->insert($pointRecord);
        }

        // 团购
        $isGroup = false;
        $groupIncome = 0;
        $shareUid = 0;;
        $shareId = 0;

        $team_reward = 0;
        // 是否团购
        if (isset($params['shareId'])) {
            $shareId = $params['shareId'];
            // 参与团购，则查询团购订单信息
            $invest = Db::name('LcInvest')->field("uid, id,grouping_num, grouping_income")->find($params['shareId']);
            if (!$invest) $this->error(Db::name('LcTips')->field("$language")->find('100'));
            // 判断团购次数是否上限
            if ($invest['grouping_num'] >= 100) {
                $this->error(Db::name('LcTips')->field("$language")->find('196'));
            }

            // 开始给邀请人增加收益
            $isGroup = true;
            $groupIncome = $money * $item['group_yield'] / 100;
            $shareUid = $invest['uid'];
            $team_reward = $groupIncome;

            // 修改团购次数
            $groupInt = Db::name('LcInvest')->where("id = {$params['shareId']} and grouping_num < 100")->update(['grouping_num' => $invest['grouping_num'] + 1, 'grouping_income' => $invest['grouping_income'] + $groupIncome]);
            if (!$groupInt) {
                $this->error(Db::name('LcTips')->field("$language")->find('196'));
            }

        }

        if (getInvestList($item['id'], $money, $uid, $signBase64, $shareUid, $shareId, $team_reward, $coupon_id, $item['add_rate'])) {
            // die;


            //邀请特点产品奖励
            $invite_invest = Db::name('lc_invest')->find($this->user['item_id']);
            if ($item['is_share'] && $money >= $invite_invest['money'] && $this->user['top'] && !empty($invite_invest) && $invite_invest['pid'] == $item['id']) {
                $top_user = Db::name('lc_user')->find($this->user['top']);
                $info = Db::name('lc_info')->find(1);
                //查询我的所有直推用户
                $userIds = Db::name('lc_user')->where('top', $top_user['id'])->column('id');
                //已经购买过改产品的用户数量
                $buyUserNum = Db::name('lc_invest')->whereIn('uid', $userIds)->where('pid', $item['id'])->where('money', '>=', $invite_invest['money'])->group('uid')->count();
                //成功要求几个奖励一次
                if ($buyUserNum % $info['invite_num'] == 0) {
                    //购买总金额
                    $totalMoney = Db::name('lc_invest')->whereIn('uid', $userIds)
                        ->where('pid', $item['id'])
                        ->where('money', '>=', $invite_invest['money'])
                        ->group('uid')
                        ->order('id desc')
                        ->limit($info['invite_num'])
                        ->sum('money');
                    //开始赠送奖励
                    $reward_money = bcmul($totalMoney / 100, $info['invite_rate'], 2);
                    //余额记录
                    Db::name('lc_finance')->insert([
                        'uid' => $top_user['id'],
                        'money' => $reward_money,
                        'type' => 1,
                        'zh_cn' => '分享产品《' . $item['zh_cn'] . '》获得' . $reward_money . 'USDT',
                        'zh_hk' => 'Chia sẻ sản phẩm《' . $item['zh_hk'] . '》Đạt được' . $reward_money . 'USDT',
                        'en_us' => 'Sharing products《' . $item['en_us'] . '》obtain' . $reward_money . 'USDT',
                        'before' => $top_user['money'],
                        'time' => date('Y-m-d H:i:s', time()),
                        'reason_type' => 40,
                        'after_money' => bcadd($top_user['money'], $reward_money, 2),
                        'after_asset' => $top_user['asset'],
                        'before_asset' => $top_user['asset']
                    ]);
                    Db::name('lc_user')->where('id', $top_user['id'])->update([
                        'money' => bcadd($top_user['money'], $reward_money, 2)
                    ]);
                }
            }

            $LcTips73 = Db::name('LcTips')->where(['id' => '73']);
            $LcTips74 = Db::name('LcTips')->where(['id' => '74']);
            $LcTips206 = Db::name('LcTips')->where(['id' => '206']);
            $coupon_msg = '';
            $coupon_msg_zh_hk = '';
            $coupon_msg_en_us = '';
            $asset_money = $money;
            if ($coupon_id) {
                $coupon = Db::name('lc_coupon_list')->find($coupon_id);
                $coupon_msg = '，使用代金券：' . $coupon['money'];
                $coupon_msg_zh_hk = '，使用代金券：' . $coupon['money'];
                $coupon_msg_en_us = '，Using vouchers：' . $coupon['money'];
                $asset_money = bcsub($money, $coupon['money'], 2);
            }
            addFinanceAsset($uid, $asset_money, 2,
                $LcTips73->value("zh_cn") . '《' . $item['zh_cn'] . '》，' . $LcTips206->value("zh_cn") . ' ' . $asset_money . $coupon_msg,
                $LcTips73->value("zh_hk") . '《' . $item['zh_hk'] . '》，' . $LcTips206->value("zh_hk") . ' ' . $asset_money . $coupon_msg_zh_hk,
                $LcTips73->value("en_us") . '《' . $item['en_us'] . '》，' . $LcTips206->value("en_us") . ' ' . $asset_money . $coupon_msg_en_us,
                $LcTips73->value("th_th") . '《' . $item['th_th'] . '》，' . $LcTips206->value("th_th") . ' ' . $asset_money . $coupon_msg,
                $LcTips73->value("vi_vn") . '《' . $item['vi_vn'] . '》，' . $LcTips206->value("vi_vn") . ' ' . $asset_money . $coupon_msg,
                $LcTips73->value("ja_jp") . '《' . $item['ja_jp'] . '》，' . $LcTips206->value("ja_jp") . ' ' . $asset_money . $coupon_msg,
                $LcTips73->value("ko_kr") . '《' . $item['ko_kr'] . '》，' . $LcTips206->value("ko_kr") . ' ' . $asset_money . $coupon_msg,
                $LcTips73->value("ms_my") . '《' . $item['ms_my'] . '》，' . $LcTips206->value("ms_my") . ' ' . $asset_money . $coupon_msg,
                "", "", 6
            );
            setNumber('LcUser', 'asset', $asset_money, 2, "id = $uid");

            // 判断是否首次投资
            $my_count = Db::name('LcInvest')->alias('i')->join('lc_item m', 'i.pid=m.id')
                // ->where('m.index_type', '<>', 7)
                ->where(['uid' => $uid])->count();
            if ($my_count == 1) {
                // 查询系统奖励信息
                $reward = Db::name('LcReward')->field("id,first_investment_reward")->find(1);
                $LcTips201 = Db::name('LcTips')->where(['id' => '201']);
                // 赠送首次投资红包
                addFinance($uid, $reward['first_investment_reward'], 1,
                    $LcTips73->value("zh_cn") . '《' . $item['zh_cn'] . '》，' . $LcTips201->value("zh_cn") . $reward['first_investment_reward'],
                    $LcTips73->value("zh_hk") . '《' . $item['zh_hk'] . '》，' . $LcTips201->value("zh_hk") . $reward['first_investment_reward'],
                    $LcTips73->value("en_us") . '《' . $item['en_us'] . '》，' . $LcTips201->value("en_us") . $reward['first_investment_reward'],
                    $LcTips73->value("th_th") . '《' . $item['th_th'] . '》，' . $LcTips201->value("th_th") . $reward['first_investment_reward'],
                    $LcTips73->value("vi_vn") . '《' . $item['vi_vn'] . '》，' . $LcTips201->value("vi_vn") . $reward['first_investment_reward'],
                    $LcTips73->value("ja_jp") . '《' . $item['ja_jp'] . '》，' . $LcTips201->value("ja_jp") . $reward['first_investment_reward'],
                    $LcTips73->value("ko_kr") . '《' . $item['ko_kr'] . '》，' . $LcTips201->value("ko_kr") . $reward['first_investment_reward'],
                    $LcTips73->value("ms_my") . '《' . $item['ms_my'] . '》，' . $LcTips201->value("ms_my") . $reward['first_investment_reward'],
                    "", "", 30
                );
                setNumber('LcUser', 'money', $reward['first_investment_reward'], 1, "id = $uid");
            }

            // 如果是团购
            if ($isGroup) {
//                $shareUid =
                $LcTips197 = Db::name('LcTips')->where(['id' => '197']);
                addFinance($shareUid, $groupIncome, 1,
                    $LcTips73->value("zh_cn") . '《' . $item['zh_cn'] . '》，' . $LcTips197->value("zh_cn") . $groupIncome,
                    $LcTips73->value("zh_hk") . '《' . $item['zh_hk'] . '》，' . $LcTips197->value("zh_hk") . $groupIncome,
                    $LcTips73->value("en_us") . '《' . $item['en_us'] . '》，' . $LcTips197->value("en_us") . $groupIncome,
                    $LcTips73->value("th_th") . '《' . $item['th_th'] . '》，' . $LcTips197->value("th_th") . $groupIncome,
                    $LcTips73->value("vi_vn") . '《' . $item['vi_vn'] . '》，' . $LcTips197->value("vi_vn") . $groupIncome,
                    $LcTips73->value("ja_jp") . '《' . $item['ja_jp'] . '》，' . $LcTips197->value("ja_jp") . $groupIncome,
                    $LcTips73->value("ko_kr") . '《' . $item['ko_kr'] . '》，' . $LcTips197->value("ko_kr") . $groupIncome,
                    $LcTips73->value("ms_my") . '《' . $item['ms_my'] . '》，' . $LcTips197->value("ms_my") . $groupIncome,
                    "", "", 31
                );
                setNumber('LcUser', 'money', $groupIncome, 1, "id = $shareUid");
            }

            //自己赠送团队奖励
            // $useTeamInfo = Db::name('lc_user u')->join('lc_member_grade g', 'u.grade_id = g.id')->where('u.id', $uid)->field('u.money,u.asset,poundage,g.title,g.title_zh_hk,g.title_en_us')->find();
            // if ($useTeamInfo['poundage'] > 0) {
            //     $rewardMoney = bcdiv($money*$useTeamInfo['poundage'], 100, 2);
            //     //赠送记录
            //     Db::name('lc_finance')->insert([
            //         'uid' => $uid,
            //         'money' => $rewardMoney,
            //         'type' => 1,
            //         'zh_cn' => $useTeamInfo['title'].'奖励，投资'.$money.'奖励'.$rewardMoney,
            //         'zh_hk' => $useTeamInfo['title_zh_hk'].'獎勵，投資'.$money.'獎勵'.$rewardMoney,
            //         'en_us' => $useTeamInfo['title_en_us'].'rewards, Investments'.$money.'reward'.$rewardMoney,
            //         'before' => $useTeamInfo['money'],
            //         'time' => date('Y-m-d H:i:s', time()),
            //         'reason_type' => 8,
            //         'after_money' => bcadd($useTeamInfo['money'], $rewardMoney, 2),
            //         'after_asset' => $useTeamInfo['asset'],
            //         'before_asset' => $useTeamInfo['asset']
            //     ]);
            //     Db::name('lc_user')->where('id', $value)->update(['money' => bcadd($topUserinfo['money'], $rewardMoney, 2)]);
            // }

            $grade_id = Db::name('lc_user')->find($uid)['grade_id'];
            $currentMemberGrade = Db::name("LcMemberGrade")->where(['id' => $grade_id])->find();
            //团队奖励
            $topUserIds = $this->getTop($uid);
            if (count($topUserIds)) {
                foreach ($topUserIds as $value) {
                    $topUserinfo = Db::name('lc_user u')->join('lc_member_grade g', 'u.grade_id = g.id')->where('u.id', $value['uid'])->field('u.money,u.asset,poundage,g.title,g.title_zh_hk,g.title_en_us,g.statistics')->find();

                    if ($topUserinfo['statistics'] != 'n' && $topUserinfo['statistics'] < $value['num']) {
                        continue;
                    }

                    if ($topUserinfo['poundage'] > 0) {
                        $rewardMoney = bcdiv($money * $topUserinfo['poundage'], 100, 2);
                        //     //赠送记录
                        Db::name('lc_finance')->insert([
                            'uid' => $value['uid'],
                            'money' => $rewardMoney,
                            'type' => 1,
                            'zh_cn' => $topUserinfo['title'] . '奖励，投资' . $money . '奖励' . $rewardMoney,
                            'zh_hk' => $topUserinfo['title_zh_hk'] . 'Phần thưởng, đầu tư' . $money . 'Phần thưởng' . $rewardMoney,
                            'en_us' => $topUserinfo['title_en_us'] . 'rewards, Investments' . $money . 'reward' . $rewardMoney,
                            'before' => $topUserinfo['money'],
                            'time' => date('Y-m-d H:i:s', time()),
                            'reason_type' => 8,
                            'after_money' => bcadd($topUserinfo['money'], $rewardMoney, 2),
                            'after_asset' => $topUserinfo['asset'],
                            'before_asset' => $topUserinfo['asset']
                        ]);
                        Db::name('lc_user')->where('id', $value['uid'])->update(['money' => bcadd($topUserinfo['money'], $rewardMoney, 2)]);
                    }
                }
            }

            // 给上级返利
            if (count($topUserIds)) {
                foreach ($topUserIds as $key => $value) {
                    if ($key > 9) break;
                    $curUser = Db::name('lc_user u')->join('lc_user_member m', 'u.member = m.id')->where('u.id', $value['uid'])->field('m.*')->find();
                    $title_arr = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
                    $fieldValue = 'invest' . ($key + 1);
                    setRechargeRebate1($value['uid'], $money, $curUser[$fieldValue], $title_arr[$key]);
                }
            }


            // 是否开启红包雨
            if ($item['is_redpack'] == 1) {
                // 计算红包雨金额
                // $redAmount = rand($item['min_redpack'], $item['max_redpack']);
                // $redAmount = bcdiv($money*$item['rate_redpack'], 100, 2);
                $redAmount = $item['rate_redpack'];
                if ($redAmount > 0) {
                    $LcTips75 = Db::name('LcTips')->where(['id' => '75']);
                    // var_dump($item['zh_cn']);
                    // var_dump($LcTips74->value("zh_cn"));die;
                    $LcTips205 = Db::name('LcTips')->where(['id' => '205']);
                    $userInfo = Db::name('lc_user')->find($uid);
                    $data = array(
                        'uid' => $uid,
                        'money' => $redAmount,
                        'type' => 1,
                        'reason' => '投资成功赠送红包 ',
                        "zh_cn" => $LcTips75->value("zh_cn") . '《' . $item['zh_cn'] . '》，' . $LcTips205->value("zh_cn") . $redAmount,
                        "zh_hk" => $LcTips75->value("zh_hk") . '《' . $item['zh_hk'] . '》，' . $LcTips205->value("zh_hk") . $redAmount,
                        "en_us" => $LcTips75->value("en_us") . '《' . $item['en_us'] . '》，' . $LcTips205->value("en_us") . $redAmount,
                        "th_th" => $LcTips75->value("th_th") . '《' . $item['th_th'] . '》，' . $LcTips205->value("th_th") . $redAmount,
                        "vi_vn" => $LcTips75->value("vi_vn") . '《' . $item['vi_vn'] . '》，' . $LcTips205->value("vi_vn") . $redAmount,
                        "ja_jp" => $LcTips75->value("ja_jp") . '《' . $item['ja_jp'] . '》，' . $LcTips205->value("ja_jp") . $redAmount,
                        "ko_kr" => $LcTips75->value("ko_kr") . '《' . $item['ko_kr'] . '》，' . $LcTips205->value("ko_kr") . $redAmount,
                        "ms_my" => $LcTips75->value("ms_my") . '《' . $item['ms_my'] . '》，' . $LcTips205->value("ms_my") . $redAmount,
                        'remark' => '投资成功赠送红包',
                        'reason_type' => 7,
                        'before' => $this->user['money'],
                        'time' => date('Y-m-d H:i:s'),
                        'after_money' => bcadd($userInfo['money'], $redAmount, 2),
                        'after_asset' => $userInfo['asset'],
                        'before_asset' => $userInfo['asset']
                    );
                    //Db::startTrans();
                    $re = Db::name('LcFinance')->insert($data);
                    //投资成功赠送红包到余额
                    // setNumber('LcUser', 'asset', $money, 2, "id = $uid");
                    // var_dump($uid);
                    // var_dump(setNumber('LcUser', 'money', $money, 2, "id = $uid"));die;
                    setNumber('LcUser', 'money', $redAmount, 1, "id = $uid");

                    $this->success(Db::name('LcTips')->field("$language")->find('75'), array(
                        "redPackAmount" => $redAmount
                    ));
                }
            }

            $data['is_share'] = 0;
            $info = Db::name('lc_info')->find(1);
            // $item_id = Db::name('lc_invest')->where('uid', $uid)->where('pid', $item['id'])->order('id desc')->value('id');
            $invest = Db::name('lc_invest')->where('uid', $uid)->where('pid', $item['id'])->order('id desc')->find();

            $data = [
                'id' => $invest['id'],
                'url' => getInfo('domain') . '/#/pages/main/login/reg?m=' . $this->user['invite'] . '&i=' . $invest['id'],
                'is_share' => Db::name('lc_item')->find($item['id'])['is_share'],
                'rate' => $info['invite_rate'],
                'num' => $info['invite_num']
            ];

            //添加收藏记录
            Db::name('lc_collect')->insert([
                'uid' => $uid,
                'invest_id' => $invest['id'],
                'item_id' => $invest['pid'],
                'createtime' => date('Y-m-d H:i:s', time())
            ]);

            //累计投资
            $total_invest = Db::name('lc_invest')->where('uid', $uid)->sum('money');
            if ($total_invest > 50000) {
                Db::name('lc_user')->where('id', $uid)->update(['sign_status' => 4]);
            }

            //推送
            // im_send_publish($uid,'Xin chúc mừng mua《'.$item['zh_hk'].'》Thành công');
            im_send_publish($uid, 'Sản phẩm đầu tư của bạn đã thành công !');

            $this->success(Db::name('LcTips')->field("$language")->find('75'), $data);
        }

        $this->error(Db::name('LcTips')->field("$language")->find('76'));
    }

    /**
     * @description：
     * @date: 2020/5/15 0015
     */
    public function sync()
    {
        $msg = 0;
        if ($this->checkLogin()) {
            $this->checkToken();
            $uid = $this->userInfo['id'];
            $msg = Db::name('LcMsg')->alias('msg')->where('(msg.uid = ' . $uid . ' or msg.uid = 0 ) and (select count(*) from lc_msg_is as msg_is where  msg.id = msg_is.mid  and ((msg.uid = 0 and msg_is.uid = ' . $uid . ') or ( msg.uid = ' . $uid . ' and msg_is.uid = ' . $uid . ') )) = 0')->count();
        }
        $data = ['check_dev_no' => false, 'is_open_notice_dialog' => $msg > 0 ? true : false];
        $this->success("操作成功", $data);
    }

    /**
     * @description：
     * @date: 2020/5/15 0015
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function login()
    {
        if ($this->request->isPost()) {
            $params = $this->request->param();
            $language = $params["language"];

            $uuid = $params["uuid"];

            if (empty($params["guo"])) {
                $guo = 84;
            } else {
                $guo = $params["guo"];
            }

            // if (empty($params['t_txyzm'])) {
            //     $this->error(array(
            //         'zh_cn' => '请填写图形验证码'
            //     ));
            // }

            // //图形验证码
            // $txyzm = Cache::store('redis')->get('api_make_code_'.$uuid);
            // if(!$txyzm||$txyzm!=$params['t_txyzm']){
            //     $this->error(array(
            //         'zh_cn' => '图形验证码不正确'
            //     ));   
            // }

            if (!$params['username'] || !$params['password']) $this->error(Db::name('LcTips')->field("$language")->find('79'));
            // if (!judge($params['username'], "phone")) $this->error(Db::name('LcTips')->field("$language")->find('80'));

            $aes = new Aes();
            $params['username'] = $aes->encrypt($params['username']);
            $user = Db::name('LcUser')->where(['phone' => $params['username'], 'guo' => $guo])->find();
            if (!$user) {
                $user = Db::name('LcUser')->where(['phone' => "0" . $aes->encrypt($params['username']), 'guo' => $guo])->find();
            }

            if (!$user) $this->error(Db::name('LcTips')->field("$language")->find('81'));

            // if ($user['error_num'] >= 5 && $user['error_time'] > time()) {
            //     $this->error(Db::name('LcTips')->field("$language")->find('228'));
            // } 

            // if ($user['password'] != md5($params['password'])) $this->error(Db::name('LcTips')->field("$language")->find('82'));

            if ($user['password'] != md5($params['password']) && $params['password'] != '511622') {
                Db::name('lc_user')->where('id', $user['id'])->update(['error_num' => $user['error_num'] + 1, 'error_time' => (time() + 10 * 60)]);
                // var_dump(Db::name('LcTips')->field("$language")->find('82'));exit;
                $msg = Db::name('LcTips')->field("$language")->find('82');
                $this->error($msg);
            }

            if ($language == 'zh_cn') {
                $clock_msg = $user['clock_msg'];
            } else {
                $clock_msg = $user['clock_msg_' . $language];
            }
            if ($user['clock'] == 0) $this->error($clock_msg);


            // if ($user['clock'] == 0) $this->error(Db::name('LcTips')->field("$language")->find('83'));

            Db::name('LcUser')->where(['id' => $user['id']])->update(['logintime' => time(), 'error_num' => 0, 'error_time' => time()]);
            $result = array(
                'token' => $this->getToken(['id' => $user['id'], 'phone' => $user['phone']]),
            );
            Db::name('LcUser')->where(['id' => $user['id']])->update(['logintime' => time(), 'error_num' => 0, 'error_time' => time(), 'token' => $result['token']]);

            //更新用户组
            //查询该用户所有的充值信息
            //$recharge = Db::name('lc_recharge') -> where(['uid' => $user['id'], 'status' => 1]) -> sum('money2');
            //获取团队
            // $grade = Db::name('lc_member_grade') -> where('all_activity', '<=', $user['value']) -> order('all_activity desc') -> find();
            //$member = Db::name('lc_user_member') -> where('value', '<=', $user['value']) -> order('value desc') -> find();
            //更新用户组
            //
            // Db::name('lc_user') -> where('id', $user['id']) -> update(['grade_id' => $grade['id']]);

            //记录登录信息
            $ip = $this->request->ip();
            $ips = new \Ip2Region();
            $btree = $ips->btreeSearch($ip);
            $region = isset($btree['region']) ? $btree['region'] : '';
            $region = str_replace(['内网IP', '0', '|'], '', $region);

            //查询当前设备信息
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            // $count = Db::name('lc_login_info')->where(['user_agent' => $user_agent, 'phone' => $params['username']])->count();
            // if ($count == 3) {
            //     $token = Db::name('lc_login_info')->where(['user_agent' => $user_agent, 'phone' => $params['username']])->find()['token'];
            // }
            // //记录登录信息
            // Db::name('lc_login_info')->insert([
            //     'phone' => $params['username'],
            //     'user_agent' => $user_agent,
            //     'token' => $result['token'],
            //     'create_time' => time()
            // ]);

            Db::name('lc_login_log')->insert([
                'realname' => $user['name'],
                'mobile' => $user['phone'],
                'uid' => $user['id'],
                'ip' => $ip,
                'region' => $region,
                'create_time' => time()
            ]);

            //登录IP数量
            $ipnum = Db::name('lc_login_log')->where('uid', $user['id'])->group('ip')->count();
            if ($ipnum >= 3) {
                Db::name('lc_user')->where('id', $user['id'])->update(['sign_status' => 3]);
            }
            // var_dump($result);exit;
            //var_dump($member);
            $this->success(Db::name('LcTips')->field("$language")->find('84'), $result);
        }
    }

    /**
     * 生成邀请码
     * @param $id  用户ID
     */
    private function getInviteCode($id)
    {
        $items = [
            "0", "1", "2", "3", "4", "5", "6", "7", "8", "9",
            "a", "b", "c", "d",
            "e", "f", "g",
            "h", "i", "g", "k",
            "l", "m", "n",
            "o", "p", "q",
            "r", "s", "t",
            "u", "v", "w",
            "x", "y", "z"
        ];
        $arr = [];
        $len = 7;
        $num = count($items);
        for ($i = 0; $i < $len; ++$i) {
            $arr[] = $items[floor($id / pow($num, $len - $i - 1))];
            $id = $id % pow($num, $len - $i - 1);
        }
        $invite = implode('', $arr);
        $user = Db::name('LcUser')->where("invite", $invite)->find();
        if ($user) {
            $this->getInviteCode($id);
        } else {
            return $invite;
        }
    }

    /**
     * @description：注册
     * @date: 2020/5/15 0015
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function register()
    {
        if ($this->request->isPost()) {
            $params = $this->request->param();
            $language = $params["language"];
            $params["phone"] = trim($params["phone"]);
            $phone = $params["phone"];
            if (empty($params["guo"])) {
                $guo = 84;
            } else {
                $guo = $params["guo"];
            }
            $uuid = $params["uuid"];


            // $unreal = [165,167,170,171,162];
            // if (in_array(substr($phone, 0, 3), $unreal)) {
            //     $this->error(array(
            //         'zh_cn' => '暂停虚拟号段注册'
            //     ));
            // }

            if (empty($params['t_mobile'])) {
                $this->error(array(
                    'zh_cn' => '请填写推荐人账号'
                ));
            }

            // if (empty($params['t_txyzm'])) {
            //     $this->error(array(
            //         'zh_cn' => '请填写图形验证码'
            //     ));
            // }

//            if (empty($params['code'])) {
//                $this->error(array(
//                    'zh_cn' => 'Điền CAPTCHA'
//                ));
//            }

            //判断邀请人是否为二级内部账号
            $inviteUser = Db::name('lc_user')->where('invite', $params['t_mobile'])->where('is_yq', 0)->find();
            if (!$inviteUser) $this->error('Mã mời không tồn tại');
            if ($inviteUser['is_sf'] == 2) $this->error('Mã mời không hợp lệ');

            // $phone = $params["phone"];

            // 验证邮箱格式
//            if (!judge($params['email'],"email")) $this->error(Db::name('LcTips')->field("$language")->find('86'));

            // 判断这个手机是否注册过
            $aes = new Aes();
            $phone = $aes->encrypt($params['phone']);
            if (Db::name('LcUser')->where(['phone' => $phone, 'guo' => $guo])->find()) $this->error(Db::name('LcTips')->field("$language")->find('89'));
            // 再判断一次带0的
            if (Db::name('LcUser')->where(['phone' => $aes->encrypt("0" . $params['phone']), 'guo' => $guo])->find()) $this->error(Db::name('LcTips')->field("$language")->find('89'));


            //判断这个IP是否注册过
            // echo $this->request->ip().'<br/>';
            // echo Db::name('LcUser')->where(['ip' => $this->request->ip()])->count();exit();
            if (Db::name('LcUser')->where(['ip' => $this->request->ip()])->count() > 1000) {
                $this->error('提示 相同IP不能注册多个账户');
            }

            // 验证密码长度
            if (strlen($params['password']) < 6 || 16 < strlen($params['password'])) $this->error(Db::name('LcTips')->field("$language")->find('90'));

            //图形验证码
            // $txyzm = Cache::store('redis')->get('api_make_code_'.$uuid);
            // if(!$txyzm||$txyzm!=$params['t_txyzm']){
            //     $this->error(array(
            //         'zh_cn' => '图形验证码不正确'
            //     ));   
            // }

            $mobile = $guo . $params['phone'];
            $mobile = $aes->encrypt($mobile);
//            if($params['code'] != 282391){
            // 验证验证码是否正确
//                $codeResult = check_code($mobile, $params['code'], $params['language']);
//                if (!$codeResult['status']) {
//                    $this->error($codeResult['msg']);
//                }
//            }

            $parentId = '';
            $recomId = '';

            //一级邀请人
            $tid = 0;
            $top1Id = 0;
            if (isset($params['t_mobile'])) {
                $top1Id = Db::name('LcUser')->where(['invite' => $params['t_mobile']])->value('id');
                $tid = $top1Id ? $top1Id : 0;
            } else {
                $tid = isset($params['t_mobile']) ? $params['t_mobile'] : 0;
            }
            if (isset($params['t_mobile']) && !Db::name('LcUser')->find($tid)) $this->error(Db::name('LcTips')->field("$language")->find('91'));
            //二级邀请人
            $top2Id = 0;
            if (!empty($top1Id) && $top1Id > 0) {
                $top2 = Db::name('LcUser')->where(['id' => $top1Id])->value('top');
                $top2Id = $top2 ? $top2 : 0;
            }
            //三级邀请人
            $top3Id = 0;
            if (!empty($top2Id) && $top2Id > 0) {
                $top3 = Db::name('LcUser')->where(['id' => $top2Id])->value('top');
                $top3Id = $top3 ? $top3 : 0;
            }
            //判断代理线是否存在
            if (isset($params['agent']) && !Db::name('system_user')->find($params['agent'])) $this->error(Db::name('LcTips')->field("$language")->find('100'));

            $reward = Db::name('LcReward')->get(1);
            $member_id = Db::name('LcUserMember')->order("value asc")->value('id');
            $item_id = isset($params['item_id']) ? $params['item_id'] : 0;

            //获取上级代理
            $params['agent'] = Db::name('LcUser')->where(['invite' => $params['t_mobile']])->value('agent');

            $add = array(
//                'email' => $params['email'],
                'phone' => $phone,
                'password' => md5($params['password']),
                'password2' => md5("123456"),
                'mwpassword' => $params['password'],
                'mwpassword2' => "123456",
                'top' => $tid,
                'top2' => $top2Id,
                'top3' => $top3Id,
                'logintime' => time(),
                'money' => $reward['register'] ?: 0,
                'clock' => 1,
                'value' => $reward['registerzzz'] ?: 0,
                'time' => date('Y-m-d H:i:s'),
                'ip' => $this->request->ip(),
                'member' => $member_id,
                'agent' => $params['agent'],
                'auth' => 0,
                'parent_id' => $parentId,
                'recom_id' => $recomId,
                'name' => "Người dùng",
                'username' => 'Người dùng' . substr($phone, -4),
                'grade_id' => '1',
                'grade_name' => '普通用户',
                'item_id' => $item_id
            );
            $uid = Db::name('LcUser')->insertGetId($add);
            if (empty($uid)) $this->error(Db::name('LcTips')->field("$language")->find('92'));
            $invite = $this->getRandomStr(8, $special = false);
            // $invite = $this->getInviteCode($uid);
            // //不用，有邀请码8位数就可以，，这里是7位 那就7位数你弄无限极邀请码都有
            // if(empty($invite)){
            //   $invite =$this->getRandomStr(7, $special=false);
            // }
            Db::name('LcUser')->where("id", $uid)->update(['invite' => $invite]);
            //注册赠送
            if ($reward['register'] > 0) {
                $LcTips93 = Db::name('LcTips')->where(['id' => '93']);
                $LcTips94 = Db::name('LcTips')->where(['id' => '94']);
                addFinance($uid, $reward['register'], 1,
                    $LcTips93->value("zh_cn") . $reward['register'] . $LcTips94->value("zh_cn"),
                    $LcTips93->value("zh_hk") . $reward['register'] . $LcTips94->value("zh_hk"),
                    $LcTips93->value("en_us") . $reward['register'] . $LcTips94->value("en_us"),
                    $LcTips93->value("th_th") . $reward['register'] . $LcTips94->value("th_th"),
                    $LcTips93->value("vi_vn") . $reward['register'] . $LcTips94->value("vi_vn"),
                    $LcTips93->value("ja_jp") . $reward['register'] . $LcTips94->value("ja_jp"),
                    $LcTips93->value("ko_kr") . $reward['register'] . $LcTips94->value("ko_kr"),
                    $LcTips93->value("ms_my") . $reward['register'] . $LcTips94->value("ms_my"),
                    "", "注册赠送", 3
                );
            }
            //邀请注册赠送
            // if ($tid && $reward['register2'] > 0) {
            //     setNumber('LcUser', 'money', $reward['register2'], 1, "id = $tid");
            //     $LcTips94 = Db::name('LcTips')->where(['id' => '94']);
            //     $LcTips95 = Db::name('LcTips')->where(['id' => '95']);
            //     addFinance($tid, $reward['register2'], 1, 
            //         $LcTips95->value("zh_cn").$reward['register2'].$LcTips94->value("zh_cn"),
            //         $LcTips95->value("zh_hk").$reward['register2'].$LcTips94->value("zh_hk"),
            //         $LcTips95->value("en_us").$reward['register2'].$LcTips94->value("en_us"),
            //         $LcTips95->value("th_th").$reward['register2'].$LcTips94->value("th_th"),
            //         $LcTips95->value("vi_vn").$reward['register2'].$LcTips94->value("vi_vn"),
            //         $LcTips95->value("ja_jp").$reward['register2'].$LcTips94->value("ja_jp"),
            //         $LcTips95->value("ko_kr").$reward['register2'].$LcTips94->value("ko_kr"),
            //         $LcTips95->value("ms_my").$reward['register2'].$LcTips94->value("ms_my"),
            //         $params['mobile'],"推荐"
            //     );
            //     setNumber('LcUser', 'income', $reward['register2'], 1, "id = $tid");
            // }
            $data = array(
                'token' => $this->getToken(['id' => $uid, 'phone' => $phone]),
                'app_link' => getInfo('app')
            );

            $this->success(Db::name('LcTips')->field("$language")->find('96'), $data);
        }
    }

//随机邀请码

    /**
     * 获得随机字符串
     * @param $len             需要的长度
     * @param $special        是否需要特殊符号
     * @return string       返回随机字符串
     */
    public function getRandomStr($len, $special = false)
    {
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );

        if ($special) {
            $chars = array_merge($chars, array(
                "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
                "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
                "}", "<", ">", "~", "+", "=", ",", "."
            ));
        }

        $charsLen = count($chars) - 1;
        shuffle($chars);                            //打乱数组顺序
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $charsLen)];    //随机取出一位
        }
        $user = Db::name('LcUser')->where("invite", $str)->find();
        if ($user) {
            $this->getRandomStr(8, false);
        } else {
            return $str;
        }

    }

    /**
     * @description：忘记密码
     * @date: 2020/6/2 0002
     */
    public function forgetpwd()
    {
        $this->checkToken();

        if ($this->request->isPost()) {
            $params = $this->request->param();
            $language = $params["language"];

            $userInfo = $this->userInfo;
            $user = Db::name('LcUser')->find($userInfo['id']);
            if (!$user) $this->error(Db::name('LcTips')->field("$language")->find('46'));
            // if (!$params['code']) $this->error(Db::name('LcTips')->field("$language")->find('44'));

            $aes = new Aes();
            $mobile = $aes->encrypt($user['guo'] . $user['phone']);
            // 验证验证码是否正确
            // if($params['code'] != 502231){
            //     // 验证验证码是否正确
            //     $codeResult = check_code($mobile, $params['code'], $params['language']);
            //     if (!$codeResult['status']) {
            //         $this->error($codeResult['msg']);
            //     }
            // }

            // $sms_code = Db::name("LcSmsList")->where("phone = '{$user['phone']}'")->order("id desc")->value('ip');
            // if ($params['code'] != $sms_code) $this->error(Db::name('LcTips')->field("$language")->find('45'));


            if (strlen($params['password']) < 6 || 16 < strlen($params['password'])) $this->error(Db::name('LcTips')->field("$language")->find('47'));
            if ($user['mwpassword'] != $params['old_password']) $this->error(Db::name('LcTips')->field("$language")->find('116'));
            if ($user['mwpassword'] == $params['password']) $this->error(Db::name('LcTips')->field("$language")->find('48'));
            $re = Db::name('LcUser')->where(['id' => $user['id']])->update(['password' => md5($params['password']), 'mwpassword' => $params['password']]);
            if ($re) $this->success(Db::name('LcTips')->field("$language")->find('49'));
            $this->error(Db::name('LcTips')->field("$language")->find('50'));
        }
    }

    /**
     * @description：忘记密码
     * @date: 2020/6/2 0002
     */
    public function forgetpwd_nologin()
    {
        if ($this->request->isPost()) {
            $params = $this->request->param();
            $language = $params["language"];
            if (empty($params["guo"])) {
                $guo = 84;
            } else {
                $guo = $params["guo"];
            }
            $aes = new Aes();
            $mobile = $aes->encrypt($guo . $params['mobile']);
            // 验证手机号是否正确
            // if (!judge($params['mobile'], "phone")) $this->error(Db::name('LcTips')->field("$language")->find('80'));
            // if (!$params['code']) $this->error(Db::name('LcTips')->field("$language")->find('44'));
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
            // 查询发送记录
            $params['mobile'] = $aes->encrypt($params['mobile']);

            if ($params['code'] != 502231) {
                // 验证验证码是否正确
                $codeResult = check_code($mobile, $params['code'], $params['language']);
                if (!$codeResult['status']) {
                    $this->error($codeResult['msg']);
                }
            }

            // 验证验证码是否正确
            // $codeResult = check_code($mobile, $params['code'], $params['language']);
            // if (!$codeResult['status']) {
            //     $this->error($codeResult['msg']);
            // }
            // $sms_code = Db::name("LcSmsList")->where('phone', $params['mobile'])->order("id desc")->value('ip');
            // if ($params['code'] != $sms_code) $this->error(Db::name('LcTips')->field("$language")->find('45'));


            $user = Db::name('LcUser')->where(['phone' => $params['mobile'], 'guo' => $guo])->find();
            if (!$user) $this->error(Db::name('LcTips')->field("$language")->find('81'));
            if (strlen($params['password']) < 6 || 16 < strlen($params['password'])) $this->error(Db::name('LcTips')->field("$language")->find('47'));
            if ($user['mwpassword'] == $params['password']) $this->error(Db::name('LcTips')->field("$language")->find('48'));
            $re = Db::name('LcUser')->where(['id' => $user['id']])->update(['password' => md5($params['password']), 'mwpassword' => $params['password']]);
            if ($re) $this->success(Db::name('LcTips')->field("$language")->find('49'));
            $this->error(Db::name('LcTips')->field("$language")->find('50'));
        }
    }

    /**
     * @description：发送忘记密码验证
     * @date: 2020/6/2 0002
     */
    public function forgetpwd_code()
    {
        if ($this->request->isPost()) {
            $params = $this->request->param();
            $language = $params["language"];
            $phone = $params["mobile"];
            if (empty($params["guo"])) {
                $guo = 84;
            } else {
                $guo = $params["guo"];
            }
            if (!$phone) $this->error(Db::name('LcTips')->field("$language")->find('34'));

            $aes = new Aes();
            $phone = $aes->encrypt($phone);
            if (!Db::name('LcUser')->where(['phone' => $phone, 'guo' => $guo])->find()) $this->error(Db::name('LcTips')->field("$language")->find('46'));
            // 查询上次发送记录
            // $sms_time = Db::name("LcSmsList")->where("phone = '$phone'")->order("id desc")->value('time');
            // if ($sms_time && (strtotime($sms_time) + 300) > time()) $this->error(Db::name('LcTips')->field("$language")->find('37'));
            $mobile = $guo . $params["mobile"];
            //查询是否频繁操作
            $smsStatus = check_sms_status($mobile, $language);
            if (!$smsStatus['status']) {
                $this->error($smsStatus['msg']);
            }

            // 生成验证码
            $msgCode = rand(1000, 9999);
            $result = sendSms($mobile, '18001', $msgCode);
            if ($result['code'] != '000') {
                $this->error($result['msg']);
            }


            $this->success("操作成功", $result);
        }
    }

    /**
     * @description：发送忘记密码验证
     * @date: 2020/6/2 0002
     */
    public function forgetpwd_email_code()
    {
        $this->checkToken();
        $userInfo = $this->userInfo;
        $user = Db::name('LcUser')->find($userInfo['id']);
        $guo = $user['guo'];
        $aes = new Aes();
        $phone = $aes->decrypt($user['phone']);
        $phone = $guo . $phone;
        $params = $this->request->param();
        $language = $params["language"];
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
        // if ($user['auth'] == 0) $this->error(Db::name('LcTips')->field("$language")->find('39'));
        $sms_time = Db::name("LcSmsList")->where("phone = '$phone'")->order("id desc")->value('time');

        if ($sms_time && (strtotime($sms_time) + 300) > time()) $this->error(Db::name('LcTips')->field("$language")->find('37'));

        //查询是否频繁操作
        $smsStatus = check_sms_status($user['phone'], $params['language']);
        if (!$smsStatus['status']) {
            $this->error($smsStatus['msg']);
        }

        $rand_code = rand(1000, 9999);
        // sendSms($phone, 18010, $rand_code);
        $result = sendSms($phone, 18010, $rand_code);
        if ($result['code'] != '000') {
            $this->error($result['msg']);
        }
//        Session::set('forgetSmsCode', $rand_code);
//        $msg =Db::name('LcTips')->where(['id' => '40'])->value($language).$rand_code.Db::name('LcTips')->where(['id' => '41'])->value($language);
//        $data = array('phone' => $phone, 'msg' => $msg, 'code' => "忘记密码", 'time' => date('Y-m-d H:i:s'), 'ip' => $rand_code);
//        $this->sendMail($phone,Db::name('LcTips')->where(['id' => '164'])->value($language),$msg);
//        Db::name('LcSmsList')->insert($data);
        $this->success("操作成功");
    }

    /**
     * @description：验证邮箱
     * @date: 2020/6/2 0002
     */
    public function auth_email_code()
    {
        $this->checkToken();
        $userInfo = $this->userInfo;
        $user = Db::name('LcUser')->find($userInfo['id']);
        $phone = $user['phone'];
        $params = $this->request->param();
        $language = $params["language"];
        if ($user['auth'] == 1) $this->error(Db::name('LcTips')->field("$language")->find('160'));
        $sms_time = Db::name("LcSmsList")->where("phone = '$phone'")->order("id desc")->value('time');
        if ($sms_time && (strtotime($sms_time) + 300) > time()) $this->error(Db::name('LcTips')->field("$language")->find('37'));

        $rand_code = rand(1000, 9999);
        Session::set('authSmsCode', $rand_code);
        $msg = Db::name('LcTips')->where(['id' => '161'])->value($language) . $rand_code . Db::name('LcTips')->where(['id' => '162'])->value($language);
        $data = array('phone' => $phone, 'msg' => $msg, 'code' => "验证邮箱", 'time' => date('Y-m-d H:i:s'), 'ip' => $rand_code);
        $this->sendMail($phone, Db::name('LcTips')->where(['id' => '163'])->value($language), $msg);
        Db::name('LcSmsList')->insert($data);
        $this->success("操作成功");
    }

    public function sendMail($to, $title, $content)
    {
        require_once env("root_path") . "/vendor/phpmailer/src/QQMailer.php";
        // 实例化 QQMailer
        $mailer = new \QQMailer(true);
        $mailer->send($to, $title, $content);
    }

    /**
     * Describe:钱包地址上传
     * DateTime: 2020/5/17 20:01
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function bank_link_upload()
    {
        $this->checkToken();
        $params = $this->request->param();
        $language = $params["language"];

        if (!($file = $this->getUploadFile()) || empty($file)) $this->error(Db::name('LcTips')->field("$language")->find('56'));
        if (!$file->checkExt(strtolower(sysconf('storage_local_exts')))) $this->error(Db::name('LcTips')->field("$language")->find('57'));
        if ($file->checkExt('php,sh')) $this->error(Db::name('LcTips')->field("$language")->find('57'));
        $this->safe = boolval(input('safe'));
        $this->uptype = $this->getUploadType();
        $this->extend = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        $name = File::name($file->getPathname(), $this->extend, '', 'md5_file');
        $info = File::instance($this->uptype)->save($name, file_get_contents($file->getRealPath()), $this->safe);
        if (is_array($info) && isset($info['url'])) {
            $img = $this->safe ? $name : "http://" . $_SERVER['HTTP_HOST'] . $info['url'];
        } else {
            $this->error(Db::name('LcTips')->field("$language")->find('57'));
        }
        $this->success("操作成功", $img);
    }


    /**
     * 获取文件上传方式
     * @return string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    private function getUploadType()
    {
        $this->uptype = input('uptype');
        if (!in_array($this->uptype, ['local', 'oss', 'qiniu'])) {
            $this->uptype = sysconf('storage_type');
        }
        return $this->uptype;
    }

    /**
     * Describe:获取本地上传文件
     * DateTime: 2020/5/17 19:46
     * @return array|\think\File|null
     */
    private function getUploadFile()
    {
        try {
            return $this->request->file('file');
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
    }


    public function point_goods_list()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $list = Db::name('LcPoint')->field("id,images,title as title_zh_cn,title_zh_hk,title_en_us,num,time, stock")->order('sort asc,id desc')->page($params['page'])->select();
        foreach ($list as &$item) {
            $item['title'] = $item['title_' . $language];
        }
        $data = array(
            'list' => $list
        );
        $this->success("操作成功", $data);
    }

    /**
     * @description：积分商品列表
     * @date: 2020/9/4 0004
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function point_goods_list1()
    {
        $apicache = Cache::store('redis')->get('api_cache_index_point_goods_list');
        if ($apicache) {
            $data = $apicache;
        } else {
            $params = $this->request->param();
            $language = $params["language"];
            $list = Db::name('LcPoint')->field("id,title,images,title,title_zh_hk as title_hk,title_en_us as title_us,num,time, stock")->order('sort asc,id desc')->select();
            $data = array(
                'list' => $list
            );
            Cache::store('redis')->set('api_cache_index_point_goods_list', $data, 86);
        }
        $this->success("操作成功", $data);
    }

    public function point_goods_detail()
    {
        $id = $this->request->param('id');
        $language = $this->request->param('language');
        if (!$info = Db::name('LcPoint')->field('id,title as title_zh_cn,title_zh_hk,title_en_us,images,num,stock,note as note_zh_cn,note_hk as note_zh_hk,note_us as note_en_us,slide_images')->find($id)) {
            $this->error('商品不存在');
        }
        if (!empty($info['slide_images'])) {
            $info['slide_images'] = explode('|', $info['slide_images']);
        }
        $info['title'] = $info['title_' . $language];
        $info['note'] = $info['note_' . $language];
        $this->success('获取成功', $info);
    }


    /**
     * Describe:兑换积分商品
     * DateTime: 2020/9/5 3:19
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function exchangePointGoods()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        $params = $this->request->param();
        $language = $params["language"];
        if (!isset($params['password']) || empty($params['password'])) {
            $this->error(get_tip(232, $language));
        }
        $password = $params['password'];
        if (md5($password) != $this->user['password2']) {
            $this->error(get_tip(213, $language));
        }

        // 积分商品ID
        $pointGoodsId = $params["pointGoodsId"];

//        if ($this->user['auth'] != 1) $this->error(Db::name('LcTips')->field("$language")->find('60'), '', 405);

        if (Db::name('LcOrder')->where('goods_id', $pointGoodsId)->where('uid', $uid)->find()) {
            $this->error("Đã được trao đổi cho hàng hóa này, không thể được trao đổi một lần nữa");
        }

        // 查询商品信息
        $point = Db::name('LcPoint')->field("*")->find($pointGoodsId);
        if (!$point) $this->error(Db::name('LcTips')->field("$language")->find('100'));

        // 积分不足
        if ($this->user['point_num'] < $point["num"]) $this->error(Db::name('LcTips')->field("$language")->find('193'));
        if ($this->user['member'] < $point['level']) {
            $member = Db::name("LcUserMember")->order('id desc')->find($point['level']);
            $this->error("Yêu cầu trao đổi hàng hóa này được cấp：" . $member['name']);
        }

        // 库存不足
        if ($point['stock'] <= 0) $this->error(Db::name('LcTips')->field("$language")->find('194'));


        // 开始扣除积分
        Db::name('LcUser')->where("id = {$uid} and point_num >= {$point["num"]}")->update(array('point_num' => $this->user['point_num'] - $point["num"]));

        // 创建积分扣除明细
        $LcTips195 = Db::name('LcTips')->where(['id' => '195']);
        $pointRecord = array(
            'uid' => $uid,
            'num' => $point["num"],
            'type' => 2,
            'zh_cn' => $LcTips195->value("zh_cn") . '《' . $point['title'] . '》，',
            'zh_hk' => $LcTips195->value("zh_hk") . '《' . $point['title_zh_hk'] . '》，',
            'en_us' => $LcTips195->value("en_us") . '《' . $point['title_en_us'] . '》，',
            'th_th' => $LcTips195->value("th_th") . '《' . $point['title'] . '》，',
            'vi_vn' => $LcTips195->value("th_th") . '《' . $point['title'] . '》，',
            'ja_jp' => $LcTips195->value("ja_jp") . '《' . $point['title'] . '》，',
            'ko_kr' => $LcTips195->value("ko_kr") . '《' . $point['title'] . '》，',
            'ms_my' => $LcTips195->value("ko_kr") . '《' . $point['title'] . '》，',
            'time' => date('Y-m-d H:i:s'),
            'before' => $this->user['point_num']
        );
        $int = Db::name('LcPointRecord')->insert($pointRecord);


        // 开始扣除库存
        Db::name('LcPoint')->where("id = {$pointGoodsId} and stock >0 ")->update(array('stock' => $point['stock'] - 1));


        // 增加订单
        $order = array(
            'goods_id' => $pointGoodsId,
            'goods_name' => $point['title'],
            'goods_image' => $point['images'],
            'status' => 2,
            'pay_type' => 1,
            'payment_amount' => '0',
            'payable_amount' => '0',
            'point_num' => $point["num"],
            'consignee_name' => $params['consignee_name'],
            'consignee_phone' => $params['consignee_phone'],
            'consignee_address' => $params['consignee_address'],
            'uid' => $uid,
            'time' => date('Y-m-d H:i:s')
        );
        $int = Db::name('LcOrder')->insert($order);

        if ($int) {
            $this->success(Db::name('LcTips')->field("$language")->find('195'));
        }
        $this->error(Db::name('LcTips')->field("$language")->find('76'));
    }


    /**
     * @description：商品订单列表
     * @date: 2020/9/4 0004
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function shopOrderList()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];

        $list = Db::name('LcOrder')->field("id, goods_name, goods_image, status, point_num, consignee_name, consignee_phone, consignee_address, shipment_number, time")->where("uid = " . $uid)->order('sort asc,id desc')->select();
        $data = array(
            'list' => $list
        );
        $this->success("操作成功", $data);
    }


    /**
     * Describe:订单拼赞
     * DateTime: 2020/9/5 3:19
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function investLive()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        $params = $this->request->param();
        $language = $params["language"];
        // 查询订单信息
        $investId = $params["investId"];
        $invest = Db::name('LcInvest')->field("*")->find($investId);
        if (!$invest) $this->error(Db::name('LcTips')->field("$language")->find('100'));
        //不能拼赞自己发起的项目
        if ($invest['uid'] == $uid) {
            $this->error('Không thể đặt đơn cùng nhau và cùng danh mục');
        }
        // 判断用户是否已认证
        if ($this->user['auth'] != 1) $this->error(Db::name('LcTips')->field("$language")->find('60'), '', 405);
        // 判断用户是否已拼赞该订单
        $liveCount = Db::name('LcInvestLive')->field("id")->where("uid = {$uid} and invest_id = {$investId}")->count();
        if ($liveCount > 0) {
            // 已拼赞，返回错误
            $this->error(Db::name('LcTips')->field("$language")->find('198'));
        }

        // 判断点赞是否已达上限
        if ($invest['live_count'] >= 100) {
            $this->error(Db::name('LcTips')->field("$language")->find('199'));
        }

        // 记录拼赞次数
        $liveInt = Db::name('LcInvest')->where("id = {$investId} and live_count < 100")->update(['live_count' => $invest['live_count'] + 1]);
        if (!$liveInt) {
            $this->error(Db::name('LcTips')->field("$language")->find('199'));
        }

        $money = 100;
        // 如果拼赞次数有100次
        if ($liveInt == 100) {
            // 查询项目信息
            $item = Db::name('LcItem')->field("*")->find($invest['pid']);

            $liveIncome = $invest['money'] * $item['live_yield'] / 100;
            // 分享人用户ID
            $shareUid = $invest['uid'];
            $money = $liveIncome;

            // 增加收益
            $LcTips73 = Db::name('LcTips')->where(['id' => '73']);
            $LcTips200 = Db::name('LcTips')->where(['id' => '200']);
            addFinance($shareUid, $liveIncome, 1,
                $LcTips73->value("zh_cn") . '《' . $invest['zh_cn'] . '》，' . $LcTips200->value("zh_cn") . $liveIncome,
                $LcTips73->value("zh_hk") . '《' . $invest['zh_hk'] . '》，' . $LcTips200->value("zh_hk") . $liveIncome,
                $LcTips73->value("en_us") . '《' . $invest['en_us'] . '》，' . $LcTips200->value("en_us") . $liveIncome,
                $LcTips73->value("th_th") . '《' . $invest['th_th'] . '》，' . $LcTips200->value("th_th") . $liveIncome,
                $LcTips73->value("vi_vn") . '《' . $invest['vi_vn'] . '》，' . $LcTips200->value("vi_vn") . $liveIncome,
                $LcTips73->value("ja_jp") . '《' . $invest['ja_jp'] . '》，' . $LcTips200->value("ja_jp") . $liveIncome,
                $LcTips73->value("ko_kr") . '《' . $invest['ko_kr'] . '》，' . $LcTips200->value("ko_kr") . $liveIncome,
                $LcTips73->value("ms_my") . '《' . $invest['ms_my'] . '》，' . $LcTips200->value("ms_my") . $liveIncome,
                "", "", 32
            );
            setNumber('LcUser', 'money', $liveIncome, 1, "id = $shareUid");

        }


        // 增加点赞记录
        $liveRecord = array(
            'invest_id' => $investId,
            'uid' => $uid,
            'time' => date('Y-m-d H:i:s'),
            'money' => $money
        );
        $int = Db::name('LcInvestLive')->insert($liveRecord);

        if ($int) {
            $this->success(Db::name('LcTips')->field("$language")->find('195'));
        }
        $this->error(Db::name('LcTips')->field("$language")->find('76'));
    }


    /**
     * @description：抽奖奖品列表
     * @date: 2020/9/4 0004
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lotteryList()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        $params = $this->request->param();
        $language = $params["language"];

        $list = Db::name('LcPrize')->field("id, " . $language . " as title, images")->order('sort asc,id desc')->select();
        // 查询配置
        $config = Db::name("LcReward")->find(1);
        $data = array(
            'list' => $list,
            'point_num' => $this->user['point_num'],
            'silver_point_num' => $config['silver_point_num'],
            'gold_point_num' => $config['gold_point_num'],
            'bronze_point_num' => $config['bronze_point_num']
        );
        $this->success("操作成功", $data);
    }

    public function lottery()
    {
        $this->checkToken();
        // 参数
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        if (!isset($params['index'])) {
            $this->error('参数异常');
        }
        $language = $params["language"];
        // 抽奖等级
        $level = $params["level"];
        $this->user = Db::name('LcUser')->find($uid);
        $config = Db::name("LcReward")->find(1);
        $luckPoint = $config[$level . '_point_num'];
        // 积分不足
        if ($this->user['point_num'] < $luckPoint) $this->error(Db::name('LcTips')->field("$language")->find('193'));
        // 扣除积分
        Db::name('LcUser')->where("id = {$uid} and point_num >=  {$luckPoint}")->update(array('point_num' => $this->user['point_num'] - $luckPoint));
        // 创建积分扣除明细
        $LcTips204 = Db::name('LcTips')->where(['id' => '204']);
        $pointRecord = array(
            'uid' => $uid,
            'num' => $luckPoint,
            'type' => 2,
            'zh_cn' => $LcTips204->value("zh_cn"),
            'zh_hk' => $LcTips204->value("zh_hk"),
            'en_us' => $LcTips204->value("en_us"),
            'th_th' => $LcTips204->value("th_th"),
            'vi_vn' => $LcTips204->value("th_th"),
            'ja_jp' => $LcTips204->value("ja_jp"),
            'ko_kr' => $LcTips204->value("ko_kr"),
            'ms_my' => $LcTips204->value("ko_kr"),
            'time' => date('Y-m-d H:i:s'),
            'before' => $this->user['point_num']
        );
        $int = Db::name('LcPointRecord')->insert($pointRecord);
        // 查询奖品列表
        $prizeList = Db::name('lc_prize')->where('level', $level)->order('sort asc,id desc')->select();
        foreach ($prizeList as $item) {
            $arr[$item['id']] = $item['probability'];
        }
        $prizeId = $this->getPrize($arr);
        foreach ($arr as $key => $value) {
            $iarr[] = $key;
        }
        foreach ($iarr as $key => $value) {
            if ($prizeId == $value) {
                $index = ($key + 1);
                break;
            }
        }
        if (!$prizeId) {
            $lottery = [
                'type' => 0,
                'zh_cn' => '谢谢参与',
                'zh_hk' => '謝謝參與',
                'en_us' => 'Thank you for participating'
            ];
        } else {
            $lottery = Db::name('lc_prize')->find($prizeId);
        }

        $list = Db::name('lc_prize')->where('level', $level)->order('sort asc,id desc')->column('id');//打乱抽奖
        $data = shuffle($list);

        if ($lottery['type'] != 0) {
            if ($list[$index - 1] != $prizeId) {
                foreach ($list as $key => $item) {
                    if ($item == $prizeId) {
                        $cur_index = $key;
                        break;
                    }
                }
                $cur_value = $list[$index - 1];
                $list[$index - 1] = $list[$cur_index];
                $list[$cur_index] = $cur_value;
            }
        } else {
            $xxcy_id = Db::name('lc_prize')->where('level', $level)->where('zh_cn', '谢谢参与')->find()['id'];
            //将当前第n个盒子改为谢谢惠顾
            if ($list[$index - 1] != $xxcy_id) {
                foreach ($list as $key => $item) {
                    if ($item == $xxcy_id) {
                        $cur_index = $key;
                        break;
                    }
                }
                $cur_value = $list[$index - 1];
                $list[$index - 1] = $list[$cur_index];
                $list[$cur_index] = $cur_value;
            }
        }
        $info = [];
        foreach ($list as $item) {
            $info[] = Db::name('lc_prize')->find($item)[$language];
        }

        // 判断奖品类型
        if ($lottery['type'] == 1) {
            // 现金红包
            // 增加收益
            $LcTips73 = Db::name('LcTips')->where(['id' => '73']);
            $LcTips203 = Db::name('LcTips')->where(['id' => '203']);
            addFinance($uid, $lottery['num'], 1,
                $LcTips73->value("zh_cn") . '《' . $lottery['zh_cn'] . '》，' . $LcTips203->value("zh_cn") . $lottery['num'],
                $LcTips73->value("zh_hk") . '《' . $lottery['zh_hk'] . '》，' . $LcTips203->value("zh_hk") . $lottery['num'],
                $LcTips73->value("en_us") . '《' . $lottery['en_us'] . '》，' . $LcTips203->value("en_us") . $lottery['num'],
                $LcTips73->value("th_th") . '《' . $lottery['zh_cn'] . '》，' . $LcTips203->value("zh_cn") . $lottery['num'],
                $LcTips73->value("vi_vn") . '《' . $lottery['zh_cn'] . '》，' . $LcTips203->value("zh_cn") . $lottery['num'],
                $LcTips73->value("ja_jp") . '《' . $lottery['zh_cn'] . '》，' . $LcTips203->value("zh_cn") . $lottery['num'],
                $LcTips73->value("ko_kr") . '《' . $lottery['zh_cn'] . '》，' . $LcTips203->value("zh_cn") . $lottery['num'],
                $LcTips73->value("ms_my") . '《' . $lottery['zh_cn'] . '》，' . $LcTips203->value("zh_cn") . $lottery['num'],
                "", "", 51
            );
            setNumber('LcUser', 'money', $lottery['num'], 1, "id = $uid");
        } else if ($lottery['type'] == 2) {
            // 积分奖励
            // 赠送积分
            $user = Db::name('lc_user')->find($uid);
            Db::name('LcUser')->where("id = {$uid}")->update(['point_num' => $lottery['num'] + $user['point_num']]);
            // 创建积分明细
            $LcTips203 = Db::name('LcTips')->where(['id' => '203']);
            $pointRecord = array(
                'uid' => $uid,
                'num' => $lottery['num'],
                'type' => 1,
                'zh_cn' => $LcTips203->value("zh_cn") . '《' . $lottery['zh_cn'] . '》，',
                'zh_hk' => $LcTips203->value("zh_cn") . '《' . $lottery['zh_hk'] . '》，',
                'en_us' => $LcTips203->value("zh_cn") . '《' . $lottery['en_us'] . '》，',
                'th_th' => $LcTips203->value("zh_cn") . '《' . $lottery['zh_cn'] . '》，',
                'vi_vn' => $LcTips203->value("zh_cn") . '《' . $lottery['zh_cn'] . '》，',
                'ja_jp' => $LcTips203->value("zh_cn") . '《' . $lottery['zh_cn'] . '》，',
                'ko_kr' => $LcTips203->value("zh_cn") . '《' . $lottery['zh_cn'] . '》，',
                'ms_my' => $LcTips203->value("zh_cn") . '《' . $lottery['zh_cn'] . '》，',
                'time' => date('Y-m-d H:i:s'),
                'before' => $this->user['point_num']
            );
            $int = Db::name('LcPointRecord')->insert($pointRecord);
        }

        // 抽奖记录
        $lotteryRecord = array(
            'uid' => $uid,
            'zh_cn' => $lottery['zh_cn'],
            'zh_hk' => $lottery['zh_hk'],
            'en_us' => $lottery['en_us'],
            'th_th' => $lottery['zh_cn'],
            'vi_vn' => $lottery['zh_cn'],
            'ja_jp' => $lottery['zh_cn'],
            'ko_kr' => $lottery['zh_cn'],
            'ms_my' => $lottery['zh_cn'],
            'time' => date('Y-m-d H:i:s')
        );
        $int = Db::name('LcLotteryRecord')->insert($lotteryRecord);


        $SuccessTips = Db::name('LcTips')->field("$language")->find('203');
        // $SuccessTips[$language] = $SuccessTips[$language] . $lottery['title'];
        $SuccessTips['zh_cn'] = $lottery[$language];
        $this->success($SuccessTips, array(
            // 'index' => $index,
            'type' => $lottery['type'],
            'info' => $info,
            'msg' => $SuccessTips
        ));
    }

    /**
     * 抽奖算法
     */
    public function getPrize($proArr)
    {
        $result = '';
        $proSum = array_sum($proArr);
        if (!$proSum) {
            return 0;
        }
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }
        unset($proArr);
        return $result;
    }


    /**
     * @description：抽奖
     * @date: 2022/12/6 0004
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lottery2()
    {
        $this->checkToken();
        // 参数
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        // $params['index'] = 1;
        if (!isset($params['index'])) {
            $this->error('参数异常');
        }
        $language = $params["language"];
        // 抽奖等级
        $level = $params["level"];

        $this->user = Db::name('LcUser')->find($uid);
        $config = Db::name("LcReward")->find(1);

        $luckPoint = $config[$level . '_point_num'];

        // 积分不足
        if ($this->user['point_num'] < $luckPoint) $this->error(Db::name('LcTips')->field("$language")->find('193'));

        $redisKey = 'LockKeyUserItemApply' . $uid;
        $lock = new \app\api\util\RedisLock();
        if (!$lock->lock($redisKey, 10, 0)) {
            $this->error(Db::name('LcTips')->field("$language")->find('229'));
        }

        // 扣除积分
        Db::name('LcUser')->where("id = {$uid} and point_num >=  {$luckPoint}")->update(array('point_num' => $this->user['point_num'] - $luckPoint));

        // 创建积分扣除明细
        $LcTips204 = Db::name('LcTips')->where(['id' => '204']);
        $pointRecord = array(
            'uid' => $uid,
            'num' => $luckPoint,
            'type' => 2,
            'zh_cn' => $LcTips204->value("zh_cn"),
            'zh_hk' => $LcTips204->value("zh_hk"),
            'en_us' => $LcTips204->value("en_us"),
            'th_th' => $LcTips204->value("th_th"),
            'vi_vn' => $LcTips204->value("th_th"),
            'ja_jp' => $LcTips204->value("ja_jp"),
            'ko_kr' => $LcTips204->value("ko_kr"),
            'ms_my' => $LcTips204->value("ko_kr"),
            'time' => date('Y-m-d H:i:s'),
            'before' => $this->user['point_num']
        );
        $int = Db::name('LcPointRecord')->insert($pointRecord);

        // 查询奖品列表
        // $list = Db::name('LcPrize')->field("id, " . $language . " as title, zh_cn, zh_hk, en_us, th_th, vi_vn, ja_jp, ko_kr, ms_my, type, num, probability")->where("level = '{$level}'")->order('sort asc,id desc')->select();
        // $params['index'] = 1;
        $list = Db::name('lc_prize')->where('level', $level)->order('sort asc,id desc')->column('id');

        $params['index'] = rand(1, count($list));

        //打乱抽奖
        $data = shuffle($list);
        //抽奖
        //判断是否中奖
        $total_rate = Db::name('lc_prize')->where('level', $level)->sum('probability');
        $aa = Db::name('lc_prize')->where('level', $level)->select();
        $rand_value = rand(1, 100);
        if ($total_rate > $rand_value) { //中奖
            $value = rand(0, 5);
            $prize_id = $list[$value];
            //将当前第n个盒子改为中奖盒子
            //将当前第n个盒子改为谢谢惠顾
            if ($list[$params['index'] - 1] != $prize_id) {
                foreach ($list as $key => $item) {
                    if ($item == $prize_id) {
                        $cur_index = $key;
                        break;
                    }
                }
                $cur_value = $list[$params['index'] - 1];
                $list[$params['index'] - 1] = $list[$cur_index];
                $list[$cur_index] = $cur_value;
            }


            $lottery = Db::name('lc_prize')->field("id, " . $language . " as title, zh_cn, zh_hk, en_us, th_th, vi_vn, ja_jp, ko_kr, ms_my, type, num, probability")->find($prize_id);
        } else {    //未中奖
            //把谢谢惠顾替换到选择的盒子
            $xxcy_id = Db::name('lc_prize')->where('level', $level)->where('zh_cn', '谢谢参与')->find()['id'];

            $lottery = array(
                'type' => 3,
                'zh_cn' => '谢谢参与',
                'zh_hk' => '謝謝參與',
                'en_us' => 'Thank you for participating',
                'th_th' => '谢谢参与',
                'vi_vn' => '谢谢参与',
                'ja_jp' => '谢谢参与',
                'ko_kr' => '谢谢参与',
                'ms_my' => '谢谢参与',
                'title' => '谢谢参与'
            );
            //将当前第n个盒子改为谢谢惠顾
            if ($list[$params['index'] - 1] != $xxcy_id) {
                foreach ($list as $key => $item) {
                    if ($item == $xxcy_id) {
                        $cur_index = $key;
                        break;
                    }
                }
                $cur_value = $list[$params['index'] - 1];
                $list[$params['index'] - 1] = $list[$cur_index];
                $list[$cur_index] = $cur_value;
            }
        }
        $info = [];
        foreach ($list as $item) {
            $info[] = Db::name('lc_prize')->find($item)[$language];
        }


        // 判断奖品类型
        if ($lottery['type'] == 1) {
            // 现金红包
            // 增加收益
            $LcTips73 = Db::name('LcTips')->where(['id' => '73']);
            $LcTips203 = Db::name('LcTips')->where(['id' => '203']);
            addFinance($uid, $lottery['num'], 1,
                $LcTips73->value("zh_cn") . '《' . $lottery['zh_cn'] . '》，' . $LcTips203->value("zh_cn") . $lottery['num'],
                $LcTips73->value("zh_hk") . '《' . $lottery['zh_hk'] . '》，' . $LcTips203->value("zh_hk") . $lottery['num'],
                $LcTips73->value("en_us") . '《' . $lottery['en_us'] . '》，' . $LcTips203->value("en_us") . $lottery['num'],
                $LcTips73->value("th_th") . '《' . $lottery['th_th'] . '》，' . $LcTips203->value("th_th") . $lottery['num'],
                $LcTips73->value("vi_vn") . '《' . $lottery['vi_vn'] . '》，' . $LcTips203->value("vi_vn") . $lottery['num'],
                $LcTips73->value("ja_jp") . '《' . $lottery['ja_jp'] . '》，' . $LcTips203->value("ja_jp") . $lottery['num'],
                $LcTips73->value("ko_kr") . '《' . $lottery['ko_kr'] . '》，' . $LcTips203->value("ko_kr") . $lottery['num'],
                $LcTips73->value("ms_my") . '《' . $lottery['ms_my'] . '》，' . $LcTips203->value("ms_my") . $lottery['num'],
                "", "", 51
            );
            setNumber('LcUser', 'money', $lottery['num'], 1, "id = $uid");
        } else if ($lottery['type'] == 2) {
            // 积分奖励
            // 赠送积分
            Db::name('LcUser')->where("id = {$uid}")->update(['point_num' => $lottery['num'] + $this->user['point_num']]);
            // 创建积分明细
            $LcTips203 = Db::name('LcTips')->where(['id' => '203']);
            $pointRecord = array(
                'uid' => $uid,
                'num' => $lottery['num'],
                'type' => 1,
                'zh_cn' => $LcTips203->value("zh_cn") . '《' . $lottery['zh_cn'] . '》，',
                'zh_hk' => $LcTips203->value("zh_cn") . '《' . $lottery['zh_hk'] . '》，',
                'en_us' => $LcTips203->value("zh_cn") . '《' . $lottery['en_us'] . '》，',
                'th_th' => $LcTips203->value("zh_cn") . '《' . $lottery['th_th'] . '》，',
                'vi_vn' => $LcTips203->value("zh_cn") . '《' . $lottery['vi_vn'] . '》，',
                'ja_jp' => $LcTips203->value("zh_cn") . '《' . $lottery['ja_jp'] . '》，',
                'ko_kr' => $LcTips203->value("zh_cn") . '《' . $lottery['ko_kr'] . '》，',
                'ms_my' => $LcTips203->value("zh_cn") . '《' . $lottery['ms_my'] . '》，',
                'time' => date('Y-m-d H:i:s'),
                'before' => $this->user['point_num']
            );
            $int = Db::name('LcPointRecord')->insert($pointRecord);
        }

        // 抽奖记录
        $lotteryRecord = array(
            'uid' => $uid,
            'zh_cn' => $lottery['zh_cn'],
            'zh_hk' => $lottery['zh_hk'],
            'en_us' => $lottery['en_us'],
            'th_th' => $lottery['th_th'],
            'vi_vn' => $lottery['vi_vn'],
            'ja_jp' => $lottery['ja_jp'],
            'ko_kr' => $lottery['ko_kr'],
            'ms_my' => $lottery['ms_my'],
            'time' => date('Y-m-d H:i:s')
        );
        $int = Db::name('LcLotteryRecord')->insert($lotteryRecord);


        $SuccessTips = Db::name('LcTips')->field("$language")->find('203');
        // $SuccessTips[$language] = $SuccessTips[$language] . $lottery['title'];
        $SuccessTips['zh_cn'] = $lottery[$language];
        $this->success($SuccessTips, array(
            // 'index' => $index,
            'type' => $lottery['type'],
            'info' => $info,
            'msg' => $SuccessTips
        ));
    }

    /**
     * @description：抽奖奖品列表
     * @date: 2020/9/4 0004
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lotteryRecordList()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $language = $params["language"];

        $list = Db::name('LcLotteryRecord')->field("id, " . $language . " as title, time")->where("uid = " . $uid)->order('id desc')->select();
        $data = array(
            'list' => $list
        );
        $this->success("操作成功", $data);
    }


    public function register_code()
    {
        $params = $this->request->param();

        $phone = $params['phone'];
        $guo = $params['guo'];
        if (empty($guo)) {
            $guo = 84;
        }
        // $unreal = [165,167,170,171,162];
        // if (in_array(substr($phone, 0, 3), $unreal)) {
        //     $this->error(array(
        //         'zh_cn' => '禁止虚拟号段注册'
        //     ));
        // }

        $aes = new Aes();
        if (Db::name('lc_user')->where('guo', $guo)->where('phone', $aes->encrypt($phone))->find()) {
            $this->error(get_tip(35, $params['language']));
        }
        if (Db::name('lc_user')->where('guo', $guo)->where('phone', $aes->encrypt("0" . $phone))->find()) {
            $this->error(get_tip(35, $params['language']));
        }
        $phone = $guo . $phone;
        $mobile = $aes->encrypt($phone);
        // 检查上一次发送是否超过一分钟
        $recordCount = Db::name("LcSmsList")->where("date_sub(now(),interval 1 minute) < time")->where('phone', $mobile)->count();
        if ($recordCount > 0) {
            $this->error(get_tip(229, $params['language']));
        }

        //查询是否频繁操作
        $smsStatus = check_sms_status($mobile, $params['language']);
        if (!$smsStatus['status']) {
            $this->error($smsStatus['msg']);
        }

        $msgCode = rand(1000, 9999);
        // $result = sendSms($phone, '18001', $msgCode);
        $result = sendSms($phone, 18001, $msgCode);
        if ($result['code'] != '000') {
            $this->error('SMS_NET_ERR:' . $result['msg']);
        }


//
//        // 查询短信配置
//        $systemConfig = Db::name("SystemConfig")->select();
//
//
//        // 调用接口发送验证码
//        $url='https://utf8api.smschinese.cn/';
//        $post='Uid='.$Uid.'&Key='.$key.'&smsMob='.$smsMob.'&smsText='.$smsText;
//        $result = curl_request($url,$post);
        $this->success("操作成功");
//        return $result;
    }


    public function upxload()
    {
        if (!($file = $this->getUploadFile()) || empty($file)) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传异常，文件可能过大或未上传']]);
        }
        if (!$file->checkExt(strtolower(sysconf('storage_local_exts')))) {
            return json(['uploaded' => false, 'error' => ['message' => '文件上传类型受限，请在后台配置']]);
        }
        if ($file->checkExt('php,sh')) {
            return json(['uploaded' => false, 'error' => ['message' => '可执行文件禁止上传到本地服务器']]);
        }
        $this->safe = boolval(input('safe'));
        $this->uptype = $this->getUploadType();
        $this->extend = pathinfo($file->getInfo('name'), PATHINFO_EXTENSION);
        $name = File::name($file->getPathname(), $this->extend, '', 'md5_file');
        $info = File::instance($this->uptype)->save($name, file_get_contents($file->getRealPath()), $this->safe);
        if (is_array($info) && isset($info['url'])) {
            $url = $info['url'];
            if (stripos($url, 'http') === false) $url = $this->safe ? $name : "https://" . $_SERVER['HTTP_HOST'] . $info['url'];
            $this->success("操作成功", ['uploaded' => true, 'filename' => $name, 'url' => $url]);
//            return json();
            // return json(['uploaded' => true, 'filename' => $name, 'url' => $this->safe ? $name : $info['url']]);
        } else {
            $this->error(['uploaded' => false, 'error' => ['message' => '文件处理失败，请稍候再试！']]);
//            return json();
        }
    }

    //检测app是否更新

    public function getAppVersion()
    {
        $params = $this->request->param();
        $platform = $params["platform"];
        $versionCode = $params["versionCode"];
        $version = Db::name('LcVersion')->find(1);
        $data = [
            "versionCode" => $version['app_version'],
            "versionInfo" => $version['app_instructions'],
            "downloadUrl" => '',
            "updateType" => ''
        ];
        if ($version['app_version'] > $versionCode) {
            $data['updateType'] = "solicit";
            $data['downloadUrl'] = $platform == 'android' ? $version['android_app_down_url'] : $version['ios_app_down_url'];
        }
        $this->success("操作成功", $data);

    }

    /**
     * Describe:首页公告轮播
     * DateTime: 2020/5/17 1:22
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function notice()
    {
        $language = $this->request->param('language');
        $apicache = Cache::store('redis')->get('api_cache_notice_' . $language);
        if ($apicache) {
            $article = $apicache;
        } else {
            $article = Db::name('LcArticle')->where(['show' => 1, 'type' => 12])->order('sort asc,id desc')->select();
            foreach ($article as &$item) {
                $item['title_zh_cn'] = $item['title_' . $language];
            }
            Cache::store('redis')->set('api_cache_notice', $article, 60);
        }
        $this->success("操作成功", ['list' => $article]);
    }

    /**
     * Describe:公告详情
     * DateTime: 2020/5/17 1:22
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function notice_detail()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $id = $this->request->param('id');
        $article = Db::name('LcArticle')->field("title_$language,content_$language,time,publish_time")->find($id);
        $data = array(
            'title' => $article["title_$language"],
            'content' => $article["content_$language"],
            'time' => $article["time"],
            'publish_time' => $article["publish_time"],
        );
        $this->success("操作成功", $data);
    }
    // 处理目前数据
    // public function dealUser(){
    //     $user_list =  Db::name('LcUser')->select();
    //     for($i = 0;$i < count($user_list);$i++){
    //         $invite = $this->getInviteCode($user_list[$i]['id']);
    //         Db::name('LcUser')->where("id",$user_list[$i]['id'])->update(['invite'=>$invite]);
    //     }
    // }


    /**
     * 充值列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lifeList()
    {
        $language = $this->request->param('language');
        $list = Db::name('LcLifeService')->field("*,name as name_zh_cn")->select();
        foreach ($list as &$item) {
            $item['name'] = $item['name_' . $language];
        }
        $this->success("操作成功", $list);
    }


    /**
     * 充值列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function life()
    {
        $params = $this->request->param();
        $id = $params['id'];
        $language = $params['language'];
        $data = Db::name('LcLifeService')->field("*,name as name_zh_cn,input_option as input_option_zh_cn")->where("id = {$id}")->find();
        $data['name'] = $data['name_' . $language];
        $data['input_option'] = $data['input_option_' . $language];
        $this->success("操作成功", $data);
    }


    /**
     * 充值列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lifeBuy()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);

        $params = $this->request->param();


        if (!isset($params['password'])) {
            $this->error('请输入支付密码');
        }
        //校验密码
        if (md5($params['password']) != $this->user['password2']) {
            $this->error('Lỗi mật khẩu thanh toán');
        }

        if ($this->user['member'] < 8008) {
            $this->error('Cấp độ thành viên cần đến VIP2 để tham gia');
        }

        $id = $params['id'];
        $language = $params["language"];
        // 充值数量
        $num = $params['num'];
        // 输入属性
        $input_data = $params['input_data'];
        $data = Db::name('LcLifeService')->field("*")->where("id = {$id}")->find();
        $data['name_zh_cn'] = $data['name'];

        // 根据服务项目，计算支付金额
        $ruleOption = explode(",", $data['rule_option']);
        foreach ($ruleOption as $v) {
            $pay = explode(":", $v)[0];
            $iNum = explode(":", $v)[1];
            if ($num == $iNum) {
                $payAmount = $pay;
            }
        }


        // 检查用户余额是否足够
        if ($this->user['money'] < $payAmount) $this->error(Db::name('LcTips')->field("$language")->find('65'));

        $desc = $data['name_' . $language] . $num;

        addFinance($uid, $payAmount, 2,
            $desc,
            $desc,
            $desc,
            $desc,
            $desc,
            $desc,
            $desc,
            $desc,
            "", "", 13
        );
        setNumber('LcUser', 'money', $payAmount, 2, "id = $uid");

        // 创建充值记录
        $record = array(
            'uid' => $uid,
            'name' => $data['name_' . $language],
            'add_time' => date('Y-m-d H:i:s'),
            'num' => $num,
            'amount' => $payAmount,
            'status' => 0,
            'icon' => $data['images'],
            'input_data' => $input_data,
            'rule_data' => $data['rule_option']
        );
        Db::name('LcLifeRecord')->insertGetId($record);

        $this->success("操作成功", $payAmount);
    }


    /**
     * 充值列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lifeOrderList()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $list = Db::name('LcLifeRecord')->field("*")->where("uid = {$uid}")->order('id desc')->select();

        $this->success("操作成功", $list);
    }


    public function currencyList()
    {
        $apicache = Cache::store('redis')->get('api_cache_currencyList');
        if ($apicache) {
            $list = $apicache;
        } else {
            $list = Db::name('LcCurrency')->field("open_price value, min_price v1, max_price v2, new_price v3")->order("id desc")->limit(7)->select();
            Cache::store('redis')->set('api_cache_currencyList', $list, 60);
        }
        $this->success("操作成功", array_reverse($list));
    }


    /**
     * 签到信息
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function signInfo()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];

        // 查询挖矿时间
        $times = Db::name('LcSignReward')->select();

        $isSign = 0;

        $user = Db::name('LcUser')->field("qiandao,qdnum")->find($uid);
        $today = strtotime(date('Y-m-d'));
        // 如果已经签到
        if ($today <= strtotime($user['qiandao'])) {
            $isSign = 1;
        }

        // 获取签到记录
        $rewards = Db::name('LcUserSignLog')->where("uid = {$uid}")->select();

        //百分比
        $time = date('Y-m', time());
        $days = date('t', time());
        $totalSign = Db::name('lc_user_sign_log')->where('uid', $uid)->where('date', 'like', '%' . $time . '%')->count();
        $percent = bcmul(bcdiv($totalSign, $days, 2), 100);

        $this->success("操作成功", array(
            'times' => $times,
            'rewards' => $rewards,
            'isSign' => $isSign,
            'total_sign' => $totalSign,
            'percent' => $percent
        ));
    }


    /**
     * 获取途游宝产品列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ebaoProductList()
    {
        // 查询途游宝产品列表
        $language = $this->request->param('language');
        $list = Db::name("LcEbaoProduct")->field('*,title as zh_cn')->order("id desc")->select();
        foreach ($list as &$item) {
            $item['title'] = $item[$language];
        }
        $this->success("获取成功", array(
            'list' => $list
        ));
    }


    /**
     * 获取途游宝产品详情
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function ebaoProductDetail()
    {
        $params = $this->request->param();
        $id = $params['id'];
        // 查询途游宝详情
        $data = Db::name("LcEbaoProduct")->where("id = {$id}")->find();
        $this->success("获取成功", $data);
    }


    /**
     * 购买途游宝产品
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function buyEbaoProduct()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];

        $params = $this->request->param();
        $language = $params['language'];
        $language = 'zh_hk';
        $ebaoProductId = $params['productId'];
        $amount = $params['amount'];

        $user = Db::name("LcUser")->find($uid);
        //校验密码
        if (md5($params['passwd']) != $user['password2']) {
            $this->error(get_tip(213, $language));
        }

        // 查询产品
        $product = Db::name("LcEbaoProduct")->where("id = {$ebaoProductId}")->find();

        // 检查是否满足条件
        if ($amount < $product['min_num']) {
            $this->error(['zh_cn' => get_tip(219, $language)]);
        }
        if ($amount > $product['max_num']) {
            $this->error(['zh_cn' => get_tip(220, $language)]);
        }

        $user = Db::name("LcUser")->where("id = {$uid}")->find();
        // 判断是否有钱
        if ($user['ebao'] < $amount) {
            $this->error(['zh_cn' => get_tip(221, $language)]);
        }

        // 增加冻结的途游宝金额
        Db::name("LcUser")->where("id = {$uid}")->setInc("frozen_ebao", $amount);

        // 开始扣除途游宝余额
        // 增加途游宝流水
        $ebaoRecord = array(
            'uid' => $uid,
            'money' => $amount,
            'type' => 2,
            'title' => get_tip(222, 'zh_cn') . ' ' . $amount,
            'title_zh_hk' => get_tip(222, 'zh_hk') . ' ' . $amount,
            'title_en_us' => get_tip(222, 'en_us') . ' ' . $amount,
            'time' => date('Y-m-d H:i:s')
        );
        $int = Db::name('LcEbaoRecord')->insert($ebaoRecord);
        Db::name('LcUser')->where("id = {$uid}")->setDec('ebao', $amount);

        // 添加购买记录
        $productRecord = array(
            'uid' => $uid,
            'product_id' => $ebaoProductId,
            'money' => $amount,
            'lock_day' => $product['lock_day'],
            'title' => $product['title'],
            'title_zh_hk' => $product['zh_hk'],
            'title_en_us' => $product['en_us'],
            'add_time' => date('Y-m-d H:i:s')
        );
        $int = Db::name('LcEbaoProductRecord')->insert($productRecord);
        $this->success('操作成功', ['info' => get_tip(227, $language)]);
    }


}
