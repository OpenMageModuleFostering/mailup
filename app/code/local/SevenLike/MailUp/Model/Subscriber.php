<?php
/**
 * Subscriber.php
 * 
 * Override Magento subscriber to allow us to enable / disable the Notifications
 */
class SevenLike_MailUp_Model_Subscriber extends Mage_Newsletter_Model_Subscriber
{
    /**
     * Send Success Email
     * 
     * @override
     * @todo    make this per store scope!
     * @return  SevenLike_MailUp_Model_Subscriber
     */
    public function sendConfirmationSuccessEmail()
    {
    	if($this->_getConfig()->isNewsletterNotificationDisabled()) {
            Mage::log("Newsletter Notification DISABLED: sendConfirmationSuccessEmail");
        	return $this;
    	} 
        else {
    		return parent::sendConfirmationSuccessEmail();
    	}
    }
    
    
    /**
     * Send Confirmation request Email
     * 
     * @override
     * @todo    make this per store scope!
     * @return  SevenLike_MailUp_Model_Subscriber
     */
    public function sendConfirmationRequestEmail()
    {
        if($this->_getConfig()->isNewsletterNotificationDisabled()) {
            Mage::log("Newsletter Notification DISABLED: sendConfirmationRequestEmail");
        	return $this;
    	} 
        else {
    		return parent::sendConfirmationRequestEmail();
    	}
    }

    /**
     * Send the Emails
     * 
     * @override
     * @todo    make this per store scope!
     * @return  SevenLike_MailUp_Model_Subscriber
     */
    public function sendUnsubscriptionEmail()
    {
    	if($this->_getConfig()->isNewsletterNotificationDisabled()) {
            Mage::log("Newsletter Notification DISABLED: sendUnsubscriptionEmail");
        	return $this;
    	} 
        else {
    		return parent::sendUnsubscriptionEmail();
    	}
    }
    
    /**
     * Get the config
     * 
     * @return  SevenLike_MailUp_Model_Config
     */
	protected function _getConfig() 
    {
		return Mage::getModel('mailup/config');
	}
}
