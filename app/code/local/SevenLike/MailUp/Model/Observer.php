<?php

require_once dirname(__FILE__) . "/MailUpWsImport.php";
require_once dirname(__FILE__) . "/Wssend.php";
class SevenLike_MailUp_Model_Observer
{
	const CRON_STRING_PATH  = 'crontab/jobs/sevenlike_mailup/schedule/cron_expr';

    /**
     * Save system config event
     *
     * @param Varien_Object $observer
     */
    public function saveSystemConfig($observer)
    {
        $store = $observer->getStore();
        $website = $observer->getWebsite();

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
	            return "0 0,6,12,18 * * * *";
            case SevenLike_MailUp_Model_Adminhtml_System_Source_Cron_Frequency::EVERY_12_HOURS:
	            return "0 0,12 * * *";
	        case SevenLike_MailUp_Model_Adminhtml_System_Source_Cron_Frequency::HOURLY:
		    default:
		        return "0 0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23 * * *";
        }
    }
	
	public function leggiUtente($observer)
	{
		$model = $observer->getEvent()->getModel();
		if (empty($model)) $model = $model = $observer->getEvent()->getDataObject();
		if (isset($GLOBALS["__sl_mailup_leggi_utente"])) return $this;
		$GLOBALS["__sl_mailup_leggi_utente"] = true;

		try {
			$WSDLUrl = 'http://services.mailupnet.it/MailupReport.asmx?WSDL';
			$user = Mage::getStoreConfig('newsletter/mailup/username_ws');
			$password = Mage::getStoreConfig('newsletter/mailup/password_ws');
			$headers = array('User' => $user, 'Password' => $password);
			$header = new SOAPHeader("http://ws.mailupnet.it/", 'Authentication', $headers);
			$soapclient = new SoapClient($WSDLUrl, array('trace' => 1, 'exceptions' => 1, 'connection_timeout' => 10));
			$soapclient->__setSoapHeaders($header);

			$loginData = array ('user' => Mage::getStoreConfig('newsletter/mailup/username_ws'),
				'pwd' => Mage::getStoreConfig('newsletter/mailup/password_ws'),
				'consoleId' => substr(Mage::getStoreConfig('newsletter/mailup/username_ws'), 1));
			$result = get_object_vars($soapclient->LoginFromId($loginData));
			$xml = simplexml_load_string($result['LoginFromIdResult']);
			$errorCode = (string)$xml->errorCode;
			$errorDescription = (string)$xml->errorDescription;
			$accessKey = (string)$xml->accessKey;

			$result = $soapclient->ReportByUser(array(
				"accessKey" => $accessKey,
				"email" => $model->getEmail(),
				"listID" => Mage::getStoreConfig('newsletter/mailup/list'),
				"messageID" => 0
			));
			$result = get_object_vars($result);
			$xml = simplexml_load_string($result['ReportByUserResult']);

			$stato_registrazione = (string)$xml->Canali->Email;
			if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log("stato registrazione: " . $stato_registrazione);
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
	
	public function inviaUtente($observer)
	{
		if (isset($GLOBALS["__sl_mailup_invia_utente"])) return $this;
		$GLOBALS["__sl_mailup_invia_utente"] = true;
		
		$model = $observer->getEvent()->getDataObject();
        if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log($model->getData());
		$status = Mage::getModel('newsletter/subscriber')->loadByEmail($model->getEmail())->getStatus();
		
		$module = Mage::app()->getRequest()->getModuleName();
		$controller = Mage::app()->getRequest()->getControllerName();
		$action = Mage::app()->getRequest()->getActionName();

        if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log("mailup: invia utente");
		
		if (($module == "customer" and $controller == "account" and $action == "createpost") or ($module == "checkout" and $controller == "onepage" and $action == "saveOrder")) {
            if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log("SONO in registrazione, LEGGO PRIMA mailup!");
			//sono in registrazione, controllo lo stato di subscribe magento, se non risulto iscritto leggo lo status da mailup e se sono iscritto lo salvo su magento prima di continuare
			if (!$status) {
				//leggo l'utente da mailup
				$this->leggiUtente($observer);
				//rileggo lo status perché potrebbe essere stato modificato dalla precedente chiamata
				$status = Mage::getModel('newsletter/subscriber')->loadByEmail($model->getEmail())->getStatus();
				// se non sono iscritto nemmeno lato mailup allora posso evitare di andare oltre
				if (!$status) return $this;
			}
		}
		
		$console = Mage::getStoreConfig('newsletter/mailup/url_console');
		$listId = Mage::getStoreConfig('newsletter/mailup/list');

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
			if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log("STATO ISCRIZIONE: $status");
			if ($status == 1) {
				$ws  = "http://{$console}/frontend/Xmlsubscribe.aspx";
			} else {
				$ws  = "http://{$console}/frontend/Xmlunsubscribe.aspx";
			}

			$ws .= "?ListGuid=" . rawurlencode($listGUID);
			$ws .= "&List=" . rawurlencode($listId);
			$ws .= "&Email=" . rawurlencode($model->getEmail());

			try {
				if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log("mailup invio utente $ws");
				$result = @file_get_contents($ws);
				if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log("mailup risultato invio $result");
			} catch (Exception $e) {}
		} catch (Exception $e) {
			Mage::logException($e);
		}
		
		return $this;
	}

	public function configCheck()
	{
		$url_console = Mage::getStoreConfig('newsletter/mailup/url_console');
		$user = Mage::getStoreConfig('newsletter/mailup/username_ws');
		$password = Mage::getStoreConfig('newsletter/mailup/password_ws');
		$list = Mage::getStoreConfig('newsletter/mailup/list');

		if (!strlen($url_console) or !strlen($user) or !strlen($password) or !strlen($list)) {
			$url = Mage::getModel('adminhtml/url');
			$url = $url->getUrl("adminhtml/system_config/edit", array(
				"section" => "newsletter"
			));
			$message = Mage::helper("mailup")->__('MailUp configuration is not complete');
			$message = str_replace("href=''", "href='$url'", $message);
			Mage::getSingleton('adminhtml/session')->addWarning($message);
			return;
		}

		$wsimport = new MailUpWsImport();
		$mapping = $wsimport->getFieldsMapping();
		if (empty($mapping)) {
			$url = Mage::getModel('adminhtml/url');
			$url = $url->getUrl("mailup/adminhtml_fieldsmapping");
			$message = Mage::helper("mailup")->__('MailUp fields mapping is not complete');
			$message = str_replace("href=''", "href='$url'", $message);
			Mage::getSingleton('adminhtml/session')->addWarning($message);
			return;
		}
	}

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

	public function prepareOrderForDataSync($observer)
	{
		$order = $observer->getEvent()->getOrder();
		$customer_id = $order->getCustomerId();
		if ($customer_id) self::setCustomerForDataSync($customer_id);
	}

	public function prepareCustomerForDataSync($observer)
	{
		$customer = $observer->getEvent()->getCustomer();
		$customer_id = $customer->getId();
		if ($customer_id) self::setCustomerForDataSync($customer_id);
	}

	private static function setCustomerForDataSync($customer_id)
	{
		if (!$customer_id) return false;

		$db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
		try {
			$db_write->insert("mailup_sync", array(
				"customer_id" => $customer_id,
				"entity" => "customer",
				"job_id" => 0,
				"needs_sync" => true,
				"last_sync" => null
			));
		} catch (Exception $e) {
			$db_write->update("mailup_sync", array(
				"needs_sync" => true
			), "customer_id=$customer_id AND entity='customer' AND job_id=0");
		}

		return true;
	}
}
