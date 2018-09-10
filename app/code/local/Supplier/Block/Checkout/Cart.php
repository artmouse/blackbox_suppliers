<?php

class Blackbox_Supplier_Block_Checkout_Cart extends Mage_Checkout_Block_Cart
{
    public function getItemsGrouped()
    {
        $items = parent::getItems();
        $colorAttributeCode = Mage::helper('blackbox_supplier/attributes')->getAttributeIdByCode('color');
        $sizeAttributeCode = Mage::helper('blackbox_supplier/attributes')->getAttributeIdByCode('size');
        $sizeOptions = Mage::helper('blackbox_categories')->getAttributeOptions('size');

        $grouped = array();

        foreach ($items as $item)
        {
            $productId = $item->getProductId();
            $attributes = unserialize($item->getOptionsByCode()['attributes']->getValue());
            $color = $attributes[$colorAttributeCode];
            if (isset($grouped[$productId][$color])) {
                $grouped[$productId][$color]['items'][] = $item;
            } else {
                $grouped[$productId][$color]['items'] = array ($item);
            }
            $grouped[$productId][$color]['sizes'][] = "{$sizeOptions[$attributes[$sizeAttributeCode]]} - {$item->getQty()}";
            if (isset($grouped[$productId][$color]['price'])) {
                $grouped[$productId][$color]['price'] += $item->getBaseRowTotalInclTax();
            } else {
                $grouped[$productId][$color]['price'] = $item->getBaseRowTotalInclTax();
            }
        }

        return $grouped;
    }

    public function getColorCircleBackground($colorCodes)
    {
        return Mage::helper('blackbox_supplier/color')->getColorCircleBackground($colorCodes);
    }

    public function getItemsCount($items)
    {
        $result = 0;
        foreach($items as $product) {
            foreach ($product as $item) {
                $result++;
            }
        }

        return $result;
    }
}