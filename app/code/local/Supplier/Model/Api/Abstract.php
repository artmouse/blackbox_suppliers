<?php
abstract class Blackbox_Supplier_Model_Api_Abstract extends Varien_Object {

    /**
     * @param Mage_Catalog_Model_Product $product Configurable product what need to calc
     * @return int Price in USD
     */
    public abstract function getPrice(Mage_Catalog_Model_Product $product);

    /**
     * @param Mage_Sales_Model_Quote $order place order to supplier
     * @return mixed Response from API.
     */
    public abstract function placeOrder(Mage_Sales_Model_Quote $order);

    /**
     * @return Blackbox_Supplier_Model_Product_Abstract[] product list from supplier
     */
    public abstract function getProducts();


    /**
     * Get basic url for image
     * @return mixed
     */
    public abstract function getBaseImgUrl();

    /**
     * @param $url target of image
     * @return string
     */
    public function uploadRemoteImg($url){
        $channel = curl_init();
        $fileTemp = $this->getDestination();
        curl_setopt($channel, CURLOPT_URL, $url);
        curl_setopt($channel, CURLOPT_POST, 0);
        curl_setopt($channel, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($channel, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($channel, CURLOPT_SSL_VERIFYHOST, 0);

        $fileBytes = curl_exec($channel);
        curl_close($channel);

        $fileWriter = fopen($fileTemp, 'w');
        fwrite($fileWriter, $fileBytes);
        fclose($fileWriter);
        return $fileTemp;
    }

    /**
     * @return string random temp file path
     */
    protected function getDestination(){
        print_r(Mage::getBaseDir('media'));
        return Mage::getBaseDir('media').'/import/'.mt_rand(10,time()).'.jpg';
    }
    /**
     * Process model configuration array.
     * This method can be used for changing totals collect sort order
     *
     * @param   array $config
     * @param   store $store
     * @return  array
     */
    public function processConfigArray($config, $store)
    {
        return $config;
    }
}