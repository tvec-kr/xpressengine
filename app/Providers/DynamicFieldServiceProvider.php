<?php
/**
 * Service provider
 *
 * PHP version 5
 *
 * @category    DyanmicField
 * @package     Xpressengine\DyanmicField
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL-2.1
 * @link        https://xpressengine.io
 */

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Xpressengine\DynamicField\ConfigHandler;
use Xpressengine\DynamicField\DatabaseProxy;
use Xpressengine\DynamicField\DynamicFieldHandler;
use Xpressengine\DynamicField\RegisterHandler;
use Xpressengine\DynamicField\RevisionManager;

/**
 * laravel service provider
 *
 * @category    DynamicField
 * @package     Xpressengine\DynamicField
 */
class DynamicFieldServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('xe.dynamicField', function ($app) {

            /** @var \Xpressengine\Database\VirtualConnectionInterface $connection */
            $connection = $app['xe.db']->connection();
            $proxyClass = $app['xe.interception']->proxy(DynamicFieldHandler::class, 'DynamicField');
            return new $proxyClass(
                $connection,
                new ConfigHandler($connection, $app['xe.config']),
                new RegisterHandler($this->app['xe.pluginRegister'], $this->app['events']),
                $app['view']
            );
        });

        $this->app->singleton('xe.dynamicField.revision', function ($app) {
            return new RevisionManager($app['xe.dynamicField']);
        });

        $this->resolving();
    }

    /**
     * Register resolving callbacks.
     *
     * @return void
     */
    protected function resolving()
    {
        $this->app->resolving('xe.db.proxy', function ($instance, $app) {
            $instance->register(new DatabaseProxy($app['xe.dynamicField']));
        });

        $this->app->resolving('validator', function ($instance) {
            $instance->extend('df_id', function ($attribute, $value) {
                if (! is_string($value) && ! is_numeric($value)) {
                    return false;
                }

                return preg_match('/^[a-zA-Z]+([a-zA-Z0-9_]+)?[a-zA-Z0-9]+$/', $value) > 0;
            });
            $instance->replacer('df_id', function ($message, $attribute, $rule, $parameters) {
                return xe_trans('xe::validation.df_id', ['attribute' => $attribute]);
            });
        });
    }
}
