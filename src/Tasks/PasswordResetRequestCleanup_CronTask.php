<?php

namespace Tipbr\Tasks;

use SilverStripe\CronTask\Interfaces\CronTask;
use TipBr\Tasks\PasswordResetCleanupTask;

/**
 * Scheduled task to clean up expired password reset requests
 */
class PasswordResetRequestCleanup_CronTask implements CronTask
{
    /**
     * Run this task every 5 minutes
     *
     * @return string
     */
    public function getSchedule()
    {
        return "*/5 * * * *";
    }

    /**
     * Task to run
     *
     * @return void
     */
    public function process()
    {
        $task = new PasswordResetCleanupTask();
        $task->run(null);
    }
}
