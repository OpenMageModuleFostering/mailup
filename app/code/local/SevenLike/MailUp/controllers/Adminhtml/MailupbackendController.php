<?php
class SevenLike_MailUp_Adminhtml_MailupbackendController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Default Action
     */
	public function indexAction()
    {
       $this->loadLayout();
	   $this->_title($this->__("MailUp Jobs"));
	   $this->renderLayout();
    }
    
    /**
     * Run The Job
     */
    public function runjobAction()
    {
        /** @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('adminhtml/session');
        $id = $this->getRequest()->getParam('id');
        
        if( ! $id) {
            $session->addError(
                Mage::helper('mailup')->__('Invalid Entity')
            );
        }
        
        $entity = Mage::getModel('mailup/job')->load($id);
        if($entity) {   
            Mage::helper('mailup')->runJob($entity->getId());
        }
 
        $session->addSuccess(
            Mage::helper('mailup')->__("Run Job [{$entity->getId()}]")
        );

        $this->_redirect('*/*/index');
    }
    
    /**
     * Delete a job
     */
    public function deleteAction()
    {
        /** @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('adminhtml/session');
        $id = $this->getRequest()->getParam('id');
        
        if( ! $id) {
            $session->addError(
                Mage::helper('mailup')->__('Invalid Entity')
            );
        }
        
        $entity = Mage::getModel('mailup/job')->load($id);
        $entity->delete();
 
        $session->addSuccess(
            Mage::helper('mailup')->__("Job [{$entity->getId()}] [Deleted]")
        );

        $this->_redirect('*/*/index');
    }
    
    /**
     * Start the process, if we've already run NewImportProcess
     * and we have a process ID we can Start it.
     */
    public function startProcessAction()
    {
        /** @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('adminhtml/session');
        $id = $this->getRequest()->getParam('id');
        
        if( ! $id) {
            $session->addError(
                Mage::helper('mailup')->__('Invalid Entity')
            );
        }
        
        $job = Mage::getModel('mailup/job')->load($id);
        /* @var $job SevenLike_MailUp_Model_Job */
        
        if( ! $job->getProcessId()) {
            $session->addError(
                Mage::helper('mailup')->__("Can't Run, There's no Process ID [{$job->getId()}]")
            );
            $this->_redirect('*/*/index');
            return;
        }
        require_once dirname(__FILE__) . "/../../Model/MailUpWsImport.php";
        require_once dirname(__FILE__) . "/../../Model/Wssend.php";
        
        $wsSend = new MailUpWsSend($job->getStoreId());
		$wsImport = new MailUpWsImport($job->getStoreId());
		$accessKey = $wsSend->loginFromId();
        
        //StartProcess(int idList, int listGUID, int idProcess)
        /*$return = (string)$wsImport->startProcess(array(
            'idList'    => $job->getListid(),
            'listGUID'  => $job->getListGuid(),
            'idProcess' => $job->getProcessId()
        ));
        
        $session->addSuccess(
            Mage::helper('mailup')->__("Job Processed [{$job->getId()}] [{$return}]")
        );*/
        
        $session->addSuccess(
            Mage::helper('mailup')->__("Start Process [DISABLED]")
        );

        $this->_redirect('*/*/index');
    }
    
    /**
     * Start the process, if we've already run NewImportProcess
     * and we have a process ID we can Start it.
     */
    public function getProcessDetailAction()
    {
        /** @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('adminhtml/session');
        $id = $this->getRequest()->getParam('id');
        
        if( ! $id) {
            $session->addError(
                Mage::helper('mailup')->__('Invalid Entity')
            );
        }
        
        $job = Mage::getModel('mailup/job')->load($id);
        /* @var $job SevenLike_MailUp_Model_Job */
        
        if( ! $job->getProcessId()) {
            $session->addError(
                Mage::helper('mailup')->__("Can't Run, There's no Process ID [{$job->getId()}]")
            );
            $this->_redirect('*/*/index');
            return;
        }
        require_once dirname(__FILE__) . "/../../Model/MailUpWsImport.php";
        require_once dirname(__FILE__) . "/../../Model/Wssend.php";
        
        $wsSend = new MailUpWsSend($job->getStoreId());
		$wsImport = new MailUpWsImport($job->getStoreId());
		$accessKey = $wsSend->loginFromId();
        
        //StartProcess(int idList, int listGUID, int idProcess)
        /*$return = $wsImport->getProcessDetail(array(
            'idList'    => $job->getListid(),
            'listGUID'  => $job->getListGuid(),
            'idProcess' => $job->getProcessId()
        ));
        
        $session->addSuccess(
            Mage::helper('mailup')->__("Process Detail [{$job->getId()}] [{$return}]")
        );*/
        
        $session->addSuccess(
            Mage::helper('mailup')->__("Process Detail [DISABLED]")
        );

        $this->_redirect('*/*/index');
    }
    
    /**
     * Get a list of processes we've added using NewImportProcess.
     * We want to get a list and then go over them Starting each one, one at a time
     * use 
     * StartProcess.
     */
    public function getCurrentProcessesAction()
    {
        /** @var $session Mage_Admin_Model_Session */
        $session = Mage::getSingleton('adminhtml/session');
        $id = $this->getRequest()->getParam('id');
        
        if( ! $id) {
            $session->addError(
                Mage::helper('mailup')->__('Invalid Entity')
            );
        }
        
        $job = Mage::getModel('mailup/job')->load($id);
        /* @var $job SevenLike_MailUp_Model_Job */
        
        if( ! $job->getProcessId()) {
            $session->addError(
                Mage::helper('mailup')->__("Can't Run, There's no Process ID [{$job->getId()}]")
            );
            $this->_redirect('*/*/index');
            return;
        }
        require_once dirname(__FILE__) . "/../../Model/MailUpWsImport.php";
        require_once dirname(__FILE__) . "/../../Model/Wssend.php";
        
        /*$wsSend = new MailUpWsSend($job->getStoreId());
		$wsImport = new MailUpWsImport($job->getStoreId());
		$accessKey = $wsSend->loginFromId();
        
        //StartProcess(int idList, int listGUID, int idProcess)
        $return = $wsImport->getProcessDetail(array(
            'idList'    => $job->getListid(),
            'listGUID'  => $job->getListGuid(),
            'idProcess' => $job->getProcessId()
        ));*/
        
        /*$session->addSuccess(
            Mage::helper('mailup')->__("Process Detail [{$job->getId()}] [{$return}]")
        );*/
        
        $session->addSuccess(
            Mage::helper('mailup')->__("Process Detail [DISABLED]")
        );

        $this->_redirect('*/*/index');
    }
}