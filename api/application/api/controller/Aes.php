<?php

namespace app\api\controller;

class Aes
{
    private $key;
    private $iv;
    
    public function __construct()
    {
        $this->key = 'lkjhnmhg9d6sw587';
        $this->iv  = '6fo24d6q3zg9k24q';
    }
    
    public function encrypt($input)
    {
        $data = openssl_encrypt($input, 'aes-128-cbc', $this->key, 1, $this->iv);
        return base64_encode($data);
    }
    
    public function decrypt($input)
    {
        return openssl_decrypt($input, 'aes-128-cbc', $this->key, 0, $this->iv);
    }
}