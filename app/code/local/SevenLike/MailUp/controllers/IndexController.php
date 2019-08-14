<?php
/**
 * IndexController.php
 */
class SevenLike_MailUp_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * Default Action
     */
    public function indexAction()
    {
        return;
        
        $config = Mage::getModel('mailup/config');
        /* @var $config SevenLike_Mailup_Model_Config */
        
        $cartCollection = Mage::getResourceModel('reports/quote_collection');
        //$cartCollection->prepareForAbandonedReport(array(1));
        $cartCollection->prepareForAbandonedReport($config->getAllStoreIds());
        $cartCollection->addFieldToFilter('customer_id', 6);
        $cartCollection->load();
        
        $end = end($cartCollection);
        
        var_dump($end);
        
        $end = $cartCollection->getLastItem();
        
        //var_dump($cartCollection);
        
        foreach($cartCollection as $cart) {
            //var_dump($cart);
            
            echo $cart->getGrandTotal() . "<br />";
            
        }
        
        
        var_dump($end);
        
        die('done');
    }
    
    /**
     * Clean the Resource Table.
     */
    public function cleanAction()
    {
        return;
        
        Mage::helper('mailup')->cleanResourceTable();
    }
    
    public function showAction()
    {
        return;
        
        Mage::helper('mailup')->showResourceTable();
    }
}
