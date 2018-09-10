<?php

class Blackbox_Supplier_Helper_Color extends Mage_Core_Helper_Abstract
{
    public function getColorCircleBackground($colorCodes)
    {
        $codeArr = explode(',', $colorCodes);
        foreach ($codeArr as &$colorCode) {
            if ($colorCode[0] != '#') {
                $colorCode = '#' . $colorCode;
            }
        }
        if (count($codeArr) == 1) {
            return $codeArr[0];
        } else {
            return 'linear-gradient(' . $codeArr[0] . ',' . $codeArr[1] . ')';
        }
    }
}