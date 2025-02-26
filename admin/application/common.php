<?php


function vnd_gsh($number) {
    return number_format($number, 0, ',', ',');
}



if (!function_exists('httpRequest')) {
    /**
     * 发起一个请求
     * @param $url
     * @param $params
     * @param string $method
     * @param array $header
     * @param false $multi
     * @return bool|string
     */
    function httpRequest($url, $params, $method = 'GET', $header = array(), $multi = false){
        $opts = array(
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => $header,
        );
        switch(strtoupper($method)){
            case 'GET':
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                break;
            case 'POST':
                $params = $multi ? $params : http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            default:
                return false;
        }
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $data  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error){
            return false;
        }
        return  $data;
    }
}