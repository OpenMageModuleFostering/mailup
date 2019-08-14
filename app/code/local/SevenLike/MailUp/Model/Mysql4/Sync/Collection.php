<?php
/**
 * Collection.php
 */
class SevenLike_MailUp_Model_Mysql4_Sync_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        $this->_init("mailup/sync");
    }
}