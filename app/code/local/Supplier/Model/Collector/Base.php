<?php

/**
 * Configuration class for suppliers
 *
 */
class Blackbox_Supplier_Model_Collector_Base extends Blackbox_Supplier_Model_Config_Ordered
{
    /**
     * Cache key for collectors
     *
     * @var string
     */
    protected $_collectorsCacheKey = 'sorted_collectors';

    /**
     * Supplier models list
     *
     * @var array
     */
    protected $_supplierModels = array();

    /**
     * Configuration path where to collect registered suppliers
     *
     * @var string
     */
    protected $_suppliersConfigNode = 'global/suppliers';

    /**
     * Init model class by configuration
     *
     * @param string $class
     * @param string $supplierCode
     * @param array $supplierConfig
     * @return Mage_Sales_Model_Order_Supplier_Abstract
     */
    protected function _initModelInstance($class, $supplierCode, $supplierConfig)
    {
        $model = Mage::getModel($class);
        if (!$model instanceof Blackbox_Supplier_Model_Api_Abstract) {
            Mage::throwException(Mage::helper('sales')->__('Supplier model should be extended from Blackbox_Supplier_Model_Api_Abstract.'));
        }

        $model->setCode($supplierCode);
        $model->setSupplierConfigNode($supplierConfig);
        $this->_modelsConfig[$supplierCode] = $this->_prepareConfigArray($supplierCode, $supplierConfig);
        $this->_modelsConfig[$supplierCode] = $model->processConfigArray($this->_modelsConfig[$supplierCode]);
        return $model;
    }

    /**
     * Retrieve supplier calculation models
     *
     * @return array
     */
    public function getSupplierModels()
    {
        if (empty($this->_supplierModels)) {
            $this->_initModels();
            $this->_initCollectors();
            $this->_supplierModels = $this->_collectors;
        }
        return $this->_supplierModels;
    }
}
