<?php

namespace Tipbr\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use TipBr\DataObjects\PasswordResetRequest;
use Symfony\Component\Console\Input\InputInterface;

class PasswordResetCleanupTask extends BuildTask
{
    protected string $title = 'Clean up expired password reset requests';
    protected static string $description = 'Deletes password reset requests that are more than 1 hour old';
    private static $segment = 'PasswordResetCleanupTask';

    public function execute(InputInterface $input, PolyOutput $output): int
    {
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-1 hour'));

        // Get all expired requests
        $expiredRequests = PasswordResetRequest::get()->filter([
            "Created:LessThan" => $cutoffTime
        ]);

        $count = $expiredRequests->count();

        if ($count > 0) {
            foreach ($expiredRequests as $request) {
                $request->delete();
            }
            $output->writeln("Deleted $count expired password reset requests.");
        } else {
            $output->writeln("No expired password reset requests found.");
        }

        return 0;
    }
}
