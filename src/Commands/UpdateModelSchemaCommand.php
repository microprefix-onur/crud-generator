<?php

namespace Microprefix\CrudGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UpdateModelSchemaCommand extends Command
{
    protected $signature = 'crud:schema {model : The name of the model to generate resources for}';
    protected $description = 'Update the OpenAPI schema for a model using the migration file';

    protected $exclude = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $model = $this->argument('model');

        // Get the table name for the model
        $table = Str::snake(Str::plural($model));

        $columns = $this->getTableColumns($table);

        if(sizeof($columns) === 0) {
            $this->error('No columns found for table ' . $table);
            $this->error('Please make sure you migrated the table before updateing the schema');
            return;
        }

        $modelsPath = app('config')->get('crud-generator.model_path', app_path('Models'));
        $modelFile = $modelsPath . '/' . $model . '.php';

        $modelContents = File::get($modelFile);

        $pattern = '/\/\*\*\n\s+\*\s+@OA\\\\Schema\s*\([\s\S]*?\n\s+\*\s+\)\n\s+\*\//';

        $schemaString = $this->generateSchemaString($model, $columns);

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

    protected function generateSchemaString($modelName, $columns)
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

        $schemaString = "/**" . PHP_EOL .
            " * @OA\Schema(" . PHP_EOL .
            " *     schema=\"" . $schemaParams['schema'] . "\"," . PHP_EOL .
            " *     title=\"" . $schemaParams['title'] . "\"," . PHP_EOL .
            " *     description=\"" . $schemaParams['description'] . "\"," . PHP_EOL .
            " *     @OA\Xml(name=\"" . $schemaParams['schema'] . "\")," . PHP_EOL;

        $schemaRequired = [];

        foreach ($columns as $property) {
            $schemaString .= " *     @OA\Property(";
            $schemaString .= "type=\"" . $property['type'] . "\", " ;
            if($property['required']) {
                $schemaString .= "example=\"" . ($property['default'] ?: '') . "\", ";
                $schemaRequired[] = $property['property'];
            } else {
                $schemaString .= "nullable=true, ";
            }
            $schemaString .= "property=\"" . $property['property'] . "\")," . PHP_EOL;
        }

        if(count($schemaRequired) > 0) {
            $schemaString .= " *    required={\"" . implode('", "', $schemaRequired) . "\"}," . PHP_EOL;
        }

        $schemaString .= " * )" . PHP_EOL;
        $schemaString .= " */";

        return $schemaString;
    }

    protected function getTableColumns($tableName)
    {
        $connection = $this->laravel['db']->connection();

        $schema = $connection->getDoctrineSchemaManager();

        $table = $schema->listTableDetails($tableName);

        if(!$table) {
            return [];
        }

        $columns = [];

        foreach ($table->getColumns() as $column) {
            if (in_array($column->getName(), $this->exclude)) {
                continue;
            }
            $columnName = $column->getName();
            $columnType = $column->getType()->getName();
            $columnIsRequired = $column->getNotnull();
            $columnDefaultValue = $column->getDefault();

            $columns[] = [
                'property' => $columnName,
                'type' => $columnType,
                'required' => $columnIsRequired,
                'default' => $columnDefaultValue,
            ];
        }

        return $columns;
    }
}
