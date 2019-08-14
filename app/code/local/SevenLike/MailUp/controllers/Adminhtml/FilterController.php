<?php

require_once dirname(__FILE__) . "/../../Model/MailUpWsImport.php";
require_once dirname(__FILE__) . "/../../Model/Wssend.php";
class SevenLike_MailUp_Adminhtml_FilterController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Default Action
     */
    public function indexAction()
    {        
	    $this->checkRunningImport();
        $this->loadLayout()->renderLayout();
    }
    
    public function confirmAction() {
	    $this->checkRunningImport();
	    $this->loadLayout()->renderLayout();
    }
    
    /**
     * Generate CSV
     * 
     * @todo    include stores
     */
    public function csvAction() 
    {
	    $post = $this->getRequest()->getPost();
        $file = '';

        if ($post['countPost'] > 0) {
            //preparo l'elenco degli iscritti da salvare nel csv
            $mailupCustomerIds = Mage::getSingleton('core/session')->getMailupCustomerIds();

            //require_once(dirname(__FILE__) . '/../Helper/Data.php');
            $customersData = SevenLike_MailUp_Helper_Data::getCustomersData();

            //CSV Column names
            $file = '"Email","First Name","Last Name"';
            if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_mailup_synchro') == 1) {
                $file .= ',"Company","City","Province","Zip code","Region","Country code","Address","Fax","Phone","Customer id"';
                $file .= ',"Last Order id","Last Order date","Last Order total","Last order product ids","Last order category ids"';
                $file .= ',"Last sent order date","Last sent order id"';
                $file .= ',"Last abandoned cart date","Last abandoned cart total","Last abandoned cart id"';
                $file .= ',"Total orders amount","Last 12 months amount","Last 30 days amount","All products ids"';
            }
            $file .= ';';


            foreach ($mailupCustomerIds as $customerId) {
                foreach ($customersData as $subscriber) {
                    if ($subscriber['email'] == $customerId['email']) {
                        $file .= "\n";
                        $file .= '"'.$subscriber['email'].'"';
                        $file .= ',"'.((!empty($subscriber['nome'])) ? $subscriber['nome'] : '') .'"';
                        $file .= ',"'.((!empty($subscriber['cognome'])) ? $subscriber['cognome'] : '') .'"';

                        $synchroConfig = Mage::getStoreConfig('mailup_newsletter/mailup/enable_mailup_synchro') == 1;

                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['azienda'])) ? $subscriber['azienda'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['città'])) ? $subscriber['città'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['provincia'])) ? $subscriber['provincia'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['cap'])) ? $subscriber['cap'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['regione'])) ? $subscriber['regione'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['paese'])) ? $subscriber['paese'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['indirizzo'])) ? $subscriber['indirizzo'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['fax'])) ? $subscriber['fax'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['telefono'])) ? $subscriber['telefono'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['IDCliente'])) ? $subscriber['IDCliente'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['IDUltimoOrdine'])) ? $subscriber['IDUltimoOrdine'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['DataUltimoOrdine'])) ? $subscriber['DataUltimoOrdine'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['TotaleUltimoOrdine'])) ? $subscriber['TotaleUltimoOrdine'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['IDProdottiUltimoOrdine'])) ? $subscriber['IDProdottiUltimoOrdine'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['IDCategorieUltimoOrdine'])) ? $subscriber['IDCategorieUltimoOrdine'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['DataUltimoOrdineSpedito'])) ? $subscriber['DataUltimoOrdineSpedito'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['IDUltimoOrdineSpedito'])) ? $subscriber['IDUltimoOrdineSpedito'] : '') .'"';
                        
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['DataCarrelloAbbandonato'])) ? $subscriber['DataCarrelloAbbandonato'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['TotaleCarrelloAbbandonato'])) ? $subscriber['TotaleCarrelloAbbandonato'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['IDCarrelloAbbandonato'])) ? $subscriber['IDCarrelloAbbandonato'] : '') .'"';
                        
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['TotaleFatturato'])) ? $subscriber['TotaleFatturato'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['TotaleFatturatoUltimi12Mesi'])) ? $subscriber['TotaleFatturatoUltimi12Mesi'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['TotaleFatturatoUltimi30gg'])) ? $subscriber['TotaleFatturatoUltimi30gg'] : '') .'"';
                        $file .= ',"'. ($synchroConfig && (!empty($subscriber['IDTuttiProdottiAcquistati'])) ? $subscriber['IDTuttiProdottiAcquistati'] : '') .'"';
                        $file .= ';';

                        continue 2;
                    }
                }
            }
	    }
	
	    //lancio il download del file
        header("Content-type: application/csv");
	    header("Content-Disposition: attachment;Filename=filtered_customers.csv");
	    echo $file;
    }
    
    /**
     * Handle Posted Data
     */
    public function postAction() 
    {
        $post = $this->getRequest()->getPost();
        $storeId = isset($post['store_id']) ? (int)$post['store_id'] : NULL;

        try {
            if (empty($post)) {
                Mage::throwException($this->__('Invalid form data.'));
            }

	        // creo il gruppo se necessario
	        $post["mailupNewGroupName"] = trim($post["mailupNewGroupName"]);
	        if ($post["mailupNewGroup"] and strlen($post["mailupNewGroupName"])) {
		        require_once dirname(__FILE__) . "/../../Model/MailUpWsImport.php";
		        $wsImport = new MailUpWsImport($storeId);
		        $post['mailupGroupId'] = $wsImport->CreaGruppo(array(
			        "idList" => $post['mailupIdList'],
			        "listGUID" => $post['mailupListGUID'],
			        "newGroupName" => $post["mailupNewGroupName"]
		        ));
	        }

	        // inserisco il job
	        $db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
	        $db_write->insert("mailup_sync_jobs", array(
		        "mailupgroupid"     => $post['mailupGroupId'],
		        "send_optin"        => isset($post['send_optin_email_to_new_subscribers']) && ($post['send_optin_email_to_new_subscribers'] == 1)  ? 1 : 0,
		        "status"            => "queued",
		        "queue_datetime"    => gmdate("Y-m-d H:i:s"),
                'store_id'          => $storeId,
	        ));
	        $job_id = $db_write->lastInsertId("mailup_sync_jobs");

	        // inserisco
            $mailupCustomerIds = Mage::getSingleton('core/session')->getMailupCustomerIds();
	        foreach ($mailupCustomerIds as $customer_id) {
		        try {
			        $db_write->insert("mailup_sync", array(
				        "customer_id"       => $customer_id,
				        "entity"            => "customer",
				        "job_id"            => $job_id,
				        "needs_sync"        => true,
				        "last_sync"         => null,
                        'store_id'          => $storeId,
			        ));
		        } catch (Exception $e) {
			        $db_write->update("mailup_sync", array(
				        "needs_sync" => true
			        ), "customer_id=$customer_id AND entity='customer' AND job_id=$job_id");
		        }
	        }

	        $db_write->insert(Mage::getSingleton('core/resource')->getTableName('cron_schedule'), array(
		        "job_code" => "sevenlike_mailup",
		        "status" => "pending",
		        "created_at" => gmdate("Y-m-d H:i:s"),
		        "scheduled_at" => gmdate("Y-m-d H:i:s", strtotime("+1minute"))
	        ));

            $message = $this->__('Members have been sent correctly');
            Mage::getSingleton('adminhtml/session')->addSuccess($message);
        } catch (Exception $e) {
	        Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
	        $errorMessage = $this->__('Warning: no member has been selected');
            Mage::getSingleton('adminhtml/session')->addError($errorMessage);
        }

        $this->_redirect('*/*');
    }

    public function saveFilterHintAction() {
	    $this->checkRunningImport();
        try {
            $post = $this->getRequest()->getPost();
            $filter_name = $post['filter_name'];
            unset($post['filter_name']);

            $MailUpWsImport = Mage::getModel('mailup/ws');
            $wsImport = new MailUpWsImport();
            $wsImport->saveFilterHint($filter_name, $post);
        } catch (Exception $e) {
            $errorMessage = $this->__('Error: unable to save current filter');
            Mage::getSingleton('adminhtml/session')->addError($errorMessage);
        }

        $this->_redirect('*/*');
    }

    public function deleteFilterHintAction() {
	    $this->checkRunningImport();
        try {
            $post = $this->getRequest()->getPost();

            $MailUpWsImport = Mage::getModel('mailup/ws');
            $wsImport = new MailUpWsImport();
            $wsImport->deleteFilterHint($post['filter_name']);
        } catch (Exception $e) {
            $errorMessage = $this->__('Error: unable to delete the filter');
            Mage::getSingleton('adminhtml/session')->addError($errorMessage);
        }

        $this->_redirect('*/*');
    }

    public function testCronAction() {
        $cron = new SevenLike_MailUp_Model_Cron();
        $cron->run();
    }

    public function testFieldsAction() {
        $wsSend = new MailUpWsSend();
        $accessKey = $wsSend->loginFromId();

        if ($accessKey !== false) {
            $fields = $wsSend->GetFields($accessKey);
            print_r($fields);
            die('success');
        } else {
            die('no access key returned');
        }
    }

    /**
     * Check if an import is currently running
     * 
     * @return type
     */
	public function checkRunningImport()
	{
        $db = Mage::getSingleton("core/resource")->getConnection("core_read");
		$cron_schedule_table = Mage::getSingleton("core/resource")->getTableName("cron_schedule");
        
        /**
         * @todo    check if a cron has been run in the past X minites
         *          notify if cron is npt up and running
         */
        $lastTime = $db->fetchOne("SELECT max(last_sync) FROM mailup_sync"); // 2013-04-18 19:23:55
        if( ! empty($lastTime)) {
            $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $lastTime);
            $lastTimeObject = clone $dateTime;
            if($dateTime) {
                $dateTime->modify('+30 minutes');
                $now = new DateTime();
                //if($dateTime < $now) {
                    Mage::getSingleton("adminhtml/session")
                        ->addNotice($this->__("Last Sync Performed: {$lastTimeObject->format('Y-m-d H:i:s e')}"))
                    ;
                //}
            }
        }

		$running_processes = $db->fetchOne("SELECT count(*) FROM $cron_schedule_table WHERE job_code='sevenlike_mailup' AND status='running'");
		if ($running_processes) {
			Mage::getSingleton("adminhtml/session")->addNotice($this->__("A MailUp import process is running."));
			return;
		}

		$scheduled_processes = $db->fetchOne("SELECT count(*) FROM $cron_schedule_table WHERE job_code='sevenlike_mailup' AND status='pending'");
		if ($scheduled_processes) {
			Mage::getSingleton("adminhtml/session")->addNotice($this->__("A MailUp import process is schedules and will be executed soon."));
			return;
		}
	}
}