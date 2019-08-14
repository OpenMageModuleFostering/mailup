<?php
/**
 * LogController.php
 */
class SevenLike_MailUp_Adminhtml_LogController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Default Action
     */
	public function indexAction()
    {
       $this->loadLayout();
	   $this->_title($this->__("Log Queue"));
	   $this->renderLayout();
    }
}