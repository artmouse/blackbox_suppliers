<?php
require_once(Mage::getModuleDir('controllers','Mage_Checkout').DS.'CartController.php');

class Blackbox_Supplier_CartController extends Mage_Checkout_CartController
{
    public function addAction()
    {
        if (!$this->_validateFormKey()) {
            $this->_goBack();
            return;
        }
        $cart = $this->_getCart();
        $params = $this->getRequest()->getParams();
        $qtyArr = json_decode($params['qty']);
        $sizeAttributeId = Mage::helper('blackbox_supplier/attributes')->getAttributeIdByCode('size');

        try {
            foreach ($qtyArr as $size => $qty) {
                $params['qty'] = $qty;
                $params['super_attribute'][$sizeAttributeId] = $size;

                if (isset($params['qty'])) {
                    $filter = new Zend_Filter_LocalizedToNormalized(
                        array('locale' => Mage::app()->getLocale()->getLocaleCode())
                    );
                    $params['qty'] = $filter->filter($params['qty']);
                }

                $product = $this->_initProduct();
                $related = $this->getRequest()->getParam('related_product');

                /**
                 * Check product availability
                 */
                if (!$product) {
                    $this->_goBack();
                    return;
                }

                $cart->addProduct($product, $params);
                if (!empty($related)) {
                    $cart->addProductsByIds(explode(',', $related));
                }

            }

            $cart->save();

            $this->_getSession()->setCartWasUpdated(true);

            /**
             * @todo remove wishlist observer processAddToCart
             */
            Mage::dispatchEvent('checkout_cart_add_product_complete',
                array('product' => $product, 'request' => $this->getRequest(), 'response' => $this->getResponse())
            );

            if (!$this->_getSession()->getNoCartRedirect(true)) {
                if (!$cart->getQuote()->getHasError()) {
                    $message = $this->__('%s was added to your shopping cart.', Mage::helper('core')->escapeHtml($product->getName()));
                    $this->_getSession()->addSuccess($message);
                }
                $this->_goBack();
            }
        } catch (Mage_Core_Exception $e) {
            if ($this->_getSession()->getUseNotice(true)) {
                $this->_getSession()->addNotice(Mage::helper('core')->escapeHtml($e->getMessage()));
            } else {
                $messages = array_unique(explode("\n", $e->getMessage()));
                foreach ($messages as $message) {
                    $this->_getSession()->addError(Mage::helper('core')->escapeHtml($message));
                }
            }

            $url = $this->_getSession()->getRedirectUrl(true);
            if ($url) {
                $this->getResponse()->setRedirect($url);
            } else {
                $this->_redirectReferer('onepage');
            }
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Cannot add the item to shopping cart.'));
            Mage::logException($e);
            $this->_goBack();
        }
    }

    /**
     * Delete shoping cart item action
     */
    public function deleteMassAction()
    {
        $result = array();
        if ($this->_validateFormKey()) {
            $ids = explode(',', $this->getRequest()->getParam('ids'));
            $removed = false;
            foreach($ids as $id) {
                $id = (int)$id;
                if ($id) {
                    try {
                        $this->_getCart()->removeItem($id);
                        $removed = true;
                        $result['success'][] = $id;
                    } catch (Exception $e) {
                        $result['errors'][$id] = 'Cannot remove the item.';
                        Mage::logException($e);
                    }
                }
            }
            if ($removed) {
                $this->_getCart()->save();
            }
        } else {
            $result = array('error' => 'Cannot remove the item.');
        }

        $this->getResponse()->setHeader('Content-type', 'application/json')
            ->setBody(Mage::helper('core')->jsonEncode($result));
    }

    public function updateQtyAction()
    {
        $id = (int)$this->getRequest()->getParam('id');
        $qty = $this->getRequest()->getParam('qty');

        $result = array();

        if ($id) {
            $quote = Mage::getSingleton('checkout/session')->getQuote();

            if ($item = $quote->getItemById($id)) {
                try {
                    if ($qty != $item->getQty()) {
                        $this->_getCart()->updateItems(array($item->getId() => array('qty' => $qty)));
                        $this->_getCart()->save();
                    }
                    $result['success'] = 1;
                    $result['total'] = Mage::helper('core')->currency($item->getRowTotal(), true, false);
                } catch (Exception $e) {
                    $result['error'] = 'Cant update item qty.';
                    $result['qty'] = $item->getQty();
                }
            } else {
                $result['error'] = 'Quote hasn\'t an item with such id.';
            }
        } else {
            $result['error'] = 'Missing item id.';
        }

        $this->getResponse()->setHeader('Content-type', 'application/json')
            ->setBody(Mage::helper('core')->jsonEncode($result));
    }

    /**
     * Set back redirect url to response
     *
     * @return Mage_Checkout_CartController
     * @throws Mage_Exception
     */
    protected function _goBack()
    {
        $returnUrl = $this->getRequest()->getParam('return_url');
        if ($returnUrl) {

            if (!$this->_isUrlInternal($returnUrl)) {
                throw new Mage_Exception('External urls redirect to "' . $returnUrl . '" denied!');
            }

            $this->_getSession()->getMessages(true);
            $this->getResponse()->setRedirect($returnUrl);
        } elseif (!Mage::getStoreConfig('checkout/cart/redirect_to_cart')
            && !$this->getRequest()->getParam('in_cart')
            && $backUrl = $this->_getRefererUrl()
        ) {
            $this->getResponse()->setRedirect($backUrl);
        } else {
            if (
                (strtolower($this->getRequest()->getActionName()) == 'add')
                && !$this->getRequest()->getParam('in_cart')
            ) {
                $this->_getSession()->setContinueShoppingUrl($this->_getRefererUrl());
            }
            $this->_redirect('onepage');
        }
        return $this;
    }
}