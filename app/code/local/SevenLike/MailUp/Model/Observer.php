<?php

require_once dirname(__FILE__) . "/MailUpWsImport.php";
require_once dirname(__FILE__) . "/Wssend.php";
class SevenLike_MailUp_Model_Observer
{
	const CRON_STRING_PATH  = 'crontab/jobs/sevenlike_mailup/schedule/cron_expr';

    /**
     * @var SevenLike_MailUp_Model_Config
     */
    protected $_config;
    
    /**
     * Save system config event
     *
     * @param Varien_Object $observer
     */
    public function saveSystemConfig($observer)
    {
	    Mage::getModel('core/config_data')
		    ->load(self::CRON_STRING_PATH, 'path')
		    ->setValue($this->_getSchedule())
		    ->setPath(self::CRON_STRING_PATH)
		    ->save();
        
	    Mage::app()->cleanCache();
        
	    $this->configCheck();
    }

    /**
     * Transform system settings option to cron schedule string
     *
     * @return string
     */
    protected function _getSchedule()
    {
        $data = Mage::app()->getRequest()->getPost('groups');
        $frequency = !empty($data['mailup']['fields']['mailup_cron_frequency']['value'])?
            $data['mailup']['fields']['mailup_cron_frequency']['value']:
            0;

        switch ($frequency) {
            case SevenLike_MailUp_Model_Adminhtml_System_Source_Cron_Frequency::DAILY:
	            return "0 0 * * *";
            case SevenLike_MailUp_Model_Adminhtml_System_Source_Cron_Frequency::EVERY_2_HOURS:
	            return "0 0,2,4,6,8,10,12,14,16,18,20,22 * * *";
            case SevenLike_MailUp_Model_Adminhtml_System_Source_Cron_Frequency::EVERY_6_HOURS:
	            return "0 0,6,12,18 * * *";
            case SevenLike_MailUp_Model_Adminhtml_System_Source_Cron_Frequency::EVERY_12_HOURS:
	            return "0 0,12 * * *";
	        case SevenLike_MailUp_Model_Adminhtml_System_Source_Cron_Frequency::HOURLY:
		    default:
		        return "0 0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23 * * *";
        }
    }
    
	/**
     * Observes: customer_customer_authenticated
     * 
     * @param type $observer
     * @return \SevenLike_MailUp_Model_Observer
     */
	public function leggiUtente($observer)
	{
		$model = $observer->getEvent()->getModel();
		if (empty($model)) $model = $model = $observer->getEvent()->getDataObject();
		if (isset($GLOBALS["__sl_mailup_leggi_utente"])) return $this;
		$GLOBALS["__sl_mailup_leggi_utente"] = true;

		try {
			$WSDLUrl = 'http://services.mailupnet.it/MailupReport.asmx?WSDL';
			$user = Mage::getStoreConfig('mailup_newsletter/mailup/username_ws');
			$password = Mage::getStoreConfig('mailup_newsletter/mailup/password_ws');
			$headers = array('User' => $user, 'Password' => $password);
			$header = new SOAPHeader("http://ws.mailupnet.it/", 'Authentication', $headers);
			$soapclient = new SoapClient($WSDLUrl, array('trace' => 1, 'exceptions' => 1, 'connection_timeout' => 10));
			$soapclient->__setSoapHeaders($header);

			$loginData = array ('user' => Mage::getStoreConfig('mailup_newsletter/mailup/username_ws'),
				'pwd' => Mage::getStoreConfig('mailup_newsletter/mailup/password_ws'),
				'consoleId' => substr(Mage::getStoreConfig('mailup_newsletter/mailup/username_ws'), 1));
			$result = get_object_vars($soapclient->LoginFromId($loginData));
			$xml = simplexml_load_string($result['LoginFromIdResult']);
			$errorCode = (string)$xml->errorCode;
			$errorDescription = (string)$xml->errorDescription;
			$accessKey = (string)$xml->accessKey;

			$result = $soapclient->ReportByUser(array(
				"accessKey" => $accessKey,
				"email" => $model->getEmail(),
				"listID" => Mage::getStoreConfig('mailup_newsletter/mailup/list'),
				"messageID" => 0
			));
			$result = get_object_vars($result);
			$xml = simplexml_load_string($result['ReportByUserResult']);

			$stato_registrazione = (string)$xml->Canali->Email;
			if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) Mage::log("stato registrazione: " . $stato_registrazione);
			if ($stato_registrazione) {
				switch (strtolower($stato_registrazione)) {
					case "iscritto":
						Mage::getModel('newsletter/subscriber')->loadByEmail($model->getEmail())->setStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)->save();
						$model->setIsSubscribed(1);
						$model->save();
						break;
					case "in attesa":
						Mage::getSingleton('core/session')->addNotice(Mage::helper("mailup")->__("Your subscription is waiting for confirmation"));
						break;
					default:
						Mage::getModel('newsletter/subscriber')->loadByEmail($model->getEmail())->setStatus(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED)->save();
						$model->setIsSubscribed(0);
						$model->save();
				}
			}
		} catch (Exception $e) {
			Mage::logException($e);
		}

		return $this;
	}
    
	/**
     * Observes subscription
     * 
     * @see     newsletter_subscriber_save_after
     * @param   type $observer
     * @return \SevenLike_MailUp_Model_Observer
     */
	public function inviaUtente($observer)
	{
		if (isset($GLOBALS["__sl_mailup_invia_utente"])) {
            return $this;
        }
		$GLOBALS["__sl_mailup_invia_utente"] = true;
		
		$model = $observer->getEvent()->getDataObject();
        if(Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
            Mage::log($model->getData());
        }
		$status = Mage::getModel('newsletter/subscriber')->loadByEmail($model->getEmail())->getStatus();
		
		$module = Mage::app()->getRequest()->getModuleName();
		$controller = Mage::app()->getRequest()->getControllerName();
		$action = Mage::app()->getRequest()->getActionName();

        if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
            Mage::log("mailup: invia utente");
        }
		
		if (($module == "customer" and $controller == "account" and $action == "createpost") or ($module == "checkout" and $controller == "onepage" and $action == "saveOrder")) {
            if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
                Mage::log("SONO in registrazione, LEGGO PRIMA mailup!");
            }
            /**
             * are recording, monitoring the status of magento subscribe, 
             * if you do not result in writing I read status from MailUp and if 
             * they are registered with the subject of magento before continuing
             */
			if ( ! $status) {
				//leggo l'utente da mailup
				$this->leggiUtente($observer);
				//rileggo lo status perché potrebbe essere stato modificato dalla precedente chiamata
				$status = Mage::getModel('newsletter/subscriber')->loadByEmail($model->getEmail())->getStatus();
				// se non sono iscritto nemmeno lato mailup allora posso evitare di andare oltre
				if ( ! $status) {
                    return $this;
                }
			}
		}
		
		$console = Mage::getStoreConfig('mailup_newsletter/mailup/url_console');
		$listId = Mage::getStoreConfig('mailup_newsletter/mailup/list');

		try {
			$wsImport = new MailUpWsImport();
			$xmlString = $wsImport->GetNlList();
			if (!$xmlString) return $this;

			$xmlString = html_entity_decode($xmlString);
			$startLists = strpos($xmlString, '<Lists>');
			$endPos = strpos($xmlString, '</Lists>');
			$endLists = $endPos + strlen('</Lists>') - $startLists;
			$xmlLists = substr($xmlString, $startLists, $endLists);
			$xmlLists = str_replace("&", "&amp;", $xmlLists);
			$xml = simplexml_load_string($xmlLists);

			foreach ($xml->List as $list) {
				if ($list['idList'] == $listId) {
					$listGUID = $list["listGUID"];
					break;
				}
			}
			if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) Mage::log("STATO ISCRIZIONE: $status");
			if ($status == 1) {
				$ws  = "http://{$console}/frontend/Xmlsubscribe.aspx";
			} else {
				$ws  = "http://{$console}/frontend/Xmlunsubscribe.aspx";
			}

			$ws .= "?ListGuid=" . rawurlencode($listGUID);
			$ws .= "&List=" . rawurlencode($listId);
			$ws .= "&Email=" . rawurlencode($model->getEmail());

			try {
				if(Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
                    Mage::log("mailup invio utente $ws");
                }
				$result = @file_get_contents($ws);
				if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
                    Mage::log("mailup risultato invio $result");
                }
			} catch (Exception $e) {}
		} catch (Exception $e) {
			Mage::logException($e);
		}
		
		return $this;
	}

    /**
     * Config Check
     * 
     * @return type
     */
	public function configCheck()
	{
		$url_console = Mage::getStoreConfig('mailup_newsletter/mailup/url_console');
		$user = Mage::getStoreConfig('mailup_newsletter/mailup/username_ws');
		$password = Mage::getStoreConfig('mailup_newsletter/mailup/password_ws');
		$list = Mage::getStoreConfig('mailup_newsletter/mailup/list');

		if (!strlen($url_console) or !strlen($user) or !strlen($password) or !strlen($list)) {
			$url = Mage::getModel('adminhtml/url');
			$url = $url->getUrl("mailup/adminhtml_configuration");
			$message = Mage::helper("mailup")->__('MailUp configuration is not complete');
			$message = str_replace("href=''", "href='$url'", $message);
			Mage::getSingleton('adminhtml/session')->addWarning($message);
			
            return;
		}

		$wsimport = new MailUpWsImport();
		$mapping = $wsimport->getFieldsMapping();
		if (empty($mapping)) {
			$url = Mage::getModel('adminhtml/url');
			$url = $url->getUrl("mailup/adminhtml_configuration");
			$message = Mage::helper("mailup")->__('MailUp fields mapping is not complete');
			$message = str_replace("href=''", "href='$url'", $message);
			Mage::getSingleton('adminhtml/session')->addWarning($message);
			
            return;
		}
	}

    /**
     * Subscribw the user, during checkout.
     * 
     * @return  void
     */
	public function subscribeDuringCheckout()
	{
        if (@$_REQUEST["mailup_subscribe2"]) {
            $order_id = Mage::getSingleton("checkout/session")->getLastRealOrderId();
            $order = Mage::getModel("sales/order")->loadByIncrementId($order_id);
            try {
                Mage::getModel("newsletter/subscriber")->subscribe($order->getCustomerEmail());
            } catch (Exception $e) {}
        }
	}

    /**
     * @var bool
     */
    protected $_hasCustomerDataSynced = FALSE;
    
    /**
     * Attach to sales_order_save_after event
     * 
     * @see     sales_order_save_after
     * @param   type $observer
     */
	public function prepareOrderForDataSync($observer)
	{
        if(Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
            Mage::log("TRIGGERED prepareOrderForDataSync");
        }
        
		$order = $observer->getEvent()->getOrder();
        /* @var $order Mage_Sales_Model_Order */
		$customerId = $order->getCustomerId();
        //$customer = Mage::getmodel('customer/customer')->load($customerId);
        /* @var $customer Mage_Customer_Model_Customer */
        if($this->_hasCustomerDataSynced) {
            return; // Don't bother if nothing has updated.
        }
        
        //$storeId = $customer->getStoreId(); // Is this always correct??
        $storeId = $order->getStoreId();
        
		if($customerId) {
            self::setCustomerForDataSync($customerId, $storeId);
            $this->_hasCustomerDataSynced = TRUE;
        }
	}
    
    /**
     * Attach to customer_save_after even
     * 
     * Track if we've synced this run, only do it ocne.
     * This event can be triggers 3+ times per run as the customer
     * model is saved! we only want one Sync though.
     * 
     * @todo    refactor
     * @see     customer_save_after
     */
	public function prepareCustomerForDataSync($observer)
	{        
        if(Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
            Mage::log("TRIGGERED prepareCustomerForDataSync");
        }
        
		$customer = $observer->getEvent()->getCustomer();
        /* @var $customer Mage_Customer_Model_Customer */
        if( ! $customer->hasDataChanges() || $this->_hasCustomerDataSynced) {
            return; // Don't bother if nothing has updated.
        }
		$customerId = $customer->getId();
        $storeId = $customer->getStoreId(); // Is this always correct??
        /**
         * Possibly getting issues here with store id not being right...
         * 
         * @todo possible issue
         * 
         * If the customer is saved, how do we know which store to sync with?
         * he could possibly have made sales on multiple websites...
         */
		if($customerId) {
            self::setCustomerForDataSync($customerId, $storeId);
            $this->_hasCustomerDataSynced = TRUE;
        }
	}

    /**
     * Add custom data to sync table
     * 
     * @param   int
     * @param   int
     * @return  boolean
     */
	private static function setCustomerForDataSync($customerId, $storeId = NULL)
	{
        if(Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
            Mage::log("TRIGGERED setCustomerForDataSync [StoreID:{$storeId}]");
        }
        
        if( ! isset($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }
        
		if( ! $customerId) {
            return FALSE;
        }

        $helper = Mage::helper('mailup');
        /* @var $helper SevenLike_Mailup_Helper_Data */
        $config = Mage::getModel('mailup/config');
        /* @var $config SevenLike_Mailup_Model_Config */
        $lists = Mage::getModel('mailup/lists');
        /* @var $lists SevenLike_MailUp_Model_Lists */
        $listID = $config->getMailupListId($storeId);
        $listGuid = $lists->getListGuid($listID, $storeId);
        $job = Mage::getModel('mailup/job');
        /* @var $job SevenLike_MailUp_Model_Job */
        
        /**
         *  Only Sync if they are a subscriber!
         */
        if( ! $helper->isSubscriber($customerId, $storeId)) {
            return;
        }
        
        $job->setData(array(
            "mailupgroupid"     => '',
            "send_optin"        => 0,
            'as_pending'        => 0,
            "status"            => "queued",
            "queue_datetime"    => gmdate("Y-m-d H:i:s"),
            'store_id'          => $storeId,
            'list_id'           => $listID,
            'list_guid'         => $listGuid,
        ));
        $job->setAsAutoSync();
        try {
            $job->save();
            $config->dbLog("Job [Insert] [Group:NO GROUP] ", $job->getId(), $storeId);
        }
        catch(Exception $e) {
            $config->dbLog("Job [Insert] [FAILED] [NO GROUP] ", $job->getId(), $storeId);
            $config->log($e);
            throw $e;
        }
        
		try {
            $jobTask = Mage::getModel('mailup/sync');
            /** @var $jobTask SevenLike_MailUp_Model_Sync */
			$jobTask->setData(array(
                'store_id'      => $storeId,
				"customer_id"   => $customerId,
				"entity"        => "customer",
				"job_id"        => $job->getId(),
				"needs_sync"    => TRUE,
				"last_sync"     => NULL,
			));
            $jobTask->save();
            $config->dbLog("Sync [Insert] [customer] [{$customerId}]", $job->getId(), $storeId);
		} 
        catch(Exception $e) {
            $config->dbLog("Sync [Insert] [customer] [FAILED] [{$customerId}]", $job->getId(), $storeId);
            $config->log($e);
            throw $e;
		}
        
        /**
         * @todo ADD CRON 
         * 
         * WE NEED TO ACTUALLY ADD A CRON JOB NOW!!
         * 
         * OR we use a separate Auto Sync job!!
         */

		return TRUE;
	}
    
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
