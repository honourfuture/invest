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

class util
{
   /**
    * 身份证号码验证（真正要调用的方法）
    * @param $id_card   身份证号码
    */
   public static function validation_filter_id_card($id_card)
   {
      if (strlen($id_card) == 18) {
         $idcard_base = substr($id_card, 0, 17);
         if (self::idcard_verify_number($idcard_base) != strtoupper(substr($id_card, 17, 1))) {
            return false;
         } else {
            return true;
         }
      } elseif ((strlen($id_card) == 15)) {
         // 如果身份证顺序码是996 997 998 999，这些是为百岁以上老人的特殊编码
         if (array_search(substr($id_card, 12, 3), array('996', '997', '998', '999')) !== false) {
            $idcard = substr($id_card, 0, 6) . '18' . substr($id_card, 6, 9);
         } else {
            $idcard = substr($id_card, 0, 6) . '19' . substr($id_card, 6, 9);
         }
         $idcard = $idcard . self::idcard_verify_number($idcard);
         if (strlen($idcard) != 18) {
            return false;
         }
         $idcard_base = substr($idcard, 0, 17);
         if (self::idcard_verify_number($idcard_base) != strtoupper(substr($idcard, 17, 1))) {
            return false;
         } else {
            return true;
         }
      } else {
         return false;
      }
   }


   /**
    * 计算身份证校验码，根据国家标准GB 11643-1999
    * @param $idcard_base   身份证号码
    */
   private static function idcard_verify_number($idcard_base)
   {
      if (strlen($idcard_base) != 17) {
         return false;
      }
      //加权因子
      $factor = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
      //校验码对应值
      $verify_number_list = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');
      $checksum = 0;
      for ($i = 0; $i < strlen($idcard_base); $i++) {
         $checksum += substr($idcard_base, $i, 1) * $factor[$i];
      }
      $mod = $checksum % 11;
      $verify_number = $verify_number_list[$mod];
      return $verify_number;
   }


}