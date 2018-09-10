<?php

class Blackbox_Supplier_Helper_Attributes extends Mage_Core_Helper_Abstract
{
    public function createAttributeSet($name, $newAttributes, $parentId = 4, $deleteOld = false)
    {
        $attibuteSet = Mage::getModel("eav/entity_attribute_set")->load($name, 'attribute_set_name');
        if ($attibuteSet->getId()) {
            if (!$deleteOld) {
                return $attibuteSet->getId();
            }
            $attibuteSet->delete();
        }

        try {
            /** @var Mage_Catalog_Model_Product_Attribute_Set_Api */
            $id = Mage::getModel('catalog/product_attribute_set_api')
                ->create($name, $parentId);
        } catch (Exception $e) {
            throw new Exception('Unable to create attribute set "' . $name . '" : ' . $e->getMessage());
        }

        $setup = new Mage_Eav_Model_Entity_Setup('core_setup');

        foreach ($newAttributes as $attributeId => $groupName) {
            $attributeGroupId = $setup->getAttributeGroupId('catalog_product', $id, $groupName);
            $setup->addAttributeToSet($entityTypeId = 'catalog_product', $id, $attributeGroupId, $attributeId);
        }

        return $id;
    }

    public function createAttribute($code, $label, $attribute_type, $product_types, $additional_data = array(), $deleteOld = false)
    {
        $attribute = Mage::getModel("eav/entity_attribute")->loadByCode("catalog_product", $code);

        if ($attribute->getId()) {
            if ($deleteOld) {
                $attribute->delete();
            } else {
                $data = $attribute->getData();
                foreach ($additional_data as $key => $value) {
                    $data[$key] = $value;
                }
                $data['frontend_input'] = $attribute_type;
                $data['apply_to'] = implode(',', $product_types);
                $data['frontend_label'] = $label;
                $attribute->setData($data);
                $attribute->save();
                return $attribute->getId();
            }
        }

        $_attribute_data = array(
            'attribute_code' => $code,
            'is_global' => '1',
            'frontend_input' => $attribute_type, //'boolean',
            'default_value_text' => '',
            'default_value_yesno' => '0',
            'default_value_date' => '',
            'default_value_textarea' => '',
            'is_unique' => '0',
            'is_required' => '0',
            'apply_to' => $product_types, //array('grouped')
            'is_configurable' => '0',
            'is_searchable' => '0',
            'is_visible_in_advanced_search' => '0',
            'is_comparable' => '0',
            'is_used_for_price_rules' => '0',
            'is_wysiwyg_enabled' => '0',
            'is_html_allowed_on_front' => '1',
            'is_visible_on_front' => '0',
            'used_in_product_listing' => '0',
            'used_for_sort_by' => '0',
            'frontend_label' => array($label)
        );

        $_attribute_data = array_merge($_attribute_data, $additional_data);

        $model = Mage::getModel('catalog/resource_eav_attribute');

        if (!isset($_attribute_data['is_configurable'])) {
            $_attribute_data['is_configurable'] = 0;
        }
        if (!isset($_attribute_data['is_filterable'])) {
            $_attribute_data['is_filterable'] = 0;
        }
        if (!isset($_attribute_data['is_filterable_in_search'])) {
            $_attribute_data['is_filterable_in_search'] = 0;
        }

        if (is_null($model->getIsUserDefined()) || $model->getIsUserDefined() != 0) {
            $_attribute_data['backend_type'] = $model->getBackendTypeByInput($_attribute_data['frontend_input']);
        }

        $defaultValueField = $model->getDefaultValueByInput($_attribute_data['frontend_input']);
        if ($defaultValueField) {
            $_attribute_data['default_value'] = $this->getRequest()->getParam($defaultValueField);
        }


        $model->addData($_attribute_data);

        $model->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
        $model->setIsUserDefined(1);

        try {
            $model->save();
        } catch (Exception $e) { throw new Exception('Error occured while trying to save the attribute. Error: ' . $e->getMessage() . PHP_EOL); }

        return $model->getId();
    }

    public function getAttributeIdByCode($code)
    {
        return Mage::getModel("eav/entity_attribute")->loadByCode("catalog_product", $code)->getId();
    }
}