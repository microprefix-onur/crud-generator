<?php

namespace Microprefix\CrudGenerator\Commands;

use Doctrine\Inflector\InflectorFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateApiResourcesCommand extends Command
{
    protected $signature = 'crud:api {model : The name of the model to generate resources for}';
    protected $description = 'Generate API resources for a given model';

    protected $modelName;
    protected $modelPath;
    protected $controllerName;
    protected $controllerPath;
    protected $requestName;
    protected $requestPath;
    protected $routePath;

    public function handle()
    {
        $model = $this->argument('model');
        $this->modelName = Str::studly($model);
        $modelsPath = app('config')->get('crud-generator.model_path', app_path('Models'));
        $modelClass = "{$this->modelPath}\\{$this->modelName}";

        if (!class_exists($modelClass)) {
            $this->createModel();
        }

        $this->createController();
        $this->createRequest();
        $this->createRoutes();

        $this->info('API resources generated successfully');

        if(app('config')->get('crud-generator.swagger_enabled')) {
            Artisan::call('l5-swagger:generate');
            $this->info("Swagger Documentation Updated");
        }
        $this->info("");
        $this->info("Please configure the migrations file to your needs and run 'php artisan migrate'");
    }

    protected function createModel()
    {
        $stubPath = __DIR__ . '/../stubs/model.stub';
        $modelsPath = app('config')->get('crud-generator.model_path', app_path('Models'));

        if (!File::exists($modelsPath)) {
            File::makeDirectory($modelsPath, 0755, true);
        }

        $modelStub = File::get($stubPath);
        $modelStub = str_replace('{{model}}', $this->modelName, $modelStub);
        $modelStub = str_replace('{{namespace}}', $this->getNamespace($modelsPath), $modelStub);

        $modelPath = $modelsPath . '/' . $this->modelName . '.php';
        File::put($modelPath, $modelStub);

        $this->info("Model created: {$modelPath}");

        $this->createMigration();
    }

    protected function createMigration()
    {
        $stubPath = __DIR__ . '/../stubs/migration.stub';
        $pluralModel = Str::plural($this->modelName);
        $table = Str::lower(Str::snake($pluralModel));
        $migrationStub = File::get($stubPath);
        $migrationStub = str_replace('{{model}}', $this->modelName, $migrationStub);
        $migrationStub = str_replace('{{pluralModel}}', $pluralModel, $migrationStub);
        $migrationStub = str_replace('{{slugPluralModel}}', $table, $migrationStub);
        $migrationStub = str_replace('{{namespace}}', 'Database\Migrations', $migrationStub);
        $migrationStub = str_replace('{{inflector}}', 'Illuminate\Support\Str', $migrationStub);

        $migrationName = 'create_' . Str::snake($pluralModel) . '_table';
        $migrationPath = database_path("migrations/" . date('Y_m_d_His') . "_{$migrationName}.php");

        File::put($migrationPath, $migrationStub);

        $this->info("Migration created: {$migrationPath}");
    }

    protected function createController()
    {
        $this->controllerName = "{$this->modelName}Controller";
        $controllersPath = app('config')->get('crud-generator.controller_path', app_path('Http/Controllers/Api'));
        $this->controllerPath = $controllersPath . "/{$this->controllerName}.php";
        $pluralModel = Str::plural($this->modelName);
        $table = Str::snake(Str::plural($pluralModel));
        $modelsPath = app('config')->get('crud-generator.model_path', app_path('Models'));
        $requestsPath = app('config')->get('crud-generator.request_path', app_path('Http/Requests/Api'));


        if (!File::exists($controllersPath)) {
            File::makeDirectory($controllersPath, 0755, true);
        }

        $inflector = InflectorFactory::create()->build();

        $stubPath = __DIR__ . '/../stubs/controller.stub';
        $controllerStub = File::get($stubPath);
        $controllerStub = str_replace('{{modelPath}}', $this->getNamespace($modelsPath), $controllerStub);
        $controllerStub = str_replace('{{requestPath}}',  $this->getNamespace($requestsPath), $controllerStub);
        $controllerStub = str_replace('{{model}}', $this->modelName, $controllerStub);
        $controllerStub = str_replace('{{table}}', $table, $controllerStub);
        $controllerStub = str_replace('{{modelVariablePlural}}', Str::plural(Str::camel($this->modelName)), $controllerStub);
        $controllerStub = str_replace('{{modelVariable}}', Str::camel($this->modelName), $controllerStub);
        $controllerStub = str_replace('{{namespace}}', $this->getNamespace($controllersPath), $controllerStub);
        $controllerStub = str_replace('{{route_path}}', Str::snake(Str::plural($this->modelName)), $controllerStub);
        $controllerStub = str_replace('{{model_request}}', $this->requestName, $controllerStub);

        if (app('config')->get('crud-generator.swagger_enabled')) {
            $controllerStub = str_replace('@if (config(\'crud-generator.swagger_enabled\'))', '', $controllerStub);
            $controllerStub = str_replace('@endif', '', $controllerStub);
        } else {
            $controllerStub = preg_replace('/@if.*?@endif\n/ms', '', $controllerStub);
        }

        File::put($this->controllerPath, $controllerStub);

        $this->info("Controller created: {$this->controllerPath}");
    }


    protected function createRequest()
    {
        $this->requestName = "{$this->modelName}Request";

        $requestsPath = app('config')->get('crud-generator.request_path', app_path('Http/Requests/Api'));

        // Check if the requests directory doesn't exist, and create it if it doesn't
        if (!File::exists($requestsPath)) {
            File::makeDirectory($requestsPath, 0755, true);
        }

        $this->requestPath = $requestsPath . '/' . $this->requestName . '.php';

        $stubPath = __DIR__ . '/../stubs/request.stub';
        $requestStub = File::get($stubPath);
        $requestStub = str_replace('{{model}}', $this->modelName, $requestStub);
        $requestStub = str_replace('{{namespace}}', $this->getNamespace($requestsPath), $requestStub);

        File::put($this->requestPath, $requestStub);

        $this->info("Request created: {$this->requestPath}");
    }

    protected function createRoutes()
    {
        $this->routePath = app('config')->get('crud-generator.routes.file', base_path('routes/api.php'));
        $routeSeperatePath = app('config')->get('crud-generator.routes.seperate_folder', base_path('routes/api'));
        $controllersPath = app('config')->get('crud-generator.controller_path', app_path('Http/Controllers/Api'));
        $modelPluralSlug = Str::slug(Str::plural($this->modelName));

        $inflector = InflectorFactory::create()->build();

        $stubPath = __DIR__ . '/../stubs/routes.stub';
        $routeStub = File::get($stubPath);
        $routeStub = str_replace('{{model}}', Str::snake(Str::plural($this->modelName)), $routeStub);
        $routeStub = str_replace('{{namespace}}', $this->getNamespace($controllersPath . "/{$this->controllerName}"), $routeStub);

        if (app('config')->get('crud-generator.routes.seperate')) {
            $routeStub = str_replace('{{controller}}', $this->controllerName, $routeStub);
            $routeStub = str_replace('@if (config(\'crud-generator.routes.seperate\'))', '', $routeStub);
            $routeStub = str_replace('@endif', '', $routeStub);
            if (!File::exists($routeSeperatePath)) {
                File::makeDirectory($routeSeperatePath, 0755, true);
            }

            File::put($routeSeperatePath . '/' . $modelPluralSlug . '.php', $routeStub);
            File::append($this->routePath, "\n" . "Route::group([], __DIR__ . '/" . $modelPluralSlug .".php');");

            $this->info("Route file generated and added to: {$this->routePath}");
        } else {
            $routeStub = str_replace('{{controller}}', $this->getNamespace($controllersPath . "/{$this->controllerName}"), $routeStub);
            $routeStub = preg_replace('/@if.*?@endif\n/ms', '', $routeStub);
            File::append($this->routePath, "\n" . $routeStub);

            $this->info("Routes added to: {$this->routePath}");
        }
    }

    function getNamespace($path) {
        return 'App' . str_replace('/', '\\', str_replace(app_path(), '', $path));
    }
}
