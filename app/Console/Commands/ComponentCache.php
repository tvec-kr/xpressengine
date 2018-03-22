<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Xpressengine\Plugin\PluginRegister;

class ComponentCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'comp:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a component cache file for faster component registration';

    /**
     * The register for plugin
     *
     * @var PluginRegister
     */
    protected $register;

    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param PluginRegister $register
     * @param Filesystem     $files
     * @return void
     */
    public function __construct(PluginRegister $register, Filesystem $files)
    {
        parent::__construct();

        $this->register = $register;
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->call('comp:clear');

        $components = $this->register->getAllComponents();

        $this->files->put(
            $this->getCachedComponentsPath(), $this->buildComponentCacheFile($components)
        );

        $this->info('Routes cached successfully!');
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

    /**
     * Build the component cache file.
     *
     * @param array $components
     * @return mixed
     */
    protected function buildComponentCacheFile($components)
    {
        $stub = $this->files->get(__DIR__.'/stubs/components.stub');

        return str_replace('{{components}}', base64_encode(serialize($components)), $stub);
    }
}
