<?php
return [
    // 缓存配置为复合类型
    'type'  =>  'complex', 
    'default'    =>    [
      'type'    =>    'redis',
      // 全局缓存有效期（0为永久有效）
      'expire'=>  0, 
      // 缓存前缀
      'prefix'=>  'think',
       // 缓存目录
      'path'  =>  '../runtime/cache/',
    ],
    'redis'    =>    [
      'type'    =>    'redis',
      'host'    =>    '127.0.0.1',
      'password'    =>    '',
      'port'=>'6379',
      // 全局缓存有效期（0为永久有效）
      'expire'=>  0, 
      // 缓存前缀
      'prefix'=>  'gthink',
    ],    
    // 添加更多的缓存类型设置
];
?>