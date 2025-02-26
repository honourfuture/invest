<?php

namespace app\admin\model;

use think\Model;
use think\Db;

class Users extends Model
{
    protected $user_table = 'LcUser';
    protected $finance_table = 'LcFinance';


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
    public function addFinance($uid, $money, $type, $zh_cn, $zh_hk, $en_us, $th_th, $vi_vn, $ja_jp, $ko_kr, $ms_my, $remark = "", $reason = "", $reason_type = 0)
    {
        $user = Db::name($this->user_table)->find($uid);
        if ($user['money'] < 0) return false;
        if (!$user) return false;
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
        $re = Db::name($this->finance_table)->insert($data);
        if ($re) {
            Db::commit();
            return true;
        } else {
            Db::rollback();
            return false;
        }
    }


    public function gradeUpgrade($uid)
    {
        // 查询用户信息
        $member = Db::name("LcUser")->find($uid);

        //团队升级 需要满足充值金额 直推人数 直推团长数
        //团队升级 本人
        //直推人数
//         $tg_num = Db::name("LcUser")->where("recom_id", $uid)->count();
//         //邀请直推团长数
//         $where_find = [
//             "grade_id" => ["gt", "1"]
//         ];
//         $tz_num = Db::name("LcUser")->where("recom_id", $uid)->where($where_find)->count();

//         //统计下级直推累计金额
// //        $xjlj_money = Db::name("LcUser")->where("recom_id", $uid)->sum("czmoney");
//         $xjlj_money = 0;
//         //团队充值 本人累计充值 + 下级直推累计充值
//         $lj_money = $member['czmoney'] + $xjlj_money;
//         // 取团队ID
//         $team_id = Db::name("LcMemberGrade")->where('id', '>', 1)->order('id desc')->find();
//         // 比较等级
//         $tid = $this->bjgrade($tg_num, $lj_money, $tz_num);
//         // 获取比较后的段对
//         $team_data = Db::name("LcMemberGrade")->where("id", $tid)->field('all_activity,title,id,recom_tz,recom_number')->find();
//         //团队满足充值升级
//         if ($team_data) {
//             //团队满足直推会员人数	升级
//             if ($tg_num >= $team_data['recom_number']) {
//                 //团队满足直推团长数	升级
//                 if ($tz_num >= $team_data['recom_tz']) {
//                     $tdate['grade_id'] = $tid;

//                     $tdate['grade_name'] = $team_data['title'];

//                     Db::name("LcUser")->where("id", $member["id"])->update($tdate);
//                 }
//             }
//         } else {
//             //团队满足充值升级
//             if ($team_id['all_activity'] <= $lj_money) {
//                 //团队满足直推会员人数	升级
//                 if ($tg_num >= $team_id['recom_number']) {
//                     //团队满足直推团长数	升级
//                     if ($tz_num >= $team_id['recom_tz']) {
//                         $tdate['grade_id'] = $team_id['id'];


//                         $tdate['grade_name'] = $team_id['title'];

//                         Db::name("LcUser")->where("id", $member["id"])->update($tdate);
//                     }
//                 }
//             }
//         }
//         //团队升级 上级
//         if ($member["recom_id"]) {
//             $sj_members = Db::name("LcUser")->where("id", $member["recom_id"])->field('level_id,czmoney,grade_id,id')->find();
//             //邀请直推会员数
//             $sjtg_num = Db::name("LcUser")->where("recom_id", $sj_members["id"])->count();
//             //邀请直推团长数
//             $sjwhere_find = [
//                 "grade_id" => ["gt", "1"]
//             ];
//             $sjtz_num = Db::name("LcUser")->where("recom_id", $sj_members["id"])->where($sjwhere_find)->count();
// //            $xj_lj = Db::name("LcUser")->where("recom_id", $sj_members["id"])->sum("czmoney");
//             $xj_lj =0;

//             //团队充值 本人累计充值+下级直推累计充值

//             $sjteam_lj = $sj_members['czmoney'] + $xj_lj;
//             $sj_tid = $this->bjgrade($sjtg_num, $sjteam_lj, $sjtz_num);
//             $sjteam_data = Db::name("LcMemberGrade")->where("id", $sj_tid)->field('all_activity,title,id,recom_tz,recom_number')->find();
         
//             if ($sjteam_data) {
//                 //团队满足直推会员人数	升级
//                 if ($sjtg_num >= $sjteam_data['recom_number']) {
//                     if ($sjtz_num >= $sjteam_data['recom_tz']) {
//                         $sj_tdate['grade_id'] = $sj_tid;
//                         $sj_tdate['grade_name'] = $sjteam_data['title'];
//                         Db::name("LcUser")->where("id", $sj_members["id"])->update($sj_tdate);
//                     }
//                 }
//             } else {
//                 //团队满足充值升级
//                 if ($team_id['all_activity'] <= $sjteam_lj) {
//                     //团队满足直推会员人数	升级
//                     if ($sjtg_num >= $team_id['recom_number']) {
//                         //团队满足直推团长数	升级
//                         if ($sjtz_num >= $team_id['recom_tz']) {
//                             $sj_tdate['grade_id'] = $team_id['id'];


//                             $sj_tdate['grade_name'] = $team_id['title'];

//                             Db::name("LcUser")->where("id", $sj_members["id"])->update($sj_tdate);
//                         }
//                     }
//                 }
//             }
//         }
    // }


    // /**
    //  * 比较等级
    //  * @param $recom_number 直推人数
    //  * @param $all_activity 累计充值
    //  * @param $recom_tz 团长充值
    //  * @return mixed
    //  */
    // public function bjgrade($recom_number, $all_activity, $recom_tz)
    // {
    //     $aid = Db::name("LcMemberGrade")->where("all_activity", '<=', $all_activity)->order("id desc")->value('id');
    //     $bid = Db::name("LcMemberGrade")->where("recom_number", '<=', $recom_number)->order("id desc")->value('id');
    //     $cid = Db::name("LcMemberGrade")->where("recom_tz", '<=', $recom_tz)->order("id desc")->value('id');
    //     $mid = $aid;
    //     if ($mid > $bid) {
    //         $mid = $bid;
    //     }
    //     if ($mid > $cid) {
    //         $mid = $cid;
    //     }
    //     return $mid;

    }
}
