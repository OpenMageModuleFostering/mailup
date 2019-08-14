<?php

class SevenLike_MailUp_Model_Adminhtml_System_Source_Cron_Frequency
{
    const HOURLY = 0;
    const EVERY_2_HOURS = 1;
    const EVERY_6_HOURS = 2;
    const EVERY_12_HOURS = 3;
	const DAILY = 4;

    /**
     * Fetch options array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'label' => 'Hourly',
                'value' => self::HOURLY),
            array(
                'label' => 'Every 2 Hours',
                'value' => self::EVERY_2_HOURS),
            array(
                'label' => 'Every 6 hours',
                'value' => self::EVERY_6_HOURS),
            array(
                'label' => 'Every 12 hours',
                'value' => self::EVERY_12_HOURS),
            array(
                'label' => 'Daily',
                'value' => self::DAILY),

        );
    }
}
