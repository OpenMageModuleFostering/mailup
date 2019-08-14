<?php
/**
 * Grid.php
 */
class SevenLike_MailUp_Block_Adminhtml_MailUp_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('MailUpGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * Prepare Collection
     *
     * @return
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('mailup/job')->getCollection();
        $this->setCollection($collection);

        //var_dump(Mage::getModel('mailup/job')->load(1));
        
        return parent::_prepareCollection();
    }

    /**
     * Prepare Grid Columns
     */
    protected function _prepareColumns()
    {        
        $this->addColumn('id', array(
          'header'    => Mage::helper('mailup')->__('ID'),
          //'align'     =>'right',
          'width'     => '80px',
          'index'     => 'id',
        ));
        
        $this->addColumn('type', array(
            'header'    => Mage::helper('mailup')->__('Type'),
            'align'     => 'left',
            'index'     => 'type',
            'type'      => 'options',
            'options'   => array(
                0   => 'Manual Sync',
                1   => 'Auto Sync',
                //2 => 'Disabled',
            ),
        ));
        
        $this->addColumn('store_id', array(
            'header'    => Mage::helper('mailup')->__('Store'),
            'align'     => 'left',
            //'width'     => '150px',
            'index'     => 'store_id',
            'type'      => 'options',
            'options'   => Mage::getModel('mailup/source_store')->getSelectOptions(),
        ));
        
        /*$this->addColumn('store_id', array(
          'header'    => Mage::helper('mailup')->__('Store ID'),
          //'align'     =>'right',
          'width'     => '80px',
          'index'     => 'store_id',
        ));*/
        
        $this->addColumn('mailupgroupid', array(
            'header'    => Mage::helper('mailup')->__('Mailup Group ID'),
            //'align'     =>'right',
            'width'     => '80px',
            'index'     => 'mailupgroupid',
        ));
        
        $this->addColumn('list_id', array(
            'header'    => Mage::helper('mailup')->__('Mailup List ID'),
            //'align'     =>'right',
            'width'     => '80px',
            'index'     => 'list_id',
        ));
        
        $this->addColumn('list_guid', array(
            'header'    => Mage::helper('mailup')->__('Mailup List GUID'),
            'index'     => 'list_guid',
        ));
        
        $this->addColumn('send_optin', array(
            'header'    => Mage::helper('mailup')->__('Opt In'),
            'align'     => 'left',
            'index'     => 'send_optin',
            'type'      => 'options',
            'options'   => array(
                0 => 'No',
                1 => 'Yes',
            ),
        ));
        
        $this->addColumn('as_pending', array(
            'header'    => Mage::helper('mailup')->__('As Pending'),
            'align'     => 'left',
            'index'     => 'as_pending',
            'type'      => 'options',
            'options'   => array(
                0 => 'No',
                1 => 'Yes',
            ),
        ));
        
        $this->addColumn('status', array(
            'header'    => Mage::helper('mailup')->__('Status'),
            //'align'     =>'right',
            'index'     => 'status',
        ));
        
        $this->addColumn('process_id', array(
            'header'    => Mage::helper('mailup')->__('Process ID'),
            //'align'     =>'right',
            'width'     => '80px',
            'index'     => 'process_id',
        ));
        
        $this->addColumn('tries', array(
            'header'    => Mage::helper('mailup')->__('Tries'),
            //'align'     =>'right',
            'width'     => '50px',
            'index'     => 'tries',
        ));
        
        $this->addColumn('queue_datetime', array(
            'header'    => Mage::helper('mailup')->__('Queue Time'),
            'type'      => 'datetime', // Add in Date Picker
            //'type'      => 'timestamp',
            //'align'     => 'center',
            'width'     => '180px',
            'index'     => 'queue_datetime',
            //'gmtoffset' => true
        ));
        
        $this->addColumn('start_datetime', array(
            'header'    => Mage::helper('mailup')->__('Started'),
            'type'      => 'datetime', // Add in Date Picker
            //'type'      => 'timestamp',
            //'align'     => 'center',
            'width'     => '180px',
            'index'     => 'start_datetime',
            //'gmtoffset' => true
        ));
        
        $this->addColumn('finish_datetime', array(
            'header'    => Mage::helper('mailup')->__('Finished'),
            'type'      => 'datetime', // Add in Date Picker
            //'type'      => 'timestamp',
            //'align'     => 'center',
            'width'     => '180px',
            'index'     => 'finish_datetime',
            //'gmtoffset' => true
        ));
        
//
//
//        $this->addColumn('status', array(
//            'header'    => Mage::helper('importer')->__('Status'),
//            'align'     => 'left',
//            'width'     => '80px',
//            'index'     => 'status',
//            'type'      => 'options',
//            'options'   => array(
//                1 => 'Enabled',
//                2 => 'Disabled',
//            ),
//        ));
//
        
        $this->addColumn('action',
            array(
                'header'    =>  Mage::helper('mailup')->__('Action'),
                'width'     => '100',
                'type'      => 'action',
                'getter'    => 'getId',
                'actions'   => array(
                    array(
                        'caption'   => Mage::helper('mailup')->__('Run'),
                        'url'       => array('base'=> '*/*/runjob'),
                        'field'     => 'id'
                    ),
                    array(
                        'caption'   => Mage::helper('mailup')->__('StartProcess'),
                        'url'       => array('base'=> '*/*/startProcess'),
                        'field'     => 'id'
                    ),
                    array(
                        'caption'   => Mage::helper('mailup')->__('GetProcessDetail'),
                        'url'       => array('base'=> '*/*/getProcessDetail'),
                        'field'     => 'id'
                    ),
                    array(
                        'caption'   => Mage::helper('mailup')->__('Delete'),
                        'url'       => array('base'=> '*/*/delete'),
                        'field'     => 'id'
                    ),
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'stores',
                'is_system' => true,
        ));


        return parent::_prepareColumns();
    }

//    /**
//     * Prepare Mass Action
//     */
//    protected function _prepareMassaction()
//    {
//        $this->setMassactionIdField('id');
//        $this->getMassactionBlock()->setFormFieldName('importer');
//
//        $this->getMassactionBlock()->addItem('delete', array(
//             'label'    => Mage::helper('importer')->__('Delete'),
//             'url'      => $this->getUrl('*/*/massDelete'),
//             'confirm'  => Mage::helper('importer')->__('Are you sure?')
//        ));
//
//        $statuses = Mage::getSingleton('importer/import')->getOptionArray();
//        array_unshift($statuses, array('label'=>'', 'value'=>''));
//        
//        return $this;
//    }

    /**
     * Get row url - None editable
     */
    public function getRowUrl($row)
    {
        return '';
        //return $this->getUrl('*/*/edit', array('id' => $row->getId()));
    }

}