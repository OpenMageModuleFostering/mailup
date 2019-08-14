<?php

class SevenLike_MailUp_Helper_Data extends Mage_Core_Helper_Abstract
{
	public static function getCustomersData($customerCollection = null)
	{
        $config = Mage::getModel('mailup/config');
        /* @var $config SevenLike_Mailup_Model_Config */
        
        if ($config->isLogEnabled()) {
            Mage::log('Getting customers data', 0);
        }
        
        if(is_array($customerCollection) && empty($customerCollection)) {
            if ($config->isLogEnabled()) {
                Mage::log('CustomerCollection is Empty!');
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
                Mage::log('Parsing Subscribers, NULL collection passed.');
            }
		}
		foreach ($customerCollection as $currentCustomerId) {
			if (is_object($currentCustomerId)) {
				$currentCustomerId = $currentCustomerId->getId();
			}
            
            if( ! $currentCustomerId) {
                if($config->isLogEnabled()) {
                    Mage::log('Skipping Empty Customer ID!');
                    continue;
                }
            }

            if($config->isLogEnabled()) {
                Mage::log('Customer with id '.$currentCustomerId, 0);
            }
			$customer = Mage::getModel('customer/customer')->load($currentCustomerId);
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
                Mage::log('Parsing orders of customer with id '.$currentCustomerId, 0);
            }
			$orders = Mage::getModel('sales/order')
				->getCollection()
				->addAttributeToFilter('customer_id', $currentCustomerId)
            ;
			foreach($orders as $order) {
                if($config->isLogEnabled()) {
                    Mage::log("ORDINE IN STATUS: " . $order->getStatus());
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
                Mage::log('Parsing abandoned carts of customer with id '.$currentCustomerId, 0);
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
                    if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
                        Mage::log('Customer with id '.$currentCustomerId .' has abandoned cart', 0);
                    }
					$datetimeCart = $lastCart->getUpdatedAt();
					//$toSend[$i]['TotaleCarrelloAbbandonato'] = self::_formatPrice($lastCart->getGrandTotal());
                    $toSend[$i]['TotaleCarrelloAbbandonato'] = self::_formatPrice($lastCart->getSubtotal());
					$toSend[$i]['DataCarrelloAbbandonato'] = self::_retriveDateFromDatetime($datetimeCart);
					$toSend[$i]['IDCarrelloAbbandonato'] = $lastCart->getId();
				}
                else {
                    if ($config->isLogEnabled()) {
                        Mage::log('Customer with id '.$currentCustomerId .' has empty LAST CART', 0);
                    }
                }
			}
            else {
                if ($config->isLogEnabled()) {
                    Mage::log('Customer id '.$currentCustomerId .' has empty abandoned cart collection', 0);
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
                    Mage::log('Adding customer with id '.$currentCustomerId, 0);
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
				if ($toSend[$i]['IDProdottiUltimoOrdine']) $toSend[$i]['IDProdottiUltimoOrdine'] = ",{$toSend[$i]['IDProdottiUltimoOrdine']},";
				$toSend[$i]['IDCategorieUltimoOrdine'] = implode(',', $categoryIds);
				if ($toSend[$i]['IDCategorieUltimoOrdine']) $toSend[$i]['IDCategorieUltimoOrdine'] = ",{$toSend[$i]['IDCategorieUltimoOrdine']},";
			}

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
            Mage::log('End getting customers data', 0);
        }
        
		return $toSend;
	}
    
    /**
     * Sebd Customer Data
     * 
     * @param   type $mailupCustomerIds
     * @param   type $post
     * @param   type $newsletter_subscribers
     * @param   int
     * @return  boolean
     */
	public static function generateAndSendCustomers($mailupCustomerIds, $post = null, $newsletter_subscribers = null, $storeId = NULL)
	{
        $config = Mage::getModel('mailup/config');
        /* @var $config SevenLike_Mailup_Model_Config */
        
		$wsSend = new MailUpWsSend($storeId);
		require_once dirname(__FILE__) . "/../Model/MailUpWsImport.php";
		$wsImport = new MailUpWsImport($storeId);
		$accessKey = $wsSend->loginFromId();

		if (empty($mailupCustomerIds)) {
            if($config->isLogEnabled($storeId)) {
                Mage::log('Empty Customer ID Array');
            }
            return false;
        }
        /**
         * Post EMPTY
         */
		if ($post === NULL) {
			// chiamata da cron, popolo con i dati del gruppo "magento" di default
			$post['mailupNewGroup'] = 0;
			$post['mailupIdList'] = Mage::getStoreConfig('mailup_newsletter/mailup/list', $storeId);

			$tmp = new SevenLike_MailUp_Model_Lists;
			$tmp = $tmp->toOptionArray($storeId); // pass store id!
            
			foreach ($tmp as $t) {
				if ($t["value"] == $post['mailupIdList']) {
					$post['mailupListGUID'] = $t["guid"];
					$post["groups"] = $t["groups"];
					break;
				}
			}
            
			unset($tmp); 
            unset($t);

			$post['mailupGroupId'] = "";
			foreach ($post["groups"] as $tmp_id_group => $tmp_group_name) {
				if ($tmp_group_name == "MAGENTO") {
					$post['mailupGroupId'] = $tmp_id_group;
					break;
				}
			}
			unset($tmp_id_group); unset($tmp_group_name);

			if (!strlen($post['mailupGroupId'])) {
				$newGroup = array(
					"idList" => $post['mailupIdList'],
					"listGUID" => $post['mailupListGUID'],
					"newGroupName" => "MAGENTO"
				);

				$post['mailupGroupId'] = $wsImport->CreaGruppo($newGroup);
			}
		}

		if ($accessKey === false) {
			Mage::throwException('no access key returned');
		}
        
		$fields = $wsSend->GetFields($accessKey);
		$fields_mapping = $wsImport->getFieldsMapping($storeId); // Pass StoreId

		//definisco il gruppo a cui aggiungere gli iscritti
		$groupId = $post['mailupGroupId'];
		$listGUID = $post['mailupListGUID'];
		$idList = $post['mailupIdList'];

		if ($post['mailupNewGroup'] == 1) {
			$newGroup = array(
				"idList" => $idList,
				"listGUID" => $listGUID,
				"newGroupName" => $post['mailupNewGroupName']
			);

			$groupId = $wsImport->CreaGruppo($newGroup);
		}

		if (isset($post["send_optin_email_to_new_subscribers"]) and $post["send_optin_email_to_new_subscribers"]) {
			$importProcessData = array(
				"idList"        => $idList,
				"listGUID"      => $listGUID,
				"idGroup"       => $groupId,
				"xmlDoc"        => "",
				"idGroups"      => $groupId,
				"importType"    => 1, 
				"mobileInputType" => 2,
				"asPending"     => 0,
				"ConfirmEmail"  => 1,
				"asOptOut"      => 0,
				"forceOptIn"    => 0, //1,
				"replaceGroups" => 0,
				"idConfirmNL"   => 0
			);
		} 
        else {
			$importProcessData = array(
				"idList"        => $idList,
				"listGUID"      => $listGUID,
				"idGroup"       => $groupId,
				"xmlDoc"        => "",
				"idGroups"      => $groupId,
				"importType"    => 1,
				"mobileInputType" => 2,
				"asPending"     => 0,
				"ConfirmEmail"  => 0,
				"asOptOut"      => 0,
				"forceOptIn"    => 0, //1,
				"replaceGroups" => 0,
				"idConfirmNL"   => 0
			);
		}

		//preparo l'xml degli iscritti da inviare a mailup (da gestire in base ai filtri)
		$xmlData = '';
		$subscribers_counter = 0;
		$total_subscribers_to_send = sizeof($mailupCustomerIds);
		foreach ($mailupCustomerIds as $customerId) {
			$tmp = array();
			$subscribers_counter++;
			$subscriber = self::getCustomersData(array($customerId));
           
            if(is_array($subscriber) && empty($subscriber)) {
                if($config->isLogEnabled($storeId)) {
                    Mage::log('EMPTY DATA FROM getCustomersData');
                }
            }
            
			$subscriber = array_values($subscriber);
			$subscriber = $subscriber[0];

			$subscriber["DataCarrelloAbbandonato"] = self::_convertUTCToStoreTimezoneAndFormatForMailup($subscriber["DataCarrelloAbbandonato"]);
			$subscriber["DataUltimoOrdineSpedito"] = self::_convertUTCToStoreTimezoneAndFormatForMailup($subscriber["DataUltimoOrdineSpedito"]);
			$subscriber["registeredDate"] = self::_convertUTCToStoreTimezoneAndFormatForMailup($subscriber["registeredDate"]);
			$subscriber["DataUltimoOrdine"] = self::_convertUTCToStoreTimezoneAndFormatForMailup($subscriber["DataUltimoOrdine"]);

			$xmlData .= '<subscriber email="'.$subscriber['email'].'" Number="" Name="">';

			if (@$fields_mapping["Name"]) $tmp[$fields_mapping["Name"]] = '<campo'.$fields_mapping["Name"].'>'."<![CDATA[". ((!empty($subscriber['nome'])) ? $subscriber['nome'] : '') ."]]>".'</campo'.$fields_mapping["Name"].'>';
			if (@$fields_mapping["Last"]) $tmp[$fields_mapping["Last"]] = '<campo'.$fields_mapping["Last"].'>'."<![CDATA[". ((!empty($subscriber['cognome'])) ? $subscriber['cognome'] : '') ."]]>".'</campo'.$fields_mapping["Last"].'>';

            
			foreach ($subscriber as $k=>$v) {
				if (!strlen($subscriber[$k])) {
					$subscriber[$k] = "-";
				} 
                else {
					$subscriber[$k] = str_replace(array("\r\n", "\r", "\n"), " ", $v);
				}
			}

			if (@$fields_mapping["Company"]) $tmp[$fields_mapping["Company"]] = '<campo'.$fields_mapping["Company"].'>'. "<![CDATA[". $subscriber['azienda'] ."]]>". '</campo'.$fields_mapping["Company"].'>';
			if (@$fields_mapping["City"]) $tmp[$fields_mapping["City"]] = '<campo'.$fields_mapping["City"].'>'. "<![CDATA[" . $subscriber['città'] ."]]>". '</campo'.$fields_mapping["City"].'>';
			if (@$fields_mapping["Province"]) $tmp[$fields_mapping["Province"]] = '<campo'.$fields_mapping["Province"].'>'. "<![CDATA[" . $subscriber['provincia'] ."]]>" . '</campo'.$fields_mapping["Province"].'>';
			if (@$fields_mapping["ZIP"]) $tmp[$fields_mapping["ZIP"]] = '<campo'.$fields_mapping["ZIP"].'>'. $subscriber['cap'].'</campo'.$fields_mapping["ZIP"].'>';
			if (@$fields_mapping["Region"]) $tmp[$fields_mapping["Region"]] = '<campo'.$fields_mapping["Region"].'>'. $subscriber['regione'] .'</campo'.$fields_mapping["Region"].'>';
			if (@$fields_mapping["Country"]) $tmp[$fields_mapping["Country"]] = '<campo'.$fields_mapping["Country"].'>'. $subscriber['paese'] .'</campo'.$fields_mapping["Country"].'>';
			if (@$fields_mapping["Address"]) $tmp[$fields_mapping["Address"]] = '<campo'.$fields_mapping["Address"].'>'."<![CDATA[". $subscriber['indirizzo'] ."]]>" .'</campo'.$fields_mapping["Address"].'>';
			if (@$fields_mapping["Fax"]) $tmp[$fields_mapping["Fax"]] = '<campo'.$fields_mapping["Fax"].'>'. $subscriber['fax'] .'</campo'.$fields_mapping["Fax"].'>';
			if (@$fields_mapping["Phone"]) $tmp[$fields_mapping["Phone"]] = '<campo'.$fields_mapping["Phone"].'>'. $subscriber['telefono'] .'</campo'.$fields_mapping["Phone"].'>';
			if (@$fields_mapping["CustomerID"]) $tmp[$fields_mapping["CustomerID"]] = '<campo'.$fields_mapping["CustomerID"].'>'. $subscriber['IDCliente'] .'</campo'.$fields_mapping["CustomerID"].'>';
			if (@$fields_mapping["LatestOrderID"]) $tmp[$fields_mapping["LatestOrderID"]] = '<campo'.$fields_mapping["LatestOrderID"].'>'. $subscriber['IDUltimoOrdine'] .'</campo'.$fields_mapping["LatestOrderID"].'>';
			if (@$fields_mapping["LatestOrderDate"]) $tmp[$fields_mapping["LatestOrderDate"]] = '<campo'.$fields_mapping["LatestOrderDate"].'>'. $subscriber['DataUltimoOrdine'] .'</campo'.$fields_mapping["LatestOrderDate"].'>';
			if (@$fields_mapping["LatestOrderAmount"]) $tmp[$fields_mapping["LatestOrderAmount"]] = '<campo'.$fields_mapping["LatestOrderAmount"].'>'. $subscriber['TotaleUltimoOrdine'] .'</campo'.$fields_mapping["LatestOrderAmount"].'>';
			if (@$fields_mapping["LatestOrderProductIDs"]) $tmp[$fields_mapping["LatestOrderProductIDs"]] = '<campo'.$fields_mapping["LatestOrderProductIDs"].'>'. $subscriber['IDProdottiUltimoOrdine'] .'</campo'.$fields_mapping["LatestOrderProductIDs"].'>';
			if (@$fields_mapping["LatestOrderCategoryIDs"]) $tmp[$fields_mapping["LatestOrderCategoryIDs"]] = '<campo'.$fields_mapping["LatestOrderCategoryIDs"].'>'. $subscriber['IDCategorieUltimoOrdine'] .'</campo'.$fields_mapping["LatestOrderCategoryIDs"].'>';
			if (@$fields_mapping["LatestShippedOrderDate"]) $tmp[$fields_mapping["LatestShippedOrderDate"]] = '<campo'.$fields_mapping["LatestShippedOrderDate"].'>'. $subscriber['DataUltimoOrdineSpedito'] .'</campo'.$fields_mapping["LatestShippedOrderDate"].'>';
			if (@$fields_mapping["LatestShippedOrderID"]) $tmp[$fields_mapping["LatestShippedOrderID"]] = '<campo'.$fields_mapping["LatestShippedOrderID"].'>'. $subscriber['IDUltimoOrdineSpedito'] .'</campo'.$fields_mapping["LatestShippedOrderID"].'>';
			if (@$fields_mapping["LatestAbandonedCartDate"]) $tmp[$fields_mapping["LatestAbandonedCartDate"]] = '<campo'.$fields_mapping["LatestAbandonedCartDate"].'>'. $subscriber['DataCarrelloAbbandonato'] .'</campo'.$fields_mapping["LatestAbandonedCartDate"].'>';
			if (@$fields_mapping["LatestAbandonedCartTotal"]) $tmp[$fields_mapping["LatestAbandonedCartTotal"]] = '<campo'.$fields_mapping["LatestAbandonedCartTotal"].'>'. $subscriber['TotaleCarrelloAbbandonato'] .'</campo'.$fields_mapping["LatestAbandonedCartTotal"].'>';
			if (@$fields_mapping["LatestAbandonedCartID"]) $tmp[$fields_mapping["LatestAbandonedCartID"]] = '<campo'.$fields_mapping["LatestAbandonedCartID"].'>'. $subscriber['IDCarrelloAbbandonato'] .'</campo'.$fields_mapping["LatestAbandonedCartID"].'>';
			if (@$fields_mapping["TotalOrdered"]) $tmp[$fields_mapping["TotalOrdered"]] = '<campo'.$fields_mapping["TotalOrdered"].'>'. $subscriber['TotaleFatturato'] .'</campo'.$fields_mapping["TotalOrdered"].'>';
			if (@$fields_mapping["TotalOrderedLast12m"]) $tmp[$fields_mapping["TotalOrderedLast12m"]] = '<campo'.$fields_mapping["TotalOrderedLast12m"].'>'. $subscriber['TotaleFatturatoUltimi12Mesi'] .'</campo'.$fields_mapping["TotalOrderedLast12m"].'>';
			if (@$fields_mapping["TotalOrderedLast30d"]) $tmp[$fields_mapping["TotalOrderedLast30d"]] = '<campo'.$fields_mapping["TotalOrderedLast30d"].'>'. $subscriber['TotaleFatturatoUltimi30gg'] .'</campo'.$fields_mapping["TotalOrderedLast30d"].'>';
			if (@$fields_mapping["AllOrderedProductIDs"]) $tmp[$fields_mapping["AllOrderedProductIDs"]] = '<campo'.$fields_mapping["AllOrderedProductIDs"].'>'. $subscriber['IDTuttiProdottiAcquistati'] .'</campo'.$fields_mapping["AllOrderedProductIDs"].'>';
            
			$last_field = max(array_keys($tmp));
			for ($i=1; $i<$last_field; $i++) {
				if (!isset($tmp[$i])) $tmp[$i] = "<campo{$i}>-</campo{$i}>";
			}
			ksort($tmp);
			$tmp = implode("", $tmp);
			$xmlData .= $tmp;
			$xmlData .= "</subscriber>\n";

            //if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log', $storeId)) Mage::log("Store ID before newImportProcess: {$storeId}");
            
			// ogni 5000 utenti invio i dati
			if ($subscribers_counter == 5000) {
				$importProcessData["xmlDoc"] = "<subscribers>$xmlData</subscribers>";
				$xmlData = "";
				$subscribers_counter = 0;
				if($config->isLogEnabled($storeId)) {
                    Mage::log('ImportProcessData SubscriberCounr == 5000');
                    Mage::log($importProcessData, 0);
                }
				$processID = $wsImport->newImportProcess($importProcessData);
				if ($processID === false) {
                    return false;
                }
			}
		}

		//invio gli ultimi utenti
		if (strlen($xmlData)) {
			$importProcessData["xmlDoc"] = "<subscribers>$xmlData</subscribers>";
            
			$xmlData = "";
			$subscribers_counter = 0;
			if($config->isLogEnabled($storeId)) {
                Mage::log('ImportProcessData');
                Mage::log($importProcessData, 0);
            }
			$processID = $wsImport->newImportProcess($importProcessData);
			if($processID === FALSE) {
                if($config->isLogEnabled($storeId)) {
                    Mage::log('newImportProcess B FALSE');
                }
                return FALSE;
            }
		}

		if (isset($newsletter_subscribers) and is_array($newsletter_subscribers) and !empty($newsletter_subscribers)) {
			$subscribers_counter = 0;
			foreach ($newsletter_subscribers as $newsletter_subscriber) {
				$subscribers_counter++;
				$xmlData .= '<subscriber email="' . $newsletter_subscriber . '" Number="" Name=""></subscriber>';
				if ($subscribers_counter == 5000 or $subscribers_counter == $total_subscribers_to_send) {
					$importProcessData["xmlDoc"] = "<subscribers>$xmlData</subscribers>";
					$xmlData = "";
					$subscribers_counter = 0;
					if($config->isLogEnabled($storeId)) {
                        Mage::log($importProcessData, 0);
                    }
					$processID = $wsImport->newImportProcess($importProcessData);
					if ($processID === FALSE) {
                        if($config->isLogEnabled($storeId)) {
                            Mage::log('newImportProcess C FALSE');
                        }
                        return FALSE;
                    }
				}
			}
		}

        /**
         * This needs unset in the newer version of the API, we needed it in the old API backend.
         */
		unset($importProcessData["xmlDoc"]);
        
		$importProcessData["listsIDs"] = $post['mailupIdList'];
		$importProcessData["listsGUIDs"] = $post['mailupListGUID']; 
		$importProcessData["groupsIDs"] = $groupId;

		if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log', $storeId)) { 
            Mage::log("mailup: StartImportProcesses (STORE: {$storeId})", 0);
            Mage::log($importProcessData, 0);
        }
        
		$check = $wsImport->StartImportProcesses($importProcessData);
        
		if ($config->isLogEnabled($storeId)) {
            Mage::log('StartImportProcesses Check: ' . $check, 0);
        }
        
		return $check;
	}

	private static function _formatPrice($price) {
		return number_format($price, 2, ',', '');
	}

	private static function _retriveDateFromDatetime($datetime) {
		if (empty($datetime)) return "";
		return date("Y-m-d H:i:s", strtotime($datetime));
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
            echo $e->getMessage();
        }
    }
        
    /**
     * Clean the Resource Table
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
}