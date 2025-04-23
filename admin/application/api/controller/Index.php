<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2023  MC Technology [   ]
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
    public function test()
    {
        $aes = new Aes();
        $mobile = '18223537801';
        $encrypt = $aes->encrypt($mobile);
        $decrypt = $aes->decrypt($encrypt);
        echo $encrypt . '<br/>';
        echo $decrypt;
    }

    //公告列表
    public function message()
    {
        $list = Db::name('lc_article')->where('type', 12)->order('sort asc,id desc')->select();
        $this->success('获取成功', $list);
    }

    //平台信息
    public function platinfo()
    {
        $info = Db::name('lc_info')->field('plat_total_num,today_inc_num,today_recharge_num,trade_total,today_trade,today_withdraw,rate_usd')->find(1);
        $info['plat_total_num'] += rand(10, 20);
        $info['today_inc_num'] += rand(1, 2);
        $info['today_recharge_num']++;
        $info['trade_total'] += rand(500, 1000);
        $info['today_trade'] += rand(100, 500);
        $info['today_withdraw'] += rand(200, 600);
        Db::name('lc_info')->where('id', 1)->update($info);

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
        $where = [];
        $where[] = ['status', '=', 1];
        $where[] = ['num', '>', 0];
        $list = Db::name('lc_figure_collect')->where($where)->field('id,name,image,num,surplus_num,price')->select();

        $this->success('获取成功', $list);
    }

    //数字藏品详情
    public function figure_collect_detail()
    {
        $id = $this->request->get('id', 0);

        if (!$info = Db::name('lc_figure_collect')->field('id,name,image,num,surplus_num,price')->find($id)) {
            $this->error('数字藏品不存在');
        }

        $this->success('获取成功', $info);
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
            $this->error('支付密码错误');
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
            'create_time' => time()
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
            'time' => date('Y-m-d H:i:s', time())
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
        $list = Db::name('lc_figure_collect_log l')->join('lc_figure_collect c', 'l.figure_collect_id = c.id')
            ->where(['uid' => $uid])
            ->whereIn('l.status', [0, 1])
            ->field('l.id,l.money,l.create_time,c.name,c.image,l.able_sell_time')
            ->order('id desc')
            ->select();

        foreach ($list as &$item) {
            $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
            $item['expire_time'] = date('Y-m-d H:i:s', $item['able_sell_time']);
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

    //开盒
    public function open_blind()
    {
        $info = Db::name('lc_info')->find(1);

        if (!$info['is_blind'] || empty($info['members'])) {
            $this->error('活动未开启');
        }


        //获取产品列表
        $product_list = Db::name('lc_blind')->where('status', 1)->order('sort asc')->column('id');
        if (!count($product_list)) $this->error('产品数据为空');

        $members = explode(',', $info['members']);
        // $uid = 38550;
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $userinfo = Db::name('lc_user')->find($uid);
        if (!in_array($userinfo['member'], $members)) {
            $this->error('等级太低，没有抽奖权限');
        }

        $rand_value = rand(1, 10000);
        $blind_rate = $info['blind_rate'] * 100;
        if ($rand_value <= $blind_rate) {
            //获取产品列表
            $rand_product_id = $product_list[array_rand($product_list, 1)];
            $product_info = Db::name('lc_blind')->field('id,name,price,rate,period')->find($rand_product_id);
            $this->success('恭喜你中奖了', $product_info);
        } else {
            $this->error('很遗憾，未抽到任何礼物');
        }
    }

    //盲盒产品详情
    public function blind_detail()
    {
        $id = $this->request->param('id', 0);
        $blind_info = Db::name('lc_blind')->find($id);
        if (!$blind_info) {
            $this->error('产品不存在');
        }
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
            'rate' => $blind_info['rate'],
            'uid' => $uid,
            'status' => 0,
            'pay_status' => 0,
            'create_time' => time()
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
            'time' => date('Y-m-d H:i:s', time())
        ]);
        Db::name('lc_blind_buy_log')->where('id', $blind_buy_log_id)->update(['pay_status' => 1]);

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
            'time' => date('Y-m-d H:i:s', time())
        ]);
        Db::name('lc_blind_buy_log')->where('id', $id)->update(['pay_status' => 1]);

        $this->success('购买成功');
    }


    //取消盲盒订单
    public function cancel_blind_order()
    {
        $id = $this->request->param('id');
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
        if ($type == 1) {
            $where[] = ['pay_status', '=', 0];
        } elseif ($type == 2) {
            $where[] = ['pay_status', '=', 2];
        }
        $where[] = ['uid', '=', $uid];
        $list = Db::name('lc_blind_buy_log l')->join('lc_blind b', 'l.blind_id = b.id')
            ->field('l.id,l.money,l.rate,b.name,b.image,l.period,l.create_time')
            ->where($where)->order('id desc')->select();

        foreach ($list as &$item) {
            if ($item['period'] == 1) {
                $time = 86400;
            } elseif ($item['period'] == 2) {
                $time = 7 * 86400;
            } elseif ($item['period'] == 3) {
                $time = 30 * 86400;
            }
            $item['expire_time'] = date('Y-m-d H:i:s', ($item['create_time'] + $time));
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
        $apicache = Cache::get('api_cache_webconfig');
        if ($apicache) {
            $data = $apicache;
        } else {
            $info = Db::name('LcInfo')->find(1);

            $list = $info['line'];
            $list = preg_replace('/\s/', '@', $list);
            $list = explode('@@', $list);

            //获取session
            $cacheLine = Session::get('line');
            if ($cacheLine == '') {
                $cacheLine = 0;
            }

            //判断是否到最大
            if ($cacheLine >= count($list)) {
                $cacheLine = 0;
            }
            $temp = explode('#', $list[$cacheLine]);
            Session::set('line', $cacheLine + 1);
            $temp = explode('#', $list[0]);
            $newData = [
                'name' => $temp[0],
                'value' => $temp[1],
                'sel' => 0
            ];

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
                'line' => $newData,
            );
            Cache::set('api_cache_webconfig', $data, 60);
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
        $apicache = Cache::get('api_cache_index_int');
        if ($apicache) {
            $data = $apicache;
        } else {
            $params = $this->request->param();
            $language = $params["language"];
            $banner = Db::name('LcSlide')->field("$language,url")->where(['show' => 1, 'type' => 0])->order('sort asc,id desc')->select();
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
                $notice_num = $msgtop = Db::name('LcMsg')->alias('msg')->where('(msg.uid = ' . $uid . ' or msg.uid = 0 ) and (select count(*) from lc_msg_is as msg_is where msg.id = msg_is.mid  and ((msg.uid = 0 and msg_is.uid = ' . $uid . ') or ( msg.uid = ' . $uid . ' and msg_is.uid = ' . $uid . ') )) = 0')->count();
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
            Cache::set('api_cache_index_int', $data, 60);
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
        $list = Db::name('LcActivity')->field("id,title_$language,desc_$language,img_$language,url,time")->where(['show' => 1])->order('sort asc,id desc')->select();
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

    /**
     * Describe:财经列表
     * DateTime: 2020/5/17 1:22
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function news()
    {
        $params = $this->request->param();
        $apicache = Cache::get('api_cache_index_news_type' . $params['type']);
        if ($apicache) {
            $article = $apicache;
        } else {
            $article = Db::name('LcArticle')->where(['show' => 1, 'type' => $params['type']])->order('sort asc,id desc')->select();
            Cache::set('api_cache_index_news_type' . $params['type'], $article, 65);
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
            'content' => $article["content_$language"],
            'time' => $article["time"],
            'publish_time' => $article["publish_time"],
        );
        $this->success("操作成功", $data);
    }


    /**
     * @description：搜索项目
     * @date: 2020/9/1 0001
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function item_search()
    {
        $params = $this->request->param();
        $language = $params["language"];
        $title = isset($params['title']) ? $params['title'] : '';
        $index_type = isset($params['index_type']) ? $params['index_type'] : '';
        $show_home = isset($params['show_home']) ? $params['show_home'] : '';
        $key = 'api_cache_index_item_search_' . $title . '_' . $index_type . '_' . $show_home;
        $apicache = Cache::get($key);
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
            $data = Db::name('LcItem')->field("id,$language,min,max,num,total,rate,percent,cycle_type,hour,index_type,user_member,add_time, (select tag_name from lc_item_tag where id = tag_one) tag_one , (select tag_name from lc_item_tag where id = tag_two) tag_two,  (select tag_name from lc_item_tag where id = tag_three) tag_three,desc_$language")->where($where)->order('sort asc,id desc')->select();
            // 查询配置
            $config = Db::name("LcReward")->find(1);
            for ($i = 0; $i < count($data); $i++) {
                $data[$i]['member_value'] = Db::name("LcUserMember")->where("id", $data[$i]['user_member'])->value("value");
                $data[$i]['new'] = time() - strtotime($data[$i]['add_time']) > $config['new_day'] * 86400 ? false : true;

                if ($data[$i]['cycle_type'] == 1) {
                    $data[$i]['cycle_type'] = '每小时返利，到期返本';
                }
                if ($data[$i]['cycle_type'] == 2) {
                    $data[$i]['cycle_type'] = '每日返利，到期返本';
                }
                if ($data[$i]['cycle_type'] == 3) {
                    $data[$i]['cycle_type'] = '每周返利，到期返本';
                }
                if ($data[$i]['cycle_type'] == 4) {
                    $data[$i]['cycle_type'] = '每月返利，到期返本';
                }
                if ($data[$i]['cycle_type'] == 5) {
                    $data[$i]['cycle_type'] = '到期返本返利';
                }

            }

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
            Cache::set($key, $data, 60);
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

        // if (isset($params['shareId'])) {
        //     $invest = Db::name('lc_invest')->find($params['shareId']);
        //     if ($invest['uid'] == $uid) {
        //         $this->error('不能团购自己发起的项目');
        //     }
        // }

        //初次购买项目强制设置支付密码
        // if (Db::name('lc_user')->find($uid)['mwpassword2'] == '123456') {
        //     $returnData = array(
        //         "$language" => "请先去设置支付密码"
        //     );
        //     $this->error($returnData, 100, 100);
        // }

        $item = Db::name('LcItem')->field("id,$language as title,min,max,num,total,rate,percent,hour,content_$language as content,img,cycle_type,num,user_member,index_type, dbjg, tzfx,zjyt,purchase_amount")->where(['status' => 1])->find($params["id"]);
        $item['log'] = Db::name("LcInvest i")
            ->leftJoin("lc_user u", " i.uid = u.id")
            ->field("$language as title,u.phone")
            ->where('u.phone is not null')
            ->order('i.id desc')
            ->limit("0,10")
            ->select();
        $cycle = ['1' => '每小时返利，到期返本', '2' => '每日返利，到期返本', '3' => '每周返利，到期返本', '4' => '每月返利，到期返本', '5' => '到期返本返利'];
        $item['cycle_name'] = $cycle[$item['cycle_type']];
        $itemId = $params["id"];
        $user = Db::name("LcUser")->find($uid);

        $member = Db::name("LcUserMember")->where("id", $user['member'])->find();
        // 返回会员等级信息
        $item['member'] = $member;


        $member_value = $member['value'];
        $item_member_value = Db::name("LcUserMember")->where("id", $item['user_member'])->value("value");
        if ($item_member_value > $member_value) {
            $returnData = array(
                "$language" => "当前会员等级不能解锁该项目"
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

            $item['numsText'] = array_reverse(array(date("m") . '月', date("m", strtotime("-1 month")) . '月', date("m", strtotime("-2 month")) . '月', date("m", strtotime("-3 month")) . '月', date("m", strtotime("-4 month")) . '月', date("m", strtotime("-5 month")) . '月'));

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

            $weekNums = ceil($hour / 7 / 25);
            $item['numsText'] = array_reverse(array($weekNums . '周', $weekNums - 1 . '周', $weekNums - 2 . '周', $weekNums - 3 . '周', $weekNums - 4 . '周', $weekNums - 5 . '周'));

        } else {
            // 按小时
            $num1 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(now(), '%Y-%m-%d %H')")->sum("num");
            $num2 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(date_sub(now(), interval 1 hour), '%Y-%m-%d %H')")->sum("num");
            $num3 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(date_sub(now(), interval 2 hour), '%Y-%m-%d %H')")->sum("num");
            $num4 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(date_sub(now(), interval 3 hour), '%Y-%m-%d %H')")->sum("num");
            $num5 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(date_sub(now(), interval 4 hour), '%Y-%m-%d %H')")->sum("num");
            $num6 = Db::name('LcItemWave')->where("item_id = ${itemId} and date_format(time, '%Y-%m-%d %H') = date_format(date_sub(now(), interval 5 hour), '%Y-%m-%d %H')")->sum("num");
            $item['nums'] = array_reverse(array($num1, $num2, $num3, $num4, $num5, $num6));

            $item['numsText'] = array_reverse(array(date("H") . '时', date("H", strtotime("-1 hour")) . '时', date("H", strtotime("-2 hour")) . '时', date("H", strtotime("-3 hour")) . '时', date("H", strtotime("-4 hour")) . '时', date("H", strtotime("-5 hour")) . '时'));
        }
        $item['log'] = Db::name('LcProjectLog')->order('sort asc,id desc')->limit(10)->select();


        // 查询k线
        //echo json_encode($item);exit;
        $this->success("操作成功", $item);
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
                "$language" => "当前会员等级不能解锁该项目"
            );
            $this->error($returnData);
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
        $money = $params["money"];
        $signBase64 = $params["signBase64"];

        //
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
        if ($this->user['asset'] < $params["money"]) $this->error(Db::name('LcTips')->field("$language")->find('65'));

        $item = Db::name('LcItem')->find($params["id"]);
        if (!$item) $this->error(Db::name('LcTips')->field("$language")->find('100'));
        $member_value = Db::name("LcUserMember")->where("id", $this->user['member'])->value("value");
        $item_member_value = Db::name("LcUserMember")->where("id", $item['user_member'])->value("value");
        if ($item_member_value > $member_value) {
            $returnData = array(
                "$language" => "当前会员等级不能解锁该项目"
            );
            $this->error($returnData);
        }

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
        $my_count = Db::name('LcInvest')->where(['uid' => $uid, 'pid' => $item['id']])->count();
        if ($my_count >= $item['num']) {
            $returnData = array(
                "$language" => Db::name('LcTips')->where(['id' => '70'])->value("$language") . $item['num'] . Db::name('LcTips')->where(['id' => '71'])->value("$language")
            );
            $this->error($returnData);
        }

        // 该项目是否参与赠送积分
        if ($item['gift_points'] == 1) {
            // 赠送积分
            Db::name('LcUser')->where("id = {$uid}")->update(['point_num' => $money + $this->user['point_num']]);
            // 创建积分明细
            $LcTips75 = Db::name('LcTips')->where(['id' => '75']);
            $pointRecord = array(
                'uid' => $uid,
                'num' => $money,
                'type' => 1,
                'zh_cn' => $LcTips75->value("zh_cn") . '《' . $item['zh_cn'] . '》，',
                'zh_hk' => $LcTips75->value("zh_cn") . '《' . $item['zh_hk'] . '》，',
                'en_us' => $LcTips75->value("zh_cn") . '《' . $item['en_us'] . '》，',
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

            // 修改团购次数
            $groupInt = Db::name('LcInvest')->where("id = {$params['shareId']} and grouping_num < 100")->update(['grouping_num' => $invest['grouping_num'] + 1, 'grouping_income' => $invest['grouping_income'] + $groupIncome]);
            if (!$groupInt) {
                $this->error(Db::name('LcTips')->field("$language")->find('196'));
            }

        }

        // //投资额满足当前等级
        // $recom_member = Db::name('LcUser')->find($uid);
        // // 是否满足升级团队要求
        // $grade_id = $recom_member['grade_id'];
        // // 下一个团队的信息
        // $mgrade = Db::name("LcMemberGrade")->where("id > {$grade_id}")->order("id asc")->limit(1)->find();
        // // 邀请人数
        // $tg_num = Db::name("LcUser")->where("recom_id", $recom_member['id'])->count();
        // // 团队查询条件
        // $where_find = [
        //     "grade_id" => ["gt", "1"]
        // ];
        // $tz_num = Db::name("LcUser")->where("recom_id", $recom_member['id'])->where("grade_id > 1")->count();
        // //当前满足条件
        // $condition = 0;
        // if (($tg_num + 1) >= $mgrade['recom_number']) $condition++;
        // if ($mgrade['recom_tz'] <= $tz_num) $condition++;
        // //当前用户团队投资额
        // $members = Db::name('LcUser')->find($recom_member['id']);
        // $memberList = Db::name('LcUser')->field('id,phone,top,czmoney')->select();
        // $itemList = $this->get_downline_list($memberList, $members['id']);
        // $ids = [$recom_member['id']];$comIds = [];
        // foreach ($itemList as $value) {
        //     $ids[] = $value['id'];
        //     $comIds[] = $value['id'];
        // }
        // $totalInvest = Db::name('lc_invest')->whereIn('uid', $ids)->sum('money');
        // if ($totalInvest >= $mgrade['all_activity']) $condition++;
        // if ($condition >= 2) {
        //     $mdate['grade_id'] = $mgrade['id'];
        //     $mdate['grade_name'] = $mgrade['title'];
        //     Db::name("LcUser")->where(["id" => $uid])->update($mdate);

        //     //赠送
        //     $user = Db::name('LcUser')->find($recom_member['id']);

        //     $cur_grade = Db::name("LcMemberGrade")->where("id <= {$grade_id}")->sum('all_activity');
        //     $reward = number_format(($mgrade['all_activity']-$cur_grade)*$mgrade['poundage']/100,2);
        //     //赠送记录
        //     Db::name('lc_finance')->insert([
        //         'uid' => $user['id'],
        //         'money' => 1,
        //         'type' => 1,
        //         'zh_cn' => '升级为'.$mgrade['title'],
        //         'before' => $user['money'],
        //         'time' => date('Y-m-d H:i:s', time()),
        //         'reason_type' => 8
        //     ]);
        //     Db::name('LcUser')->where('id', $user['id'])->update(['money' => bcadd($user['money'], $reward, 2)]);
        // }


        if (getInvestList($item['id'], $money, $uid, $signBase64, $shareUid, $shareId)) {


            $LcTips73 = Db::name('LcTips')->where(['id' => '73']);
            $LcTips74 = Db::name('LcTips')->where(['id' => '74']);
            $LcTips206 = Db::name('LcTips')->where(['id' => '206']);
            addFinance($uid, $money, 2,
                $LcTips73->value("zh_cn") . '《' . $item['zh_cn'] . '》，' . $LcTips206->value("zh_cn") . $money,
                $LcTips73->value("zh_hk") . '《' . $item['zh_hk'] . '》，' . $LcTips206->value("zh_hk") . $money,
                $LcTips73->value("en_us") . '《' . $item['en_us'] . '》，' . $LcTips206->value("en_us") . $money,
                $LcTips73->value("th_th") . '《' . $item['th_th'] . '》，' . $LcTips206->value("th_th") . $money,
                $LcTips73->value("vi_vn") . '《' . $item['vi_vn'] . '》，' . $LcTips206->value("vi_vn") . $money,
                $LcTips73->value("ja_jp") . '《' . $item['ja_jp'] . '》，' . $LcTips206->value("ja_jp") . $money,
                $LcTips73->value("ko_kr") . '《' . $item['ko_kr'] . '》，' . $LcTips206->value("ko_kr") . $money,
                $LcTips73->value("ms_my") . '《' . $item['ms_my'] . '》，' . $LcTips206->value("ms_my") . $money,
                "", "", 6
            );
            setNumber('LcUser', 'asset', $money, 2, "id = $uid");

            // 判断是否首次投资
            $my_count = Db::name('LcInvest')->where(['uid' => $uid])->count();
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
            // 给上级返利
            // setRechargeReward($uid, $money);
            //上级奖励（一、二、三级）
            $top = $this->user['top'];
            $top2 = $this->user['top2'];
            $top3 = $this->user['top3'];
            //一级

            $topuser = Db::name("LcUser")->find($top);
            if ($topuser) {
                $invest1 = Db::name("LcUserMember")->where(['id' => $topuser['member']])->value("invest1");

                setRechargeRebate1($top, $money, $invest1, '一级');
            }

            //二级
            $topuser2 = Db::name("LcUser")->find($top2);
            if ($topuser2) {
                $invest2 = Db::name("LcUserMember")->where(['id' => $topuser2['member']])->value("invest2");
                setRechargeRebate1($top2, $money, $invest2, '二级');
            }
            //三级
            $topuser3 = Db::name("LcUser")->find($top3);
            if ($topuser3) {

                $invest3 = Db::name("LcUserMember")->where(['id' => $topuser3['member']])->value("invest3");
                setRechargeRebate1($top3, $money, $invest3, '三级');
            }

            // 是否开启红包雨
            if ($item['is_redpack'] == 1) {
                // 计算红包雨金额
                // $redAmount = rand($item['min_redpack'], $item['max_redpack']);
                $redAmount = bcdiv($money * $item['rate_redpack'], 100, 2);
                if ($redAmount > 0) {
                    // 开始赠送红包
                    // 赠送投资红包
                    /*$ebaoRecord = array(
                        'uid' => $uid,
                        'money' => $redAmount,
                        'type' => 1,
                        'title' => '投资成功赠送红包 ',
                        'time' => date('Y-m-d H:i:s')
                    );
                    $LcTips201 = Db::name('LcTips')->where(['id' => '201']);
                     $int = Db::name('LcEbaoRecord')->insert($ebaoRecord);
                          addFinance($uid, $redAmount, 1,
                         $LcTips73->value("zh_cn").'《' . $item['zh_cn'] . '》，投资成功赠送红包 '.$redAmount,
                         $LcTips73->value("zh_hk").'《' . $item['zh_hk'] . '》，投资成功赠送红包 '.$redAmount,
                         $LcTips73->value("en_us").'《' . $item['en_us'] . '》，投资成功赠送红包 '.$redAmount,
                         $LcTips73->value("th_th").'《' . $item['th_th'] . '》，投资成功赠送红包 '.$redAmount,
                         $LcTips73->value("vi_vn").'《' . $item['vi_vn'] . '》，投资成功赠送红包 '.$redAmount,
                         $LcTips73->value("ja_jp").'《' . $item['ja_jp'] . '》，投资成功赠送红包 '.$redAmount,
                         $LcTips73->value("ko_kr").'《' . $item['ko_kr'] . '》，投资成功赠送红包 '.$redAmount,
                         $LcTips73->value("ms_my").'《' . $item['ms_my'] . '》，投资成功赠送红包 '.$redAmount,
                         "投资成功赠送红包","",7
                     );*/
                    $LcTips75 = Db::name('LcTips')->where(['id' => '75']);
                    // var_dump($item['zh_cn']);
                    // var_dump($LcTips74->value("zh_cn"));die;
                    $LcTips205 = Db::name('LcTips')->where(['id' => '205']);
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
                        'time' => date('Y-m-d H:i:s')
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


            $this->success(Db::name('LcTips')->field("$language")->find('75'));
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

            if (!$params['username'] || !$params['password']) $this->error(Db::name('LcTips')->field("$language")->find('79'));
            if (!judge($params['username'], "phone")) $this->error(Db::name('LcTips')->field("$language")->find('80'));

            $aes = new Aes();
            $params['username'] = $aes->encrypt($params['username']);
            $user = Db::name('LcUser')->where(['phone' => $params['username']])->find();

            if (!$user) $this->error(Db::name('LcTips')->field("$language")->find('81'));

            if ($user['password'] != md5($params['password'])) $this->error(Db::name('LcTips')->field("$language")->find('82'));

            if ($user['clock'] == 0) $this->error(Db::name('LcTips')->field("$language")->find('83'));

            Db::name('LcUser')->where(['id' => $user['id']])->update(['logintime' => time()]);
            $result = array(
                'token' => $this->getToken(['id' => $user['id'], 'phone' => $user['phone']]),
            );

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

            Db::name('lc_login_log')->insert([
                'realname' => $user['name'],
                'mobile' => $user['phone'],
                'uid' => $user['id'],
                'ip' => $ip,
                'region' => $region,
                'create_time' => time()
            ]);

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
            $phone = $params["phone"];


            $unreal = [165, 167, 170, 171, 162];
            if (in_array(substr($phone, 0, 3), $unreal)) {
                $this->error(array(
                    'zh_cn' => '暂停虚拟号段注册'
                ));
            }


            if (empty($params['t_mobile'])) {
                $this->error(array(
                    'zh_cn' => '请填写推荐人账号'
                ));
            }

            if (empty($params['code'])) {
                $this->error(array(
                    'zh_cn' => '请填写验证码'
                ));
            }

            // $phone = $params["phone"];

            // 验证邮箱格式
//            if (!judge($params['email'],"email")) $this->error(Db::name('LcTips')->field("$language")->find('86'));

            // 判断这个手机是否注册过
            $aes = new Aes();
            $params['phone'] = $aes->encrypt($params['phone']);
            if (Db::name('LcUser')->where(['phone' => $params['phone']])->find()) $this->error(Db::name('LcTips')->field("$language")->find('89'));

            //判断这个IP是否注册过
            // echo $this->request->ip().'<br/>';
            // echo Db::name('LcUser')->where(['ip' => $this->request->ip()])->count();exit();
            if (Db::name('LcUser')->where(['ip' => $this->request->ip()])->count() > 2) {
                $this->error('提示 相同IP不能注册多个账户');
            }

            // 验证密码长度
            if (strlen($params['password']) < 6 || 16 < strlen($params['password'])) $this->error(Db::name('LcTips')->field("$language")->find('90'));


            // 验证验证码是否正确
            // $phone = $aes->encrypt($params['phone'])
            $countRecord = Db::name("LcSmsList")->where('phone', $params['phone'])->order("id desc")->limit(1)->find();

            if ($countRecord['ip'] != $params['code']) {
                $this->error(array(
                    'zh_cn' => '验证码不正确'
                ));
            }


            $parentId = '';
            $recomId = '';
            // 如果有邀请人  这个应该是你不小心碰键盘，，，
            // if (isset($params['t_mobile'])) {
            //     $recom_member = Db::name('LcUser')->where(['invite' => $params['t_mobile']])->find();
            //     // 会员不存在
            //     if (!$recom_member) {
            //         $this->error(Db::name('LcTips')->field("$language")->find('91'));
            //     }

            //     // 判断上级是否有上级
            //     if (!empty($recom_member["parent_id"])) {
            //         $parentId = implode(",", explode(",", "'" . $recom_member["id"] . "'," . $recom_member["parent_id"]));
            //     } else {
            //         $parentId = implode(",", explode(",", "'" . $recom_member["id"] . "'"));
            //     }

            //     $recomId = $recom_member['id'];


            //     // 是否满足升级团队要求
            //     $grade_id = $recom_member['grade_id'];
            //     // 下一个团队的信息
            //     $mgrade = Db::name("LcMemberGrade")->where("id > {$grade_id}")->order("id asc")->limit(1)->find();


            //     // 邀请人数
            //     $tg_num = Db::name("LcUser")->where("recom_id", $recom_member['id'])->count();
            //     // 团队查询条件
            //     $where_find = [
            //         "grade_id" => ["gt", "1"]
            //     ];
            //     $tz_num = Db::name("LcUser")->where("recom_id", $recom_member['id'])->where("grade_id > 1")->count();

            //     //当前满足条件
            //     $condition = 0;
            //     if (($tg_num + 1) >= $mgrade['recom_number']) $condition++;
            //     if ($mgrade['recom_tz'] <= $tz_num) $condition++;
            //     //当前用户团队投资额
            //     $members = Db::name('LcUser')->find($recom_member['id']);
            //     $memberList = Db::name('LcUser')->field('id,phone,top,czmoney')->select();
            //     $itemList = $this->get_downline_list($memberList, $members['id']);
            //     $ids = [$recom_member['id']];$comIds = [];
            //     foreach ($itemList as $item) {
            //         $ids[] = $item['id'];
            //         $comIds[] = $item['id'];
            //     }
            //     $totalInvest = Db::name('lc_invest')->whereIn('uid', $ids)->sum('money');
            //     if ($totalInvest >= $mgrade['all_activity']) $condition++;
            //     if ($condition >= 2) {
            //         $mdate['grade_id'] = $mgrade['id'];
            //         $mdate['grade_name'] = $mgrade['title'];
            //         Db::name("LcUser")->where(["id" => $recomId])->update($mdate);

            //         //赠送
            //         $user = Db::name('LcUser')->find($recom_member['id']);

            //         $cur_grade = Db::name("LcMemberGrade")->where("id <= {$grade_id}")->sum('all_activity');
            //         $reward = number_format(($mgrade['all_activity']-$cur_grade)*$mgrade['poundage']/100,2);
            //         //赠送记录
            //         Db::name('lc_finance')->insert([
            //             'uid' => $user['id'],
            //             'money' => 1,
            //             'type' => 1,
            //             'zh_cn' => '升级为'.$mgrade['title'],
            //             'before' => $user['money'],
            //             'time' => date('Y-m-d H:i:s', time()),
            //             'reason_type' => 8
            //         ]);
            //         Db::name('LcUser')->where('id', $user['id'])->update(['money' => bcadd($user['money'], $reward, 2)]);
            //     }


            // }


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
            $add = array(
//                'email' => $params['email'],
                'phone' => $params['phone'],
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
                'name' => "默认昵称",
                'grade_id' => '1',
                'grade_name' => '普通用户'
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
                'token' => $this->getToken(['id' => $uid, 'phone' => $params['phone']]),
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
            if (!$params['code']) $this->error(Db::name('LcTips')->field("$language")->find('44'));
            $sms_code = Db::name("LcSmsList")->where("phone = '{$user['phone']}'")->order("id desc")->value('ip');
            if ($params['code'] != $sms_code) $this->error(Db::name('LcTips')->field("$language")->find('45'));
            if (strlen($params['password']) < 6 || 16 < strlen($params['password'])) $this->error(Db::name('LcTips')->field("$language")->find('47'));
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
            // 验证手机号是否正确
            if (!judge($params['mobile'], "phone")) $this->error(Db::name('LcTips')->field("$language")->find('80'));
            if (!$params['code']) $this->error(Db::name('LcTips')->field("$language")->find('44'));
            // 查询发送记录
            $aes = new Aes();
            $params['mobile'] = $aes->encrypt($params['mobile']);
            $sms_code = Db::name("LcSmsList")->where('phone', $params['mobile'])->order("id desc")->value('ip');
            if ($params['code'] != $sms_code) $this->error(Db::name('LcTips')->field("$language")->find('45'));
            $user = Db::name('LcUser')->where(['phone' => $params['mobile']])->find();
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
            if (!$phone) $this->error(Db::name('LcTips')->field("$language")->find('34'));

            $aes = new Aes();
            $phone = $aes->encrypt($phone);
            if (!Db::name('LcUser')->where(['phone' => $phone])->find()) $this->error(Db::name('LcTips')->field("$language")->find('46'));
            // 查询上次发送记录
            $sms_time = Db::name("LcSmsList")->where("phone = '$phone'")->order("id desc")->value('time');
            if ($sms_time && (strtotime($sms_time) + 300) > time()) $this->error(Db::name('LcTips')->field("$language")->find('37'));

            // 生成验证码
            $msgCode = rand(1000, 9999);
            $result = sendSms($phone, '18001', $msgCode);

            $this->success("操作成功");
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
        $aes = new Aes();
        $phone = $aes->decrypt($user['phone']);
        $params = $this->request->param();
        $language = $params["language"];

//        if ($user['auth'] == 0) $this->error(Db::name('LcTips')->field("$language")->find('39'));

        $sms_time = Db::name("LcSmsList")->where("phone = '$phone'")->order("id desc")->value('time');

        if ($sms_time && (strtotime($sms_time) + 300) > time()) $this->error(Db::name('LcTips')->field("$language")->find('37'));

        $rand_code = rand(1000, 9999);
        sendSms($phone, 18010, $rand_code);
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


    /**
     * @description：积分商品列表
     * @date: 2020/9/4 0004
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function point_goods_list()
    {
        $apicache = Cache::get('api_cache_index_point_goods_list');
        if ($apicache) {
            $data = $apicache;
        } else {
            $params = $this->request->param();
            $language = $params["language"];
            $list = Db::name('LcPoint')->field("id,title,images,title_$language,num,time, stock")->order('sort asc,id desc')->select();
            $data = array(
                'list' => $list
            );
            Cache::set('api_cache_index_point_goods_list', $data, 86);
        }
        $this->success("操作成功", $data);
    }

    public function point_goods_detail()
    {
        $id = $this->request->param('id');
        if (!$info = Db::name('LcPoint')->field('id,title,images,num,stock,note,slide_images')->find($id)) {
            $this->error('商品不存在');
        }
        if (!empty($info['slide_images'])) {
            $info['slide_images'] = explode('|', $info['slide_images']);
        }
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
        // 积分商品ID
        $pointGoodsId = $params["pointGoodsId"];

//        if ($this->user['auth'] != 1) $this->error(Db::name('LcTips')->field("$language")->find('60'), '', 405);

        // 查询商品信息
        $point = Db::name('LcPoint')->field("*")->find($pointGoodsId);
        if (!$point) $this->error(Db::name('LcTips')->field("$language")->find('100'));

        // 积分不足
        if ($this->user['point_num'] < $point["num"]) $this->error(Db::name('LcTips')->field("$language")->find('193'));
// var_dump($this->user['member']);die();
        if ($this->user['member'] < $point['level']) {
            $member = Db::name("LcUserMember")->order('id desc')->find($point['level']);
            $this->error("此商品兑换要求等级为：" . $member['name']);
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
            'zh_hk' => $LcTips195->value("zh_hk") . '《' . $point['title'] . '》，',
            'en_us' => $LcTips195->value("en_us") . '《' . $point['title'] . '》，',
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


        $msgCode = rand(1000, 9999);
        $result = sendSms($phone, '18001', $msgCode);


        // 检查上一次发送是否超过一分钟
        $recordCount = Db::name("LcSmsList")->where("date_sub(now(),interval 1 minute) < time and phone = ${phone}")->count();
        if ($recordCount > 0) {
            $this->error(array(
                'zh_cn' => '发送成功'
            ));
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


    /**
     * Describe:首页公告轮播
     * DateTime: 2020/5/17 1:22
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function notice()
    {
        $apicache = Cache::get('api_cache_notice');
        if ($apicache) {
            $article = $apicache;
        } else {
            $article = Db::name('LcArticle')->where(['show' => 1, 'type' => 12])->order('sort asc,id desc')->select();
            Cache::set('api_cache_notice', $article, 60);
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
        $list = Db::name('LcLifeService')->field("*")->select();
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
        $data = Db::name('LcLifeService')->field("*")->where("id = {$id}")->find();
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
            $this->error('支付密码错误');
        }

        $id = $params['id'];
        $language = $params["language"];
        // 充值数量
        $num = $params['num'];
        // 输入属性
        $input_data = $params['input_data'];
        $data = Db::name('LcLifeService')->field("*")->where("id = {$id}")->find();


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

        $desc = "购买" . $data['name'] . $num;

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
            'name' => $data['name'],
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
        $list = Db::name('LcLifeRecord')->field("*")->where("uid = {$uid}")->select();
        $this->success("操作成功", $list);
    }


    public function currencyList()
    {
        $apicache = Cache::get('api_cache_currencyList');
        if ($apicache) {
            $list = $apicache;
        } else {
            $list = Db::name('LcCurrency')->field("open_price value, min_price v1, max_price v2, new_price v3")->order("id desc")->limit(7)->select();
            Cache::set('api_cache_currencyList', $list, 60);
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

        $this->success("操作成功", array(
            'times' => $times,
            'rewards' => $rewards,
            'isSign' => $isSign
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
        $list = Db::name("LcEbaoProduct")->order("id desc")->select();
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
        $ebaoProductId = $params['productId'];
        $amount = $params['amount'];

        $user = Db::name("LcUser")->find($uid);
        //校验密码
        if (md5($params['passwd']) != $user['password2']) {
            $this->error('支付密码错误');
        }

        // 查询产品
        $product = Db::name("LcEbaoProduct")->where("id = {$ebaoProductId}")->find();

        // 检查是否满足条件
        if ($amount < $product['min_num']) {
            $this->error(array(
                'zh_cn' => '低于最低购买金额'
            ));
        }
        if ($amount > $product['max_num']) {
            $this->error(array(
                'zh_cn' => '高于最高购买金额'
            ));
        }

        $user = Db::name("LcUser")->where("id = {$uid}")->find();
        // 判断是否有钱
        if ($user['ebao'] < $amount) {
            $this->error(array(
                'zh_cn' => '途游宝余额不足，无法购买'
            ));
        }

        // 增加冻结的途游宝金额
        Db::name("LcUser")->where("id = {$uid}")->setInc("frozen_ebao", $amount);

        // 开始扣除途游宝余额
        // 增加途游宝流水
        $ebaoRecord = array(
            'uid' => $uid,
            'money' => $amount,
            'type' => 2,
            'title' => '购买途游宝产品 ' . $amount,
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
            'add_time' => date('Y-m-d H:i:s')
        );
        $int = Db::name('LcEbaoProductRecord')->insert($productRecord);
        $this->success("操作成功");
    }


}
