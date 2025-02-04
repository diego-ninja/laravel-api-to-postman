<?php

namespace AndreasElia\PostmanGenerator;

use AndreasElia\PostmanGenerator\Commands\ExportCollectionCommand;
use AndreasElia\PostmanGenerator\Exporters\InsomniaExporter;
use AndreasElia\PostmanGenerator\Exporters\PostmanExporter;
use AndreasElia\PostmanGenerator\Processors\RouteProcessor;
use Illuminate\Support\ServiceProvider;

class PostmanGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/api-postman.php' => config_path('api-postman.php'),
            ], 'postman-config');
        }

        $this->commands(ExportCollectionCommand::class);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/api-postman.php', 'api-postman'
        );

        $this->app->bind(PostmanExporter::class, function ($app) {
            return new PostmanExporter(
                $app['config'],
                $app->make(RouteProcessor::class)
            );
        });

        $this->app->bind(InsomniaExporter::class, function ($app) {
            return new InsomniaExporter(
                $app['config'],
                $app->make(RouteProcessor::class)
            );
        });
    }
}
