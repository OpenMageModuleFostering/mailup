<?php

require_once dirname(__FILE__) . "/MailUpWsImport.php";
require_once dirname(__FILE__) . "/Wssend.php";
class SevenLike_MailUp_Model_Lists
{
    public function toOptionArray() {
        //genero la select per Magento
        $selectLists = array();

        if (Mage::getStoreConfig('newsletter/mailup/url_console') && Mage::getStoreConfig('newsletter/mailup/username_ws') && Mage::getStoreConfig('newsletter/mailup/password_ws')) {
            $wsSend = new MailUpWsSend();
            $accessKey = $wsSend->loginFromId();
            
            if ($accessKey !== false) {
	            require_once dirname(__FILE__) . "/MailUpWsImport.php";
                $wsImport = new MailUpWsImport();
                
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
                        $selectLists[$count] = array('value' => (string)$list['idList'], 'label'=> (string)$list['listName'], 'guid'=>(string)$list['listGUID'], "groups"=>$groups);
                        $count++;
                    }
                }
            } else {
                if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log('LoginFromId failed', 0);
                $selectLists[0] = array('value' => 0, 'label'=>$GLOBALS["__sl_mailup_login_error"]);
            }
        }

        return $selectLists;
    }
}
