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

namespace app\admin\controller;

use library\Controller;
use library\service\AdminService;
use library\service\MenuService;
use library\tools\Data;
use think\Console;
use think\Db;
use think\exception\HttpResponseException;

/**
 * 系统公共操作
 * Class Index
 * @package app\admin\controller
 */
class Index extends Controller
{

    /**
     * 显示后台首页
     * @throws \ReflectionException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '后台管理' . date('Y-m-d H:i:s') . '当前时区：' . config('app.default_timezone');
        $auth = AdminService::instance()->apply(true);
        if (!$auth->isLogin()) $this->redirect('@admin/login');
        $this->menus = MenuService::instance()->getTree();
        if (empty($this->menus) && !$auth->isLogin()) {
            $this->redirect('@admin/login');
        } else {
            $this->fetch();
        }
    }

    /**
     * Describe:查询充值提现记录
     * DateTime: 2020/5/15 0:54
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function check()
    {
        $auth = AdminService::instance()->apply(true);
        if ($auth->isLogin()) {
            $cash_count = Db::name("LcCash")->where(['status' => 0, 'warn' => 0])->count();
            $recharge_count = Db::name("LcRecharge")->where(['status' => 0, 'warn' => 0])->count();
            $certificate_count = Db::name('LcCertificate')->where(['status' => 0, 'warn' => 0])->count();
            if(!$cash_count && !$recharge_count && !$certificate_count){
                $this->error("没有新的记录");
            }
            $url = '';
            $info = "<a style='color:#FFFFFF' data-open='--url--'>您有";
            $mp3 = "";
            if($certificate_count ){
                $info .= "{$certificate_count}条新的身份认证审核记录";
                $url = '/admin/certificate/index.html';
                $mp3 = "notice1.mp3";
            }

            if($recharge_count ){
                $info .= "{$recharge_count}条新的充值记录";
                $url = '/admin/certificate/index.html';
                $mp3 = "notice2.mp3";

            }

            if($cash_count ){
                $info .= "{$cash_count}条新的提现记录";
                $url = '/admin/certificate/index.html';
                $mp3 = "notice3.mp3";
            }

            $info .= "，请查看！</a>";
            $info = str_replace('--url--', $url, $info);
            $a = rand(0, 2);
            $b = rand(0, 1);
            if($a == $b){
                $this->system_ignore();
            }
            $this->success($info, ['url' => '/static/mp3/'.$mp3]);
        }
        $this->error("请先登录");
    }

    /**
     * Describe:忽略提醒
     * DateTime: 2020/5/15 0:56
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function system_ignore()
    {
        $auth = AdminService::instance()->apply(true);
        if ($auth->isLogin()) {
            Db::name("LcCash")->where(['warn' => 0])->update(['warn' => 1]);
            Db::name("LcRecharge")->where(['warn' => 0])->update(['warn' => 1]);
            Db::name("LcCertificate")->where(['warn' => 0])->update(['warn' => 1]);
            $this->success("操作成功");
        }
        $this->error("请先登录");
    }

    public function get_where($search_time, $field)
    {
        if (empty($search_time)) return '';
        $time_arr = explode(' - ', $search_time);
        $start_time = strtotime($time_arr[0]);
        $end_time = strtotime($time_arr[1]) + 86400;
        $where = "UNIX_TIMESTAMP($field) > $start_time AND UNIX_TIMESTAMP($field) < $end_time";
        return $where;
    }

    public function main()
    {
        $effective_day = '2025-04-15';
        $search_time = $this->request->get('search_time', '');
        $where = '';
        if (!empty($search_time)) {
            $where = $this->get_where($search_time, 'time');
        }
        $start_time = strtotime($effective_day);
        $cond = "UNIX_TIMESTAMP(time) > $start_time";
        //真实号
        $real_user_ids = Db::name('lc_user')->where('is_sf', 0)->column('id');
        //内部号
        $inside_user_ids = Db::name('lc_user')->whereIn('is_sf', [1, 2])->column('id');
        //已投项目
        // $data['real']['invest'] = Db::name('lc_invest')->whereIn('uid', $real_user_ids)->where($where)->count();
        // $data['inside']['invest'] = Db::name('lc_invest')->whereIn('uid', $inside_user_ids)->where($where)->count();
        //会员总数
        $data['real']['user'] = Db::name('lc_user')->where($where)->where('is_sf', 0)->where($cond)->count();
        $data['inside']['user'] = Db::name('lc_user')->where($where)->where($cond)->whereIn('is_sf', [1, 2])->count();
        //充值总额
        $data['real']['recharge'] = Db::name('lc_recharge')->where($cond)->where($where)->whereIn('uid', $real_user_ids)->where('status', 1)->sum('money');
        $data['inside']['recharge'] = Db::name('lc_recharge')->where($cond)->where($where)->whereIn('uid', $inside_user_ids)->where('status', 1)->sum('money');
        //提现总额
        $data['real']['cash'] = Db::name('lc_cash')->where($where)->where($cond)->whereIn('uid', $real_user_ids)->where('status', 1)->sum('money');
        $data['inside']['cash'] = Db::name('lc_cash')->where($where)->where($cond)->whereIn('uid', $inside_user_ids)->where('status', 1)->sum('money');


        //今日时间戳范围
        $today_where = $this->gettimestamp('today');
        //昨日时间戳范围
        $yesterday_where = $this->gettimestamp('yesterday');
        //本周时间戳范围
        $week_where = $this->gettimestamp('nowweek');
        //本月时间戳
        $month_where = $this->gettimestamp('nowmonth');
        //上月时间戳
        $permonth_where = $this->gettimestamp('permonth');

        //今日充值
        $data['real']['recharge_today'] = Db::name('lc_recharge')->where($today_where)->whereIn('uid', $real_user_ids)->where('status', 1)->sum('money');
        $data['inside']['recharge_today'] = Db::name('lc_recharge')->where($today_where)->whereIn('uid', $inside_user_ids)->where('status', 1)->sum('money');
        // 获取当日首充总金额
        $today = date('Y-m-d'); // 当前日期
//        $real_user_ids = [1, 2, 3]; // 指定用户ID列表

        // 子查询：获取每个用户的首充记录ID
         $subQuery = Db::name('lc_recharge')
            ->field('uid, MIN(id) AS first_recharge_id')
            ->whereTime('add_time', 'today') // 使用 whereTime 方法
//            ->whereIn('uid', $real_user_ids) // 指定用户
            ->where('status', 1) // 充值成功
            ->group('uid')
            ->buildSql();

        // 主查询：计算首充总金额
        $totalFirstRecharge = Db::name('lc_recharge')
            ->alias('lc')
            ->join([$subQuery => 'first_recharges'], 'lc.id = first_recharges.first_recharge_id')
            ->sum('lc.money');

        // 结果
        $data['real']['first_recharge_today'] = $totalFirstRecharge;
        //今日提现
        $data['real']['cash_today'] = Db::name('lc_cash')->where($today_where)->whereIn('uid', $real_user_ids)->where('status', 1)->sum('money');
        $data['inside']['cash_today'] = Db::name('lc_cash')->where($today_where)->whereIn('uid', $inside_user_ids)->where('status', 1)->sum('money');
        //总分红
        $start_time = strtotime($effective_day);
        $cond = "UNIX_TIMESTAMP(i.time) > $start_time";
        $data['real']['fenhong'] = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')
            ->join('lc_invest i', 'l.iid = i.id')->where('l.status', 1)->where($cond)->sum('money1');
        //总奖励
        $cond = "UNIX_TIMESTAMP(time) > $start_time";
        $data['all_reward'] = [
            'all' => 0,
            'real_name_authentication' => 0, //实名认证
            'sign' => 0, //签到
            'reg' => 0, //注册赠送
            'first_invest' => 0, //首次投资
            'next_rebate' => 0, //下级返佣
            'vip' => 0, // vip购买奖励
        ];
        $data['all_reward']['all'] = Db::name('lc_finance')
            ->where('type', 1)->where($cond)
            ->whereIn('reason_type', [15, 3, 31, 30, 0, 8])
            ->sum('money');

        $data['all_reward']['real_name_authentication'] = Db::name('lc_finance')
            ->where('type', 1)->where($cond)
            ->whereIn('reason_type', [15])
            ->sum('money');

        $data['all_reward']['reg'] = Db::name('lc_finance')
            ->where('type', 1)->where($cond)
            ->whereIn('reason_type', [3])
            ->sum('money');

        $data['all_reward']['sign'] = Db::name('lc_finance')
            ->where('type', 1)->where($cond)
            ->whereIn('reason_type', [31])
            ->sum('money');

        $data['all_reward']['first_invest'] = Db::name('lc_finance')
            ->where('type', 1)->where($cond)
            ->whereIn('reason_type', [30])
            ->sum('money');

        $data['all_reward']['next_rebate'] = Db::name('lc_finance')
            ->where('type', 1)->where($cond)
            ->whereIn('reason_type', [0])
            ->sum('money');

        $data['all_reward']['vip'] = Db::name('lc_finance')
            ->where('type', 1)->where($cond)
            ->whereIn('reason_type', [8])
            ->sum('money');

        $data['first']['member'] = Db::name('lc_recharge')
            ->where('status', 1)
            ->whereIn('uid', $real_user_ids)
            ->where($cond)
            ->group('uid')
            ->count('id');

        $data['all']['cash'] = Db::name('lc_cash')->where($cond)->whereIn('uid', $real_user_ids)->where('status', 1)->sum('money');
        $data['all']['recharge'] =  Db::name('lc_recharge')->where($cond)->whereIn('uid', $real_user_ids)->where('status', 1)->sum('money');
        $data['all']['balance'] = bcsub($data['all']['recharge'], $data['all']['cash'], 2);
        $data['all']['bobby'] = $data['all']['recharge'] ? bcdiv($data['all']['cash'], $data['all']['recharge'], 2) : 0;

        // //本周充值
        // $data['real']['recharge_week'] = Db::name('lc_recharge')->where($week_where)->whereIn('uid', $real_user_ids)->where('status', 1)->sum('money');
        // $data['inside']['recharge_week'] = Db::name('lc_recharge')->where($week_where)->whereIn('uid', $inside_user_ids)->where('status', 1)->sum('money');
        // //本周提现
        // $data['real']['cash_week'] = Db::name('lc_cash')->where($week_where)->whereIn('uid', $real_user_ids)->where('status', 1)->sum('money');
        // $data['inside']['cash_week'] = Db::name('lc_cash')->where($week_where)->whereIn('uid', $inside_user_ids)->where('status', 1)->sum('money');
        // //本月充值
        // $data['real']['recharge_month'] = Db::name('lc_recharge')->where($month_where)->whereIn('uid', $real_user_ids)->where('status', 1)->sum('money');
        // $data['inside']['recharge_month'] = Db::name('lc_recharge')->where($month_where)->whereIn('uid', $inside_user_ids)->where('status', 1)->sum('money');
        // //本月提现
        // $data['real']['cash_month'] = Db::name('lc_cash')->where($month_where)->whereIn('uid', $real_user_ids)->where('status', 1)->sum('money');
        // $data['inside']['cash_month'] = Db::name('lc_cash')->where($month_where)->whereIn('uid', $inside_user_ids)->where('status', 1)->sum('money');
        //今日注册用户
        $data['real']['register_today'] = Db::name('lc_user')->where($today_where)->where('is_sf', 0)->count();
        $data['inside']['register_today'] = Db::name('lc_user')->where($today_where)->whereIn('is_sf', [1, 2])->count();
        //今日投资用户
        $data['real']['invest_today'] = Db::name('lc_invest')->where($today_where)->whereIn('uid', $real_user_ids)->count();
        $data['inside']['invest_today'] = Db::name('lc_invest')->where($today_where)->whereIn('uid', $inside_user_ids)->count();
        // //昨日注册用户
        // $data['real']['register_yesterday'] = Db::name('lc_user')->where($yesterday_where)->where('is_sf', 0)->count();
        // $data['inside']['register_yesterday'] = Db::name('lc_user')->where($yesterday_where)->whereIn('is_sf', [1,2])->count();
        // //昨日投资用户
        // $data['real']['invest_yesterday'] = Db::name('lc_invest')->where($yesterday_where)->whereIn('uid', $real_user_ids)->count();
        // $data['inside']['invest_yesterday'] = Db::name('lc_invest')->where($yesterday_where)->whereIn('uid', $inside_user_ids)->count();
        // //本周注册用户
        // $data['real']['register_week'] = Db::name('lc_user')->where($week_where)->where('is_sf', 0)->count();
        // $data['inside']['register_week'] = Db::name('lc_user')->where($week_where)->whereIn('is_sf', [1,2])->count();
        // //本周投资用户
        // $data['real']['invest_week'] = Db::name('lc_invest')->where($week_where)->whereIn('uid', $real_user_ids)->count();
        // $data['inside']['invest_week'] = Db::name('lc_invest')->where($week_where)->whereIn('uid', $inside_user_ids)->count();
        // //本月注册用户
        // $data['real']['register_month'] = Db::name('lc_user')->where($month_where)->where('is_sf', 0)->count();
        // $data['inside']['register_month'] = Db::name('lc_user')->where($month_where)->whereIn('is_sf', [1,2])->count();
        // //本月投资用户
        // $data['real']['invest_month'] = Db::name('lc_invest')->where($month_where)->whereIn('uid', $real_user_ids)->count();
        // $data['inside']['invest_month'] = Db::name('lc_invest')->where($month_where)->whereIn('uid', $inside_user_ids)->count();
        // //上月注册用户
        // $data['real']['register_permonth'] = Db::name('lc_user')->where($permonth_where)->where('is_sf', 0)->count();
        // $data['inside']['register_permonth'] = Db::name('lc_user')->where($permonth_where)->whereIn('is_sf', [1,2])->count();
        // //上月投资用户
        // $data['real']['invest_permonth'] = Db::name('lc_invest')->where($permonth_where)->whereIn('uid', $real_user_ids)->count();
        // $data['inside']['invest_permonth'] = Db::name('lc_invest')->where($permonth_where)->whereIn('uid', $inside_user_ids)->count();

        //明日预计发放收益
        //项目收益
        //     $invest_tomorrow = $this->calc_invest($real_user_ids,$inside_user_ids,'tomorrow','time1',0,'money1');
        //     //藏品收益
        //     $figure_collect_tomorrow = $this->calc_figure_collect($real_user_ids,$inside_user_ids,'tomorrow','able_sell_time',1,'expect_profit');
        //     //盲盒收益
        //     $blind_tomorrow = $this->calc_blind($real_user_ids,$inside_user_ids,'tomorrow','expect_time',0,'expect_profit');
        //     //途游宝收益
        //     $ebao_tomorrow = $this->calc_ebao($real_user_ids,$inside_user_ids,'tomorrow','time',0,'money',1);
        // //明日预计发放本金
        //     //项目收益
        //     $invest_bj_tomorrow = $this->calc_invest($real_user_ids,$inside_user_ids,'tomorrow','time1',0,'money');
        //     //藏品收益
        //     $figure_collect_bj_tomorrow = $this->calc_figure_collect($real_user_ids,$inside_user_ids,'tomorrow','able_sell_time',1,'money');
        //     //盲盒收益
        //     $blind_bj_tomorrow = $this->calc_blind($real_user_ids,$inside_user_ids,'tomorrow','expect_time',0,'money');
        //     //途游宝收益
        //     $ebao_bj_tomorrow = $this->calc_ebao($real_user_ids,$inside_user_ids,'tomorrow','time',0,'money',2);

        // //本周预计发放收益
        //     //项目收益
        //     $invest_week = $this->calc_invest($real_user_ids,$inside_user_ids,'nowweek','time1',0,'money1');
        //     //藏品收益
        //     $figure_collect_week = $this->calc_figure_collect($real_user_ids,$inside_user_ids,'nowweek','able_sell_time',1,'expect_profit');
        //     //盲盒收益
        //     $blind_week = $this->calc_blind($real_user_ids,$inside_user_ids,'nowweek','expect_time',0,'expect_profit');
        //     //途游宝收益
        //     $ebao_week = $this->calc_ebao($real_user_ids,$inside_user_ids,'nowweek','time',0,'money',1);
        // //本周预计发放本金
        //     //项目收益
        //     $invest_bj_week = $this->calc_invest($real_user_ids,$inside_user_ids,'nowweek','time1',0,'money');
        //     //藏品收益
        //     $figure_collect_bj_week = $this->calc_figure_collect($real_user_ids,$inside_user_ids,'nowweek','able_sell_time',1,'money');
        //     //盲盒收益
        //     $blind_bj_week = $this->calc_blind($real_user_ids,$inside_user_ids,'nowweek','expect_time',0,'money');
        //     //途游宝收益
        //     $ebao_bj_week = $this->calc_ebao($real_user_ids,$inside_user_ids,'nowweek','time',0,'money',2);

        // //本月预计发放收益
        //     //项目收益
        //     $invest_month = $this->calc_invest($real_user_ids,$inside_user_ids,'nowmonth','time1',0,'money1');
        //     //藏品收益
        //     $figure_collect_month = $this->calc_figure_collect($real_user_ids,$inside_user_ids,'nowmonth','able_sell_time',1,'expect_profit');
        //     //盲盒收益
        //     $blind_month = $this->calc_blind($real_user_ids,$inside_user_ids,'nowmonth','expect_time',0,'expect_profit');
        //     //途游宝收益
        //     $ebao_month = $this->calc_ebao($real_user_ids,$inside_user_ids,'nowmonth','time',0,'money',1);
        // //本月预计发放本金
        //     //项目收益
        //     $invest_bj_month = $this->calc_invest($real_user_ids,$inside_user_ids,'nowmonth','time1',0,'money');
        //     //藏品收益
        //     $figure_collect_bj_month = $this->calc_figure_collect($real_user_ids,$inside_user_ids,'nowmonth','able_sell_time',1,'money');
        //     //盲盒收益
        //     $blind_bj_month = $this->calc_blind($real_user_ids,$inside_user_ids,'nowmonth','expect_time',0,'money');
        //     //途游宝收益
        //     $ebao_bj_month = $this->calc_ebao($real_user_ids,$inside_user_ids,'nowmonth','time',0,'money',2);

        // //上月预计发放收益
        //     //项目收益
        //     $invest_premonth = $this->calc_invest($real_user_ids,$inside_user_ids,'permonth','time1',0,'money1');
        //     //藏品收益
        //     $figure_collect_premonth = $this->calc_figure_collect($real_user_ids,$inside_user_ids,'permonth','able_sell_time',1,'expect_profit');
        //     //盲盒收益
        //     $blind_premonth = $this->calc_blind($real_user_ids,$inside_user_ids,'permonth','expect_time',0,'expect_profit');
        //     //途游宝收益
        //     $ebao_premonth = $this->calc_ebao($real_user_ids,$inside_user_ids,'permonth','time',0,'money',1);
        // //上月预计发放本金
        //     //项目收益
        //     $invest_bj_premonth = $this->calc_invest($real_user_ids,$inside_user_ids,'permonth','time1',0,'money');
        //     //藏品收益
        //     $figure_collect_bj_premonth = $this->calc_figure_collect($real_user_ids,$inside_user_ids,'permonth','able_sell_time',1,'money');
        //     //盲盒收益
        //     $blind_bj_premonth = $this->calc_blind($real_user_ids,$inside_user_ids,'permonth','expect_time',0,'money');
        //     //途游宝收益
        //     $ebao_bj_premonth = $this->calc_ebao($real_user_ids,$inside_user_ids,'permonth','time',0,'money',2);

        // //明日预计发放收益
        // $data['real']['profit_tomorrow'] = $invest_tomorrow['real']+$figure_collect_tomorrow['real']+$blind_tomorrow['real']+$ebao_tomorrow['real'];
        // $data['inside']['profit_tomorrow'] = $invest_tomorrow['inside']+$figure_collect_tomorrow['inside']+$blind_tomorrow['inside']+$ebao_tomorrow['inside'];
        // //明日预计返还本金
        // $data['real']['bj_tomorrow'] = $invest_bj_tomorrow['real']+$figure_collect_bj_tomorrow['real']+$blind_bj_tomorrow['real']+$ebao_bj_tomorrow['real'];
        // $data['inside']['bj_tomorrow'] = $invest_bj_tomorrow['inside']+$figure_collect_bj_tomorrow['inside']+$blind_bj_tomorrow['inside']+$ebao_bj_tomorrow['inside'];
        // //本周预计返还收益 
        // $data['real']['profit_week'] = $invest_week['real']+$figure_collect_week['real']+$blind_week['real']+$ebao_week['real'];
        // $data['inside']['profit_week'] = $invest_week['inside']+$figure_collect_week['inside']+$blind_week['inside']+$ebao_week['inside'];
        // //本周预计返还本金
        // $data['real']['bj_week'] = $invest_bj_week['real']+$figure_collect_bj_week['real']+$blind_bj_week['real']+$ebao_bj_week['real'];
        // $data['inside']['bj_week'] = $invest_bj_week['inside']+$figure_collect_bj_week['inside']+$blind_bj_week['inside']+$ebao_bj_week['inside'];
        // //本月预计返还收益 
        // $data['real']['profit_month'] = $invest_month['real']+$figure_collect_month['real']+$blind_month['real']+$ebao_month['real'];
        // $data['inside']['profit_month'] = $invest_month['inside']+$figure_collect_month['inside']+$blind_month['inside']+$ebao_month['inside'];
        // //本月预计返还本金
        // $data['real']['bj_month'] = $invest_bj_month['real']+$figure_collect_bj_month['real']+$blind_bj_month['real']+$ebao_bj_month['real'];
        // $data['inside']['bj_month'] = $invest_bj_month['inside']+$figure_collect_bj_month['inside']+$blind_bj_month['inside']+$ebao_bj_month['inside'];

        // //上月预计返还收益 
        // $data['real']['profit_premonth'] = $invest_premonth['real']+$figure_collect_premonth['real']+$blind_premonth['real']+$ebao_premonth['real'];
        // $data['inside']['profit_premonth'] = $invest_premonth['inside']+$figure_collect_premonth['inside']+$blind_premonth['inside']+$ebao_premonth['inside'];
        // //上月预计返还本金
        // $data['real']['bj_premonth'] = $invest_bj_premonth['real']+$figure_collect_bj_premonth['real']+$blind_bj_premonth['real']+$ebao_bj_premonth['real'];
        // $data['inside']['bj_premonth'] = $invest_bj_premonth['inside']+$figure_collect_bj_premonth['inside']+$blind_bj_premonth['inside']+$ebao_bj_premonth['inside'];

        //今日项目交易量
        // $trade_today = $this->calc_trade('today',$real_user_ids,$inside_user_ids);
        // $data['real']['trade_today'] = $trade_today['real'];
        // $data['inside']['trade_today'] = $trade_today['inside'];
        // //昨日项目交易量
        // $trade_yesterday = $this->calc_trade('yesterday',$real_user_ids,$inside_user_ids);
        // $data['real']['trade_yesterday'] = $trade_yesterday['real'];
        // $data['inside']['trade_yesterday'] = $trade_yesterday['inside'];
        // //本周项目交易量
        // $trade_week = $this->calc_trade('nowweek',$real_user_ids,$inside_user_ids);
        // $data['real']['trade_week'] = $trade_week['real'];
        // $data['inside']['trade_week'] = $trade_week['inside'];
        // //当月项目交易量
        // $trade_month = $this->calc_trade('nowmonth',$real_user_ids,$inside_user_ids);
        // $data['real']['trade_month'] = $trade_month['real'];
        // $data['inside']['trade_month'] = $trade_month['inside'];
        // //上月项目交易量
        // $trade_premonth = $this->calc_trade('premonth',$real_user_ids,$inside_user_ids);
        // $data['real']['trade_premonth'] = $trade_premonth['real'];
        // $data['inside']['trade_premonth'] = $trade_premonth['inside'];
        // //团队数量
        // $data['member_grade'] = Db::name('lc_member_grade')->field('id,title')->select();
        // foreach ($data['member_grade'] as &$item) {
        //     $item['real_total_num'] = Db::name('lc_user')->where($where)->where('grade_id', $item['id'])->whereIn('id', $real_user_ids)->count();
        //     $item['inside_total_num'] = Db::name('lc_user')->where($where)->where('grade_id', $item['id'])->whereIn('id', $inside_user_ids)->count();
        // }

        // // 持币总数
        // $data['real']['imb'] = Db::name('lc_user')->where($where)->whereIn('id', $real_user_ids)->sum('kj_money');
        // $data['inside']['imb'] = Db::name('lc_user')->where($where)->whereIn('id', $inside_user_ids)->sum('kj_money');
        // // 提币总数
        // $data['real']['out_imb'] = Db::name('lc_mechines_finance')->where($this->get_where($search_time, 'add_time'))->where('type', 2)->whereIn('id', $real_user_ids)->sum('amount');
        // $data['inside']['out_imb'] = Db::name('lc_mechines_finance')->where($this->get_where($search_time, 'add_time'))->where('type', 2)->whereIn('id', $inside_user_ids)->sum('amount');

        //在线人数
        $data['real']['online'] = Db::name('lcUser')->whereIn('id', $real_user_ids)->where('logintime', '>', time() - 300)->count();
        $data['inside']['online'] = Db::name('lcUser')->whereIn('id', $inside_user_ids)->where('logintime', '>', time() - 300)->count();
        //今日签到人数
        // var_dump($this->gettimestamp('today','date'));exit;
        $data['real']['sign'] = Db::name('lc_user_sign_log l')->join('lc_user u', 'l.uid = u.id')->where($this->gettimestamp('today', 'date'))->where('is_sf', 0)->count();
        $data['inside']['sign'] = Db::name('lc_user_sign_log l')->join('lc_user u', 'l.uid = u.id')->where($this->gettimestamp('today', 'date'))->whereIn('is_sf', [1, 2])->count();

        //明日预计返回本金及收益
        $data['real']['item_sy'] = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')->where('is_sf', 0)->where($this->gettimestamp('tomorrow', 'time1'))->sum('money1');
        $data['inside']['item_sy'] = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')->whereIn('is_sf', [1, 2])->where($this->gettimestamp('tomorrow', 'time1'))->sum('money1');
        $data['real']['item_bj'] = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')->where('is_sf', 0)->where($this->gettimestamp('tomorrow', 'time1'))->sum('money');
        $data['inside']['item_bj'] = Db::name('lc_invest_list l')->join('lc_user u', 'l.uid = u.id')->whereIn('is_sf', [1, 2])->where($this->gettimestamp('tomorrow', 'time1'))->sum('money');
        //今日复投金额
        $start = strtotime(date('Y-m-d'));
        $over = strtotime(date('Y-m-d', strtotime('+1 day')));
        $map = "UNIX_TIMESTAMP(f.time) >= $start AND UNIX_TIMESTAMP(f.time) <= $over";
        $data['real']['ft_balance'] = Db::name('lc_finance f')->join('lc_user u', 'f.uid = u.id')->where('is_sf', 0)->where('reason_type', 16)->where($map)->sum('f.money');
        $data['inside']['ft_balance'] = Db::name('lc_finance f')->join('lc_user u', 'f.uid = u.id')->whereIn('is_sf', [1, 2])->where('reason_type', 16)->where($map)->sum('f.money');

        $this->assign('data_statistics', $data);
        $data = [];

        //上月-入款、出款、发送收益、新增投资、新增投资额
        $lastMonthFirstDate = strtotime(date('Y-m-01 00:00:00', strtotime(date("Y-m-d"))) . " -1 month");
        $lastMonthLastDate = strtotime(date('Y-m-01 23:59:59', strtotime(date("Y-m-d"))) . " -1 day");
        $data['report']['last_month'] = [
            'recharge' => Db::name('LcRecharge')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate AND status = 1")->sum('money'),
            'cash' => Db::name('LcCash')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate AND status = 1")->sum('money'),
            'invest_list' => Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time2) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate AND status = 1")->sum('pay1'),
            'invest' => Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate")->count(),
            'invest_sum' => Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate")->sum('money')
        ];
        //本月-入款、出款、发送收益、新增投资、新增投资额
        $firstDate = strtotime(date('Y-m-01 00:00:00', strtotime(date("Y-m-d"))));
        $lastDate = strtotime(date('Y-m-01 23:59:59', strtotime(date("Y-m-d"))) . " +1 month -1 day");
        $data['report']['month'] = [
            'recharge' => Db::name('LcRecharge')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate AND status = 1")->sum('money'),
            'cash' => Db::name('LcCash')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate AND status = 1")->sum('money'),
            'invest_list' => Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time2) BETWEEN $firstDate AND $lastDate AND status = 1")->sum('pay1'),
            'invest' => Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate")->count(),
            'invest_sum' => Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate")->sum('money')
        ];
        $data['report']['total'] = [
            'recharge' => bcadd($data['report']['month']['recharge'], $data['report']['last_month']['recharge'], 2),
            'cash' => bcadd($data['report']['month']['cash'], $data['report']['last_month']['cash'], 2),
            'invest_list' => bcadd($data['report']['month']['invest_list'], $data['report']['last_month']['invest_list'], 2),
            'invest' => bcadd($data['report']['month']['invest'], $data['report']['last_month']['invest'], 2),
            'invest_sum' => bcadd($data['report']['month']['invest_sum'], $data['report']['last_month']['invest_sum'], 2),
        ];

        $monthDays = $this->getMonthDays();
        $monthDays = array_reverse($monthDays);

        foreach ($monthDays as $k => $v) {
            $first = strtotime($v);
            $last = $first + 86400 - 1;
            $day[$k]['date'] = $v;
            $static = Db::name('lc_static')->where('date', $v)->find();
            $day[$k]['real_user'] = $static['real_user'];
            $day[$k]['inside_user'] = $static['inside_user'];
            $day[$k]['real_recharge'] = $static['real_recharge'];
            $day[$k]['inside_recharge'] = $static['inside_recharge'];
            $day[$k]['real_cash'] = $static['real_cash'];
            $day[$k]['inside_cash'] = $static['inside_cash'];
            $day[$k]['real_profit'] = $static['real_profit'];
            $day[$k]['inside_profit'] = $static['inside_profit'];
            $day[$k]['real_invest_num'] = $static['real_invest_num'];
            $day[$k]['inside_invest_num'] = $static['inside_invest_num'];
            $day[$k]['real_invest'] = $static['real_invest'];
            $day[$k]['inside_invest'] = $static['inside_invest'];
            $day[$k]['real_expire_money'] = $static['real_expire_money'];
            $day[$k]['inside_expire_money'] = $static['inside_expire_money'];
            $day[$k]['real_interest'] = $static['real_interest'];
            $day[$k]['inside_interest'] = $static['inside_interest'];

            // $day[$k]['recharge'] = Db::name('LcRecharge')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last AND status = 1")->sum('money');
            // $day[$k]['cash'] = Db::name('LcCash')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last AND status = 1")->sum('money');
            // $day[$k]['invest_list'] = Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time2) BETWEEN $first AND $last AND status = 1")->sum('pay1');
            // $day[$k]['new_user'] = Db::name('LcUser')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last")->count();
            // $day[$k]['invest'] = Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last")->count();
            // $day[$k]['invest_sum'] = Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last")->sum('money');
            // $day[$k]['expire'] = Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time1) BETWEEN $first AND $last")->sum('money');
            // $day[$k]['interest'] = Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time1) BETWEEN $first AND $last")->sum('money1');


            // $day[$k]['recharge'] = 0;
            // $day[$k]['cash'] = 0;
            // $day[$k]['invest_list'] = 0;
            // $day[$k]['new_user'] = 0;
            // $day[$k]['invest'] = 0;
            // $day[$k]['invest_sum'] = 0;
            // $day[$k]['expire'] = 0;
            // $day[$k]['interest'] = 0;
        }

        $data['today'] = $day;
        $this->assign('data', $data);
        $this->fetch();


    }

    public function calc_trade($time, $real_user_ids, $inside_user_ids)
    {
        //项目
        $real_trade_invest = Db::name('lc_invest')->whereIn('uid', $real_user_ids)->where($this->gettimestamp($time))->sum('money');
        $inside_trade_invest = Db::name('lc_invest')->whereIn('uid', $inside_user_ids)->where($this->gettimestamp($time))->sum('money');
        //藏品
        $real_trade_figure_collect = Db::name('lc_figure_collect_log')->whereIn('uid', $real_user_ids)->where($this->gettimestamp($time, 'create_time', 1))->sum('money');
        $inside_trade_figure_collect = Db::name('lc_figure_collect_log')->whereIn('uid', $inside_user_ids)->where($this->gettimestamp($time, 'create_time', 1))->sum('money');
        //盲盒
        $real_trade_blind = Db::name('lc_blind_buy_log')->whereIn('uid', $real_user_ids)->where('pay_status', 1)
            ->where($this->gettimestamp($time, 'create_time', 1))->sum('money');
        $inside_trade_blind = Db::name('lc_blind_buy_log')->whereIn('uid', $inside_user_ids)->where('pay_status', 1)
            ->where($this->gettimestamp($time, 'create_time', 1))->sum('money');
        //途游宝
        $real_trade_ebao = Db::name('lc_ebao_product_record')->whereIn('uid', $real_user_ids)
            ->where($this->gettimestamp($time, 'add_time'))->sum('money');
        $inside_trade_ebao = Db::name('lc_ebao_product_record')->whereIn('uid', $inside_user_ids)
            ->where($this->gettimestamp($time, 'add_time'))->sum('money');
        $real = $real_trade_blind + $real_trade_figure_collect + $real_trade_blind + $real_trade_ebao;
        $inside = $inside_trade_blind + $inside_trade_figure_collect + $inside_trade_blind + $inside_trade_ebao;
        return ['real' => $real, 'inside' => $inside];
    }

    //计算项目
    public function calc_invest($real_user_ids, $inside_user_ids, $time, $field, $type = 0, $s_field)
    {
        $real = Db::name('lc_invest_list')->where($this->gettimestamp($time, $field, $type))->whereIn('uid', $real_user_ids)->where('status', 0)->sum($s_field);
        $inside = Db::name('lc_invest_list')->where($this->gettimestamp($time, $field, $type))->whereIn('uid', $inside_user_ids)->where('status', 0)->sum($s_field);
        return ['real' => $real, 'inside' => $inside];
    }

    //计算藏品收益
    public function calc_figure_collect($real_user_ids, $inside_user_ids, $time, $field, $type = 0, $s_field)
    {
        $real = Db::name('lc_figure_collect_log')->where($this->gettimestamp($time, $field, $type))->whereIn('uid', $real_user_ids)->sum($s_field);
        $inside = Db::name('lc_figure_collect_log')->where($this->gettimestamp($time, $field, $type))->whereIn('uid', $inside_user_ids)->sum($s_field);
        return ['real' => $real, 'inside' => $inside];
    }

    //计算盲盒收益
    public function calc_blind($real_user_ids, $inside_user_ids, $time, $field, $type = 0, $s_field)
    {
        $real = Db::name('lc_blind_buy_log')->where($this->gettimestamp($time, $field, $type))->whereIn('uid', $real_user_ids)->where('pay_status', 1)->sum($s_field);
        $inside = Db::name('lc_blind_buy_log')->where($this->gettimestamp($time, $field, $type))->whereIn('uid', $inside_user_ids)->where('pay_status', 1)->sum($s_field);
        return ['real' => $real, 'inside' => $inside];
    }

    //计算途游宝收益
    public function calc_ebao($real_user_ids, $inside_user_ids, $time, $field, $type = 0, $s_field, $status)
    {
        $real = Db::name('lc_ebao_record')->where($this->gettimestamp($time))->whereIn('uid', $real_user_ids)->where('status', $status)->sum($s_field);
        $inside = Db::name('lc_ebao_record')->where($this->gettimestamp($time))->whereIn('uid', $inside_user_ids)->where('status', $status)->sum($s_field);
        return ['real' => $real, 'inside' => $inside];
    }

    public function gettimestamp($targetTime, $field = 'time', $type = 0)
    {
        switch ($targetTime) {
            case 'today'://今天
                $timeamp['start'] = strtotime(date('Y-m-d'));
                $timeamp['over'] = strtotime(date('Y-m-d', strtotime('+1 day')));
                break;
            case 'tomorrow': //明日
                $timeamp['start'] = strtotime(date('Y-m-d', strtotime('+1 day')));
                $timeamp['over'] = strtotime(date('Y-m-d', strtotime('+2 day')));
                break;
            case 'yesterday'://昨天
                $timeamp['start'] = strtotime(date('Y-m-d', strtotime('-1 day')));
                $timeamp['over'] = strtotime(date('Y-m-d'));
                break;
            case 'beforyesterday'://前天
                $timeamp['start'] = strtotime(date('Y-m-d', strtotime('-2 day')));
                $timeamp['over'] = strtotime(date('Y-m-d', strtotime('-1 day')));
                break;
            case 'nowmonth'://本月
                $timeamp['start'] = strtotime(date('Y-m-01'));
                $timeamp['over'] = strtotime(date('Y-m-d', strtotime('+1 day')));
                break;
            case 'permonth'://上月
                $timeamp['start'] = strtotime(date('Y-m-01', strtotime('-1 month')));
                $timeamp['over'] = strtotime(date('Y-m-01'));
                break;
            case 'preweek'://上周 注意我们是从周一开始算
                $timeamp['start'] = strtotime(date('Y-m-d', strtotime('-2 week Monday')));
                $timeamp['over'] = strtotime(date('Y-m-d', strtotime('-1 week Monday +1 day')));
                break;
            case 'nowweek'://本周
                $timeamp['start'] = strtotime(date('Y-m-d', strtotime('-1 week Monday')));
                $timeamp['over'] = strtotime(date('Y-m-d', strtotime('+1 day')));
                break;
            case 'preday'://30
                $timeamp['start'] = strtotime(date('Y-m-d'), strtotime($param . ' day'));
                $timeamp['end'] = strtotime(date('Y-m-d'));
                break;
            case 'nextday'://30
                $timeamp['start'] = strtotime(date('Y-m-d'));
                $timeamp['over'] = strtotime(date('Y-m-d'), strtotime($param . ' day'));
                break;
            case 'preyear'://去年
                $timeamp['start'] = strtotime(date('Y-01-01', strtotime('-1 year')));
                $timeamp['over'] = strtotime(date('Y-12-31', strtotime('-1 year')));
                break;
            case 'nowyear'://今年
                $timeamp['start'] = strtotime(date('Y-01-01'));
                $timeamp['over'] = strtotime(date('Y-m-d', strtotime('+1 day')));
                break;
            case 'quarter'://季度
                $quarter = ceil((date('m')) / 3);
                $timeamp['start'] = mktime(0, 0, 0, $quarter * 3 - 3 + 1, 1, date('Y'));
                $timeamp['over'] = mktime(23, 59, 59, $quarter * 3, date('t', mktime(0, 0, 0, $season * 3, 1, date("Y"))), date('Y'));
                break;
            default:
                $timeamp['start'] = strtotime(date('Y-m-d'));
                $timeamp['over'] = strtotime(date('Y-m-d', strtotime('+1 day')));
                break;
        }
        $start_time = $timeamp['start'];
        $end_time = $timeamp['over'];
        if ($type) {
            return "$field > $start_time AND $field < $end_time";
        }
        return "UNIX_TIMESTAMP($field) >= $start_time AND UNIX_TIMESTAMP($field) <= $end_time";
    }

    /**
     * 后台环境信息
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function main2()
    {

        $now = time();

        /*$this->think_ver = \think\App::VERSION;
        $this->mysql_ver = Db::query('select version() as ver')[0]['ver'];*/
        $this->invest_count = Db::name('LcInvest')->count();
        // 用户总户数
        $this->user_count = Db::name('LcUser')->count();
        // 今日注册用户数量
        // $this->current_reg_user_count = Db::name('LcUser')->where('time', 'today')->count();
        $this->current_reg_user_count = Db::name('LcUser')->where("to_days(time) = to_days(now())")->count();


        // 今日投资
        $today_tz = Db::name("LcInvest")->where("to_days(time) = to_days(now())")->field('uid')->select();
        $yestoday_tz = Db::name("LcInvest")->where("TO_DAYS(NOW( ) ) - TO_DAYS( time) <= 1  ")->field('uid')->select();

        $today_tz_num = count(array_map("unserialize", array_unique(array_map("serialize", $today_tz))));
        $yestoday_tz_num = count(array_map("unserialize", array_unique(array_map("serialize", $yestoday_tz))));

        //今日投资
        // $today_tz_num = Db::name("LcInvest")->where('time', 'today')->group('uid')->count();
        //昨日投资
        // $yestoday_tz_num = Db::name('LcInvest')->where('time', 'yestoday')->group('uid')->count();


        // 获取明日收益
        // 查询利率
        $reward = Db::name("LcReward")->find(1);
        $rete = $reward['ebao_rate'];
        $money_m = Db::name("LcInvestList")->where("UNIX_TIMESTAMP(time1) <= $now AND status = '0'")->sum("money1");
        $benjin_bj = Db::name("LcInvestList")->where("DATE_FORMAT(time1,'%Y-%m-%d') = date_add(DATE_FORMAT(NOW(),'%Y-%m-%d'), interval 1 day) AND status = '0'")->sum("money");


        // 团队数量
        $grade_num = Db::name("LcUser")->where('grade_id', ">=", '2')->count();

        // 持币总数
        $imbNum = Db::name("LcUser")->sum("kj_money");

        // 提币总数
        $outImbNum = Db::name("LcMechinesFinance")->where("type = 2")->sum("amount");

        // 当天交易
        // $todayTransaction = Db::name("LcInvest")->where('time', 'today')->sum("money");
        $todayTransaction = Db::name("LcInvest")->where("to_days(time) = to_days(now())")->sum("money");
        // 昨天
        // $yesterdayTransaction = Db::name("LcInvest")->where('time', 'yestoday')->sum("money");
        $yesterdayTransaction = Db::name("LcInvest")->where("TO_DAYS(NOW( ) ) - TO_DAYS( time) <= 1  ")->sum("money");
        // 本周
        // $weekTransaction = Db::name("LcInvest")->where('time', 'week')->sum("money");
        $weekTransaction = Db::name("LcInvest")->where("YEARWEEK(date_format(time,'%Y-%m-%d'),1) = YEARWEEK(now(),7)")->sum("money");
        // 本月
        // $toMonthTransaction = Db::name("LcInvest")->where('time', 'month')->sum("money");
        $toMonthTransaction = Db::name("LcInvest")->where("DATE_FORMAT(time, '%Y-%m') = DATE_FORMAT(now(),'%Y-%m')")->sum("money");


        // $this->recharge_sum = Db::name('LcRecharge')->where("status = 1")->sum('money');

        // $this->cash_sum = Db::name('LcCash')->where("status = 1")->sum('money');
        //   $adnid= db("LcUser")->where('is_sf','eq',0)->field('id')->select();
        // $adnid=array_column($adnid,'id');

        $adnid = Db::name('LcUser')->where('is_sf', 0)->column('id');
        // 今日充值
        // $this->today_cz = Db::name('LcRecharge')->whereIn('uid', $adnid)->where('time', 'today')->where('status', 1)->sum('money');
        $this->today_cz = Db::name("LcRecharge")->where('uid', 'in', $adnid)->where("to_days(time) = to_days(now())")->where("status = 1")->sum('money');
        //今日回款
        // $this->today_hk = Db::name("LcInvestList")->whereIn('uid', $adnid)->where('time2', 'today')->where('status', 1)->sum('pay2');
        $this->today_hk = Db::name("LcInvestList")->where('uid', 'in', $adnid)->where("to_days(time2) = to_days(now())")->where("status = 1")->sum('pay2');
        // where('id','eq',36024)->

        // $this->recharge_sum = Db::name('LcRecharge')->whereIn('uid', $adnid)->where('status', 1)->sum('money');
        $this->recharge_sum = Db::name('LcRecharge')->where('uid', 'in', $adnid)->where("status = 1")->sum('money');

        // $this->cash_sum = Db::name('LcCash')->whereIn('uid', $adnid)->where('status', 1)->sum('money');
        $this->cash_sum = Db::name('LcCash')->where('uid', 'in', $adnid)->where("status = 1")->sum('money');


        // $allmnid= db("LcUser")->where('is_sf','in',[1,2])->field('id')->select();
        //  $allmnid=array_column($allmnid,'id');
        $allmnid = Db::name('LcUser')->whereIn('is_sf', [1, 2])->column('id');
        //  $allmnid=implode(',',$allmnid);
        //  var_dump($allmnid);


        $this->mn_recharge_sum = Db::name('LcRecharge')->where('uid', 'in', $allmnid)->where("status = 1")->sum('money');

        $this->mn_recharge_sum_today = Db::name('LcRecharge')->where('uid', 'in', $allmnid)->where("status = 1")->where("to_days(time) = to_days(now())")->sum('money');
        $this->mn_cash_sum_today = Db::name('LcCash')->where('uid', 'in', $allmnid)->where("status = 1")->where("to_days(time) = to_days(now())")->sum('money');
        $this->mn_cash_sum = Db::name('LcCash')->where('uid', 'in', $allmnid)->where("status = 1")->sum('money');
        $this->mn_tz_sum_today = Db::name('LcInvest')->where('uid', 'in', $allmnid)->where("to_days(time) = to_days(now())")->sum('money');

        //获取在线人数
        $pCount = Db::name('lcUser')->where('logintime', '>', time() - 300)->count();


        $table = $this->finance_report();
        $this->month = $table['month'];
        $this->last_month = $table['last_month'];
        $this->day = $table['day'];
        $this->today_tz_num = $today_tz_num;
        $this->yestoday_tz_num = $yestoday_tz_num;
        $this->money_m = $money_m;
        $this->benjin_bj = $benjin_bj;
        $this->grade_num = $grade_num;
        $this->imbNum = $imbNum;
        $this->outImbNum = $outImbNum;
        $this->todayTransaction = $todayTransaction;
        $this->yesterdayTransaction = $yesterdayTransaction;
        $this->weekTransaction = $weekTransaction;
        $this->toMonthTransaction = $toMonthTransaction;
        $this->pCount = $pCount;
        $this->fetch();
    }

    public function main3()
    {
        $now = time();
        //已投项目
        $data['invest_count'] = Db::name('LcInvest')->count();
        //会员总数
        $data['user_count'] = Db::name('LcUser')->count();
        //充值总额
        $adnid = Db::name('LcUser')->where('is_sf', 0)->column('id');
        $data['recharge_sum'] = Db::name('LcRecharge')->whereIn('uid', $adnid)->where('status', 1)->sum('money');
        //提现总额
        $data['cash_sum'] = Db::name('LcCash')->whereIn('uid', $adnid)->where('status', 1)->sum('money');
        //今日注册用户
        $data['current_reg_user_count'] = Db::name('LcUser')->where('time', 'today')->count();
        //今日投资用户
        $data['today_tz_num'] = Db::name("LcInvest")->where('time', 'today')->group('uid')->count();
        //昨日投资用户
        $data['yestoday_tz_num'] = Db::name('LcInvest')->where('time', 'yestoday')->group('uid')->count();
        // 今日充值
        $data['today_cz'] = Db::name('LcRecharge')->whereIn('uid', $adnid)->where('time', 'today')->where('status', 1)->sum('money');
        //今日回款
        $data['today_hk'] = Db::name("LcInvestList")->whereIn('uid', $adnid)->where('time2', 'today')->where('status', 1)->sum('pay2');

        //明日预计发放收益
        $data['money_m'] = Db::name("LcInvestList")->where("UNIX_TIMESTAMP(time1) <= $now AND status = '0'")->sum("money1");
        //明日预计返还本金
        $data['benjin_bj'] = Db::name("LcInvestList")->where("DATE_FORMAT(time1,'%Y-%m-%d') = date_add(DATE_FORMAT(NOW(),'%Y-%m-%d'), interval 1 day) AND status = '0'")->sum("money");
        //团队数量
        $data['grade_num'] = Db::name("LcUser")->where('grade_id', ">=", '2')->count();
        // 当天交易
        $data['todayTransaction'] = Db::name("LcInvest")->where('time', 'today')->sum("money");
        // 昨天交易
        $data['yesterdayTransaction'] = Db::name("LcInvest")->where('time', 'yestoday')->sum("money");
        // 本周交易
        $data['weekTransaction'] = Db::name("LcInvest")->where('time', 'week')->sum("money");
        // 本月交易
        $data['monthTransaction'] = Db::name("LcInvest")->where('time', 'month')->sum("money");


        $allmnid = Db::name('LcUser')->whereIn('is_sf', [1, 2])->column('id');
        //今日模拟账户充值
        $data['mn_recharge_sum_today'] = Db::name('LcRecharge')->whereIn('uid', $allmnid)->where("status = 1")->where('time', 'today')->sum('money');
        $data['mn_recharge_sum'] = Db::name('LcRecharge')->whereIn('uid', $allmnid)->where("status = 1")->sum('money');

        $data['mn_cash_sum_today'] = Db::name('LcCash')->whereIn('uid', $allmnid)->where("status = 1")->where('time', 'today')->sum('money');
        $data['mn_cash_sum'] = Db::name('LcCash')->whereIn('uid', $allmnid)->where("status = 1")->sum('money');
        $data['mn_tz_sum_today'] = Db::name('LcInvest')->whereIn('uid', $allmnid)->where('time', 'today')->sum('money');
        // 持币总数
        $data['imbNum'] = Db::name("LcUser")->sum("kj_money");

        // 提币总数
        $data['outImbNum'] = Db::name("LcMechinesFinance")->where("type = 2")->sum("amount");

        //获取在线人数
        $data['pCount'] = Db::name('lcUser')->where('logintime', '>', time() - 300)->count();

        //上月-入款、出款、发送收益、新增投资、新增投资额
        $lastMonthFirstDate = strtotime(date('Y-m-01 00:00:00', strtotime(date("Y-m-d"))) . " -1 month");
        $lastMonthLastDate = strtotime(date('Y-m-01 23:59:59', strtotime(date("Y-m-d"))) . " -1 day");
        $data['report']['last_month'] = [
            'recharge' => Db::name('LcRecharge')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate AND status = 1")->sum('money'),
            'cash' => Db::name('LcRecharge')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate AND status = 1")->sum('money'),
            'invest_list' => Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time2) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate AND status = 1")->sum('pay1'),
            'invest' => Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate")->count(),
            'invest_sum' => Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate")->sum('money')
        ];
        //本月-入款、出款、发送收益、新增投资、新增投资额
        $firstDate = strtotime(date('Y-m-01 00:00:00', strtotime(date("Y-m-d"))));
        $lastDate = strtotime(date('Y-m-01 23:59:59', strtotime(date("Y-m-d"))) . " +1 month -1 day");
        $data['report']['month'] = [
            'recharge' => Db::name('LcRecharge')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate AND status = 1")->sum('money'),
            'cash' => Db::name('LcRecharge')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate AND status = 1")->sum('money'),
            'invest_list' => Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time2) BETWEEN $firstDate AND $lastDate AND status = 1")->sum('pay1'),
            'invest' => Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate")->count(),
            'invest_sum' => Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate")->sum('money')
        ];
        $data['report']['total'] = [
            'recharge' => bcadd($data['report']['month']['recharge'], $data['report']['last_month']['recharge'], 2),
            'cash' => bcadd($data['report']['month']['cash'], $data['report']['last_month']['cash'], 2),
            'invest_list' => bcadd($data['report']['month']['invest_list'], $data['report']['last_month']['invest_list'], 2),
            'invest' => bcadd($data['report']['month']['invest'], $data['report']['last_month']['invest'], 2),
            'invest_sum' => bcadd($data['report']['month']['invest_sum'], $data['report']['last_month']['invest_sum'], 2),
        ];

        $monthDays = $this->getMonthDays();
        foreach ($monthDays as $k => $v) {
            $first = strtotime($v);
            $last = $first + 86400 - 1;
            $day[$k]['date'] = $v;
            $day[$k]['recharge'] = Db::name('LcRecharge')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last AND status = 1")->sum('money');
            $day[$k]['cash'] = Db::name('LcCash')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last AND status = 1")->sum('money');
            $day[$k]['invest_list'] = Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time2) BETWEEN $first AND $last AND status = 1")->sum('pay1');
            $day[$k]['new_user'] = Db::name('LcUser')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last")->count();
            $day[$k]['invest'] = Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last")->count();
            $day[$k]['invest_sum'] = Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last")->sum('money');
            $day[$k]['expire'] = Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time1) BETWEEN $first AND $last")->sum('money');
            $day[$k]['interest'] = Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time1) BETWEEN $first AND $last")->sum('money1');
        }

        $data['today'] = $day;
        $this->assign('data', $data);
        $this->fetch('main1');
    }

    private function finance_report()
    {
        $firstDate = strtotime(date('Y-m-01 00:00:00', strtotime(date("Y-m-d"))));
        $lastDate = strtotime(date('Y-m-01 23:59:59', strtotime(date("Y-m-d"))) . " +1 month -1 day");
        $month['recharge'] = Db::name('LcRecharge')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate AND status = 1")->sum('money');
        $month['cash'] = Db::name('LcCash')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate AND status = 1")->sum('money');
        $month['invest_list'] = Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time2) BETWEEN $firstDate AND $lastDate AND status = 1")->sum('pay1');
        $month['invest'] = Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate")->count();
        $month['invest_sum'] = Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $firstDate AND $lastDate")->sum('money');

        $lastMonthFirstDate = strtotime(date('Y-m-01 00:00:00', strtotime(date("Y-m-d"))) . " -1 month");
        $lastMonthLastDate = strtotime(date('Y-m-01 23:59:59', strtotime(date("Y-m-d"))) . " -1 day");
        $lastMonth['recharge'] = Db::name('LcRecharge')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate AND status = 1")->sum('money');
        $lastMonth['cash'] = Db::name('LcCash')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate AND status = 1")->sum('money');
        $lastMonth['invest_list'] = Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time2) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate AND status = 1")->sum('pay1');
        $lastMonth['invest'] = Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate")->count();
        $lastMonth['invest_sum'] = Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $lastMonthFirstDate AND $lastMonthLastDate")->sum('money');

        $monthDays = $this->getMonthDays();
        foreach ($monthDays as $k => $v) {
            $first = strtotime($v);
            $last = $first + 86400 - 1;
            $day[$k]['date'] = $v;
            $day[$k]['recharge'] = Db::name('LcRecharge')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last AND status = 1")->sum('money');
            $day[$k]['cash'] = Db::name('LcCash')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last AND status = 1")->sum('money');
            $day[$k]['invest_list'] = Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time2) BETWEEN $first AND $last AND status = 1")->sum('pay1');
            $day[$k]['new_user'] = Db::name('LcUser')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last")->count();
            $day[$k]['invest'] = Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last")->count();
            $day[$k]['invest_sum'] = Db::name('LcInvest')->where("UNIX_TIMESTAMP(time) BETWEEN $first AND $last")->sum('money');
            $day[$k]['expire'] = Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time1) BETWEEN $first AND $last")->sum('money');
            $day[$k]['interest'] = Db::name('LcInvestList')->where("UNIX_TIMESTAMP(time1) BETWEEN $first AND $last")->sum('money1');
        }
        return array('day' => $day, 'month' => $month, 'last_month' => $lastMonth);
    }

    /**
     * 获取当前月已过日期
     * @return array
     */
    private function getMonthDays()
    {
        $monthDays = [];
        $firstDay = date('Y-m-01', time());
        $i = 0;
        $now_day = date('d');
        $lastDay = date('Y-m-d', strtotime("$firstDay +1 month -1 day"));
        while (date('Y-m-d', strtotime("$firstDay +$i days")) <= $lastDay) {
            if ($i >= $now_day) break;
            $monthDays[] = date('Y-m-d', strtotime("$firstDay +$i days"));
            $i++;
        }
        return $monthDays;
    }

    /**
     * 修改密码
     * @login true
     * @param integer $id
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function pass($id)
    {
        $this->applyCsrfToken();
        if (intval($id) !== intval(session('user.id'))) {
            $this->error('只能修改当前用户的密码！');
        }
        if (!AdminService::instance()->isLogin()) {
            $this->error('需要登录才能操作哦！');
        }
        if ($this->request->isGet()) {
            $this->verify = true;
            $this->_form('SystemUser', 'admin@user/pass', 'id', [], ['id' => $id]);
        } else {
            $data = $this->_input([
                'password' => $this->request->post('password'),
                'repassword' => $this->request->post('repassword'),
                'oldpassword' => $this->request->post('oldpassword'),
            ], [
                'oldpassword' => 'require',
                'password' => 'require|min:4',
                'repassword' => 'require|confirm:password',
            ], [
                'oldpassword.require' => '旧密码不能为空！',
                'password.require' => '登录密码不能为空！',
                'password.min' => '登录密码长度不能少于4位有效字符！',
                'repassword.require' => '重复密码不能为空！',
                'repassword.confirm' => '重复密码与登录密码不匹配，请重新输入！',
            ]);
            $user = Db::name('SystemUser')->where(['id' => $id])->find();
            // echo md5(md5($data['oldpassword']).$user['salt']);exit;
            if (md5(md5($data['oldpassword']) . $user['salt']) !== $user['password']) {
                $this->error('旧密码验证失败，请重新输入！');
            }
            if (Data::save('SystemUser', ['id' => $user['id'], 'password' => md5(md5($data['password']) . $user['salt'])])) {
                $this->success('密码修改成功，下次请使用新密码登录！', '');
            } else {
                $this->error('密码修改失败，请稍候再试！');
            }
        }
    }

    /**
     * 修改用户资料
     * @login true
     * @param integer $id 会员ID
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function info($id = 0)
    {
        if (!AdminService::instance()->isLogin()) {
            $this->error('需要登录才能操作哦！');
        }
        $this->applyCsrfToken();
        if (intval($id) === intval(session('user.id'))) {
            $this->_form('SystemUser', 'admin@user/form', 'id', [], ['id' => $id]);
        } else {
            $this->error('只能修改登录用户的资料！');
        }
    }

    /**
     * 清理运行缓存
     * @auth true
     */
    public function clearRuntime()
    {
        try {
            Console::call('clear');
            Console::call('xclean:session');
            $this->success('清理运行缓存成功！');
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $e) {
            $this->error("清理运行缓存失败，{$e->getMessage()}");
        }
    }

    /**
     * 压缩发布系统
     * @auth true
     */
    public function buildOptimize()
    {
        try {
            Console::call('optimize:route');
            Console::call('optimize:schema');
            Console::call('optimize:autoload');
            Console::call('optimize:config');
            $this->success('压缩发布成功！');
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $e) {
            $this->error("压缩发布失败，{$e->getMessage()}");
        }
    }

}
