<?php
namespace Latrell\Lock\Console;

use Illuminate\Console\Command;
use Latrell\Lock\LockManager;

class ClearCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lock:clear {--timeout=300 : 超时时间，默认五分钟。} {--store= : 清理的仓库名称。}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清理过期的死锁。';

    /**
     * The lock manager instance.
     *
     * @var \Latrell\Lock\LockManager
     */
    protected $lock;

    /**
     * Create a new lock clear command instance.
     *
     * @param  \Latrell\Lock\LockManager  $lock
     * @return void
     */
    public function __construct(LockManager $lock)
    {
        parent::__construct();

        $this->lock = $lock;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $timeout = $this->option('timeout');
        $store_name = $this->option('store');

        $this->laravel['events']->fire('lock:clearing', [
            $store_name
        ]);

        $num = $this->lock->store($store_name)->clear($timeout);

        $this->laravel['events']->fire('lock:cleared', [
            $store_name
        ]);

        $this->info("cleared {$num} lock!");
    }
}
