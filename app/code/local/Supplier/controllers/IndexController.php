<?php

class Blackbox_Supplier_IndexController extends Mage_Core_Controller_Front_Action
{
    public function getProductImageAction()
    {
        $id = $this->getRequest()->getParam('id','');

        if ($id == '') {
            $result['error'] = 'Id param is missing';
        }
        else {
            $result['result'] = Mage::getModel('catalog/product')->load($id)->getImageUrl();
        }

        $this->getResponse()->setHeader('Content-type', 'application/json')
            ->setBody(Mage::helper('core')->jsonEncode($result));
    }
}