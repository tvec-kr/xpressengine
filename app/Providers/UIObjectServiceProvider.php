<?php
/**
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL-2.1
 * @link        https://xpressengine.io
 */

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Xpressengine\UIObject\UIObjectHandler;

class UIObjectServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            'xe.uiobject',
            function ($app) {

                $aliases = $app['config']->get('xe.uiobject.aliases');

                $uiObjectHandler = $app['xe.interception']->proxy(UIObjectHandler::class, 'UIObejct');
                $uiObjectHandler = new $uiObjectHandler($app['xe.pluginRegister'], $aliases);

                return $uiObjectHandler;
            }
        );
    }

    public function boot()
    {
    }
}
