<?php

class Blackbox_Supplier_Model_Product_Configurable
{
    protected $data;
    protected $category;

    protected $parentCategoryId = 2;

    protected $sizeAttribute;
    protected $colorAttribute;
    protected $attributeSet;

    protected $attributeSetName = 'SupplierConfigurable';

    protected $attributesCache = array();
    protected $productsCache = array();

    protected $configurableUsedProductsIds;
    protected $configurableAttributesData;
    protected $colorId;
    protected $sizeId;

    protected $products;

    protected $helper;

    protected $images = array();
    protected $clearImages = false;

    public function __construct()
    {
        $this->colorAttribute = Mage::getModel("eav/entity_attribute")->loadByCode("catalog_product", 'color')->getId();
        $this->sizeAttribute = Mage::getModel("eav/entity_attribute")->loadByCode("catalog_product", 'size')->getId();
        $this->attributeSet = Mage::getModel("eav/entity_attribute_set")->load($this->attributeSetName, 'attribute_set_name')->getId();

        $this->helper = Mage::helper('blackbox_supplier');
    }

    /**
     * Set data for main product.
     *
     * @param $data set color, size and gtin if it's global for all set. Ignore if not.
     * @param $category
     */
    public function setGeneralData($data, $category)
    {
        $this->data = $data;
        $this->category = $category;
    }

    /**
     * Add simple product with data such as gtin, size, name and any one else
     * @param $data one product
     */
    public function addProduct($data)
    {
        $this->products[] = $data;
    }


    /**
     * Add array simple products to list
     * @param $products array of product data
     */
    public function addProducts($products)
    {
        foreach ($products as $product) {
            $this->products[] = $product;
        }
    }

    /**
     * Save configurable products to db.
     * @param array $rewriteAttributes array $key - magento attribute, $value - you array attribute or switch
     */
    public function save($rewriteAttributes = array(), $allowEmpty = false)
    {
        $configurable = $this->createConfigurableProduct();
        $haveSimpleProducts = false;
        foreach ($this->products as $data) {
            try {
                if ($this->createSimpleProduct($data, $rewriteAttributes)) {
                    $haveSimpleProducts = true;
                }
            } catch (Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }
        }
        $isEmpty = count($this->configurableUsedProductsIds) == 0;
        if ($haveSimpleProducts && !$isEmpty || $isEmpty && $allowEmpty) {
            if ($configurable->getId()) {
                $resource = Mage::getSingleton('core/resource');
                $write = $resource->getConnection('core_write');
                $table = $resource->getTableName('catalog/product_super_attribute');
                $write->delete($table, "product_id = " . $configurable->getId());
            }

            $configurable->setCanSaveConfigurableAttributes(true);
            $configurable->setConfigurableAttributesData($this->configurableAttributesData);
            $configurable->save();

            if ($haveSimpleProducts) {
                Mage::getResourceSingleton('catalog/product_type_configurable')
                    ->saveProducts($configurable, $this->configurableUsedProductsIds);
            }
        } else if ($isEmpty) {
            $configurable->delete();
        }

        /*$configurable->getOptionInstance()->unsetOptions()->clearInstance();
        $configurable->clearInstance();
        unset($configurable, $options);*/

        if ($this->clearImages) {
            $this->_clearImages();
        }
    }

    /**
     * Global category ID. Deprecated
     * @deprecated
     * @param $id
     */
    public function setParentCategoryId($id)
    {
        $this->parentCategoryId = $id;
    }

    public function setClearImages($clearImages) {
        $this->clearImages = $clearImages;
    }

    protected function getAttributeValue($code, $label)
    {
        if (!key_exists($code, $this->attributesCache) == true) {
            $attribute = Mage::getModel('eav/config')->getAttribute('catalog_product', $code);
            if ($attribute->getFrontendInput() == 'select') {
                $this->attributesCache[$code] = $attribute->getSource()->getAllOptions(true, true);
            } else {
                $this->attributesCache[$code] = null;
            }
        }
        foreach ($this->attributesCache[$code] as $value) {
            if ($value['label'] == $label) {
                return $value['value'];
            }
        }
        return null;
    }

    protected function _clearImages()
    {
        foreach($this->images as $file => $dummy)
        {
            unlink($file);
        }
    }

    protected function _isAttributeSelect($code)
    {
        if (key_exists($code, $this->attributesCache) == true) {
            return $this->attributesCache[$code] != null;
        }
        $attribute = Mage::getModel('eav/config')->getAttribute('catalog_product', $code);
        if ($attribute->getFrontendInput() == 'select') {
            $this->attributesCache[$code] = $attribute->getSource()->getAllOptions(true, true);
            return true;
        } else {
            $this->attributesCache[$code] = null;
            return false;
        }
    }

    protected function isOptionExists($code, $option)
    {
        foreach ($this->attributesCache[$code] as $value) {
            if ($value['label'] == $option) {
                return true;
            }
        }
        return false;
    }

    protected function addAttributeOptions($attribute_code, $option)
    {
        $attribute_model = Mage::getModel('eav/entity_attribute');

        $id = $attribute_model->getIdByCode('catalog_product', $attribute_code);
        $attribute = $attribute_model->load($id);

        $value['option'] = array($option, $option);
        $result = array('value' => $value);
        $attribute->setData('option', $result);
        $attribute->save();

        $attribute = Mage::getModel('eav/config')->getAttribute('catalog_product', $attribute_code);
        $this->attributesCache[$attribute_code] = $attribute->getSource()->getAllOptions(true, true);
    }

    /**
     * Private method what's create global product
     * @return bool|false|Mage_Core_Model_Abstract
     */
    protected function createConfigurableProduct()
    {
        if (($product = Mage::helper('blackbox_supplier/product')->getProductBySku($this->data['sku'])) !== false) {

            $childProducts = Mage::getModel('catalog/product_type_configurable')
                ->getUsedProducts(null, $product);

            foreach ($childProducts as $child) {
                $child->load();
                $this->productsCache[$child->getId()] = array('color' => $child->getColor(), 'size' => $child->getSize());
            }

            $this->configurableUsedProductsIds = $product->getTypeInstance()->getUsedProductIds();

            $this->configurableAttributesData = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
            foreach ($this->configurableAttributesData as $key => &$value) {
                if ($value['attribute_code'] == 'color') {
                    $this->colorId = $key;
                } else if ($value['attribute_code'] == 'size') {
                    $this->sizeId = $key;
                }

                $value['id'] = null;
                foreach ($value['values'] as $valueOfValue) {
                    $valueOfValue['product_super_attribute_id'] = null;
                }
            }
        } else {
            $product = Mage::getModel('catalog/product');

            $product->setStoreId(Mage::app()->getStore()->getId())
                ->setWebsiteIds(array(1))
                ->setAttributeSetId($this->attributeSet)
                ->setTypeId('configurable')
                ->setCreatedAt(strtotime('now'))
                ->setStatus(1)
                ->setTaxClassId(4)
                ->setStockData(array(
                    'qty' => 999,
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 0,
                    'is_in_stock' => 1,
                ));

            $images = $this->data['Images'];

            if (isset($images)) {
                unset($this->data['Images']);

                $product->setMediaGallery(array('images' => array(), 'values' => array()));
                foreach ($images as $image => $types) {
                    if (count($types) == 0) {
                        try {
                            $product->addImageToMediaGallery($image, null, false, false);
                        } catch (Exception $e) {echo $e->getMessage() . PHP_EOL;}
                    } else {
                        try {
                            $product->addImageToMediaGallery($image, $types, false, false);
                        } catch (Exception $e) {echo $e->getMessage() . PHP_EOL;}
                    }
                }
            }

            foreach ($this->data as $code => &$value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                if ($this->_isAttributeSelect($code)) {
                    if (!$this->isOptionExists($code, $value)) {
                        $this->addAttributeOptions($code, $value);
                    }
                    $value = $this->getAttributeValue($code, $value);
                }
            }

            $product->addData($this->data);

            if ($product->getPrice() === null)
            {
                $product->setPrice(0);
            }
        }

        if (is_array($this->category)) {
            $categories = array();
            foreach ($this->category as $category) {
                if (!$category) {
                    continue;
                }
                $categories = array_merge($categories, $this->helper->createCategory($category, null, $this->parentCategoryId, true));
            }
            $product->setCategoryIds($categories);
        } else {
            if ($this->category) {
                $product->setCategoryIds($this->helper->createCategory($this->category, null, $this->parentCategoryId, true));
            }
        }

        if (!is_array($this->configurableAttributesData) || count($this->configurableAttributesData) < 2) {
            $this->configurableAttributesData = array(
                '0' => array(
                    'id' => NULL,
                    'label' => 'Color',
                    'position' => NULL,
                    'attribute_id' => $this->colorAttribute,
                    'attribute_code' => 'color',
                    'frontend_label' => 'Color'),
                '1' => array(
                    'id' => NULL,
                    'label' => 'Size',
                    'position' => NULL,
                    'attribute_id' => $this->sizeAttribute,
                    'attribute_code' => 'size',
                    'frontend_label' => 'Size')
            );
            $this->colorId = 0;
            $this->sizeId = 1;
        }

        return $product;
    }

    protected function createSimpleProduct(&$data, $rewriteAttributes = array())
    {
        if (!($images = $this->validateImages($data['Images']))) {
            return false;
        }
        if (($product = $this->getSameSimpleProduct($data['color'], $data['size'])) !== false || ($product = $this->getProductByGtin($data['gtin'])) !== false) {

            $data = array_intersect_key($data, array_flip($rewriteAttributes));

            //$images = $data['Images'];

            if (isset($images)) {
                unset($data['Images']);

                $product->setMediaGallery(array('images' => array(), 'values' => array()));
                foreach ($images as $image => $types) {
                    if (count($types) == 0) {
                        try {
                            $product->addImageToMediaGallery($image, null, false, false);
                        } catch (Exception $e) {echo $e->getMessage() . PHP_EOL;}
                    } else {
                        if (isset($types['label'])) {
                            $label = $types['label'];
                            unset($types['label']);
                        } else {
                            $label = '';
                        }
                        try {
                            $product->addImageToMediaGallery($image, $types, false, false, $label);
                        } catch (Exception $e) {echo $e->getMessage() . PHP_EOL;}
                    }
                }
            }

            if (count($data) > 0) {
                $product->addData($data);

                $product->save();
            }
        } else {
            $product = Mage::getModel('catalog/product');

            $product->setStoreId(Mage::app()->getStore()->getId())
                ->setWebsiteIds(array(1))
                ->setAttributeSetId($this->attributeSet)
                ->setTypeId('simple')
                ->setCreatedAt(strtotime('now'))
                ->setStatus(1)
                ->setTaxClassId(4)
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
                ->setStockData(array(
                    'qty' => 999,
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 0,
                    'is_in_stock' => 1,
                ));

            //$images = $data['Images'];

            if (isset($images)) {
                unset($data['Images']);

                $product->setMediaGallery(array('images' => array(), 'values' => array()));
                foreach ($images as $image => $types) {
                    if (count($types) == 0) {
                        try {
                            $product->addImageToMediaGallery($image, null, false, false);
                        } catch (Exception $e) {
                            echo $e->getMessage() . PHP_EOL;
                        }
                    } else {
                        if (isset($types['label'])) {
                            $label = $types['label'];
                            unset($types['label']);
                        } else {
                            $label = '';
                        }
                        if (count($types) == 0) {
                            $types = null;
                        }
                        try {
                            $product->addImageToMediaGallery($image, $types, false, false, $label);
                        } catch (Exception $e) {
                            echo $e->getMessage() . PHP_EOL;
                        }
                    }
                }
            }

            foreach ($data as $code => &$value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                if ($this->_isAttributeSelect($code)) {
                    if (!$this->isOptionExists($code, $value)) {
                        $this->addAttributeOptions($code, $value);
                    }
                    $value = $this->getAttributeValue($code, $value);
                }
            }

            $product->addData($data);

            $product->save();
        }

        if ($this->clearImages && isset($images)) {
            foreach ($images as $image => $types) {
                $this->images[$image] = true;
            }
        }

        $colorAttributeData = array(
            'label' => $product->getAttributeText('color'),
            'attribute_id' => $this->colorAttribute,
            'value_index' => (int)$product->getColor(),
            'is_percent' => 0,
            'pricing_value' => $product->getPrice()
        );
        $this->configurableAttributesData[$this->colorId]['values'][] = $colorAttributeData;

        $sizeAttributeData = array(
            'label' => $product->getAttributeText('size'),
            'attribute_id' => $this->sizeAttribute,
            'value_index' => (int)$product->getSize(),
            'is_percent' => 0,
            'pricing_value' => '0chan'
        );
        $this->configurableAttributesData[$this->sizeId]['values'][] = $sizeAttributeData;

        $this->configurableUsedProductsIds[] = $product->getId();

        $this->productsCache[$product->getId()] = array('color' => $product->getColor(), 'size' => $product->getSize());

        /*$product->getOptionInstance()->unsetOptions()->clearInstance();
        $product->clearInstance();
        unset($product, $options);*/

        return true;
    }

    protected function validateImages($images)
    {
        if (!($front = $this->findImageWithLabel($images, 'Front'))) {
            if (!$this->findImageWithLabel($images, 'Back')) {
                return false;
            }
            $side = $this->findImageWithLabel($images, 'Side');
            if (!$side) {
                return false;
            }
            $front = $this->copyImage($side);
            if (!$front) {
                return false;
            }
            $images[$front] = array('image', 'small_image', 'thumbnail', 'label' => 'Front');
        } else if (!$this->areImagesWithLabelsExist($images, array('Side', 'Back'))) {
            return false;
        }
        return $images;
    }

    protected function findImageWithLabel(&$images, $label)
    {
        foreach ($images as $file => $params) {
            if ($params['label'] == $label) {
                return $file;
            }
        }
        return false;
    }

    protected function areImagesWithLabelsExist(&$images, $labels)
    {
        foreach ($images as $file => $params) {
            foreach ($labels as $key => $label) {
                if ($params['label'] == $label) {
                    unset($labels[$key]);
                    if (count($labels) == 0) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    protected function copyImage($path) {
        $path_parts = pathinfo($path);
        $newPath = $path_parts['dirname'] . DS . $path_parts['filename'] . '_copy' . '.' . $path_parts['extension'];
        if (copy($path, $newPath)) {
            return $newPath;
        } else {
            return false;
        }
    }

    /**
     * Get product from cache by $color and $size
     * @param $color
     * @param $size
     * @return bool|Mage_Core_Model_Abstract
     */
    protected function getSameSimpleProduct($color, $size)
    {
        $color = $this->getAttributeValue($this->colorAttribute, $color);
        $size = $this->getAttributeValue($this->sizeAttribute, $size);
        foreach ($this->productsCache as $value => $product) {
            if ($color == $product['color'] && $size == $product['size']) {
                return Mage::getModel('catalog/product')->load($value);
            }
        }
        return false;
    }

    /**
     * Get product from database by GTIN
     * @param $gtin
     * @return bool
     */
    protected function getProductByGtin($gtin)
    {
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('gtin')
            ->addAttributeToSelect('price')
            ->addAttributeToSelect('color')
            ->addAttributeToSelect('size')
            ->addAttributeToFilter('gtin', array('eq' => $gtin))
            ->setPageSize(1);

        if (count($collection) == 0) {
            return false;
        }

        return $collection->getFirstItem();
    }
}