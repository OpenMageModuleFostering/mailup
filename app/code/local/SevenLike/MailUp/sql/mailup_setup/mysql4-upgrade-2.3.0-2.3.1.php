<?php

$this->startSetup();
/**
 * We want to record the process id, and the number of attempts we've made at 
 * processing the job!
 */
$this->run("
    ALTER TABLE mailup_sync_jobs 
    ADD `process_id` INT UNSIGNED DEFAULT NULL,
    ADD `tries` INT UNSIGNED DEFAULT 0;
");

$this->endSetup();