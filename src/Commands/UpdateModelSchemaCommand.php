<?php

namespace Microprefix\CrudGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UpdateModelSchemaCommand extends Command
{
    protected $signature = 'crud:schema {model : The name of the model to generate resources for}';
    protected $description = 'Update the OpenAPI schema for a model using the migration file';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $model = $this->argument('model');
        $migrationFile = database_path('migrations/*_create_' . Str::snake(Str::plural($model)) . '_table.php');

        $migrationFiles = File::glob($migrationFile);

        if (empty($migrationFiles)) {
            $this->error('No migration file found for ' . $model . '.');
            return;
        }

        $migrationFile = $migrationFiles[0];

        $migrationContents = File::get($migrationFile);

        $pattern = '/\$table->(.*?)\;/';

        preg_match_all($pattern, $migrationContents, $matches);

        $properties = [];

        foreach ($matches[0] as $match) {
            if (strpos($match, '$table->id()') !== false) {
                continue;
            }
            if (strpos($match, '$table->timestamps()') !== false) {
                continue;
            }
            if (strpos($match, '$table->softDeletes()') !== false) {
                continue;
            }
            if (preg_match('/\$table->(.+?)\((.+?)\)/', $match, $matches2)) {
                $name = str_replace(['\'', '"'], '', $matches2[2]);
                $type = $matches2[1];
                $property = [
                    'property' => $name,
                    'type' => $type,
                    'description' => ucfirst($name),
                ];
                $properties[] = $property;
            }
        }

        $schema = [
            'title' => $model,
            'description' => $model . ' schema',
            'properties' => $properties,
        ];

        $schema = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $modelsPath = app('config')->get('crud-generator.model_path', app_path('Models'));
        $modelFile = $modelsPath . '/' . $model . '.php';

        $modelContents = File::get($modelFile);

        $pattern = '/\/\*\*\n\s+\*\s+@OA\\\\Schema\s*\([\s\S]*?\n\s+\*\s+\)\n\s+\*\//';

        $schemaString = $this->generateSchemaString($model, $properties);

        if (preg_match($pattern, $modelContents, $matches)) {
            $modelContents = str_replace($matches[0], $schemaString, $modelContents);
        } else {
            $modelContents = str_replace('class ' . $model . ' extends Model', $schemaString . PHP_EOL . 'class ' . $model . ' extends Model', $modelContents);
        }

        File::put($modelFile, $modelContents);

        $this->info('OpenAPI schema for ' . $model . ' updated successfully.');

        Artisan::call('l5-swagger:generate');
        $this->info("Swagger Documentation Updated");
    }

    protected function generateSchemaString($modelName, $params)
    {
        $schemaParams = [
            'schema' => $modelName,
            'title' => ucfirst($modelName) . ' Schema',
            'description' => ucfirst($modelName) . ' schema',
            '@OA\Xml' => [
                'name' => $modelName,
            ],
            'properties' => [],
        ];

        foreach ($params as $param) {
            $propertyName = $param['property'];
            $propertyType = $param['type'];
            $propertyDescription = $param['description'];

            $property = [
                'property' => $propertyName,
                'type' => $propertyType,
                'description' => $propertyDescription,
            ];

            $schemaParams['properties'][] = $property;
        }

        $schemaString = "/**" . PHP_EOL .
            " * @OA\Schema(" . PHP_EOL .
            " *     schema=\"" . $schemaParams['schema'] . "\"," . PHP_EOL .
            " *     title=\"" . $schemaParams['title'] . "\"," . PHP_EOL .
            " *     description=\"" . $schemaParams['description'] . "\"," . PHP_EOL .
            " *     @OA\Xml(name=\"" . $schemaParams['schema'] . "\")," . PHP_EOL;

        foreach ($schemaParams['properties'] as $property) {
            $schemaString .= " *     @OA\Property(property=\"" . $property['property'] . "\", type=\"" . $property['type'] . "\", description=\"" . $property['description'] . "\")," . PHP_EOL;
        }

        $schemaString .= " * )" . PHP_EOL;
        $schemaString .= " */";

        return $schemaString;
    }
}
