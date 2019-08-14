<?php
/**
 * Cron.php
 * 
 * Scheduled Task handler.
 */
require_once dirname(__FILE__) . "/MailUpWsImport.php";
require_once dirname(__FILE__) . "/Wssend.php";

class SevenLike_MailUp_Model_Cron
{
    /**
     * Run the Task
     * 
     * IF ANY Job we run fails, due to another processes being run we should
     * gracefully exit and wait our next go!
     * 
     * Also change auto sync to just create a job, and run a single job Queue!
     */
	public function run()
	{
        if($this->_config()->isLogEnabled()) {
            $this->_config()->dbLog("Cron [Triggered]");
        }

		if ($this->_config()->isCronExportEnabled()) {
            /**
             * This doesn't exist in 1.3.2!
             */
			$indexProcess = new Mage_Index_Model_Process();
			$indexProcess->setId("mailupcronrun");
			if ($indexProcess->isLocked()) {
				$this->_config()->log('MAILUP: cron already running or locked');
				return false;
			}
			$indexProcess->lockAndBlock();
            
            require_once dirname(__FILE__) . '/../Helper/Data.php';
			$db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
			$db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
            $syncTableName = Mage::getSingleton('core/resource')->getTableName('mailup/sync');
            $jobsTableName = Mage::getSingleton('core/resource')->getTableName('mailup/job');
			$lastsync = gmdate("Y-m-d H:i:s");
			// reading customers (jobid == 0, their updates)
			$customer_entity_table_name = Mage::getSingleton('core/resource')->getTableName('customer_entity');

            /**
             * Now Handle Jobs we need to Sync, and all customers attached to each job
             */
			foreach(Mage::getModel('mailup/job')->fetchQueuedJobsCollection() as $jobModel) {
                /* @var $jobModel SevenLike_MailUp_Model_Job */
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
                    $this->_config()->dbLog("Job Task [update] [Synced] [customer count:{$customerCount}]", $job["id"], $storeId);
					// finishing the job also
					$db_write->query("
                        UPDATE {$jobsTableName} SET status='finished', finish_datetime='" . gmdate("Y-m-d H:i:s") . "' 
                        WHERE id={$job["id"]}"
                    );
                    $this->_config()->dbLog("Jobs [Update] [Complete] [{$job["id"]}]", $job["id"], $storeId);
				}
                /**
                 * Only successfull if we get 0 back. False is also a fail.
                 */
                else {
                    $stmt = $db_write->query(
                        "UPDATE {$jobsTableName} SET status='queued' WHERE id={$job["id"]}"
                    );
                    if($this->_config()->isLogEnabled()) {
                        $this->_config()->dbLog(sprintf("generateAndSendCustomers [ReturnCode] [ERROR] [%d]", $returnCode), $job["id"], $storeId);
                    }
                }
			}

			$indexProcess->unlock();
        }
 
        if ($this->_config()->isLogEnabled()) {
           $this->_config()->dbLog("Cron [Completed]");
        }
	}
    
    /**
     * Run Auto Sync Jobs
     */
    public function autoSync()
    {
        // Only run Auto Sync Jobs
        $job = Mage::getModel('mailup/job');
        /* @var $job SevenLike_MailUp_Model_Job */
        
        foreach($job->fetchAutoSyncQueuedJobsCollection() as $job) {
           
        }
    }
    
    /**
     * Run Manual Sync Jobs
     */
    public function manualSync()
    {
        // Only run Auto Sync Jobs
        
        $job = Mage::getModel('mailup/job');
        /* @var $job SevenLike_MailUp_Model_Job */
        
        foreach($job->fetchManualSyncQueuedJobsCollection() as $job) {
            
        }
    }
    
    /**
     * Start the next job in the Queue!
     */
    public function startNextJob()
    {
        $jobModel = Mage::getModel('mailup/job');
        /* @var $jobModel SevenLike_MailUp_Model_Job */
        foreach($jobModel->fetchQueuedJobsCollection() as $job) {
            /* @var $job SevenLike_MailUp_Model_Job */
            
            /**
             * Try and Start it... if it fails, we can try the next one!
             */
        }
    }
    
    /**
     * Add the jobs to the import queue on Mailup.
     */
    public function newImportProcesses()
    {
        
    }
	
    /**
     * handle connection issues
     * 
     * @todo    implement
     */
	public static function resendConnectionErrors()
	{
        // never implemented.
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