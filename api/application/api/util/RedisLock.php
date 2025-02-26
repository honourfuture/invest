<?php
namespace app\api\util;
use think\facade\Cache;
class RedisLock {

    public $_redis;
    
    /**
    *这里我直接使用的$this->connect();连接的redis
    *用tp的Cache::handler();一直报错，不知道什么情况。。
    *如果有人知道请在下方留言 感激不敬
    */ 
    public function __construct() {
 
        // $handler = Cache::handler();
        // return $this->_redis = $handler->handler();
        $handler = Cache::store('redis')->handler();
        // $this->_redis = $this->connect();
        $this->_redis = $handler;
    }
 
    /**
     * 获取锁
     * @param  String  $key    锁标识
     * @param  Int     $expire 锁过期时间
     * @param  Int     $num    重试次数
     * @return Boolean
     */
    public function lock($key, $expire = 10, $num = 0){
        $is_lock = $this->_redis->setnx($key, time()+$expire);
 
        if(!$is_lock) {
            //获取锁失败则重试{$num}次
            for($i = 0; $i < $num; $i++){
 
                $is_lock = $this->_redis->setnx($key, time()+$expire);
 
                if($is_lock){
                    break;
                }
                sleep(1);
            }
        }
 
        // 不能获取锁
        if(!$is_lock){
 
            // 判断锁是否过期
            $lock_time = $this->_redis->get($key);
 
            // 锁已过期，删除锁，重新获取
            if(time()>$lock_time){
                $this->unlock($key);
                $is_lock = $this->_redis->setnx($key, time()+$expire);
            }
        }
 
        return $is_lock? true : false;
    }
 
    /**
     * 释放锁
     * @param  String  $key 锁标识
     * @return Boolean
     */
    public function unlock($key){
        return $this->_redis->del($key);
    }


    /**
     * 创建redis连接
     * @return Link
     */
    private function connect(){
        try{
            $redis = new \Redis();
            $redis->connect('127.0.0.1',6379);

            // $redis->auth('xiaolong123456');

        }catch(Exception $e){
            throw new Exception($e->getMessage());
            return false;
        }
        return $redis;
    }
 
}

