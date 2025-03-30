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
use library\tools\Data;
use think\Image;

/**
 * 首页
 * Class Index
 * @package app\index\controller
 */
class Im extends Controller
{
    public function publicGroup()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $page = $this->request->param('page', 1);
        $size = $this->request->param('size', 10);
        $language = $this->request->param('language', 'zh_cn');
        $lists = Db::name('lc_group')->where('is_public', 1)->order('id desc')->select();
        foreach ($lists as &$item) {
            //进群状态
            $exit = Db::name('lc_group_member')->where('uid', $uid)->where('group_id', $item['id'])->find();
            if ($exit) {
                $item['join_status'] = 1;
            } else {
                $notice = Db::name('lc_group_invite')->where('group_id', $item['id'])->where('receiver_id', $uid)->where('status', 0)->find();
                if ($notice) {
                    $item['join_status'] = 2;
                } else {
                    $item['join_status'] = 3;
                }
            }
            $item['reason'] = $item['reason_'.$language];
        }
        $this->success('获取成功', $lists);
    }
    
    public function applyJoin()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $groupId = $this->request->param('groupId', 0);
        if (!$group = Db::name('lc_group')->find($groupId)) {
            $this->error('Nhóm chát không tồn tại');
        } elseif ($group['able_apply'] == 0) {
            $this->error('该群不可申请进群');
        }
        if (Db::name('lc_group_member')->where('uid', $uid)->where('group_id', $group['id'])->find()) {
            $this->error('已加入该群，请直接进入');
        }
        //提交申请
        Db::name('LcGroupInvite')->insert([
            'group_id' => $groupId,
            'receiver_id' => $uid,
            'send_id' => $group['main_uid'],
            'add_time' => date('Y-m-d H:i:s', time())
        ]);
    }
    
    public function getInGroupTime()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $group_id = $this->request->param('groupId', 0);
        $group = Db::name('lc_group_member')->where(['uid' => $uid, 'group_id' => $group_id])->value('add_time');
        
        $this->success('获取成功', strtotime($group).'000');
    }
    
    //修改群公告
    public function  edit_group_notice()
    {
        $group_id = $this->request->param('groupId', 0);
        $content = $this->request->param('content', '');
        if (!$info = Db::name('lc_group')->find($group_id)) {
            $this->error('Nhóm chát không tồn tại');
        }
        Db::name('lc_group')->where('id', $info['id'])->update(['notice' => $content]);
        $this->success('修改成功');
    }
    
    //设置群聊昵称
    public function edit_group_name()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $group_id = $this->request->param('groupId', 0);
        $name = $this->request->param('name', '');
        if (!$info = Db::name('lc_group')->find($group_id)) {
            $this->error('Nhóm chát không tồn tại');
        }
        Db::name('lc_group_member')->where('uid', $uid)->where('group_id', $group_id)->update(['nickname' => $name]);
        $this->success('修改成功');
    }

    /**
     * 好友列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function friendsList()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // echo $uid;exit;
        // $uid = 40191;
        $group_id = $this->request->param('groupId', 0);
        
        if ($group_id) {
            $tuids = Db::name('lc_group_member')->where('group_id', $group_id)->column('uid');
             $flist = Db::name('LcFriendsRelation f')
            ->leftJoin("lc_user u", "f.tuid = u.id")
            ->where(['f.uid' => $uid])
            ->whereNotIn('f.tuid', $tuids)
            ->field("u.id uid, u.phone, u.username, u.avatar, f.remarks, f.status,f.remark")
            ->select();
        } else {
             $flist = Db::name('LcFriendsRelation f')
            ->leftJoin("lc_user u", "f.tuid = u.id")
            ->where(['f.uid' => $uid])
            ->field("u.id uid, u.phone, u.username, u.avatar, f.remarks, f.status,f.remark")
            ->select();
        }
            
        $aes = new Aes();
        foreach($flist as &$item) {
            $item['phone'] = $aes->decrypt($item['phone']);
            if (!empty($item['remark'])) {
                $item['username'] = $item['remark'];
            }
            if(empty($item['username'])){
                $item['username'] = \lang('text-3').$item['uid'];
            }
        }

        $this->success("获取成功", $flist);
    }
    
    //修改好友备注
    public function editFriendRemark()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $tuid = $this->request->param('tuid', 0); //好友ID
        $remark = $this->request->param('remark', '');  //备注名称
        if (empty($remark)) {
            $this->error('备注名称不能为空');
        }
        Db::name('lc_friends_relation')->where('uid', $uid)->where('tuid', $tuid)->update(['remark' => $remark]);
        $this->success('修改成功');
    }


    /**
     * 查找
     * @return void
     */
    public function find(){
        $params = $this->request->param();
        
        //当前用户信息
        $this->checkToken();
        $user = Db::name('lc_user')->find($this->userInfo['id']);
        
        
        if ($user['search_friend_num'] > 30 || $user['search_friend_time'] > time()) {
            Db::name('lc_user')->where('id', $user['id'])->update(['search_friend_time' => time()+60]);
             $this->error(get_tip(229, $params['language']));
        } else {
            Db::name('lc_user')->where('id', $user['id'])->update(['search_friend_num' => $user['search_friend_num']+1, 'search_friend_time' => time()]);
        }
        
        
        
        
        $keyword = intval($params['keyword']);
        if($keyword<111111||$keyword>999999){
            $this->error("没有搜索到相关数据");
        }
        // 查询用户信息
        //$aes = new Aes();
        //$keyword = $aes->encrypt($keyword);
        //$userInfo = Db::name('LcUser')->where(['phone' => $keyword])->field('id')->find();
        $userInfo = Db::name('LcUser')->where(['mid' => $keyword])->field('id')->find();
        

        // 如果搜索到用户，则返回
        if($userInfo){
            $this->success("获取成功", array(
                'type' => 1,
                'info' => $userInfo
            ));
        }

        // 查询群聊
        $groupInfo = Db::name('LcGroup')->where(['no' => $keyword])->field('id')->find();
        if($groupInfo){
            $this->success("获取成功", array(
                'type' => 2,
                'info' => $groupInfo
            ));
        }
        $this->error("没有搜索到相关数据");
    }


    /**
     * 好友资料
     * @return void
     */
    public function friendsInfo(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // 好友资料
        $params = $this->request->param();
        $tuid = $params['uid'];
        $group_id = $this->request->param('groupId', 0);
        // 查询用户信息
        $userInfo = Db::name('LcUser')->where(['id' => $tuid])->field('id, phone, username, avatar, member')->find();
        if (!$userInfo) $this->error('用户信息不存在');
        $aes = new Aes();
        $userInfo['phone'] = $aes->decrypt($userInfo['phone']);
        // 是否好友，0非好友，1好友
        $isFriends = 0;
        // 查询好友信息
        $friendsRelation = Db::name('LcFriendsRelation')->where(['uid' => $uid, 'tuid' => $tuid])->find();
        if($friendsRelation){
            $isFriends = 1;
            $userInfo['remarks'] = $friendsRelation['remarks'];
            $userInfo['friendsStatus'] = $friendsRelation['status'];
            $userInfo['remark'] = $friendsRelation['remark'];
        } else {
            $userInfo['remark'] = '';
        }
        $userInfo['isFriends'] = $isFriends;
        $userInfo['member_info'] = Db::name('lc_user_member')->find($userInfo['member']);
        
        //获取群聊昵称
        $nickname = Db::name('lc_group_member')->where('uid', $tuid)->where('group_id', $group_id)->value('nickname');
        if (!empty($nickname)) {
            $userInfo['name'] = $nickname;
        }
        $userInfo['role'] = Db::name('lc_group_member')->where('uid', $tuid)->where('group_id', $group_id)->value('role');
        
        $this->success("获取成功", $userInfo);
    }


    /**
     * 添加好友
     * @return
     */
    public function addFriends(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // 好友资料
        $params = $this->request->param();
        $tuid = $params['uid'];
        
        if ($tuid == $uid) {
            $this->error('不能添加自己为好友');
        }

        // 判断是否有重复的未通过
        $count = Db::name('LcFriendsNotice')
            ->where(['tuid' => $tuid, 'uid' => $uid, 'status' => '0'])
            ->count();
        if($count > 0){
            $this->success("操作成功");
        }
        $unPass = Db::name('LcFriendsNotice')
            ->where(['tuid' => $tuid, 'uid' => $uid, 'status' => '2'])
            ->find();
        if($unPass){
            // 是拒绝了的
            Db::name('LcFriendsNotice')
            ->where(['id' => $unPass['id']])
            ->update(['status'=> '0']);
            $this->success("操作成功");
        }
        //一分钟内只能添加两次好友申请
        // $list = Db::name('lc_friends_notice')->where('uid', $uid)->order('add_time desc')->limit(10)->field('add_time')->select();
        // if (count($list) == 10) {
        //     if (strtotime($list[1]['add_time']) > time()-60) {
        //         $this->error('Hoạt động thường xuyên, xin vui lòng thử lại sau');
        //     }
        // }

        // 创建添加好友申请
        $add = array(
            'uid' => $uid,
            'tuid' => $tuid,
            'add_time' => date('Y-m-d H:i:s'),
            'status' => 0
        );
        $re = Db::name('LcFriendsNotice')->insertGetId($add);
        if($re){
            $this->success("操作成功");
        }
//        $this->success("请勿频繁操作");
    }


    // 好友通知
    public function friendsNotice(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $flist = Db::name('LcFriendsNotice f')
            ->leftJoin("lc_user u", "f.uid = u.id")
            ->where(['f.tuid' => $uid])
            ->field("f.id id, u.id uid, u.phone, u.username, u.avatar, f.status")
            ->order('id desc')
            ->select();
        $aes = new Aes();
        foreach($flist as &$vo)
        {
            $vo['phone'] = substr($aes->decrypt($vo['phone']),0,3).'*****'.substr($aes->decrypt($vo['phone']),-3);
        }
        $this->success("获取成功", $flist);
    }


    // 好友审核
    public function friendsNoticeAudit(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // 好友资料
        $params = $this->request->param();
        $status = $this->request->param('status', 1);
        $nid = $params['nid'];

        // 查询通知信息
        $nocite = Db::name('LcFriendsNotice')->where(['id' => $nid])->find();
        //拒绝申请
        if ($status == 2) {
            Db::name('LcFriendsNotice')->where("id = {$nid}")->update(['status' => 2]);
            $this->success("操作成功");
        }


        // 查询是否已经是好友
        $isExtens = Db::name('LcFriendsRelation')->where(['uid' => $nocite['uid'], 'tuid' => $nocite['tuid']])->count();
        if($isExtens> 0){
            Db::name('LcFriendsNotice')->where("id = {$nid}")->update(['status' => 1]);

            $this->success("操作成功");
        }


        // 添加好友关系
        $add = array(
            'uid' => $nocite['uid'],
            'tuid' => $nocite['tuid'],
            'remarks' => '',
            'add_time' => date('Y-m-d H:i:s'),
            'status' => 0
        );
        $re = Db::name('LcFriendsRelation')->insertGetId($add);
        $add2 = array(
            'uid' => $nocite['tuid'],
            'tuid' => $nocite['uid'],
            'remarks' => '',
            'add_time' => date('Y-m-d H:i:s'),
            'status' => 0
        );
        $re = Db::name('LcFriendsRelation')->insertGetId($add2);

        // 修改状态
        Db::name('LcFriendsNotice')->where("id = {$nid}")->update(['status' => 1]);

        $this->success("操作成功");
    }
    //撤回
    public function delGroupMsg(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $data = array('appkey' => 'BC-b7e0e5d84c7b48a1b0d011512168fdc2', 
        'groupId' => $params['groupId'],
        'messages'=>[$params['timestamp']]
        );
        $json_data = json_encode($data);
        
        // 设置请求选项
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://rest-hz.goeasy.io/v2/im/history/recall');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($json_data)));
        
        // 执行请求
        $result = curl_exec($ch);
        curl_close($ch);
        
        // 处理响应数据
        $response_data = json_decode($result, true);
        $this->success("操作成功");
    }
    //设置管理员
    public function setGroupAdmin(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        if($uid==$params['uid']){
            $this->error('操作错误');
        }
        $userInfo = Db::name('LcGroupMember')->where(['uid' => $uid,'group_id'=>$params['groupId']])->field('role')->find();
        if($userInfo['role']!=0){
            $this->error('权限不足');
        }
        $where = array(
            'uid' => $params['uid'],
            'group_id' => $params['groupId']
        );
        // $role = 0;
        // if($params['role']==1) $role = 1;
        Db::name('LcGroupMember')->where($where)->update(['role'=>$params['role']]);
        $this->success("操作成功");
    }
    /**
     * 创建群聊
     * @return void
     */
    public function createGroup(){
        $this->checkToken();
        $uid = $this->userInfo['id'];

        $params = $this->request->param();
        // 开始获取创建群聊参数
        $name = 'nhóm'.rand(1000, 9999); //$params['name'];
        $avatar = ''; //$params['avatar'];
        $uids = $params['uids'];
        //建群条件
        // $userInfo = Db::name('LcUser')->where(['id' => $uid])->field('id, grade_id')->find();
        // $grade = Db::name('lc_member_grade')->find($userInfo['grade_id'])['title'];
        // if($grade=='普通用户'){
        //     $this->error('未达到一级团队长');
        // }
        
        //一分钟内只能添加两次群聊申请
        // $list = Db::name('lc_group')->where('main_uid', $uid)->order('add_time desc')->limit(10)->field('add_time')->select();
        // if (count($list) == 10) {
        //     if (strtotime($list[1]['add_time']) > time()-60) {
        //         $this->error('一分钟内只能添加两次群聊申请');
        //     }
        // }

        // 先创建一个ID
        $add = array(
            'id' => rand(1000000, 9999999),
            'type' => 1
        );
        $no = Db::name('LcAutoNo')->insertGetId($add);

        // 创建群聊记录
        $add = array(
            'main_uid' => $uid,
            'avatar' => $avatar,
            'name' => $name,
            'add_time' => date('Y-m-d H:i:s'),
            'status' => 1,
            'no' => $no
        );
        $groupId = Db::name('LcGroup')->insertGetId($add);

        // 创建群成员
        $member = array(
            'uid' => $uid,
            'role' => 0,
            'add_time' => date('Y-m-d H:i:s'),
            'group_id' => $groupId
        );
        Db::name('LcGroupMember')->insertGetId($member);

        if (count($uids)) {
          // 创建群员通知
            foreach ($uids as $v){
                // 创建群成员
                $member = array(
                    'receiver_id' => $v,
                    'send_id' => $uid,
                    'add_time' => date('Y-m-d H:i:s'),
                    'group_id' => $groupId,
                    'type' => 2,
                    'status' => 0
                );
                Db::name('LcGroupNotice')->insertGetId($member);
            }  
        }

        $this->success("操作成功", $groupId);
    }


    /**
     * 获取群聊信息
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGroupInfo(){
        $this->checkToken();
        $uid = $this->userInfo['id'];

        $params = $this->request->param();
        $groupId = $params['groupId'];
        $groupInfo = Db::name('LcGroup')->where(['id' => $groupId])->find();


        $groupInfo['is_main'] = $uid == $groupInfo['main_uid'];
//        if($groupInfo['is_estoppel'] == 1){}
        $info = Db::name('LcGroupMember')->where(['group_id' => $groupId,'uid' => $uid])->find();
        $groupInfo['is_admin'] = $info['role'] == 1;

        $groupInfo['estoppel'] = $groupInfo['is_estoppel'] == 1;
        
        $groupInfo['user_name'] = Db::name('lc_group_member')->where('uid', $uid)->where('group_id', $groupId)->value('nickname');
        if (empty($groupInfo['user_name'])) {
            $groupInfo['user_name'] = Db::name('lc_user')->find($uid)['name'];
        }
        

        $this->success("操作成功", $groupInfo);
    }
    
    public function setGroupInviteStatus()
    {
        $groupId = $this->request->param('groupId');
        $info = Db::name('lc_group')->find($groupId);
        $invite_status = $info['invite_status'] ? 0 : 1;
        Db::name('lc_group')->where('id', $groupId)->update(['invite_status' => $invite_status]);
        $this->success("操作成功");
    }
    
    public function addGroupStatus()
    {
        $status = 1;//是否开启建群1是0不是
        $this->success('获取成功', $status);
    }


    /**
     * 群聊成员列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGroupMemberList(){
        $this->checkToken();
        $params = $this->request->param();
        $groupId = $params['groupId'];
        
        // $groupMemberList = Db::name('LcGroupMember')->where('group_id', $groupId)->select();
        
        // $this->success("操作成功", $groupMemberList);
        
        $groupMemberList = Db::name('LcGroupMember m')
            ->leftJoin("lc_user u ", "u.id = m.uid")
            ->field("u.avatar avatar, u.username, u.id,m.nickname,m.role,m.add_time")
            ->where(['group_id' => $groupId])->select();
        foreach ($groupMemberList as &$item) {
            if (!empty($item['nickname'])) {
                $item['name'] = $item['nickname'];
            }
        }
        $this->success("操作成功", $groupMemberList);
    }


    /**
     * 群聊列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getGroupList(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $groupList = Db::name('LcGroupMember m')
            ->rightJoin("lc_group g ", "g.id = m.group_id")
            ->field("g.avatar,  g.name, g.id")
            ->group('g.id')
            ->where(['m.uid' => $uid, 'g.status' => 1])->select();
        $this->success("操作成功", $groupList);
    }


    /**
     * 移除成员
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function removeMember(){
        $this->checkToken();
        $params = $this->request->param();
        $groupId = $params['groupId'];
        $uid = $params['uid'];

        // 查询当前用户权限
        $currentUid = $this->userInfo['id'];
        $memberInfo = Db::name('LcGroupMember')->where(['group_id' => $groupId, 'uid' => $currentUid])->find();
        if($memberInfo['role'] == 2){
            $this->error("Không được phép");
        }

        // 移除
        Db::name('LcGroupMember')->where(['group_id' => $groupId, 'uid' => $uid])->delete();
        $this->success("操作成功");
    }
    
    /**
     * 解散群聊
     * @return void
     */
    public function closeGroupChat()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $groupId = $params['groupId'];
        $groupInfo = Db::name('lc_group')->find($groupId);
        if ($uid != $groupInfo['main_uid']) {
            $this->error('群主才有解散群聊权限');
        }
        
        Db::name('lc_group')->where('id', $groupId)->update(['status' => 2]);
        $this->success('操作成功');
    }

    //邀请好友进群
    public function addMember()
    {
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $uids = $params['uids'];
        $groupId = $params['groupId'];
        if (!$groupInfo = Db::name('lc_group')->find($groupId)) {
            $this->error('Nhóm chát không tồn tại');
        }
        // if ($uid != $groupInfo['main_uid']) {
        //     $this->error('仅限群主邀请好友');
        // }
        
        // if (!$groupInfo['invite_status'] || $uid != $groupInfo['main_uid']) {
        //     $this->error('邀请群成员未开启');
        // }
        if (!count($uids)) {
            $this->error('Hãy chọn ít nhất một thành viên');
        }
        $user = Db::name('lc_user')->find($uid);
        $role = Db::name('lc_group_member')->where(['uid' => $uid, 'group_id' => $groupId])->find();
        foreach ($uids as $v) {
            //已经在群
            $count = Db::name('LcGroupMember')->where(['group_id' => $groupId, 'uid' => $v])->count();
            if($count > 0){
                continue;
            }
            $user = Db::name('lc_user')->find($v);
            //如果为管理员或群主直接进群
            if ($role['role'] == 2) {   //非群主、管理员
                if(Db::name('LcGroupInvite')->where('status',0)->where([
                    'group_id' => $groupId,
                    'receiver_id' => $v,
                    // 'send_id' => $uid
                    ])->find()){
                    $this->error('Đã được mời, đừng lặp lại chiến dịch!');
                    
                }
                //创建审核申请
                Db::name('LcGroupInvite')->insert([
                    'group_id' => $groupId,
                    'receiver_id' => $v,
                    'send_id' => $uid,
                    'add_time' => date('Y-m-d H:i:s', time())
                ]);
            } else {
                //创建群成员
                Db::name('LcGroupMember')->insert([
                    'uid' => $v,
                    'role' => 2,
                    'add_time' => date('Y-m-d H:i:s', time()),
                    'group_id' => $groupId,
                    'nickname' => $user['username']
                ]);
            }
        }
        $this->success("操作成功");
    }

    /**
     * 邀请成员-旧
     * @return void
     */
    public function addMemberOld(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $uids = $params['uids'];
        $groupId = $params['groupId'];
        
        $groupInfo = Db::name('lc_group')->find($groupId);
        if ($uid != $groupInfo['main_uid']) {
            $this->error('仅限群主邀请好友');
        }
        
        if (!$groupInfo['invite_status'] || $uid != $groupInfo['main_uid']) {
            $this->error('邀请群成员未开启');
        }
        
        if (!count($uids)) {
            $this->error('请至少选择一位成员');
        }

        // 查询是否已加入
        foreach ($uids as $v){
            $count = Db::name('LcGroupMember')->where(['group_id' => $groupId, 'uid' => $v])->count();
            if($count > 0){
                continue;
            }
            
            $user = Db::name('lc_user')->find($v);
            // var_dump($uid);
            // var_dump($v);exit;
            if (Db::name('lc_group_notice')->where(['send_id' => $uid,'receiver_id' => $v, 'group_id' => $groupId])->find()) {
                $this->error($user['name'].'已收到群聊邀请，请勿重复发送');
            }
            
            // 创建群成员
//            $member = array(
//                'uid' => $v,
//                'role' => 2,
//                'add_time' => date('Y-m-d H:i:s'),
//                'group_id' => $groupId
//            );
            // 创建群成员
            $member = array(
                'receiver_id' => $v,
                'send_id' => $uid,
                'add_time' => date('Y-m-d H:i:s'),
                'group_id' => $groupId,
                'type' => 2,
                'status' => 0
            );
            Db::name('LcGroupNotice')->insertGetId($member);
        }
        $this->success("操作成功");
    }


    /**
     * 设置群聊名称
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function setGroupName(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        // 开始获取创建群聊参数
        $groupId = $params['groupId'];
        $name = $params['name'];

        // 先查询群聊
        $groupInfo = Db::name('LcGroup')->where(['id' => $groupId])->find();
        if($groupInfo['main_uid'] != $uid){
            $this->error("Không được phép");
        }

        // 开始修改
        Db::name('LcGroup')->where("id = {$groupId}")->update(['name' => $name]);
        $this->success("操作成功");
    }


    /**
     * 设置群聊公告
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function setGroupAnnouncement(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        // 开始获取创建群聊参数
        $groupId = $params['groupId'];
        $announcement = $params['announcement'];

        // 先查询群聊
        $groupInfo = Db::name('LcGroup')->where(['id' => $groupId])->find();
        if($groupInfo['main_uid'] != $uid){
            $this->error("Không được phép");
        }

        // 开始修改
        Db::name('LcGroup')->where("id = {$groupId}")->update(['announcement' => $announcement]);
        $this->success("操作成功");
    }


    /**
     * 删除好友
     * @return void
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function removeFriends(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        // 好友用户ID
        $tuid = $params['tuid'];

        Db::name('LcFriendsRelation')->where("uid = {$uid} and tuid = {$tuid}")->delete();
        Db::name('LcFriendsRelation')->where("uid = {$tuid} and tuid = {$uid}")->delete();
        $this->success("操作成功");
    }


    /**
     * 设置全员禁言
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function setGroupEstoppel(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        // 开始获取创建群聊参数
        $groupId = $params['groupId'];
        $estoppel = $params['estoppel'];

        // 先查询群聊
        $groupInfo = Db::name('LcGroup')->where(['id' => $groupId])->find();
        if($groupInfo['main_uid'] != $uid){
            $this->error("Không được phép");
        }

        // 开始修改
        Db::name('LcGroup')->where("id = {$groupId}")->update(['is_estoppel' => $estoppel]);
        $this->success("操作成功");
    }


    /**
     * 群聊审核
     * @return void
     */
    public function groupAudit(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        // 好友用户ID
        $nid = $params['nid'];

        // 查询通知信息
        $notice = Db::name('LcGroupNotice')->where("id = {$nid} and receiver_id = {$uid}")->find();

        // 修改状态为已通过
        Db::name('LcGroupNotice')->where("id = {$nid} and receiver_id = {$uid}")->update(['status' => 1]);

        $tuid = 0;
        // 判断类型
        if($notice['type'] == 2){
            // 邀请审核
            $tuid = $uid;
        }else if($notice['type'] == 3){
            $tuid = $notice['send_id'];
        }

        // 判断是否已经入群
        $count = Db::name("LcGroupMember")->where("group_id = {$notice['group_id']} and uid = {$uid}")->count();

        if($count > 0){
            $this->success("操作成功");
        }


        // 加入到群成员
        // 创建群成员
        $member = array(
            'uid' => $tuid,
            'role' => 2,
            'add_time' => date('Y-m-d H:i:s'),
            'group_id' => $notice['group_id']
        );
        Db::name('LcGroupMember')->insertGetId($member);

        $this->success("操作成功");
    }



    // 好友通知
    public function groupNotice(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $list = Db::name('LcGroupNotice n')
            ->leftJoin("lc_user u", "n.send_id = u.id")
            ->leftJoin("lc_group g", "n.group_id = g.id")
            ->where(['n.receiver_id ' => $uid])
            ->field("n.id id, u.username userName, u.phone, g.avatar, u.avatar userAvatar, g.name, n.group_id, n.type,  n.status ")
            ->order("id desc")
            ->select();
        $this->success("获取成功", $list);
    }


    /**
     * 发送消息验证
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function sendGroupMessageCheck(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $groupId = $params['groupId'];
        // 查询当前用户是否处于该群聊
        $member = Db::name('LcGroupMember')->where("uid = {$uid} and group_id = {$groupId}")->find();
        if(!$member){
            $this->error("Nếu bạn không tham gia cuộc trò chuyện nhóm này, bạn không thể nói chuyện!");
        }

        // 判断是否管理员
        if($member['role'] != 2){
            $this->success("操作成功");
        }

        // 查询群聊信息
        $group = Db::name('LcGroup')->where("id = {$groupId}")->find();

        // 检查是否处于禁言状态
        if($group['is_estoppel'] == 1){
            $this->error("Quản trị viên đã khoá nhóm, không thể gửi tin nhắn！");
        }
        
        
        // 检查是否被单独禁言
        if(strtotime($member['estoppel_time']) > time()){
            $time = date('Y-m-d H:i:s', strtotime($member['estoppel_time']));
            $this->error("你当前处于禁言中，无法发言，解封时间为".$time);
        }
        
        $this->success("操作成功");
    }



    /**
     * 设置群聊头像
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function setGroupAvatar(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        // 开始获取创建群聊参数
        $groupId = $params['groupId'];
        $avatar = $params['avatar'];

        // 先查询群聊
        $groupInfo = Db::name('LcGroup')->where(['id' => $groupId])->find();
        if($groupInfo['main_uid'] != $uid){
            $this->error("Không được phép");
        }

        // 开始修改
        Db::name('LcGroup')->where("id = {$groupId}")->update(['avatar' => $avatar]);
        $this->success("操作成功");
    }


    /**
     * 发送红包
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function sendRedPack(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);
        
        $redisKey = 'LockKeyUserSendRedPack'.$uid;
        $lock = new \app\api\util\RedisLock();
        if(!$lock->lock($redisKey,60,0)){
            $this->error("Hoạt động thường xuyên, xin vui lòng thử lại sau");
        }

        // 获取红包参数
        $params = $this->request->param();
        $language = $params['language'];
        $amount = $params['amount'];
        $description = $params['description'];
        $method = $params['method'];
        $type = $params['type'];
        $num = $params['num'];
        $receiverId = $params['receiverId'];
        $password = $params['password'];
        
        // if ($method == 2) {
        //     $groupInfo = Db::name('lc_group')->find($receiverId);
        //     if (!$groupInfo['is_redpack']) {
        //         $this->error('暂未开通红包功能');
        //     }
        // } 
        // $single_chat_redpack = Db::name('lc_info')->find(1)['single_chat_redpack'];
        // if ($method == 1 && !$single_chat_redpack) {
        //         $this->error('暂未开通红包功能');
        // }
        
        // if (!$this->user['auth']) $this->error(get_tip(237, $language));
        
        //会员等级
        if (in_array($this->user['member'], [8015, 8003])) {
            $this->error('Để tham gia phong bì đỏ, các thành viên cần phải có VIP2');
        }
        
        //一分钟内只能发送两次
        $list = Db::name('lc_redpack_record')->where('uid', $uid)->order('add_time desc')->limit(2)->field('add_time')->select();
        if (count($list) == 2) {
            if (strtotime($list[1]['add_time']) > time()-60) {
                $this->error('Hoạt động thường xuyên, xin vui lòng thử lại sau');
            }
        }
        
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $amount)) {
            $this->error('Hoạt động thường xuyên, xin vui lòng thử lại sau 1');
        }
        
        if($amount > 200) {
            $this->error('Một phong bì màu đỏ số tiền không quá 200 nhân dân tệ');
        }
        
        $now = time();
        $todayTotalAmount = Db::name('lc_redpack_record')->where('uid', $uid)->where("to_days(add_time) = to_days(now())")->sum('total_amount');
        // if (($todayTotalAmount+$amount) > 18000) {
        //     if ($todayTotalAmount >= 18000) {
        //         $differ = 0;
        //     } else {
        //         $differ = 18000 - $todayTotalAmount;
        //     }
        //     $this->error('当天限额18000元，剩余额度'.$differ.'元');
        // }
        
        if (!preg_match('/^[0-9]+$/', $num)) {
            $this->error('Hãy nhập đúng định dạng số');
        }
        
        // 判断交易密码是否正确
        if(md5($password) != $this->user['password2']){
            $this->error("Mật khẩu là không đúng");
        }

        // 检查用户余额是否足够
        if ($this->user['money'] < $amount) $this->error(Db::name('LcTips')->field("$language")->find('65'));

        // 扣除余额
        $desc = "Gửi lì xì" . $amount;

        addFinance($uid, $amount, 2,
            $desc,
            $desc,
            $desc,
            $desc,
            $desc,
            $desc,
            $desc,
            $desc,
            "","",14
        );
        setNumber('LcUser', 'money', $amount, 2, "id = $uid");

        // 创建发红包记录
        $record = array(
            'uid' => $uid,
            'method' => $method,
            'receiver_id' => $receiverId,
            'total_amount' => $amount,
            'add_time' => date('Y-m-d H:i:s'),
            'type' => $type,
            'remaining_amount' => $amount,
            'remaining_num' => $num,
            'num' => $num,
            'description' => $description
        );
        $redId = Db::name('LcRedpackRecord')->insertGetId($record);

        // 如果是拼手气红包，则提前算好红包金额
        if($type == 2){
            $redpacks = $this->redAlgorithm($amount,$num);
            // 先提前创建好红包记录
            foreach ($redpacks as $v){
                // 创建领红包记录
                $reveive = array(
                    'red_id' => $redId,
                    'send_id' => $uid,
                    'receiver_id' => 0,
                    'amount' => $v,
                    'add_time' => date('Y-m-d H:i:s'),
                    'type' => 1
                );
                Db::name('LcRedpackReceive')->insertGetId($reveive);
            }
        }

        $this->success("操作成功", $redId);
    }


    /**
     * 拆红包算法
     * @return void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function openRedPack(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $this->user = Db::name('LcUser')->find($uid);

        // 获取红包参数
        $params = $this->request->param();
        $language = $params['language'];
        $redId = $params['redId'];

        // 查询红包信息
        $redInfo = Db::name('LcRedpackRecord')->where(['id' => $redId])->find();
        
        
        if (!$redInfo) $this->error('红包不存在');
        
        //会员等级
        // if (in_array($this->user['member'], [8015, 8003])) {
        //     $this->error('Để tham gia phong bì đỏ, các thành viên cần phải có VIP2');
        // }
        
        //echo $redInfo['method'];exit;
        // 检查红包类型
        if($redInfo['method'] == 1){    //单聊

            // 判断是不是自己发的
            if($uid == $redInfo['uid']){
                $this->error("Không thể nhận được phong bì màu đỏ của mình!");
            }

            // 私聊，check是否为对方好友
            $friend = Db::name('LcFriendsRelation')->where(['tuid' => $uid, 'uid' => $redInfo['uid']])->find();
            if(!$friend){
                $this->error("非对方好友，无法领取红包！");
            }

            // 检查是否已经领取过这个红包
            $reveive = Db::name('LcRedpackReceive')->where(['red_id' => $redId, 'receiver_id' => $uid])->find();
            if($reveive){
                $this->success("操作成功", $reveive);
            }

            if($redInfo['remaining_num'] == 0){
                $this->error("Đã mở lì xì!");
            }
            
            if ($redInfo['status']) {
                $this->error('Phong bì màu đỏ đã hết hạn!');
            }

            // 开始拆红包
            // 修改红包信息
            $rs = Db::name('LcRedpackRecord')
                ->where("id = {$redId} and remaining_amount > 0 and remaining_num > 0")
                ->update(['remaining_amount' => 0, 'remaining_num' => 0]);

            if($rs){
                // 创建领红包记录
                $reveive = array(
                    'red_id' => $redId,
                    'send_id' => $redInfo['uid'],
                    'receiver_id' => $uid,
                    'amount' => $redInfo['total_amount'],
                    'add_time' => date('Y-m-d H:i:s'),
                    'type' => 1
                );
                Db::name('LcRedpackReceive')->insertGetId($reveive);

                // 开始增加收入
                $desc = "đề" . $redInfo['total_amount'];
                addFinance($uid, $redInfo['total_amount'], 1,
                    $desc,
                    $desc,
                    $desc,
                    $desc,
                    $desc,
                    $desc,
                    $desc,
                    $desc,
                    "","",14
                );
                setNumber('LcUser', 'money', $redInfo['total_amount'], 1, "id = $uid");
                $this->success("操作成功", $reveive);
            }
            $this->error("Không hoạt động thường xuyên");
        }else if($redInfo['method'] == 2){  //群聊
            // 群聊红包，验证是否处于该群聊
            $member = Db::name('LcGroupMember')->where(['group_id' => $redInfo['receiver_id'], 'uid' => $uid])->find();
            if(!$member){
                $this->error("不在该群聊，无法领取红包！");
            }

            // 检查是否已经领取过这个红包
            $reveive = Db::name('LcRedpackReceive')->where(['red_id' => $redId, 'receiver_id' => $uid])->find();
            if($reveive){
                $this->success("操作成功", $reveive);
            }

            // 判断是否还有剩余
            if($redInfo['remaining_num'] == 0){
                $this->error("Đã mở lì xì！");
            }
            
            if ($redInfo['status']) {
                $this->error('Phong bì màu đỏ đã hết hạn！');
            }

            // 修改红包数量 -1
            Db::name('LcRedpackRecord')
                ->where("id = {$redId} and remaining_amount > 0")
                ->setDec("remaining_num", 1);

            // 开始拆红包
            $amount = 0;
            // 判断红包类型，普通红包 or 拼手气红包
            if($redInfo['type'] == 1){
                $amount = $redInfo['total_amount'] / $redInfo['num'];
                // 直接分配金额，创建领红包记录
                $reveive = array(
                    'red_id' => $redId,
                    'send_id' => $redInfo['uid'],
                    'receiver_id' => $uid,
                    'amount' => $amount,
                    'add_time' => date('Y-m-d H:i:s'),
                    'type' => 1
                );
                Db::name('LcRedpackReceive')->insertGetId($reveive);
            }else{
                // 拼手气，查询一个未领取过的红包金额
                $reveive = Db::name('LcRedpackReceive')->where(['red_id' => $redId, 'receiver_id' => 0])->find();
                if(!$reveive){
                    $this->success("Đã mở lì xì", $reveive);
                }

                // 绑定给该用户
                Db::name('LcRedpackReceive')
                    ->where("id = {$reveive['id']} and receiver_id = 0")
                    ->update(['receiver_id' => $uid]);
                $amount = $reveive['amount'];
            }


            // 开始扣除金额
            Db::name('LcRedpackRecord')
                ->where("id = {$redId} and remaining_amount > 0")
                ->setDec("remaining_amount", $amount);

            // 开始增加用户余额
            // 开始增加收入
            $desc = "Nhận một phong bì màu đỏ" . $amount;
            addFinance($uid, $amount, 1,
                $desc,
                $desc,
                $desc,
                $desc,
                $desc,
                $desc,
                $desc,
                $desc,
                "","",14
            );
            setNumber('LcUser', 'money', $amount, 1, "id = $uid");
            $this->success("操作成功", $reveive);
        }
    }


    /**
     * 拼手气红包分配算法
     *
     * @param $money 金额
     * @param $count 数量
     */
    function redAlgorithm($money, int $count)
    {
        // 参数校验
        if ($count * 0.01 > $money) {
            throw new Exception("单个红包不能低于0.01USDT");
        }
        // 存放随机红包
        $redpack = [];
        // 未分配的金额
        $surplus = $money;
        for ($i = 1; $i <= $count; $i++) {
            // 安全金额
            $safeMoney = $surplus - ($count - $i) * 0.01;
            // 平均金额
            $avg = $i == $count ? $safeMoney : bcdiv($safeMoney, ($count - $i), 2);
            // 随机红包
            $rand = $avg > 0.01 ? mt_rand(1, $avg * 100) / 100 : 0.01;
            // 剩余红包
            $surplus = bcsub($surplus, $rand, 2);
            $redpack[] = $rand;
        }
        // 平分剩余红包
        $avg = bcdiv($surplus, $count, 2);
        for ($n = 0; $n < count($redpack); $n++) {
            $redpack[$n] = bcadd($redpack[$n], $avg, 2);
            $surplus = bcsub($surplus, $avg, 2);
        }
        // 如果还有红包没有分配完时继续分配
        if ($surplus > 0) {
            // 随机抽取分配好的红包，将剩余金额分配进去
            $keys = array_rand($redpack, $surplus * 100);
            // array_rand 第二个参数为 1 时返回的是下标而不是数组
            $keys = is_array($keys) ? $keys : [$keys];
            foreach ($keys as $key) {
                $redpack[$key] = bcadd($redpack[$key], 0.01, 2);
                $surplus = bcsub($surplus, 0.01, 2);
            }
        }
        // 红包分配结果
        return $redpack;
    }


    /**
     * 获取群聊资料
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function findGroupInfo(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $groupId = $params['groupId'];

        // 返回群聊信息
        $groupInfo = Db::name('LcGroup')->where(['id' => $groupId])->find();

        // 查询成员信息
        $count = Db::name('LcGroupMember')->where("uid = {$uid} and group_id = {$groupId}")->count();

        $groupInfo['is_apply'] = $count;

        $this->success("操作成功", $groupInfo);
    }




    public function applyGroup(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();
        $groupId = $params['groupId'];
        $description = $params['description'];

        // 查询群聊
        $groupInfo = Db::name('LcGroup')->where(['id' => $groupId])->find();

        // 创建群成员
        $member = array(
            'receiver_id' => $groupInfo['main_uid'],
            'send_id' => $uid,
            'add_time' => date('Y-m-d H:i:s'),
            'group_id' => $groupId,
            'type' => 3,
            'status' => 0,
            'description' => $description
        );
        Db::name('LcGroupNotice')->insertGetId($member);

        $this->success("操作成功");
    }




    /**
     *  红包记录
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function redLog(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();


        // 根据类型判断收发
        $type = $params['type'];

        if($type == 0){
            // 发
            $list = Db::name('LcRedpackRecord')->where("uid = {$uid}")->order('id desc')->select();
        }else{
            $list = Db::name('LcRedpackReceive')->where("receiver_id = {$uid}")->order('id desc')->select();
        }
        $this->success("操作成功", $list);
    }
    
    //领取记录
    public function receiveLog()
    {
        $red_id = $this->request->param('red_id', 0);
        $lists = Db::name('lc_redpack_receive r')->join('lc_user u', 'r.receiver_id = u.id')->field('amount,r.add_time,u.username')->where('red_id', $red_id)->order('r.id desc')->select();
        $this->success('获取成功', $lists);
    }


    /**
     * 获取通知数量
     * @return void
     */
    public function getNociteNum(){
        // 查询未处理的通知
        $this->checkToken();
        $uid = $this->userInfo['id'];

        // $groupCount = Db::name("lc_group_notice")->where("receiver_id = {$uid} and status = 0")->count();
        
        //获取当前用户管理的所有组ID
        $groupIds = Db::name('lc_group_member')->where('uid', $uid)->whereIn('role',[0,1])->column('group_id');
        $groupCount = Db::name('lc_group_invite')->whereIn('group_id', $groupIds)->where('status', 0)->count();

        $friendsCount = Db::name("lc_friends_notice")->where("tuid = {$uid} and status = 0")->count();

        $data = array(
            'group' => $groupCount,
            'friends' => $friendsCount
        );
            $this->success("操作成功", $data);
    }
    
    //入群申请
    public function groupInvite()
    {
        // 查询未处理的通知
        $this->checkToken();
        $uid = $this->userInfo['id'];
        // $uid = 40576;
        $groupIds = Db::name('lc_group_member')->where('uid', $uid)->whereIn('role',[0,1])->column('group_id');
        $lists = Db::name('lc_group_invite')->whereIn('group_id', $groupIds)->order('id desc')->select();
        foreach($lists as &$item) {
            $receiver = Db::name('lc_user')->find($item['receiver_id']);
            $send = Db::name('lc_user')->find($item['send_id']);
            $group = Db::name('lc_group')->find($item['group_id']);
            $item['group_name'] = $group['name'];
            $item['receiver_name'] = $receiver['username'];
            $item['send_name'] = $send['username'];
            // $item['message'] = $send['username'].'邀请'.$receiver['username'].'加入群聊：【'.$group['name'].'】';
        }
        $this->success('获取成功', $lists);
    }
    
    //群审核
    public function groupCheck()
    {
        $id = $this->request->param('id', 0);
        $status = $this->request->param('status', 1); //1=通过 2=拒绝
        if (!$log = Db::name('lc_group_invite')->find($id)) {
            $this->error('审核记录不存在');
        } elseif ($log['status'] != 0) {
            $this->error('记录已处理');
        }
        //审核通过
        if ($status == 1) {
            //创建群成员
            Db::name('lc_group_member')->insert([
                'uid' => $log['receiver_id'],
                'role' => 2,
                'add_time' => date('Y-m-d H:i:s', time()),
                'group_id' => $log['group_id']
            ]);
        }
        Db::name('lc_group_invite')->where('id', $id)->update(['status' => $status, 'handle_time' => date('Y-m-d H:i:s', time())]);
        $this->success('操作成功');
    }


    public function exitGroup(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();

        $groupId = $params['groupId'];

        // 直接delete数据
        Db::name('lc_group_member')
            ->where("group_id = {$groupId} and uid = {$uid}")
            ->delete();
        $this->success("操作成功");
    }


    public function dissolutionGroup(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();

        $groupId = $params['groupId'];

        // 修改群聊状态
        Db::name('lc_group')
            ->where("id = {$groupId} and main_uid = {$uid}")
            ->update(['status' => 2]);
        $this->success("操作成功");
    }



    public function kjTrade(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();


    }
    
    
    
    
    public function setMemberEstoppel(){
        $this->checkToken();
        $uid = $this->userInfo['id'];
        $params = $this->request->param();

        $groupId = $params['groupId'];
        $memberUid = $params['uid'];
        $time = $params['time'];

        // 检查当前用户是否有权限
        // 先查询群聊
        $groupInfo = Db::name('LcGroup')->where(['id' => $groupId])->find();
        if($groupInfo['main_uid'] != $uid){
            $this->error("Không được phép");
        }

        // 查询群成员信息
//        $memberInfo = Db::name("LcGroupMember")->where("uid = {$memberUid} and group_id = {$groupId}")->find();

        Db::name("LcGroupMember")->where("uid = {$memberUid} and group_id = {$groupId}")->update(array(
            'estoppel_time' => $time
        ));

        $this->success("操作成功");

    }
    public function imptest(){
        im_send_publish('38724','发送一条订单消息');
    }

}
