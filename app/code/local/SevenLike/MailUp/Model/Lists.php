<?php
/**
 * Lists.php
 */
require_once dirname(__FILE__) . "/MailUpWsImport.php";
require_once dirname(__FILE__) . "/Wssend.php";

class SevenLike_MailUp_Model_Lists
{
    /**
     * @var array
     */
    protected $_cache = array();
    
    /**
     * Get as options
     * 
     * array(
     *     array(
     *          'value'     => (string)$list['idList'], 
                'label'     => (string)$list['listName'], 
                'guid'      =>(string)$list['listGUID'], 
                "groups"    => array(
     *           ...
     *          )
     *     )
     * )
     * 
     * @return  array
     */
    public function toOptionArray($storeId = NULL) 
    {
        $websiteCode = Mage::app()->getRequest()->getParam('website');
        $storeCode = Mage::app()->getRequest()->getParam('store');
        
        if(isset($storeId) && $storeId != FALSE) {
            $storeId = $storeId; // ?
        }
        elseif($storeCode) {
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

        //genero la select per Magento
        $selectLists = array();

        if (Mage::getStoreConfig('mailup_newsletter/mailup/url_console', $storeId) 
            && Mage::getStoreConfig('mailup_newsletter/mailup/username_ws', $storeId) 
            && Mage::getStoreConfig('mailup_newsletter/mailup/password_ws', $storeId)) {
            
            $wsSend = new MailUpWsSend($storeId);
            $accessKey = $wsSend->loginFromId();
            
            if ($accessKey !== false) {
	            require_once dirname(__FILE__) . "/MailUpWsImport.php";
                $wsImport = new MailUpWsImport($storeId);
                
                $xmlString = $wsImport->GetNlList();

                $selectLists[0] = array('value' => 0, 'label'=>'-- Select a list (if any) --');

                if($xmlString) {
                    $xmlString = html_entity_decode($xmlString);
                    $startLists = strpos($xmlString, '<Lists>');
                    $endPos = strpos($xmlString, '</Lists>');
                    $endLists = $endPos + strlen('</Lists>') - $startLists;
                    $xmlLists = substr($xmlString, $startLists, $endLists);
                    $xmlLists = str_replace("&", "&amp;", $xmlLists);
                    $xml = simplexml_load_string($xmlLists);
                    $count = 1;
                    foreach ($xml->List as $list) {
						$groups = array();
						foreach ($list->Groups->Group as $tmp) {
							$groups[(string)$tmp["idGroup"]] = (string)$tmp["groupName"];
						}
                        $selectLists[$count] = array(
                            'value'     => (string)$list['idList'], 
                            'label'     => (string)$list['listName'], 
                            'guid'      =>(string)$list['listGUID'], 
                            "groups"    => $groups
                        );
                        $count++;
                    }
                }
            } else {
                if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log', $storeId)) Mage::log('LoginFromId failed', 0);
                $selectLists[0] = array('value' => 0, 'label'=>$GLOBALS["__sl_mailup_login_error"]);
            }
        }

        return $selectLists;
    }

    /**
     *
     * Get an array of list data, and its groups.
     * @param $listId
     * @param $storeId
     * @return bool|array
     */
    public function getListDataArray($listId, $storeId) 
    {
        $listData = $this->getDataArray($storeId);
        if (isset($listData[$listId])) {
           return $listData[$listId];
        }

        // If list not found, return false
        if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log', $storeId)) {
            Mage::log('Invalid List ID: ' . $listId);
        }

        return false;
    }
    
    /**
     * Get an array of all lists, and their groups!
     * 
     * @return  array
     */
    public function getDataArray($storeId) 
    {
        $selectLists = array();

        if( ! isset($this->_cache[$storeId])) {
            if($this->_config()->getUrlConsole($storeId) && $this->_config()->getUsername($storeId)
                && $this->_config()->getPassword($storeId)) {
                $wsSend = new MailUpWsSend($storeId);
                $accessKey = $wsSend->loginFromId();
                if($accessKey !== false) {
                    require_once dirname(__FILE__) . "/MailUpWsImport.php";
                    $wsImport = new MailUpWsImport($storeId);
                    $xmlString = $wsImport->GetNlList();
                    if($xmlString) {
                        $xmlString = html_entity_decode($xmlString);
                        $startLists = strpos($xmlString, '<Lists>');
                        $endPos = strpos($xmlString, '</Lists>');
                        $endLists = $endPos + strlen('</Lists>') - $startLists;
                        $xmlLists = substr($xmlString, $startLists, $endLists);
                        $xmlLists = str_replace("&", "&amp;", $xmlLists);
                        $xml = simplexml_load_string($xmlLists);
                        foreach ($xml->List as $list) {
                            $groups = array();
                            foreach ($list->Groups->Group as $tmp) {
                                $groups[(string)$tmp["idGroup"]] = (string)$tmp["groupName"];
                            }
                            $selectLists[(string)$list['idList']] = array(
                                'idList'    => (string)$list['idList'], 
                                'listName'  => (string)$list['listName'], 
                                'listGUID'  =>(string)$list['listGUID'], 
                                "groups"    => $groups
                            );
                        }
                   }
                }
            }
            
            $this->_cache[$storeId] = $selectLists;
        }
        
        return $this->_cache[$storeId];
    }
    
    /**
     * Get a List Guid
     * 
     * @param   int
     * @param   int
     * @return  string|false
     */
    public function getListGuid($listId, $storeId)
    {
        $listData = $this->getListDataArray($listId, $storeId);

        if ($listData === false || !isset($listData['listGUID'])) {
            return false;
        }
        
        return $listData['listGUID'];
    }
    
    /**
     * Get the groups for a given list.
     * 
     * @param   int|false
     */
    public function getListGroups($listId, $storeId)
    {
        $listData = $this->getListDataArray($listId, $storeId);

        if ($listData === false || !isset($listData['groups'])) {
            return false;
        }
        
        return $listData['groups'];
    }
    
    /**
     * @var SevenLike_MailUp_Model_Config
     */
    protected $_config;
    
    /**
     * Get the config
     * 
     * @reutrn SevenLike_MailUp_Model_Config
     */
    protected function _config()
    {        
        if(NULL === $this->_config) {
            $this->_config = Mage::getModel('mailup/config');
        }
        
        return $this->_config;
    }
}
