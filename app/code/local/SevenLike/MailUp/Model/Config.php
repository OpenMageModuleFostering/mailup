<?php
/**
 * Config.php
 * 
 * Central config model
 */
class SevenLike_MailUp_Model_Config
{
	const XML_CONSOLE               = 'mailup_newsletter/mailup/url_console';
    const XML_LOG_ENABLE            = 'mailup_newsletter/mailup/enable_log';
    const XML_CRON_EXPORT_ENABLE    = 'mailup_newsletter/mailup/enable_cron_export';
    const XML_MAILUP_USERNAME       = 'mailup_newsletter/mailup/username_ws';
    const XML_MAILUP_PASSWORD       = 'mailup_newsletter/mailup/password_ws';
    const XML_MAILUP_LIST_ID        = 'mailup_newsletter/mailup/list';
    const XML_SUBSCRIBE_IN_CHECKOUT = 'mailup_newsletter/mailup/enable_subscribe_in_checkout';
    const XML_CRON_FREQ             = 'mailup_newsletter/mailup/mailup_cron_frequency';
    const XML_WEBHOOK_KEY           = 'mailup_newsletter/mailup/webhook_crypt_key';
    const XML_DISABLE_NOTIFICATION  = 'mailup_newsletter/mailup/disablenewslettersuccesses';
    
    const XML_MAPPING_SECTION       = 'mailup_newsletter/mailup_mapping';
    
    /**
     * Is the log enabled?
     * 
     * @param   int
     * @return bool
     */
    public function isLogEnabled($storeId = NULL)
    {
        return (int) Mage::getStoreConfig(self::XML_LOG_ENABLE, $storeId);
    }
    
    /**
     * Write a log entry it enabled.
     * 
     * @param   string
     * @param   int
     * @return bool
     */
    public function log($message, $storeId = NULL)
    {
        if( ! $this->isLogEnabled($storeId)) {
            return ;
        }
        
        Mage::log($message, null, 'mailup.log');
    }
    
    /**
     * Write a log entry it enabled.
     * 
     * @param   string
     * @param   int
     * @param   int
     * @param   string
     * @param   string
     * @return bool
     */
    public function dbLog($info, $jobId = 0, $storeId = NULL, $status = 'DEBUG', $type = 'DEBUG')
    {
        if( ! $this->isLogEnabled($storeId)) {
            return ;
        }
        
        if( ! isset($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }

        $log = Mage::getModel('mailup/log');
        /* @var $log SevenLike_MailUp_Model_Log */
        $log->setData(array(
            'store_id'      => $storeId,
            'job_id'        => $jobId,
            'type'          => $type,
            'status'        => $status,
            'data'          => $info,
            'event_time'    => date("Y-m-d H:i:s"),
        ));
        
        try {
            $log->save();
        }
        catch(Exception $e) {
            $this->log($e->getMessage(), $storeId);
        }
    }
    
    /**
     * Disable Magnetos Newsletter Subscription Notifiactions??
     * 
     * @param   int
     * @return  bool
     */
    public function isNewsletterNotificationDisabled($storeId = NULL)
    {
        return (int) Mage::getStoreConfig(self::XML_DISABLE_NOTIFICATION, $storeId);
    }
    
    /**
     * Get the url console url from Config
     * 
     * @param   int
     * @return  string
     */
    public function getUrlConsole($storeId = NULL) 
    {
        return Mage::getStoreConfig(self::XML_CONSOLE, $storeId);
    }
    
    /**
     * Get the WSDL Url.
     * 
     * @param   int $storeId
     * @return  string
     */
    public function getWsdlUrl($storeId)
    {
        return 'http://'. $this->getUrlConsole($storeId) .'/services/WSMailUpImport.asmx?WSDL';
    }
    
    /**
     * Is the cron enabled?
     * 
     * @param   int
     * @return  int
     */
    public function isCronExportEnabled($storeId = NULL) 
    {
        return (int) Mage::getStoreConfig(self::XML_CRON_EXPORT_ENABLE, $storeId);
    }
    
    /**
     * Get the list ID
     * 
     * @param   int
     * @return  int
     */
    public function getMailupListId($storeId = NULL) 
    {
        return Mage::getStoreConfig(self::XML_MAILUP_LIST_ID, $storeId);
    }
    
    /**
     * Get the username from Config
     * 
     * @param   int
     * @return  string
     */
    public function getUsername($storeId = NULL) 
    {
        return Mage::getStoreConfig(self::XML_MAILUP_USERNAME, $storeId);
    }
    
    /**
     * Get the password from Config
     * 
     * @param   int
     * @return  string
     */
    public function getPassword($storeId = NULL) 
    {
        return Mage::getStoreConfig(self::XML_MAILUP_PASSWORD, $storeId);
    }
    
    /**
     * Is Subscribe in checkout enabled?
     * 
     * @param   int
     * @return int
     */
    public function isSubscribeInCheckout($storeId = NULL) 
    {
        return (int) Mage::getStoreConfig(self::XML_SUBSCRIBE_IN_CHECKOUT, $storeId);
    }
    
    /**
     * Get the cron freq settings
     * 
     * @param   int
     * @return  string
     */
    public function getCronFrequency($storeId = NULL) 
    {
        return Mage::getStoreConfig(self::XML_CRON_FREQ, $storeId);
    }
    
    /**
     * Get Field Mapping
     * 
     * @todo    Fix to use the config for mappings, per store..
     * @param   int
     * @return  array
     */
	public function getFieldsMapping($storeId = NULL) 
    {
        return Mage::getStoreConfig(self::XML_MAPPING_SECTION, $storeId);
        
        /*$return = array();
        
        foreach(Mage::getStoreConfig(self::XML_MAPPING_SECTION, $storeId) as $key => $field) {
            var_dump($key);
            var_dump($field);
        }
        
        return $return;*/
	}
    
    /**
     * Get the name of the Sync Table
     * 
     * @return  string
     */
    public function getSyncTableName()
    {
        return Mage::getSingleton('core/resource')->getTableName('mailup/sync');
    }
    
    /**
     * Get the name of the Jobs Table
     * 
     * @return string
     */
    public function getJobsTableName()
    {
        return Mage::getSingleton('core/resource')->getTableName('mailup/job');
    }
    
    /**
     * Get an array of Stores, for use in a dropdown.
     * 
     * array(
     *     id => code
     * )
     * 
     * @return  array
     */
    public function getStoreArray()
    {
        //$storeModel = Mage::getSingleton('adminhtml/system_store');
        /* @var $storeModel Mage_Adminhtml_Model_System_Store */
        //$websiteCollection = $storeModel->getWebsiteCollection();
        //$groupCollection = $storeModel->getGroupCollection();
        //$storeCollection = $storeModel->getStoreCollection();
        $storesArr = array();
        
        /*$defaultStoreId = Mage::app()->getDefaultStoreView()->getStoreId();
        $storesArr[$defaultStoreId] = array(
            'id'    => $defaultStoreId,
            'code'  => Mage::app()->getDefaultStoreView()->getCode(),
            'name'  => Mage::app()->getDefaultStoreView()->getName(),
        );*/
        
        $storesArr[0] = array(
            'id'    => 0,
            'code'  => 'default',
            'name'  => 'Default',
        );
        
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    /* @var $store Mage_Core_Model_Store */
                    $storesArr[$store->getId()] = array(
                        'id'    => $store->getId(),
                        'code'  => $store->getCode(),
                        'name'  => $store->getName(),
                    );
                }
            }
        }
        
        return $storesArr;
    }
    
    /**
     * Get an array of all store ids
     * 
     * @reutrn  array
     */
    public function getAllStoreIds()
    {
        $ids = array();
        
        $allStores = Mage::app()->getStores();
        foreach ($allStores as $storeId => $val) {
            $ids[] = Mage::app()->getStore($storeId)->getId();
        }
        
        return $ids;
    }
}