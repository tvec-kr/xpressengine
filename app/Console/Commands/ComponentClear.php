<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ComponentClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'comp:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the component cache file';

    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param Filesystem $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->files->delete($this->getCachedComponentsPath());

        $this->info('Route cache cleared!');
    }

    /**
     * Get the path to the cached components.php file.
     *
     * @return string
     */
    protected function getCachedComponentsPath()
    {
        return base_path(config('xe.plugin.cache_path.component'));
    }
}
