<?php
/**
 * Lists.php
 */
require_once dirname(__FILE__) . "/MailUpWsImport.php";
require_once dirname(__FILE__) . "/Wssend.php";

class SevenLike_MailUp_Model_Lists
{
    /**
     * Get as options
     * 
     * @todo    Add Caching
     * @return  array
     */
    public function toOptionArray($storeId = NULL) 
    {
        $websiteCode = Mage::app()->getRequest()->getParam('website');
        $storeCode = Mage::app()->getRequest()->getParam('store');
        
        if(isset($storeId) && $storeId != FALSE) {
            $storeId = $storeId;
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

                if ($xmlString) {
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
                            'value' => (string)$list['idList'], 
                            'label'=> (string)$list['listName'], 
                            'guid'=>(string)$list['listGUID'], 
                            "groups"=>$groups
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
}
