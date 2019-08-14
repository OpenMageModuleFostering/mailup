<?php
/**
 * Cron.php
 */
require_once dirname(__FILE__) . "/MailUpWsImport.php";
require_once dirname(__FILE__) . "/Wssend.php";

class SevenLike_MailUp_Model_Cron
{
	public function run()
	{
        if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
            Mage::log('Cron mailup', 0);
        }

		if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_cron_export') == 1) {
			$indexProcess = new Mage_Index_Model_Process();
			$indexProcess->setId("mailupcronrun");
			if ($indexProcess->isLocked()) {
				Mage::log("MAILUP: cron already running or locked");
				return false;
			}
			$indexProcess->lockAndBlock();

            require_once dirname(__FILE__) . '/../Helper/Data.php';
			$db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
			$db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
			$lastsync = gmdate("Y-m-d H:i:s");

			// reading newsletter subscribers
			//$newsletter_subscriber_table_name = Mage::getSingleton('core/resource')->getTableName('newsletter_subscriber');
			//$newsletter_subscribers = $db_read->fetchAll("SELECT ms.*, ns.subscriber_email FROM mailup_sync ms JOIN $newsletter_subscriber_table_name ns ON (ms.customer_id = ns.subscriber_id) WHERE ms.needs_sync=1 AND ms.entity='subscriber'");

			// reading customers (jobid == 0, their updates)
			$customer_entity_table_name = Mage::getSingleton('core/resource')->getTableName('customer_entity');

			$stmt = $db_read->query("
                SELECT ms.*, ce.email FROM mailup_sync ms 
                JOIN $customer_entity_table_name ce 
                    ON (ms.customer_id = ce.entity_id) 
                WHERE 
                ms.needs_sync=1 
                AND ms.entity='customer' 
                AND job_id=0"
            );
            
            $storeArr = array();
            $rows = $stmt->fetchAll();
            /**
             * Customer Updates, job_id = 0
             */
			foreach($rows as $row) {
                $storeId = $row["store_id"];
                /*if( ! isset($storeId)) {
                    Mage::log('StoreID Not Set On Cron Job');
                    //$storeId = Mage::app()->getDefaultStoreView()->getStoreId(); // Fallback incase not set?!?
                }*/
                /**
                 * Send/Group each stores data together.
                 */
                $storeArr[$storeId][] = $row["customer_id"];
			}
            
            if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
                if(count($storeArr) > 0) {
                    Mage::log('STORE DATA ARRAY');
                    Mage::log($storeArr);
                }
            }
            
            /**
             * Send each Store's data together!
             */
            foreach($storeArr as $singleStoreId => $customers) {
                // generating and sending data to mailup
                $check = SevenLike_MailUp_Helper_Data::generateAndSendCustomers($customers, NULL, NULL, $singleStoreId);
            }

			// reading and processing jobs
			$jobs = $db_read->fetchAll("SELECT * FROM mailup_sync_jobs WHERE status='queued'");
            /**
             * Sync Jobs
             */
			foreach ($jobs as $job) {
				$stmt = $db_write->query("UPDATE mailup_sync_jobs SET status='started', start_datetime='" . gmdate("Y-m-d H:i:s") . "' WHERE id={$job["id"]}");
                
                $storeId = isset($job['store_id']) ? $job['store_id'] : NULL; 
                //if( ! isset($storeId)) {
                    //Mage::log('StoreID Not Set On Cron Job');
                    //$storeId = Mage::app()->getDefaultStoreView()->getStoreId(); // Fallback incase not set?!?
                //}
                
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
				unset($tmp); unset($t);
				$stmt = $db_read->query("
                    SELECT ms.*, ce.email 
                    FROM mailup_sync ms 
                    JOIN $customer_entity_table_name ce 
                        ON (ms.customer_id = ce.entity_id) 
                    WHERE ms.needs_sync=1 
                    AND ms.entity='customer' 
                    AND job_id={$job["id"]}"
                );
				while ($row = $stmt->fetch()) {
					$customers[] = $row["customer_id"];
				}

                $check = SevenLike_MailUp_Helper_Data::generateAndSendCustomers($customers, $job, NULL, $storeId);
                
                /**
                 * @todo    We need to check the result of the import, if there's an error
                 *          we do not want to mark this ask Synced! we need to retry..
                 */
				if ($check) {
					// saving sync state for customers
					foreach ($customers as $row) {
						$db_write->query("
                            UPDATE mailup_sync SET needs_sync=0, last_sync='$lastsync' 
                            WHERE customer_id={$row} 
                            AND entity='customer'"
                        );
					}

					// finishing the job also
					$db_write->query("
                        UPDATE mailup_sync_jobs SET status='finished', finish_datetime='" . gmdate("Y-m-d H:i:s") . "' 
                        WHERE id={$job["id"]}"
                    );
				}
			}

			$indexProcess->unlock();
        } 
        else {
            if(Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
                Mage::log('Cron export not enabled', 0);
            }
        }

        if (Mage::getStoreConfig('mailup_newsletter/mailup/enable_log')) {
            Mage::log('Cron mailup finished', 0);
        }
	}
	
	public static function resendConnectionErrors()
	{
	}
}