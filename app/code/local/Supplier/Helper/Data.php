<?php

class Blackbox_Supplier_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_mediaDir;

    public function __construct()
    {
        $this->_mediaDir = Mage::getBaseDir('media');
    }

    public function downloadImage($domain, $relativeUrl, $folder)
    {
        $imagePath = $this->_mediaDir . '/catalog/' . $folder . '/' . $relativeUrl;
        if (!$this->_downloadFile($imagePath, $domain . $relativeUrl)) {
            return false;
        }
        return $imagePath;
    }

    public function _downloadFile($path, $url)
    {
        try {
            if (file_exists($path))
                return true;

            $pathInfo = pathinfo($path);
            if (!file_exists($pathInfo['dirname'])) {
                if (!(new Varien_Io_File())->mkdir($pathInfo['dirname'], 0777, true)) {
                    return false;
                }
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $data = curl_exec ($ch);
            curl_close ($ch);
            $file = fopen($path, "w+");
            fputs($file, $data);
            fclose($file);

            return true;
        } catch (Exception $ex) {
            return false;
        }
    }

    public function createCategory($name, $image, $parentId = '2', $returnWithParentCategories = false)
    {
        if (!$name) {
            return false;
        }
        $category = $this->getCategoryByName($name);
        if ($category === false) {
            $category = Mage::getModel('catalog/category');
            $category->setName($name);
            $category->setUrlKey($name);
            $category->setImage($image);
            $category->setIsActive(1);
            $category->setDisplayMode('PRODUCTS');
            $category->setIsAnchor(1); //for active anchor
            $category->setStoreId(Mage::app()->getStore()->getId());
            $parentCategory = Mage::getModel('catalog/category')->load($parentId);
            $category->setPath($parentCategory->getPath());
            $category->save();
        }

        if ($returnWithParentCategories) {
            $ids = explode('/', $category->getPath());
            while ($ids[0] <= 2) {
                array_shift($ids);
            }
            return $ids;
        } else {
            return $category->getId();
        }
    }

    public function getCategoryByName($name)
    {
        $categories = Mage::getResourceModel('catalog/category_collection')
            ->addFieldToFilter('name', $name)
            ->setPageSize(1)
            ->load();

        if ($categories->count() > 0) {
            return $categories->getFirstItem();
        }
        return false;
    }

    public function getCategoryIdByName($name)
    {
        $categories = Mage::getResourceModel('catalog/category_collection')
            ->addFieldToFilter('name', $name)
            ->setPageSize(1)
            ->load();

        if ($categories->count() > 0) {
            return $categories->getFirstItem()->getId();
        }
        return false;
    }

    public function getGenders() {
        return array('Women', 'Men');
    }

    public function getGenderFromName($name)
    {
        $genders = array(
            'Women' => array(
                'women',
                'woman',
                'ladies',
                'lady'
            ),
            'Men' => array(
                'man',
                'men'
            )
        );
        $name = strtolower($name);

        foreach ($genders as $gender => $search) {
            foreach ($search as $word) {
                if (preg_match("/\b$word\b/", $name)) {
                    return $gender;
                }
            }
        }

        return null;
    }
}