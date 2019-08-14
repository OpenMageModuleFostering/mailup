<?php
/**
 * Filters.php
 * 
 * Adminhtml block for the filters section
 */
class SevenLike_MailUp_Block_Filters extends Mage_Core_Block_Template
{
    public function _toHtml()
    {
	    return parent::_toHtml();
    }
    
    /**
     * Get an array of all stores
     * 
     * @return  array
     */
    protected function _getStoresArray()
    {
        $config = Mage::getModel('mailup/config');
        /* @var $config SevenLike_Mailup_Model_Config */
        return $config->getStoreArray();
    }
}
