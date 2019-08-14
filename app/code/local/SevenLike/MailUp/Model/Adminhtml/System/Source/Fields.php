<?php 

class SevenLike_MailUp_Model_Adminhtml_System_Source_Fields
{
    const CACHE_LIFETIME = 600; // 10 min
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $websiteCode = Mage::app()->getRequest()->getParam('website');
        $storeCode = Mage::app()->getRequest()->getParam('store');
        
        if($storeCode) {
            $storeId = Mage::app()->getStore($storeCode)->getId();
            $cacheId = 'mailup_fields_array_store_'.$storeId;
        }
        elseif($websiteCode) {
            $storeId = Mage::app()
                ->getWebsite($websiteCode)
                ->getDefaultGroup()
                ->getDefaultStoreId()
            ;
            $cacheId = 'mailup_fields_array_store_'.$storeId;
        }
        else {
            $storeId = NULL;
            $cacheId = 'mailup_fields_array';
            //$storeId = Mage::app()->getDefaultStoreView()->getStoreId();
        }
        
//        var_dump($storeCode);
//        var_dump($websiteCode);
//        var_dump($storeId);
//        var_dump(Mage::app()->getStores());

        
        $options = array(array('value' => '', 'label' => ''));
        if(false !== ($data = Mage::app()->getCache()->load($cacheId))) {
            $options = unserialize($data);
        } 
        else {
            $wsSend = new MailUpWsSend($storeId);
            $accessKey = $wsSend->loginFromId();
            if($accessKey !== false) {
                $wsFields = $wsSend->getFields($accessKey);
                //$wsFields = array('test' => 'test');
                foreach ($wsFields as $label => $value) {
                    $options[] = array(
                        'value' => $value, 
                        'label' => $label, //Mage::helper('adminhtml')->__($label)
                    );
                }
            }
            Mage::app()->getCache()->save(serialize($options), $cacheId, array(), self::CACHE_LIFETIME);
        }
        
        return $options;
    }

}
