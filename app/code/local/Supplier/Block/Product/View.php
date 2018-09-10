<?php

class Blackbox_Supplier_Block_Product_View extends Mage_Catalog_Block_Product_View
{
    protected $_colors = null;

    public function &getColors()
    {
        if ($this->_colors) {
            return $this->_colors;
        }

        $this->_colors =& Mage::helper('blackbox_supplier/product')->getColors($this->getProduct());

        return $this->_colors;
    }

    public function getSizes()
    {
        return Mage::helper('blackbox_supplier/product')->getSizes($this->getProduct());
    }
}