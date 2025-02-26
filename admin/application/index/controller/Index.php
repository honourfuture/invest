<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2022~2023  天美工作室 [   ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\index\controller;

use library\Controller;
use think\Db;

/**
 * 应用入口
 * Class Index
 * @package app\index\controller
 */
class index extends Controller {
    public function index()
    {
        $this->redirect(url('@admin/login'));
        // $this->redirect(url('www.baidu.com'));
    }
}