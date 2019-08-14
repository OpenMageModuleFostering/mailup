<?php
/**
 * TestController.php
 */
class SevenLike_MailUp_TestController extends Mage_Core_Controller_Front_Action
{
    /**
     * Predispatch: should set layout area
     * 
     * This is causing an issue and making 404s, something to do with the install
     * being messed up and the code inside parent method doing something strange!
     *
     * @return Mage_Core_Controller_Front_Action
     */
    public function preDispatch()
    {
        //$config = Mage::getModel('mailup/config');
        /* @var $config SevenLike_MailUp_Model_Config */
        
        //if( ! $config->isTestMode()) {
        //    die('Access Denied.');
        //}
        
        return parent::preDispatch();
    }
    
    /**
     * Default Action
     */
    public function indexAction()
    { 
        //$this->loadLayout();
        //$this->renderLayout();
        //var_dump(Mage::helper('mailup')->getAllCustomerAttributes());

        die('done');
    }
    
    public function SubscriberAction()
    {
        $helper = Mage::helper('mailup');
        
        var_dump($helper->isSubscriber(27, 1));
        var_dump($helper->isSubscriber(29, 99));
    }

    /**
     * Start the process, if we've already run NewImportProcess
     * and we have a process ID we can Start it.
     */
    public function startProcessAction()
    {
        require_once dirname(__FILE__) . "/../Model/MailUpWsImport.php";
        require_once dirname(__FILE__) . "/../Model/Wssend.php";
        
        $wsSend = new MailUpWsSend($job->getStoreId());
		$wsImport = new MailUpWsImport($job->getStoreId());
		$accessKey = $wsSend->loginFromId();
        
        /**
         * We need the ListID and ListGuid, which we will NOT
         * have for sync items, as we've not saved the process id
         * or anything else!!
         */
        
        //StartProcess(int idList, int listGUID, int idProcess)
        
        /*$return = $wsImport->startProcess(array(
            'idList'    => $job->getListid(),
            'listGUID'  => $job->getListGuid(),
            'idProcess' => $job->getProcessId()
        ));*/
    }
    
    /**
     * Test the models..
     */
    public function modelsAction()
    {
        $jobTask = Mage::getModel('mailup/sync');
        /* @var $jobTask SevenLike_MailUp_Model_Sync */
        
        $job = Mage::getModel('mailup/job');
        /* @var $job SevenLike_MailUp_Model_Job */
        
        foreach($job->fetchQueuedJobsCollection() as $job) {
            echo "Job [{$job->getId()}] [{$job->getType()}] <br />";
        }
        
        echo '<br />----<br />';
        
        foreach($job->fetchManualSyncQueuedJobsCollection() as $job) {
            echo "Job [{$job->getId()}] [{$job->getType()}] <br />";
        }
        
        echo '<br />----<br />';
        
        foreach($job->fetchAutoSyncQueuedJobsCollection() as $job) {
            echo "Job [{$job->getId()}] [{$job->getType()}] <br />";
        }
        
        return;
        
        $tasks = $jobTask->getSyncItemsCollection();
        foreach($tasks as $task) {
            var_dump($task->getData());
        }
        
        foreach($jobTask->fetchByJobId(0) as $task) {
            var_dump($task->getData());
        }
        
        var_dump($jobTask->getJob());
    }
    
    /**
     * List jobs
     */
    public function cronAction()
    {
        echo "Server Time: " . date('H:i:s') . "<br /><br />";
        
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $stmt = $read->query("
            SELECT * 
            FROM cron_schedule 
            ORDER BY scheduled_at DESC"
        );
        while ($row = $stmt->fetch()) {
            echo "{$row['job_code']} | {$row['status']} | {$row['scheduled_at']} | {$row['messages']}<br />";
        }
    }
    
    /**
     * List pending jobs
     */
    public function cronPendingAction()
    {
        echo "Server Time: " . date('H:i:s') . "<br /><br />";
        
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $stmt = $read->query("
            SELECT * 
            FROM cron_schedule where status = 'pending'
            ORDER BY scheduled_at DESC"
        );
        while ($row = $stmt->fetch()) {
            echo "{$row['job_code']} | {$row['status']} | {$row['scheduled_at']} | {$row['messages']}<br />";
        }
    }
    
    /**
     * List jobs
     */
    public function removeRunningAction()
    {
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $stmt = $write->query("
            DELETE FROM cron_schedule WHERE job_code = 'sevenlike_mailup' AND status = 'running'"
        );
        die('done');
    }
    
    /**
     * Show Current Processes
     */
    public function processesAction()
    {
        require_once dirname(dirname(__FILE__)) . "/Model/MailUpWsImport.php";
        require_once dirname(dirname(__FILE__)) . "/Model/Wssend.php";
        $wsimport = new MailUpWsImport();
        
        var_dump($wsimport->getProcessDetail(array(
            
        )));
    }
}
