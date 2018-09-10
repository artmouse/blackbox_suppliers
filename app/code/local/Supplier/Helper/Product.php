<?php

class Blackbox_Supplier_Helper_Product extends Mage_Core_Helper_Abstract
{
    public function selectSupplier($product)
    {
        $bestSupplier = null;
        $minPrice = null;

        foreach ($this->getSuppliers() as $supplier => $attribute)
        {
            $price = $product->getData($attribute);
            if ($price) {
                if ($minPrice == null || $minPrice > $price) {
                    $bestSupplier = $supplier;
                    $minPrice = $price;
                }
            }
        }

        return $bestSupplier;
    }

    /**
     * get product by SKU from database
     * @param $sku
     * @return bool|false|Mage_Core_Model_Abstract
     */
    public function getProductBySku($sku)
    {
        $product = Mage::getModel('catalog/product');
        $id = Mage::getModel('catalog/product')->getResource()->getIdBySku($sku);
        if ($id) {
            $product->load($id);
            return $product;
        }

        return false;
    }

    public function getSuppliers()
    {
        return array(
            'Alphabroder' => 'ab_price',
            'Activewear' => 'aw_price',
            'San Mar' => 'sm_price',
            );
    }

    public function &getColors($product, $needImages = true, $smallImage = false)
    {
        $childProducts = Mage::getModel('catalog/product_type_configurable')
            ->getUsedProducts(null, $product);
        $colors = array();
        foreach($childProducts as $child) {
            $colorCodes = strtoupper($child->getResource()->getAttributeRawValue($child->getId(), 'color_code', Mage::app()->getStore()));
            $colorCodesArr = explode(',', $colorCodes);
            foreach ($colorCodesArr as &$colorCode) {
                if ($colorCode[0] != '#') {
                    $colorCode = '#' . $colorCode;
                }
            }
            $colorCodes = implode(',',$colorCodesArr);
            if ($this->array_usearch($colors, function($item) use ($colorCodes) { return $item['code_str'] == $colorCodes; }) === false) {


                $child->load();
                $colorOption = $child->getColor();

                $color = array(
                    'product_id' => $child->getId(),
                    'code_str' => $colorCodes,
                    'code_arr' => $colorCodesArr,
                    'price' => $child->getFinalPrice(),
                );

                if ($smallImage)
                {
                    $color['small_image'] = (string)Mage::helper('catalog/image')->init($child, 'image')->keepFrame(false)->resize($smallImage);
                }

                if ($needImages) {
                    $images = $child->getMediaGalleryImages();

                    foreach ($images as $image) {
                        switch ($image->getLabel()) {
                            case 'Front':
                                $imageFront = array(
                                    'url' => $image->getUrl(),
                                    'thumbnail' => Mage::helper('catalog/image')->init($child, 'thumbnail', $image->getFile())->resize(75)->__toString(),
                                    'id' => $image->getId(),
                                    'label' => $image->getLabel()
                                );
                                break;
                            case 'Back':
                                $imageBack = array(
                                    'url' => $image->getUrl(),
                                    'thumbnail' => Mage::helper('catalog/image')->init($child, 'thumbnail', $image->getFile())->resize(75)->__toString(),
                                    'id' => $image->getId(),
                                    'label' => $image->getLabel()
                                );
                                break;
                            case 'Side':
                                $imageSide = array(
                                    'url' => $image->getUrl(),
                                    'thumbnail' => Mage::helper('catalog/image')->init($child, 'thumbnail', $image->getFile())->resize(75)->__toString(),
                                    'id' => $image->getId(),
                                    'label' => $image->getLabel()
                                );
                                break;
                        }
                    }

                    $color['images'] = array(
                        'front' => $imageFront,
                        'back' => $imageBack,
                        'side' => $imageSide,
                    );
                }

                $colors[$colorOption] = $color;
            }
        }

        return $colors;
    }

    public function &getSizes($product)
    {
        $sizes = array();
        $productAttributeOptions = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
        $sizeAttributeIndex = $this->array_usearch($productAttributeOptions, function ($element) { return $element['attribute_code'] == 'size'; });
        if ($sizeAttributeIndex !== false) {
            foreach($productAttributeOptions[$sizeAttributeIndex]['values'] as $value) {
                $sizes[] = array('option' => $value['value_index'], 'label' => $value['store_label'], 'price' => $value['pricing_value']);
            }
        }
        return $sizes;
    }

    protected function array_usearch(array $array, callable $comparitor)
    {
        foreach ($array as $key => $element) {
            if ($comparitor($element)) {
                return $key;
            }
        }
        return false;
    }
}