<?php

namespace Tipbr\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use TipBr\DataObjects\PasswordResetRequest;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\Core\Config\Config;

class PasswordResetCleanupTask extends BuildTask
{
    protected string $title = 'Clean up expired password reset requests';
    protected static string $description = 'Deletes password reset requests that are more than 1 hour old';
    private static $segment = 'PasswordResetCleanupTask';

    /**
     * Configurable cutoff time for deleting expired password reset requests.
     * Default is '1 hour' - requests older than this will be deleted.
     * Format: any string acceptable by strtotime() with a leading minus sign (e.g., '1 hour', '30 minutes', '2 days')
     */
    private static string $cutoff_time = '1 hour';

    public function execute(InputInterface $input, PolyOutput $output): int
    {
        $cutoffTime = Config::inst()->get(self::class, 'cutoff_time');
        $cutoffTimestamp = date('Y-m-d H:i:s', strtotime('-' . $cutoffTime));

        // Get all expired requests
        $expiredRequests = PasswordResetRequest::get()->filter([
            "Created:LessThan" => $cutoffTimestamp
        ]);

        $count = $expiredRequests->count();

        if ($count > 0) {
            foreach ($expiredRequests as $request) {
                $request->delete();
            }
            $output->writeln("Deleted $count expired password reset requests (older than $cutoffTime).");
        } else {
            $output->writeln("No expired password reset requests found (older than $cutoffTime).");
        }

        return 0;
    }
}
