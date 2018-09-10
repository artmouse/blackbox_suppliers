<?php
$this->startSetup();

$helper = Mage::helper('blackbox_supplier/attributes');

$attributeSet = $helper->createAttributeSet('SupplierConfigurable', array($sizeAttribute => 'General', $colorAttribute => 'General'), 4, false);

$this->addAttribute('catalog_product', 'gender', array(
    'group'         => 'General',
    'input'         => 'select',
    'type'          => 'int',
    'attribute_set' =>  $attributeSet,
    'label'         => 'Gender',
    'backend'       => '',
    'visible'       => true,
    'required'      => false,
    'visible_on_front' => true,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'sort_order'    => 200,
    'source' => 'eav/entity_attribute_source_table'
));

$this->addAttribute('catalog_product', 'brand', array(
    'group'         => 'General',
    'input'         => 'select',
    'type'          => 'int',
    'attribute_set' =>  $attributeSet,
    'label'         => 'Brand',
    'backend'       => '',
    'visible'       => false,
    'required'      => false,
    'visible_on_front' => false,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'sort_order'    => 200,
    'source' => 'eav/entity_attribute_source_table'
));

$this->endSetup();