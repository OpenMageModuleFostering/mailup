<?php

class MailUpWsImport
{
	protected $ns = "http://ws.mailupnet.it/";

	//protected $WSDLUrl = "http://g4a0.s03.it/services/WSMailUpImport.asmx?WSDL";
	//protected $headers = array("User" => "a7410", "Password" => "GA6VAN0W");
	protected $rCode;
	private $soapClient;
	private $xmlResponse;
	protected $domResult;

	function __construct() {
		$urlConsole = Mage::getStoreConfig('newsletter/mailup/url_console');
		$WSDLUrl = 'http://'. $urlConsole .'/services/WSMailUpImport.asmx?WSDL';
		$user = Mage::getStoreConfig('newsletter/mailup/username_ws');
		$password = Mage::getStoreConfig('newsletter/mailup/password_ws');
		$headers = array('User' => $user, 'Password' => $password);
		$this->header = new SOAPHeader($this->ns, 'Authentication', $headers);

		try {
			$this->soapClient = new SoapClient($WSDLUrl, array('trace' => 1, 'exceptions' => 1, 'connection_timeout' => 10));
			$this->soapClient->__setSoapHeaders($this->header);
		} catch (Exception $e) {
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper("mailup")->__("Unable to connect to MailUp console"));
		}
	}

	function __destruct() {
		unset($this->soapClient);
	}

	public function getFunctions() {
		print_r($this->soapClient->__getFunctions());
	}

	public function creaGruppo($newGroup) {
		if (!is_object($this->soapClient)) return false;
		try {
			if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log("Mailup: creazione nuovo gruppo");
			if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log($newGroup);
			$this->soapClient->CreateGroup($newGroup);
			$this->printLastRequest();
			$this->printLastResponse();
			return $this->readReturnCode('CreateGroup', 'ReturnCode');
		} catch (SoapFault $soapFault) {
			Mage::log('SOAP error', 0);
			Mage::log($soapFault, 0);
		}
	}

	public function GetNlList() {
		if (!is_object($this->soapClient)) return false;
		try {
			$this->soapClient->GetNlLists();
			$this->printLastRequest();
			$this->printLastResponse();
			$result = $this->soapClient->__getLastResponse();
			if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log($result, 0);
			return $result;
		} catch (SoapFault $soapFault) {
			Mage::log('SOAP error', 0);
			Mage::log($soapFault, 0);
		}
	}

	public function newImportProcess($importProcessData) {
		if (!is_object($this->soapClient)) return false;
		try {
			$this->soapClient->NewImportProcess($importProcessData);
			$returncode = $this->readReturnCode('NewImportProcess', 'ReturnCode');
			if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log("mailup: newImportProcess result: $returncode", 0);
			return $returncode;
		} catch (SoapFault $soapFault) {
			Mage::log('SOAP error', 0);
			Mage::log($soapFault, 0);
			return false;
		}
	}

	public function startProcess($processData) {
		if (!is_object($this->soapClient)) return false;
		try {
			$this->soapClient->StartProcess($processData);
			if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log("mailup: ws: startimportprocess", 0);
			return true;
		} catch (SoapFault $soapFault) {
			Mage::log('SOAP error', 0);
			Mage::log($soapFault, 0);
			return false;
		}
	}

	public function getProcessDetail($processData) {
		if (!is_object($this->soapClient)) return false;
		try {
			if (Mage::getStoreConfig('newsletter/mailup/enable_log')) Mage::log($this->soapClient->GetProcessDetails($processData), 0);
		} catch (SoapFault $soapFault) {
			Mage::log('SOAP error', 0);
			Mage::log($soapFault, 0);
		}
	}

	public function startImportProcesses($processData) {
		if (!is_object($this->soapClient)) return false;
		try {
			$this->soapClient->StartImportProcesses($processData);
			$this->printLastRequest();
			$this->printLastResponse();
			return true;
		} catch (SoapFault $soapFault) {
			Mage::log('SOAP error', 0);
			Mage::log($soapFault, 0);
			return false;
		}
	}

	private function readReturnCode($func, $param) {
		if (!is_object($this->soapClient)) return false;

		static $func_in = ''; //static variable to test xmlResponse update
		if ($func_in != $func) {//(!isset($this->xmlResponse))
			$func_in = $func;
			//prendi l'XML di ritorno se non l'ho già preso
			$this->xmlResponse = $this->soapClient->__getLastResponse();

			$dom = new DomDocument();
			$dom->loadXML($this->xmlResponse) or die('File XML non valido!');
			$xmlResult = $dom->getElementsByTagName($func.'Result');

			$this->domResult = new DomDocument();
			$this->domResult->LoadXML(html_entity_decode($xmlResult->item(0)->nodeValue)) or die('File XML non valido!');
		}
		$rCode = $this->domResult->getElementsByTagName($param);
		return $rCode->item(0)->nodeValue;
	}

	private function printLastRequest()
	{
		return "";
		if (Mage::getStoreConfig('newsletter/mailup/enable_log')) $this->soapClient->__getLastRequest();
	}

	private function printLastResponse()
	{
		return "";
		if (Mage::getStoreConfig('newsletter/mailup/enable_log')) $this->soapClient->__getLastResponse();
	}

	public function getCustomersFiltered($request)
	{
		$TIMEZONE_STORE = new DateTimeZone(Mage::getStoreConfig("general/locale/timezone"));
		$TIMEZONE_UTC = new DateTimeZone("UTC");

		//inizializzo l'array dei clienti
		$customersFiltered = array();

		if (!$request->getRequest()->getParam('mailupCustomerFilteredMod')) {
			//ottengo la collection con tutti i clienti
			$customerCollection = Mage::getModel('customer/customer')
				->getCollection()
				->addAttributeToSelect('entity_id')
				->addAttributeToSelect('group_id')
				->addAttributeToSelect('created_at')
				->getSelect()
				->query();

			while ($row = $customerCollection->fetch()) {
				$customersFiltered[] = $row;
			}

			//se richiesto, seleziono solo quelli iscritti alla newsletter di Magento
			if ($request->getRequest()->getParam('mailupSubscribed') > 0) {
				$tempSubscribed = array();
				foreach ($customersFiltered as $customer) {
					$customerItem = Mage::getModel('customer/customer')->load($customer['entity_id']);
					if (Mage::getModel('newsletter/subscriber')->loadByCustomer($customerItem)->isSubscribed()) {
						$tempSubscribed[] = $customer;
					}
				}
				$customersFiltered = array_intersect($tempSubscribed, $customersFiltered);
			}

			//FILTRO 1 ACQUISTATO: in base al fatto se ha effettuato o meno acquisti: 0 = tutti, 1 = chi ha acquistato, 2 = chi non ha mai acquistato
			$count = 0;
			$result = array();
			$tempPurchased = array();
			$tempNoPurchased = array();

			if ($request->getRequest()->getParam('mailupCustomers') > 0) {
				foreach ($customersFiltered as $customer) {
					$result[] = $customer;

					//filtro gli ordini in base al customer id
					$orders = Mage::getModel('sales/order')->getCollection()->addAttributeToFilter('customer_id', $result[$count]['entity_id']);

					//aggiungo il cliente ad un determinato array in base a se ha ordinato o meno
					if ($orders->getData()) {
						$tempPurchased[] = $result[$count];
					} else {
						$tempNoPurchased[] = $result[$count];
					}

					//unsetto la variabile
					unset($orders); //->unsetData();

					$count++;
				}

				if ($request->getRequest()->getParam('mailupCustomers') == 1) {
					$customersFiltered = array_intersect($tempPurchased, $customersFiltered);
				} elseif ($request->getRequest()->getParam('mailupCustomers') == 2) {
					$customersFiltered = array_intersect($tempNoPurchased, $customersFiltered);
				}
			}
			//FINE FILTRO 1 ACQUISTATO: testato OK

			//FILTRO 2 PRODOTTO ACQUISTATO: in base al fatto se ha acquistato un determinato prodotto
			$count = 0;
			$result = array();
			$tempProduct = array();

			if ($request->getRequest()->getParam('mailupProductSku')) {
				foreach ($customersFiltered as $customer) {
					$result[] = $customer;

					//filtro gli ordini in base al customer id
					$orders = Mage::getModel('sales/order')->getCollection()->addAttributeToFilter('customer_id', $result[$count]['entity_id']);
					$purchasedProduct = 0;

					$mailupProductId = Mage::getModel('catalog/product')->getIdBySku($request->getRequest()->getParam('mailupProductSku'));

					foreach ($orders->getData() as $order) {
						if (!in_array($order["status"], array("closed", "complete", "processing"))) continue;
						$orderIncrementId = $order['increment_id'];

						//carico i dati di ogni ordine
						$orderData = Mage::getModel('sales/order')->loadByIncrementID($orderIncrementId);
						$items = $orderData->getAllItems();
						$ids = array();
						foreach ($items as $itemId => $item) {
							$ids[] = $item->getProductId();
						}

						if (in_array($mailupProductId, $ids)) {
							$purchasedProduct = 1;
						}
					}

					//aggiungo il cliente ad un determinato array in base a se ha ordinato o meno
					if ($purchasedProduct == 1) {
						$tempProduct[] = $result[$count];
					}

					//unsetto la variabile
					unset($orders); //->unsetData();

					$count++;
				}

				$customersFiltered = array_intersect($tempProduct, $customersFiltered);
			}
			//FINE FILTRO 2 PRODOTTO ACQUISTATO: testato OK


			//FILTRO 3 ACQUISTATO IN CATEGORIA: in base al fatto se ha acquistato almeno un prodotto in una determinata categoria
			$count = 0;
			$result = array();
			$tempCategory = array();

			if ($request->getRequest()->getParam('mailupCategoryId') > 0) {
				foreach ($customersFiltered as $customer) {
					$result[] = $customer;

					//filtro gli ordini in base al customer id
					$orders = Mage::getModel('sales/order')->getCollection()->addAttributeToFilter('customer_id', $result[$count]['entity_id']);
					$purchasedCategory = 0;

					foreach ($orders->getData() as $order) {
						if (!in_array($order["status"], array("closed", "complete", "processing"))) continue;
						$orderIncrementId = $order['increment_id'];

						//carico i dati di ogni ordine
						$orderData = Mage::getModel('sales/order')->loadByIncrementID($orderIncrementId);
						$items = $orderData->getAllItems();
						$cat_ids = array();
						foreach ($items as $product) {
							if (in_array($request->getRequest()->getParam('mailupCategoryId'), Mage::getResourceSingleton('catalog/product')->getCategoryIds($product))) {
								$tempCategory[] = $result[$count];
								break 2;
							}
						}
					}

					unset($orders);
					$count++;
				}

				$customersFiltered = array_intersect($tempCategory, $customersFiltered);
			}
			//FINE FILTRO 3 ACQUISTATO IN CATEGORIA: testato ok


			//FILTRO 4 GRUPPO DI CLIENTI
			$count = 0;
			$result = array();
			$tempGroup = array();

			if ($request->getRequest()->getParam('mailupCustomerGroupId') > 0) {
				foreach ($customersFiltered as $customer) {
					if ($customer['group_id'] == $request->getRequest()->getParam('mailupCustomerGroupId')) {
						$tempGroup[] = $customer;
					}
				}

				$customersFiltered = array_intersect($tempGroup, $customersFiltered);
			}
			//FINE FILTRO 4 GRUPPO DI CLIENTI: testato ok


			//FILTRO 5 PAESE DI PROVENIENZA
			$count = 0;
			$result = array();
			$tempCountry = array();

			if ($request->getRequest()->getParam('mailupCountry') != '0') {
				foreach ($customersFiltered as $customer) {
					//ottengo la nazione del primary billing address
					$customerItem = Mage::getModel('customer/customer')->load($customer['entity_id']);
					$customerAddress = $customerItem->getPrimaryBillingAddress();
					$countryId = $customerAddress['country_id'];

					if ($countryId == $request->getRequest()->getParam('mailupCountry')) {
						$tempCountry[] = $customer;
					}

					//unsetto la variabile
					unset($customerItem); //->unsetData();
				}

				$customersFiltered = array_intersect($tempCountry, $customersFiltered);
			}
			//FINE FILTRO 5 PAESE DI PROVENIENZA: testato ok


			//FILTRO 6 CAP DI PROVENIENZA
			$count = 0;
			$result = array();
			$tempPostCode = array();

			if ($request->getRequest()->getParam('mailupPostCode')) {
				foreach ($customersFiltered as $customer) {
					//ottengo la nazione del primary billing address
					$customerItem = Mage::getModel('customer/customer')->load($customer['entity_id']);
					$customerAddress = $customerItem->getPrimaryBillingAddress();
					$postCode = $customerAddress['postcode'];

					if ($postCode == $request->getRequest()->getParam('mailupPostCode')) {
						$tempPostCode[] = $customer;
					}

					//unsetto la variabile
					unset($customerItem); //->unsetData();
				}

				$customersFiltered = array_intersect($tempPostCode, $customersFiltered);
			}
			//FINE FILTRO 6 CAP DI PROVENIENZA: testato ok


			//FILTRO 7 DATA CREAZIONE CLIENTE
			$count = 0;
			$result = array();
			$tempDate = array();

			if ($request->getRequest()->getParam('mailupCustomerStartDate') || $request->getRequest()->getParam('mailupCustomerEndDate') ) {
				foreach ($customersFiltered as $customer) {
					$createdAt = $customer['created_at'];
					$createdAt = new DateTime($createdAt, $TIMEZONE_UTC);
					$createdAt->setTimezone($TIMEZONE_STORE);
					$createdAt = (string)$createdAt->format("Y-m-d H:i:s");
					$filterStart = '';
					$filterEnd = '';

					if ($request->getRequest()->getParam('mailupCustomerStartDate')) {
						$date =  Zend_Locale_Format::getDate($request->getRequest()->getParam('mailupCustomerStartDate'), array('locale'=>Mage::app()->getLocale()->getLocale(), 'date_format'=>Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT), 'fix_date'=>true));
						$date['month'] = str_pad($date['month'], 2, 0, STR_PAD_LEFT);
						$date['day'] = str_pad($date['day'], 2, 0, STR_PAD_LEFT);
						$filterStart = "{$date['year']}-{$date['month']}-{$date['day']} 00:00:00";
					}
					if ($request->getRequest()->getParam('mailupCustomerEndDate')) {
						$date =  Zend_Locale_Format::getDate($request->getRequest()->getParam('mailupCustomerEndDate'), array('locale'=>Mage::app()->getLocale()->getLocale(), 'date_format'=>Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT), 'fix_date'=>true));
						$date['month'] = str_pad($date['month'], 2, 0, STR_PAD_LEFT);
						$date['day'] = str_pad($date['day'], 2, 0, STR_PAD_LEFT);
						$filterEnd = "{$date['year']}-{$date['month']}-{$date['day']} 23:59:59";
					}
					if ($filterStart && $filterEnd) {
						//compreso tra start e end date
						if ($createdAt >= $filterStart and $createdAt <= $filterEnd) {
							$tempDate[] = $customer;
						}
					} elseif ($filterStart) {
						// >= di start date
						if ($createdAt >= $filterStart) {
							$tempDate[] = $customer;
						}
					} else {
						// <= di end date
						if ($createdAt <= $filterEnd) {
							$tempDate[] = $customer;
						}
					}
				}

				$customersFiltered = array_intersect($tempDate, $customersFiltered);
			}
			//FINE FILTRO 7 DATA CREAZIONE CLIENTE: testato ok


			//FILTRO 8 TOTALE ACQUISTATO
			$count = 0;
			$result = array();
			$tempTotal = array();

			if ($request->getRequest()->getParam('mailupTotalAmountValue') > 0) {
				foreach ($customersFiltered as $customer) {
					$result[] = $customer;

					//filtro gli ordini in base al customer id
					$orders = Mage::getModel('sales/order')->getCollection()->addAttributeToFilter('customer_id', $result[$count]['entity_id']);

					$totalOrdered = 0;

					foreach ($orders->getData() as $order) {
						if (!in_array($order["status"], array("closed", "complete", "processing"))) continue;
						$totalOrdered += $order['subtotal'];
					}

					if ($totalOrdered == $request->getRequest()->getParam('mailupTotalAmountValue') && $request->getRequest()->getParam('mailupTotalAmountCond') == "eq") {
						$tempTotal[] = $result[$count];
					}

					if ($totalOrdered > $request->getRequest()->getParam('mailupTotalAmountValue') && $request->getRequest()->getParam('mailupTotalAmountCond') == "gt") {
						$tempTotal[] = $result[$count];
					}

					if ($totalOrdered < $request->getRequest()->getParam('mailupTotalAmountValue') && $request->getRequest()->getParam('mailupTotalAmountCond') == "lt" ) {
						$tempTotal[] = $result[$count];
					}

					$count++;

					//unsetto la variabile
					unset($orders); //->unsetData();
				}

				$customersFiltered = array_intersect($tempTotal, $customersFiltered);
			}
			//FINE FILTRO 8 TOTALE ACQUISTATO: testato ok


			//FILTRO 9 DATA ACQUISTATO
			$count = 0;
			$result = array();
			$tempOrderedDateYes = array();
			$tempOrderedDateNo = array();

			if ($request->getRequest()->getParam('mailupOrderStartDate') || $request->getRequest()->getParam('mailupOrderEndDate') ) {
				foreach ($customersFiltered as $customer) {
					$result[] = $customer;

					//filtro gli ordini in base al customer id
					$orders = Mage::getModel('sales/order')->getCollection()->addAttributeToFilter('customer_id', $result[$count]['entity_id']);

					$orderedDate = 0;

					foreach ($orders->getData() as $order) {
						if (!in_array($order["status"], array("closed", "complete", "processing"))) continue;
						$createdAt = $order['created_at'];
						$createdAt = new DateTime($createdAt, $TIMEZONE_UTC);
						$createdAt->setTimezone($TIMEZONE_STORE);
						$createdAt = (string)$createdAt->format("Y-m-d H:i:s");
						$filterStart = '';
						$filterEnd = '';

						if ($request->getRequest()->getParam('mailupOrderStartDate')) {
							$date =  Zend_Locale_Format::getDate($request->getRequest()->getParam('mailupOrderStartDate'), array('locale'=>Mage::app()->getLocale()->getLocale(), 'date_format'=>Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT), 'fix_date'=>true));
							$date['month'] = str_pad($date['month'], 2, 0, STR_PAD_LEFT);
							$date['day'] = str_pad($date['day'], 2, 0, STR_PAD_LEFT);
							$filterStart = "{$date['year']}-{$date['month']}-{$date['day']} 00:00:00";
						}
						if ($request->getRequest()->getParam('mailupOrderEndDate')) {
							$date =  Zend_Locale_Format::getDate($request->getRequest()->getParam('mailupOrderEndDate'), array('locale'=>Mage::app()->getLocale()->getLocale(), 'date_format'=>Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT), 'fix_date'=>true));
							$date['month'] = str_pad($date['month'], 2, 0, STR_PAD_LEFT);
							$date['day'] = str_pad($date['day'], 2, 0, STR_PAD_LEFT);
							$filterEnd = "{$date['year']}-{$date['month']}-{$date['day']} 23:59:59";
						}

						if ($filterStart and $filterEnd) {
							//compreso tra start e end date
							if ($createdAt >= $filterStart and $createdAt <= $filterEnd) {
								$orderedDate = 1;
							}
						} elseif ($filterStart) {
							// >= di start date
							if ($createdAt >= $filterStart) {
								$orderedDate = 1;
							}
						} else {
							// <= di end date
							if ($createdAt <= $filterEnd) {
								$orderedDate = 1;
							}
						}

						//unsetto la variabile
						unset($orders); //->unsetData();
					}

					if ($orderedDate == 1) {
						$tempOrderedDateYes[]  = $result[$count];
					} else {
						$tempOrderedDateNo[]  = $result[$count];
					}

					$count++;
				}

				if ($request->getRequest()->getParam('mailupOrderYesNo') == 'yes') {
					$customersFiltered = array_intersect($tempOrderedDateYes, $customersFiltered);
				} else {
					$customersFiltered = array_intersect($tempOrderedDateNo, $customersFiltered);
				}
			}
			//FINE FILTRO 9 DATA ACQUISTATO: testato ok

		} else {
			//GESTISCO LE MODIFICHE MANUALI
			$count = 0;
			$result = array();
			$tempMod = array();

			$emails = explode("\n", $request->getRequest()->getParam('mailupCustomerFilteredMod'));

			foreach ($emails as $email) {
				$email = trim($email);

				if (strstr($email, '@') !== false) {
					$customerModCollection = Mage::getModel('customer/customer')
						->getCollection()
						->addAttributeToSelect('email')
						->addAttributeToFilter('email', $email);

					$added = 0;

					foreach ($customerModCollection as $customerMod) {
						$tempMod[] = $customerMod->toArray();
						$added = 1;
					}

					if ($added == 0) {
						$tempMod[] = array('entity_id'=>0, 'firstname'=>'', 'lastname'=>'', 'email'=>$email);
					}
				}
			}

			//$customersFiltered = array_intersect($tempMod, $customersFiltered);
			$customersFiltered = $tempMod;
		}
		//FINE GESTISCO LE MODIFICHE MANUALI

		return $customersFiltered;
	}


	public function getFilterHints() {
		$filter_hints = array();
		try {
			// fetch write database connection that is used in Mage_Core module
			$connectionRead = Mage::getSingleton('core/resource')->getConnection('core_read');

			// now $write is an instance of Zend_Db_Adapter_Abstract
			$result = $connectionRead->query("select * from mailup_filter_hints");

			while ($row = $result->fetch()) {
				array_push($filter_hints, array('filter_name' => $row['filter_name'], 'hints' => $row['hints']));
			}
		} catch (Exception $e) {
			Mage::log('Exception: '.$e->getMessage(), 0);
			die($e);
		}

		return $filter_hints;
	}

	public function saveFilterHint($filter_name, $post) {
		try {
			$hints = '';
			foreach ($post as $k => $v) {
				if ($v!='' && $k!='form_key') {
					if ($hints!='') {
						$hints .= '|';
					}
					$hints .= $k.'='.$v;
				}
			}
			//(e.g. $hints = 'mailupCustomers=2|mailupSubscribed=1';)

			$connectionWrite = Mage::getSingleton('core/resource')->getConnection('core_write');

			$connectionWrite->query("INSERT INTO mailup_filter_hints (filter_name, hints) VALUES ('".$filter_name."', '".$hints."')");
		} catch (Exception $e) {
			Mage::log('Exception: '.$e->getMessage(), 0);
			die($e);
		}
	}

	public function deleteFilterHint($filter_name) {
		try {
			$connectionWrite = Mage::getSingleton('core/resource')->getConnection('core_write');

			$connectionWrite->query("DELETE FROM mailup_filter_hints WHERE filter_name LIKE '".$filter_name."'");
		} catch (Exception $e) {
			Mage::log('Exception: '.$e->getMessage(), 0);
			die($e);
		}
	}

	public function getFieldsMapping() {
		$fieldsMappings = array();
		try {
			$connectionRead = Mage::getSingleton('core/resource')->getConnection('core_read');
			return $connectionRead->fetchPairs("select magento_field_name, mailup_field_id from mailup_fields_mapping");
		} catch (Exception $e) {
			Mage::log('Exception: '.$e->getMessage(), 0);
			die($e);
		}

		return $fieldsMappings;
	}

	public function saveFieldMapping($post) {
		try {
			$connectionWrite = Mage::getSingleton('core/resource')->getConnection('core_write');
			$connectionWrite->query("DELETE FROM mailup_fields_mapping");
			foreach ($post as $k => $v) {
				if (strlen($v) == 0) continue;
				$connectionWrite->insert("mailup_fields_mapping", array(
					"magento_field_name" => $k,
					"mailup_field_id" => $v
				));
			}
		} catch (Exception $e) {
			Mage::log('Exception: '.$e->getMessage(), 0);
			die($e);
		}
	}
}