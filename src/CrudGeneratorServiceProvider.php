<?php

namespace Microprefix\CrudGenerator;

use Illuminate\Support\ServiceProvider;
use Microprefix\CrudGenerator\Commands\GenerateApiResourcesCommand;
use Microprefix\CrudGenerator\Commands\UpdateModelSchemaCommand;

class CrudGeneratorServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/crud-generator.php', 'crud-generator');

        $this->publishes([
            __DIR__ . '/config/crud-generator.php' => config_path('crud-generator.php'),
        ], 'config');

        $this->commands([
            Commands\GenerateApiResourcesCommand::class,
            Commands\UpdateModelSchemaCommand::class,
        ]);
    }

    public function boot()
    {
        // ...
    }
}
