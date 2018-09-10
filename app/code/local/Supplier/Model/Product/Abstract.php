<?php
abstract class Blackbox_Supplier_Model_Product_Abstract
{
    /**
     * @return Mage_Catalog_Model_Product valid product for add to magento
     */

    public abstract function toMageProductModel();

    /**
     * Create instance of child class from stdObject
     * @param $stdClass
     * @return string
     */
    public static function factory($stdClass)
    {
        $destination = get_called_class();
        $sourceObject  = &$stdClass;

        if (is_string($destination)) {
            $destination = new $destination();
        }

        $sourceReflection = new ReflectionObject($sourceObject);
        $destinationReflection = new ReflectionObject($destination);
        $sourceProperties = $sourceReflection->getProperties();

        foreach ($sourceProperties as $sourceProperty) {
            $sourceProperty->setAccessible(true);
            $name = $sourceProperty->getName();
            $value = $sourceProperty->getValue($sourceObject);
            if ($destinationReflection->hasProperty($name)) {
                $propDest = $destinationReflection->getProperty($name);
                $propDest->setAccessible(true);
                $propDest->setValue($destination, $value);
            } else {
                $destination->$name = $value;
            }
        }
        return $destination;
    }

}