<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\response;

use think\Response;


class Json extends Response
{
    // 输出参数
    protected $options = [
        'json_encode_param' => JSON_UNESCAPED_UNICODE,
    ];

    protected $contentType = 'application/json';


    /**
     * 处理数据
     * @access protected
     * @param  mixed $data 要处理的数据
     * @return mixed
     * @throws \Exception
     */
    protected function output($data)
    {
        try {

            $data = json_encode($data, $this->options['json_encode_param']);
            $module = request()->module(true);
            $controller = request()->controller(true);
            if( $module == 'api' ){
                if( in_array($controller, ['index', 'user'])) {
                    $config = require '../config/database.php';

                    $cryptKey = 'mdhjdiglj5f8fd6d';
                    $iv = 'fmkj568shd39kqdk';
                    $encrypt = openssl_encrypt($data, 'AES-128-CBC', $cryptKey, 0, $iv);
                    return $encrypt;  
          
                    return base64_encode($this->rc4($data, $config['apiencode']));
                }
            }
            
            
            // 返回JSON数据格式到客户端 包含状态信息
             

            if (false === $data) {
                throw new \InvalidArgumentException(json_last_error_msg());
            }

            return $data;
        } catch (\Exception $e) {
            if ($e->getPrevious()) {
                throw $e->getPrevious();
            }
            throw $e;
        }
    }

}
