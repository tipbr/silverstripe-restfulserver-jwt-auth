<?php

namespace Tipbr\Tasks;

use TipBr\Tasks\PasswordResetCleanupTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\CronTask\Interfaces\CronTask;
use Symfony\Component\Console\Input\ArrayInput;
use SilverStripe\Core\Config\Config;

class PasswordResetRequestCleanup_CronTask implements CronTask
{
    /**
     * Configurable cron schedule for password reset cleanup task.
     * Default is "every 5 minutes" pattern.
     * Format: standard cron expression (minute hour day month weekday)
     */
    private static string $schedule = "*/5 * * * *";

    public function getSchedule()
    {
        return Config::inst()->get(self::class, 'schedule');
    }

    public function process()
    {
        $task = new PasswordResetCleanupTask();
        
        $input = new ArrayInput([]);
        $output = new PolyOutput('cli');

        $task->execute($input, $output);
    }
}
