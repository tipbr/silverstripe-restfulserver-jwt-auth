<?php

namespace Tipbr\Tasks;

use TipBr\Tasks\PasswordResetCleanupTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\CronTask\Interfaces\CronTask;
use Symfony\Component\Console\Input\ArrayInput;

class PasswordResetRequestCleanup_CronTask implements CronTask
{
    public function getSchedule()
    {
        return "*/5 * * * *";
    }

    public function process()
    {
        $task = new PasswordResetCleanupTask();
        
        $input = new ArrayInput([]);
        $output = new PolyOutput('cli');

        $task->execute($input, $output);
    }
}
