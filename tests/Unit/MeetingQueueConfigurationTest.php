<?php

use App\Jobs\ProcessMeetingTranscriptJob;

it('reserves long meeting summary jobs beyond their timeout', function (string $connection): void {
    $job = new ProcessMeetingTranscriptJob(1);
    $queueConfig = require dirname(__DIR__, 2).'/config/queue.php';

    expect($queueConfig['connections'][$connection]['retry_after'])
        ->toBeInt()
        ->toBeGreaterThan($job->timeout);
})->with([
    'database',
    'beanstalkd',
    'redis',
]);
