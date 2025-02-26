<?php
function rmbToUsdt($money,$num = 2){
    if($money == 0 || $money == "0.00"){
        return  "0.00";
        
    }
    $payment = Db::name('LcPayment')
        ->where(['show' => 1])
        ->order("sort asc,id desc")
        ->select();
     $list = array();
        if ($payment) {
            foreach ($payment as $k => $v) {
                $list[$k]["id"] = $v["id"];
                $list[$k]["type"] = $v["type"];
                $list[$k]["logo"] = $v["logo"];
                $list[$k]["give"] = $v["give"];
                $list[$k]["rate"] = $v["rate"];
                $list[$k]["mark"] = $v["mark"];
                $list[$k]["description"] = $v["description"];
                switch ($v["type"]) {
                    case 1:
                        $list[$k]["name"] = $v["crypto"];
                        $list[$k]["address"] = $v["crypto_qrcode"];
                        $list[$k]["qrcode"] = $v["crypto_link"];
                        break;
                    case 2:
                        $list[$k]["name"] = $v["alipay"];
                        $list[$k]["qrcode"] = $v["alipay_qrcode"];
                        break;
                    case 3:
                        $list[$k]["name"] = $v["wx"];
                        $list[$k]["qrcode"] = $v["wx_qrcode"];
                        break;
                    case 4:
                        $list[$k]["name"] = $v["bank"];
                        $list[$k]["user"] = $v["bank_name"];
                        $list[$k]["account"] = $v["bank_account"];
                        break;
                    default:
                }
            }
        }  
   
   
    if ($list[0]['rate'] > 10000) {
		$num = 8;
	} else if ($list[0]['rate'] > 1000) {
		$num = 6;
	} else if ($list[0]['rate'] > 10) {
		$num = 4;
	}
	echo round_bcdiv($money,$list[0]['rate'],$num);
}

function round_bcdiv($left_value, $right_value, $decimal_places = 0)
{
    $result = round(bcdiv($left_value, $right_value, bcadd($decimal_places, 2)), $decimal_places);
    return $decimal_places === 0 ? (int)$result : $result;
}

