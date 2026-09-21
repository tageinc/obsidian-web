<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessAppUpdateMail extends Command
{
    protected $signature = 'mail:work';

    protected $description = 'Process a bounded batch of queued application update emails';

    public function handle(): int
    {
        // The existing minute scheduler starts this worker. Individual jobs
        // control retry deadlines and exception limits, including pacing delays.
        return $this->call('queue:work', [
            'connection' => 'app-updates',
            '--queue' => 'mail',
            '--stop-when-empty' => true,
            '--max-time' => 45,
            '--max-jobs' => 100,
            '--sleep' => 1,
            '--timeout' => 30,
            '--tries' => 0,
        ]);
    }
}
