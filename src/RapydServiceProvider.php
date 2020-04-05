<?php

namespace Zofe\Rapyd;

use Collective\Html\HtmlServiceProvider;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Zofe\Rapyd\Facades\DataEdit;
use Zofe\Rapyd\Facades\DataEmbed;
use Zofe\Rapyd\Facades\DataFilter;
use Zofe\Rapyd\Facades\DataForm;
use Zofe\Rapyd\Facades\DataGrid;
use Zofe\Rapyd\Facades\DataSet;
use Zofe\Rapyd\Facades\DataTree;
use Zofe\Rapyd\Facades\Documenter;
use Zofe\Rapyd\Facades\Rapyd;

class RapydServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__ . '/../views', 'rapyd');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'rapyd');

        //assets
        $this->publishes([__DIR__ . '/../public/assets' => public_path('packages/zofe/rapyd/assets')], 'assets');

        //config
        $this->publishes([__DIR__ . '/../config/rapyd.php' => config_path('rapyd.php')], 'config');
        $this->mergeConfigFrom(__DIR__ . '/../config/rapyd.php', 'rapyd');

        $this->publishes([
            __DIR__ . '/routes.php' => base_path() . '/routes/rapyd.php',
        ], 'routes');

        if (file_exists(base_path() . '/routes/rapyd.php')) {
            $this->loadRoutesFrom(base_path() . '/routes/rapyd.php');
        } else {
            $this->loadRoutesFrom(__DIR__ . '/routes.php');
        }

        include __DIR__ . '/macro.php';
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(HtmlServiceProvider::class);
        //$this->app->register(BurpServiceProvider::class);

        Rapyd::setContainer($this->app);

        $this->app->booting(static function () {
            $loader = AliasLoader::getInstance();

            $loader->alias('Request', Request::class);
            $loader->alias('Rapyd', Rapyd::class);

            //deprecated .. and more facade are really needed ?
            $loader->alias('DataSet', DataSet::class);
            $loader->alias('DataGrid', DataGrid::class);
            $loader->alias('DataForm', DataForm::class);
            $loader->alias('DataEdit', DataEdit::class);
            $loader->alias('DataFilter', DataFilter::class);
            $loader->alias('DataEmbed', DataEmbed::class);
            $loader->alias('DataTree', DataTree::class);
            $loader->alias('Documenter', Documenter::class);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
