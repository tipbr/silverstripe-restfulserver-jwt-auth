<?php

namespace Tipbr\Tasks;

use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;
use TipBr\DataObjects\PasswordResetRequest;

/**
 * Task to clean up expired PasswordResetRequests
 */
class PasswordResetCleanupTask extends BuildTask
{
    /**
     * @var string
     */
    protected $title = 'Clean up expired password reset requests';

    /**
     * @var string
     */
    protected $description = 'Deletes password reset requests that are more than 1 hour old';

    /**
     * @var bool
     */
    private static $segment = 'PasswordResetCleanupTask';

    /**
     * Run the task
     */
    public function run($request)
    {
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-1 hour'));

        // Get all expired requests
        $expiredRequests = PasswordResetRequest::get()->where([
            "Created < ?" => $cutoffTime
        ]);

        $count = $expiredRequests->count();

        if ($count > 0) {
            foreach ($expiredRequests as $request) {
                $request->delete();
            }

            DB::alteration_message("Deleted $count expired password reset requests.", 'deleted');
        } else {
            DB::alteration_message("No expired password reset requests found.", 'created');
        }
    }
}
