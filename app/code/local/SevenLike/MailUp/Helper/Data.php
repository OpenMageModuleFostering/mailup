<?php
/**
 * Data.php
 * 
 * @todo    get rid of these static methods!
 */
class SevenLike_MailUp_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * split customers into batches
     */
    const BATCH_SIZE = 2000;
    
    /**
     * Get the Customer Data
     * 
     * @param   array
     * @return  array
     */
	public static function getCustomersData($customerCollection = null)
	{
        $config = Mage::getModel('mailup/config');
        /* @var $config SevenLike_Mailup_Model_Config */
        
        if ($config->isLogEnabled()) {
            $config->log('Getting customers data');
        }
        
        if(is_array($customerCollection) && empty($customerCollection)) {
            if ($config->isLogEnabled()) {
                $config->log('CustomerCollection is Empty!');
            }
        }
        
		$dateFormat = 'm/d/y h:i:s';
		$lastDateTime = date($dateFormat, Mage::getModel('core/date')->timestamp(time())-7*3600*24);
		$thirtyDaysAgo = date($dateFormat, Mage::getModel('core/date')->timestamp(time())-30*3600*24);
		$twelveMonthsAgo = date($dateFormat, Mage::getModel('core/date')->timestamp(time())-365*3600*24);

		$parseSubscribers = false;
		$toSend = array();
		if ($customerCollection === null) {
            /**
             * @todo    Change to only load form current store/website
             */
			$customerCollection = Mage::getModel('customer/customer')->getCollection();
			$parseSubscribers = true;
            if ($config->isLogEnabled()) {
                $config->log('Parsing Subscribers, NULL collection passed.');
            }
		}
		foreach ($customerCollection as $currentCustomerId) {
			if (is_object($currentCustomerId)) {
				$currentCustomerId = $currentCustomerId->getId();
			}
            
            if( ! $currentCustomerId) {
                if($config->isLogEnabled()) {
                    $config->log('Skipping Empty Customer ID!');
                    continue;
                }
            }

            if($config->isLogEnabled()) {
                $config->log('Customer with id '.$currentCustomerId);
            }
			$customer = Mage::getModel('customer/customer')->load($currentCustomerId);
            /* @var $customer Mage_Customer_Model_Customer */
			$i = $customer->getEmail();

			//recupero gli ordini del cliente corrente
			$allOrdersTotalAmount = 0;
			$allOrdersDateTimes = array();
			$allOrdersTotals = array();
			$allOrdersIds = array();
			$allProductsIds = array();
			$last30daysOrdersAmount = 0;
			$last12monthsOrdersAmount = 0;
			$lastShipmentOrderId = null;
			$lastShipmentOrderDate = null;

            if($config->isLogEnabled()) {
                $config->log('Parsing orders of customer with id '.$currentCustomerId);
            }
			$orders = Mage::getModel('sales/order')
				->getCollection()
				->addAttributeToFilter('customer_id', $currentCustomerId)
            ;
			foreach($orders as $order) {
                if($config->isLogEnabled()) {
                    $config->log("ORDINE IN STATUS: " . $order->getStatus());
                }
				if( ! in_array($order->getStatus(), array("closed", "complete", "processing"))) { 
                    continue;
                }
				$currentOrderTotal = floatval($order->getGrandTotal());
				$allOrdersTotalAmount += $currentOrderTotal;

				$currentOrderCreationDate = $order->getCreatedAt();
				if ($currentOrderCreationDate > $thirtyDaysAgo) {
					$last30daysOrdersAmount += $currentOrderTotal;
				}
				if ($currentOrderCreationDate > $twelveMonthsAgo) {
					$last12monthsOrdersAmount += $currentOrderTotal;
				}

				$currentOrderTotal = self::_formatPrice($currentOrderTotal);
				$currentOrderId = $order->getIncrementId();
				$allOrdersTotals[$currentOrderId] = $currentOrderTotal;
				$allOrdersDateTimes[$currentOrderId] = $currentOrderCreationDate;
				$allOrdersIds[$currentOrderId] = $currentOrderId;

				if ($order->hasShipments() and ($order->getId()>$lastShipmentOrderId)) {
					$lastShipmentOrderId = $order->getId();
					$lastShipmentOrderDate = self::_retriveDateFromDatetime($order->getCreatedAt());
				}

				$items = $order->getAllItems();
				foreach ($items as $item) {
					$allProductsIds[] = $item->getProductId();
				}
			}

			$toSend[$i]['TotaleFatturatoUltimi30gg'] = self::_formatPrice($last30daysOrdersAmount);
			$toSend[$i]['TotaleFatturatoUltimi12Mesi'] = self::_formatPrice($last12monthsOrdersAmount);
			$toSend[$i]['IDTuttiProdottiAcquistati'] = implode(',', $allProductsIds);

			ksort($allOrdersDateTimes);
			ksort($allOrdersTotals);
			ksort($allOrdersIds);

			//recupero i carrelli abbandonati del cliente
            if($config->isLogEnabled()) {
                $config->log('Parsing abandoned carts of customer with id '.$currentCustomerId);
            }
			$cartCollection = Mage::getResourceModel('reports/quote_collection');
            $cartCollection->prepareForAbandonedReport($config->getAllStoreIds());
			$cartCollection->addFieldToFilter('customer_id', $currentCustomerId);
			$cartCollection->load();

			$datetimeCart = null;
			if ( ! empty($cartCollection)) {
                $lastCart = $cartCollection->getLastItem();
				$toSend[$i]['TotaleCarrelloAbbandonato'] = '';
				$toSend[$i]['DataCarrelloAbbandonato'] = '';
				$toSend[$i]['IDCarrelloAbbandonato'] = '';

				if ( ! empty($lastCart)) {
                    if ($config->isLogEnabled()) {
                        $config->log('Customer with id '.$currentCustomerId .' has abandoned cart');
                    }
					$datetimeCart = $lastCart->getUpdatedAt();
					//$toSend[$i]['TotaleCarrelloAbbandonato'] = self::_formatPrice($lastCart->getGrandTotal());
                    $toSend[$i]['TotaleCarrelloAbbandonato'] = self::_formatPrice($lastCart->getSubtotal());
					$toSend[$i]['DataCarrelloAbbandonato'] = self::_retriveDateFromDatetime($datetimeCart);
					$toSend[$i]['IDCarrelloAbbandonato'] = $lastCart->getId();
				}
                else {
                    if ($config->isLogEnabled()) {
                        $config->log('Customer with id '.$currentCustomerId .' has empty LAST CART');
                    }
                }
			}
            else {
                if ($config->isLogEnabled()) {
                    $config->log('Customer id '.$currentCustomerId .' has empty abandoned cart collection');
                }
            }

			$toSend[$i]['IDUltimoOrdineSpedito'] = $lastShipmentOrderId;
			$toSend[$i]['DataUltimoOrdineSpedito'] = $lastShipmentOrderDate;

			$lastOrderDateTime = end($allOrdersDateTimes);

			if ($customer->getUpdatedAt() > $lastDateTime
				|| $lastOrderDateTime > $lastDateTime
				|| ($datetimeCart && $datetimeCart > $lastDateTime))
			{
                if ($config->isLogEnabled()) {
                    $config->log('Adding customer with id '.$currentCustomerId);
                }

				$toSend[$i]['nome'] = $customer->getFirstname();
				$toSend[$i]['cognome'] = $customer->getLastname();
				$toSend[$i]['email'] = $customer->getEmail();
				$toSend[$i]['IDCliente'] = $currentCustomerId;

				$toSend[$i]['registeredDate'] = self::_retriveDateFromDatetime($customer->getCreatedAt());

				//controllo se iscritto o meno alla newsletter
				if (Mage::getModel('newsletter/subscriber')->loadByCustomer($customer)->isSubscribed()) {
					$toSend[$i]['subscribed'] = 'yes';
				} 
                else {
					$toSend[$i]['subscribed'] = 'no';
				}

				//recupero i dati dal default billing address
				$customerAddressId = $customer->getDefaultBilling();
				if ($customerAddressId) {
					$address = Mage::getModel('customer/address')->load($customerAddressId);
					$toSend[$i]['azienda'] = $address->getData('company');
					$toSend[$i]['paese'] = $address->getCountry();
					$toSend[$i]['città'] = $address->getData('city');
					$toSend[$i]['regione'] = $address->getData('region');
					$regionId = $address->getData('region_id');
					$regionModel = Mage::getModel('directory/region')->load($regionId);
					$regionCode = $regionModel->getCode();
					$toSend[$i]['provincia'] = $regionCode;
					$toSend[$i]['cap'] = $address->getData('postcode');
					$toSend[$i]['indirizzo'] = $address->getData('street');
					$toSend[$i]['fax'] = $address->getData('fax');
					$toSend[$i]['telefono'] = $address->getData('telephone');
				}
                else {
                    $toSend[$i]['azienda'] = '';
					$toSend[$i]['paese'] = '';
					$toSend[$i]['città'] = '';
					$toSend[$i]['regione'] = '';
					$toSend[$i]['provincia'] = '';
					$toSend[$i]['cap'] = '';
					$toSend[$i]['indirizzo'] = '';
					$toSend[$i]['fax'] = '';
					$toSend[$i]['telefono'] = '';
                }

				$toSend[$i]['DataUltimoOrdine'] = self::_retriveDateFromDatetime($lastOrderDateTime);
				$toSend[$i]['TotaleUltimoOrdine'] = end($allOrdersTotals);
				$toSend[$i]['IDUltimoOrdine'] = end($allOrdersIds);

				$toSend[$i]['TotaleFatturato'] = self::_formatPrice($allOrdersTotalAmount);

				//ottengo gli id di prodotti e categorie (dell'ultimo ordine)
				$lastOrder = Mage::getModel('sales/order')->loadByIncrementId(end($allOrdersIds));
				$items = $lastOrder->getAllItems();
				$productIds = array();
				$categoryIds = array();
				foreach ($items as $item) {
					$productId = $item->getProductId();
					$productIds[] = $productId;
					$product = Mage::getModel('catalog/product')->load($productId);
					if ($product->getCategoryIds()) {
						$categoryIds[] = implode(',', $product->getCategoryIds());
					}
				}

				$toSend[$i]['IDProdottiUltimoOrdine'] = implode(',', $productIds);
				if ($toSend[$i]['IDProdottiUltimoOrdine']) {
                    $toSend[$i]['IDProdottiUltimoOrdine'] = ",{$toSend[$i]['IDProdottiUltimoOrdine']},";
                }
				$toSend[$i]['IDCategorieUltimoOrdine'] = implode(',', $categoryIds);
				if ($toSend[$i]['IDCategorieUltimoOrdine']) {
                    $toSend[$i]['IDCategorieUltimoOrdine'] = ",{$toSend[$i]['IDCategorieUltimoOrdine']},";
                }
			}
            
            $toSend[$i]['DateOfBirth'] = self::_retriveDobFromDatetime($customer->getDob());
            $toSend[$i]['Gender'] = $customer->getAttribute('gender')->getSource()->getOptionText($customer->getGender());

			//unsetto la variabile
			unset($customer);
		}

		/*
		 *  disabled cause useless in segmentation
		if ($parseSubscribers) {
			if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) Mage::log('Parsing subscribers', 0);
			$subscriberCollection = Mage::getModel('newsletter/subscriber')
				->getCollection()
				->useOnlySubscribed()
				->addFieldToFilter('customer_id', 0);

			foreach ($subscriberCollection as $subscriber) {
				$subscriber = Mage::getModel('newsletter/subscriber')->load($subscriber->getId());
				$i = $subscriber->getEmail();
				if (strlen($i)) continue;
				if (isset($toSend[$i])) continue;
				$toSend[$i]['nome'] = '';
				$toSend[$i]['cognome'] = '';
				$toSend[$i]['email'] = $i;
				$toSend[$i]['subscribed'] = 'yes';
			}
		}
		*/

        if($config->isLogEnabled()) {
            $config->log('End getting customers data');
        }
        
		return $toSend;
	}
    
    /**
     * Send Customer Data
     * 
     * @param   array $mailupCustomerIds
     * @param   array
     * @param   int
     * @return  int|FALSE ReturnCode
     */
	public static function generateAndSendCustomers($mailupCustomerIds, $post = null, $storeId = NULL)
	{
        $config = Mage::getModel('mailup/config');
        /* @var $config SevenLike_Mailup_Model_Config */
        
		$wsSend = new MailUpWsSend($storeId);
		require_once dirname(__FILE__) . "/../Model/MailUpWsImport.php";
		$wsImport = new MailUpWsImport($storeId);
		$accessKey = $wsSend->loginFromId();

		if (empty($mailupCustomerIds)) {
            if($config->isLogEnabled($storeId)) {
                $config->log('generateAndSendCustomers [Empty Customer ID Array]');
            }
            return FALSE;
        }

        $jobId = $post['id'];
        $jobModel = Mage::getModel('mailup/job')->load($post['id']);
        /* @var $jobModel SevenLike_Mailup_Model_Job */

		if ($accessKey === false) {
			Mage::throwException('no access key returned');
		}
        
		//$fields = $wsSend->GetFields($accessKey);
		$fields_mapping = $wsImport->getFieldsMapping($storeId); // Pass StoreId

		//definisco il gruppo a cui aggiungere gli iscritti
		$groupId = $post['mailupGroupId'];
		$listGUID = $post['mailupListGUID'];
		$idList = $post['mailupIdList'];

        /**
         * Create a new Mailup Group.
         */
		if ($post['mailupNewGroup'] == 1) {
			$newGroup = array(
				"idList"        => $idList,
				"listGUID"      => $listGUID,
				"newGroupName"  => $post['mailupNewGroupName']
			);
			$groupId = $wsImport->CreaGruppo($newGroup);
		}

        $importProcessData = array(
            "idList"        => $idList,
            "listGUID"      => $listGUID,
            "idGroup"       => $groupId,
            "xmlDoc"        => "",
            "idGroups"      => $groupId,
            "importType"    => 1, 
            "mobileInputType" => 2,
            "asPending"     => $jobModel->getAsPending() ? 1 : 0,
            "ConfirmEmail"  => $jobModel->getSendOptin() ? 1 : 0,
            "asOptOut"      => 0,
            "forceOptIn"    => 0, //1,
            "replaceGroups" => 0,
            "idConfirmNL"   => 0
        );
        
        $xmlData = '';
		$subscribers_counter = 0;
		$totalCustomers = sizeof($mailupCustomerIds);
		foreach ($mailupCustomerIds as $customerId) {
			$subscribers_counter++;
            $xmlData .= self::_getCustomerXml($customerId, $fields_mapping, $storeId);
		}
		/**
         * We have Valid Data to send
         */
		if(strlen($xmlData)) {
			$importProcessData["xmlDoc"] = "<subscribers>$xmlData</subscribers>";
			$xmlData = "";
			$subscribers_counter = 0;
			/*if($config->isLogEnabled($storeId)) {
                Mage::log('ImportProcessData');
                Mage::log($importProcessData, 0);
            }*/
			$processID = $wsImport->newImportProcess($importProcessData);
            /**
             * Failure
             */
            if($processID === FALSE | $processID < 0) {
                if($config->isLogEnabled($storeId)) {
                    $config->dbLog(sprintf('newImportProcess [ERROR] [%d]', $processID), $jobId, $storeId);
                }
                return $processID;
            }
            /**
             * Success
             */
            else {
                $config->dbLog(sprintf("newImportProcess [SUCCESS] [ProcessID: %d]", $processID), $jobId, $storeId);
                $jobModel->setProcessId($processID);
            }
		}
        /**
         * Build Data for StartImportProcesses
         */
        $startImportProcessesData = array(
            'listsIDs'      => $post['mailupIdList'],
            'listsGUIDs'    => $post['mailupListGUID'],
            'groupsIDs'     => $groupId,
            "idList"        => $idList,
            "importType"    => 1, 
            "mobileInputType" => 2,
            "asPending"     => $jobModel->getAsPending() ? 1 : 0,
            "ConfirmEmail"  => $jobModel->getSendOptin() ? 1 : 0,
            "asOptOut"      => 0,
            "forceOptIn"    => 0, //1,
            "replaceGroups" => 0,
            "idConfirmNL"   => 0
        );

		if ($config->isLogEnabled($storeId)) {
            $config->log("mailup: StartImportProcesses (STORE: {$storeId})", $storeId);
            $config->log($startImportProcessesData);
        }
		$startProcessesReturnCode = $wsImport->StartImportProcesses($startImportProcessesData);
        /**
         * Save the Job Model, and update the tries as we've just tried to Start the Process
         */
        $jobModel->incrementTries();
        try {
            $jobModel->save();
        }
        catch(Exception $e) {
            Mage::log($e->getMessage());
        }
		if ($config->isLogEnabled($storeId)) {
            if($startProcessesReturnCode < 0) {
                $config->dbLog(sprintf("StartImportProcesses [ReturnCode] [ERROR] [%d]", $startProcessesReturnCode), $jobId, $storeId);
            } 
            else {
                $config->dbLog(sprintf("StartImportProcesses [ReturnCode] [SUCCESS] [%d]", $startProcessesReturnCode), $jobId, $storeId);
            }
        }
        
		return (int) $startProcessesReturnCode;
	}

    public function newImportProcess()
    {
        
    }
    
    public function startImportProcess()
    {
        
    }
    
    /**
     * Get a single customers XML data.
     * 
     * @param   int
     * @param   array
     * @param   int
     * @return  string
     */
    protected static function _getCustomerXml($customerId, $fields_mapping, $storeId)
    {
        $config = Mage::getModel('mailup/config');
        /* @var $config SevenLike_Mailup_Model_Config */
        $xmlData = '';
        $tmp = array();
        $subscriber = self::getCustomersData(array($customerId));
        
        if(is_array($subscriber) && empty($subscriber)) {
            if($config->isLogEnabled($storeId)) {
                $config->log('getCustomersData [EMPTY]');
            }
        }

        $subscriber = array_values($subscriber);
        $subscriber = $subscriber[0];

        $subscriber["DataCarrelloAbbandonato"] = self::_convertUTCToStoreTimezoneAndFormatForMailup($subscriber["DataCarrelloAbbandonato"]);
        $subscriber["DataUltimoOrdineSpedito"] = self::_convertUTCToStoreTimezoneAndFormatForMailup($subscriber["DataUltimoOrdineSpedito"]);
        $subscriber["registeredDate"] = self::_convertUTCToStoreTimezoneAndFormatForMailup($subscriber["registeredDate"]);
        $subscriber["DataUltimoOrdine"] = self::_convertUTCToStoreTimezoneAndFormatForMailup($subscriber["DataUltimoOrdine"]);

        /**
         * As mobileInputType = 2 we need this format: Prefix="+001" Number="8889624587"
         */
        $xmlData .= '<subscriber email="'.$subscriber['email'].'" Prefix="" Number="" Name="">';

        foreach($subscriber as $k => $v) {
            if ( ! strlen($subscriber[$k])) {
                $subscriber[$k] = ' '; // blank it out in mailup
            } 
            else {
                $subscriber[$k] = str_replace(array("\r\n", "\r", "\n"), " ", $v);
            }
        }
        
        /**
         * Map from Customer Data to Mailup Fields.
         * 
         * @todo Need to map Gener field
         */
        $mappings = array(
            'Name'                          => 'nome',
            'Last'                          => 'cognome',
            "Company"                       => 'azienda',
            "City"                          => 'città',
            "Province"                      => 'provincia',
            "ZIP"                           => 'cap',
            "Region"                        => 'regione',
            "Country"                       => 'paese',
            "Address"                       => 'indirizzo',
            "Fax"                           => 'fax',
            "Phone"                         => 'telefono',
            "CustomerID"                    => 'IDCliente',
            "LatestOrderID"                 => 'IDUltimoOrdine',
            "LatestOrderDate"               => 'DataUltimoOrdine',
            "LatestOrderAmount"             => 'TotaleUltimoOrdine',
            "LatestOrderProductIDs"         => 'IDProdottiUltimoOrdine',
            "LatestOrderCategoryIDs"        => 'IDCategorieUltimoOrdine',
            "LatestShippedOrderDate"        => 'DataUltimoOrdineSpedito',
            "LatestShippedOrderID"          => 'IDUltimoOrdineSpedito',
            "LatestAbandonedCartDate"       => 'DataCarrelloAbbandonato',
            "LatestAbandonedCartTotal"      => 'TotaleCarrelloAbbandonato',
            "LatestAbandonedCartID"         => 'IDCarrelloAbbandonato',
            "TotalOrdered"                  => 'TotaleFatturato',
            "TotalOrderedLast12m"           => 'TotaleFatturatoUltimi12Mesi',
            "TotalOrderedLast30d"           => 'TotaleFatturatoUltimi30gg',
            "AllOrderedProductIDs"          => 'IDTuttiProdottiAcquistati',
            'DateOfBirth'                   => 'DateOfBirth',
            'Gender'                        => 'Gender',
        );
        
        foreach($mappings as $mapTo => $mapFrom) {
            if(isset($fields_mapping[$mapTo]) && ! empty($fields_mapping[$mapTo])) {
                $tmp[$fields_mapping[$mapTo]] = '<campo'.$fields_mapping[$mapTo].'>'. "<![CDATA[". $subscriber[$mapFrom] ."]]>". '</campo'.$fields_mapping[$mapTo].'>';
            }
            elseif( ! empty($fields_mapping[$mapTo])) {
                $tmp[$fields_mapping[$mapTo]] = '<campo'.$fields_mapping[$mapTo].'>'. '"  "'. '</campo'.$fields_mapping[$mapTo].'>';
            }
            //else {}
        }

        $last_field = max(array_keys($tmp));
        
        for($i=1; $i < $last_field; $i++) {
            if( ! isset($tmp[$i]) && ! empty($i)) {
                /**
                 * If we leave a space it will blank the value out in mail up.
                 * if we leave it empty, it will leave the old value alone!
                 */
                $tmp[$i] = "<campo{$i}>" ." ". "</campo{$i}>"; 
            }
        }
        
        ksort($tmp);
        $tmp = implode("", $tmp);
        /**  All field values are handled as strings, character '|' (pipe) is not allowed and may lead to "-402" error codes **/
        //$tmp = str_replace('|', '', $tmp);
        $xmlData .= $tmp;
        $xmlData .= "</subscriber>\n";

        /**
         * @todo REMOVE
         */
        $config->log($xmlData); 
                
        return $xmlData;
    }
    
    /**
     * Run a particular job
     * 
     * @param int
     */
    public function runJob($jobId)
    {
        $config = Mage::getModel('mailup/config');
        /* @var $config SevenLike_Mailup_Model_Config */
        require_once dirname(__FILE__) . '/../Helper/Data.php';
        $db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $syncTableName = Mage::getSingleton('core/resource')->getTableName('mailup/sync');
        $jobsTableName = Mage::getSingleton('core/resource')->getTableName('mailup/job');
        $lastsync = gmdate("Y-m-d H:i:s");
        // reading customers (jobid == 0, their updates)
        $customer_entity_table_name = Mage::getSingleton('core/resource')->getTableName('customer_entity');
        $jobModel = Mage::getModel('mailup/job')->load($jobId);
        /* @var $jobModel SevenLike_MailUp_Model_Job */
        
        if( ! $jobModel) {
            throw new Mage_Exception('No Job Exists: ' . $jobId);
        }
        
        $job = $jobModel->getData();
        $stmt = $db_write->query(
            "UPDATE {$jobsTableName} 
            SET status='started', start_datetime='" . gmdate("Y-m-d H:i:s") . "' 
            WHERE id={$job["id"]}"
        );
        $storeId = isset($job['store_id']) ? $job['store_id'] : NULL; 
        //$storeId = Mage::app()->getDefaultStoreView()->getStoreId(); // Fallback incase not set?!?
        $customers = array();
        $job['mailupNewGroup'] = 0;
        $job['mailupIdList'] = Mage::getStoreConfig('mailup_newsletter/mailup/list', $storeId);
        $job["mailupGroupId"] = $job["mailupgroupid"];
        $job["send_optin_email_to_new_subscribers"] = $job["send_optin"];

        $tmp = new SevenLike_MailUp_Model_Lists;
        $tmp = $tmp->toOptionArray($storeId); // pass store id!
        foreach ($tmp as $t) {
            if ($t["value"] == $job['mailupIdList']) {
                $job['mailupListGUID'] = $t["guid"];
                $job["groups"] = $t["groups"];
                break;
            }
        }
        unset($tmp); 
        unset($t);
        $stmt = $db_read->query("
            SELECT ms.*, ce.email 
            FROM {$syncTableName} ms 
            JOIN $customer_entity_table_name ce 
                ON (ms.customer_id = ce.entity_id) 
            WHERE ms.needs_sync=1 
            AND ms.entity='customer' 
            AND job_id={$job["id"]}"
        );
        while ($row = $stmt->fetch()) {
            $customers[] = $row["customer_id"];
        }
        /**
         * Send the Data!
         */
        $returnCode = SevenLike_MailUp_Helper_Data::generateAndSendCustomers($customers, $job, $storeId);
        /**
         * Check return OK
         */
        if($returnCode === 0) {
            $customerCount = count($customers);
            $db_write->query("
                UPDATE {$syncTableName} SET needs_sync=0, last_sync='$lastsync' 
                WHERE job_id = {$job["id"]} 
                AND entity='customer'"
            );
            $config->dbLog("Job Task [update] [Synced] [customer count:{$customerCount}]", $job["id"], $storeId);
            // finishing the job also
            $db_write->query("
                UPDATE {$jobsTableName} SET status='finished', finish_datetime='" . gmdate("Y-m-d H:i:s") . "' 
                WHERE id={$job["id"]}"
            );
            $config->dbLog("Jobs [Update] [Complete] [{$job["id"]}]", $job["id"], $storeId);
        }
        /**
         * Only successfull if we get 0 back. False is also a fail.
         */
        else {
            $stmt = $db_write->query(
                "UPDATE {$jobsTableName} SET status='queued' WHERE id={$job["id"]}"
            );
            if($config->isLogEnabled()) {
                $config->dbLog(sprintf("generateAndSendCustomers [ReturnCode] [ERROR] [%d]", $returnCode), $job["id"], $storeId);
            }
        }
    }
   
    /**
     * Get sub Categories of a Category
     * 
     * @param   int
     * @return  array|string
     */
    public function getSubCategories($categoryId)
    {
        // Not sure what version this was introduced.
        $parent = Mage::getModel('catalog/category')->load($categoryId);
        $children = $parent->getAllChildren(TRUE);
        
        if( ! empty($children) && is_array($children)) {
            return $children;
        }
        
        return array();
        
//        // Maybe fall back to this in older versions?
//        $ids = array();
//        $children = Mage::getModel('catalog/category')->getCategories($categoryId);
//        foreach ($children as $category) {
//            /* @var $category Mage_Catalog_Model_Category */
//            $ids[] = $category->getId();
//        }
//        
//        return $ids;
    }
    
    /**
     * Format the Price
     * 
     * @param   float
     * @return  string
     */
	private static function _formatPrice($price) 
    {
		return number_format($price, 2, ',', '');
	}

    /**
     * Get Date from DateTime
     * 
     * @param type $datetime
     * @return string
     */
	private static function _retriveDateFromDatetime($datetime) 
    {
		if (empty($datetime)) return "";
		return date("Y-m-d H:i:s", strtotime($datetime));
	}
    
    /**
     * Get DOB Format from DateTime
     * 
     * @param   string $datetime
     * @return  string
     */
	private static function _retriveDobFromDatetime($datetime) 
    {
		if (empty($datetime)) {
            return "";
        }
		return date("d/m/Y", strtotime($datetime));
	}

	public static function _convertUTCToStoreTimezone($datetime)
	{
		if (empty($datetime)) return "";

		$TIMEZONE_STORE = new DateTimeZone(Mage::getStoreConfig("general/locale/timezone"));
		$TIMEZONE_UTC = new DateTimeZone("UTC");

		$datetime = new DateTime($datetime, $TIMEZONE_UTC);
		$datetime->setTimezone($TIMEZONE_STORE);
		$datetime = (string)$datetime->format("Y-m-d H:i:s");

		return $datetime;
	}

	public static function _convertUTCToStoreTimezoneAndFormatForMailup($datetime)
	{
		if (empty($datetime)) return "";
		$datetime = self::_convertUTCToStoreTimezone($datetime);
		return date("d/m/Y", strtotime($datetime));
	}
    
    /**
     * Clean the Resource Table
     * 
     * @return  void
     */
    public function cleanResourceTable()
    {
        $sql = "DELETE FROM `core_resource` WHERE `code` = 'mailup_setup';";
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');     
        try {
            $connection->query($sql);
            die('deleted module in core_resource!');
        } 
        catch(Exception $e){
            Mage::log($e->getMessage());
        }
    }
        
    /**
     * Clean the Resource Table
     * 
     * @return  void
     */
    public function showResourceTable()
    {
        $sql = "SELECT * FROM `core_resource`";
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');     
        try {
            $result = $connection->fetchAll($sql);
            foreach($result as $row) {
                echo $row['code'] . "<br />";
            }
        } 
        catch(Exception $e){
            echo $e->getMessage();
        }
    }
    
    /**
     * Get all product attributes
     * 
     * Note if we don't use a keyed array below the first item with key 0
     * gets replaced by an empty option by magento. this results in a missing attribute
     * from the list!
     * 
     * @reutrn  array
     */
    public function getAllProductAttributes()
    {
        //$attributes = Mage::getModel('catalog/product')->getAttributes();
        $attributes = Mage::getSingleton('eav/config')
            ->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getAttributeCollection()
        ;
        // Localize attribute label (if you need it)
        $attributes->addStoreLabel(Mage::app()->getStore()->getId());
        $attributeArray = array();
        foreach($attributes as $att) {
            /* @var $att Mage_Catalog_Model_Resource_Eav_Attribute */
            if($att->getIsVisible()) {
                $attributeArray[$att->getAttributeCode()] = array(
                    'value' => $att->getAttributeCode(),
                    'label' => $att->getStoreLabel() ? $att->getStoreLabel() : $att->getFrontendLabel()
                );
            }
        }
        return $attributeArray; 
    }
    
    /**
     * Get all product attributes
     * 
     * Note if we don't use a keyed array below the first item with key 0
     * gets replaced by an empty option by magento. this results in a missing attribute
     * from the list!
     * 
     * @reutrn  array
     */
    public function getAllCustomerAttributes()
    {
        //$attributes = Mage::getModel('catalog/product')->getAttributes();
        $attributes = Mage::getSingleton('eav/config')
            ->getEntityType('customer')->getAttributeCollection()
        ;
        // Localize attribute label (if you need it)
        $attributes->addStoreLabel(Mage::app()->getStore()->getId());
        $attributeArray = array();
        foreach($attributes as $att) {
            /* @var $att Mage_Catalog_Model_Resource_Eav_Attribute */
            if($att->getIsVisible()) {
                $attributeArray[$att->getAttributeCode()] = array(
                    'value' => $att->getAttributeCode(),
                    'label' => $att->getStoreLabel() ? $att->getStoreLabel() : $att->getFrontendLabel()
                );
            }
        }
        return $attributeArray; 
    }
    
    /**
     * Is Someone a subscriber?
     * 
     * @param   int
     * @param   int
     * @return  bool
     */
    public function isSubscriber($customerId, $storeId)
    {
        $customerId = (int) $customerId;
        $storeId = (int) $storeId;
        $table = Mage::getSingleton('core/resource')->getTableName('newsletter_subscriber');
        $connection = Mage::getSingleton('core/resource')->getConnection('core_read'); 
        //$sql = "SELECT * FROM {$table} WHERE customer_id = '{$customerId}' AND store_id = '{$storeId}'";
        // Issue with magento, it seems to only subscribe on a websote leve, not store level!
        $sql = "SELECT * FROM {$table} WHERE customer_id = '{$customerId}'";
        try {
            $result = $connection->fetchAll($sql); // array
            if(count($result) == 0) {
                return FALSE;
            }
            $result = $result[0];
            
            return $result['subscriber_status'] == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED;
        } 
        catch(Exception $e){
            Mage::log($e->getMessage());
        }
        
        return FALSE;
    }
    
    /**
     * Schedule a Task
     * 
     * @param   string
     * @param   string
     */
    public function scheduleTask($when, $type = 'sevenlike_mailup')
    {
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $write->insert(Mage::getSingleton('core/resource')->getTableName('cron_schedule'), array(
            "job_code"      => $type,
            "status"        => "pending",
            "created_at"    => gmdate("Y-m-d H:i:s"),
            "scheduled_at"  => $when
        ));
        
        /*$schedule = Mage::getModel('cron/schedule');
        $schedule->setJobCode($jobCode)
            ->setCreatedAt($timecreated)
            ->setScheduledAt($timescheduled)
            ->setStatus(Mage_Cron_Model_Schedule::STATUS_PENDING)
            ->save();*/
    }
    
    /**
     * Retrieve Attribute Id Data By Id or Code
     *
     * @param   mixed
     * @param   int
     * @return  int
     */
    public function getAttributeId($id, $entityTypeId = NULL)
    {
        if($entityTypeId == NULL) {
            $entityTypeId = Mage::getModel('catalog/product')->getResource()->getEntityType()->getId();
        }
        
        $installer = new Mage_Catalog_Model_Resource_Eav_Mysql4_Setup('core_setup');
        if ( ! is_numeric($id)) {
            $id = $installer->getAttribute($entityTypeId, $id, 'attribute_id');
        }
        if ( ! is_numeric($id)) {
            //throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Wrong attribute ID.'));
            return FALSE;
        }
        
        return $id;
    }
}