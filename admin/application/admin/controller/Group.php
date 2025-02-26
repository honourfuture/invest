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
use library\tools\Data;
use think\Db;

/**
 * 群聊管理
 * Class Item
 * @package app\admin\controller
 */
class Group extends Controller
{
    /**
     * 绑定数据表
     * @var string
     */
    protected $table = 'LcGroup';

    /**
     * 群聊管理
     * @auth true
     * @menu true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index()
    {
        $this->title = '群聊管理';
        $query = $this->_query($this->table)->alias('i')->equal("no")->like('name')->field('i.*,u.phone as main_phone');
        // $query->order('id desc')->page();
         $query->join('lc_user u','i.main_uid=u.id')->order('i.id desc')->page();
    }

    /**
     * 数据列表处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function _index_page_filter(&$data)
    {
//
        $aes = new Aes();
        foreach ($data as &$vo) {
            $vo['total_people'] = Db::name('lc_group_member')->where('group_id', $vo['id'])->count();
            $vo['main_phone'] = $aes->decrypt($vo['main_phone']);
            // list($vo['pay_type'], $vo['item_class']) = [[], []];
            // foreach ($this->mlist as $class) if ($class['id'] == $vo['class']) $vo['item_class'] = $class;
        }
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $aes = new Aes();
            $mobile = $aes->encrypt($data['mobile']);
            $groupLeader = Db::name('lc_user')->where('phone', $mobile)->find();
            if (!$groupLeader) {
                $this->error('群主不存在');
            }
            //创建群聊
            $groupId = Db::name('lc_group')->insertGetId([
                'name' => $data['name'],
                'main_uid' => $groupLeader['id'],
                'add_time' => date('Y-m-d H:i:s', time()),
                'status' => 1,
                'is_estoppel' => $data['is_estoppel'],
                'no' => $data['no'],
                'is_redpack' => $data['is_redpack'],
                'is_public' => $data['is_public'],
                'able_apply' => $data['able_apply'],
                'reason_zh_cn' => $data['reason_zh_cn'],
                'reason_zh_hk' => $data['reason_zh_hk'],
                'reason_en_us' => $data['reason_en_us'],
            ]);
            //创建群主
            Db::name('lc_group_member')->insert([
               'uid' => $groupLeader['id'],
               'role' => 0,
               'add_time' => date('Y-m-d H:i:s', time()),
               'group_id' => $groupId
            ]);
            $this->success('添加成功');
        }
        return $this->fetch();
    }

    /**
     * 编辑项目
     * @auth true
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit()
    {
        $this->title = '编辑群聊';
        $this->_form($this->table, 'form');
    }


    /**
     * 删除项目
     * @auth true
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function remove()
    {
        $this->_delete($this->table);
    }

}
