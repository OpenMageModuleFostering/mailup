<?php

require_once dirname(__FILE__) . "/../../Model/MailUpWsImport.php";
require_once dirname(__FILE__) . "/../../Model/Wssend.php";
class SevenLike_MailUp_Adminhtml_ConfigurationController extends Mage_Adminhtml_Controller_Action
{
	public function indexAction()
	{
		$url = Mage::getModel('adminhtml/url');
		$url = $url->getUrl("adminhtml/system_config/edit", array(
			"section" => "newsletter"
		));
		Mage::app()->getResponse()->setRedirect($url);
	}
}