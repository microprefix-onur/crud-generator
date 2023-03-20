# Laravel CRUD Generator

The Laravel CRUD Generator package is a tool for generating API resources for your Laravel applications. With this package, you can easily create controllers, requests, and routes for your existing models and migrations.
Perfect for rapid prototyping and development. It uses Swagger annotations to generate the OpenAPI schema for your API resources.

Swagger annotation can be disabled in the config file.

[!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://www.buymeacoffee.com/microprefix)

## Installation

You can install the package via Composer:

```bash
composer require microprefix/crud-generator
```

After installing the package, you need to add the service provider to your config/app.php file:

```php
'providers' => [
    // ...
    Microprefix\CrudGenerator\CrudGeneratorServiceProvider::class,
];
```

## Usage

You can generate a resource by running the following command:

```bash
php artisan crud:generate Post
```

This command will generate a controller, request, and routes for the Test model. By default, the controller and request will be placed in the app/Http/Controllers and app/Http/Requests directories, respectively.:

- app/Http/Controllers/PostController.php
- app/Http/Requests/PostRequest.php
- routes/api.php
- database/migrations/2019_01_01_000000_create_posts_table.php


You can customize the paths for the generated files by setting the controller_path and request_path options in the package config file (config/crud-generator.php). You can also enable or disable Swagger annotations for the generated API resources by setting the use_swagger option in the config file.

To update the OpenAPI schema for a model based on its migration file, you can use the crud:schema Artisan command:

```bash
php artisan crud:schema Post
```

This command will update the OpenAPI schema for the Post model based on the fields defined in its migration file. You can customize the path for the schema file by setting the schema_path option in the package config file (config/crud-generator.php).

## Configuration

You can publish the configuration file with:

```bash
php artisan vendor:publish --provider="Microprefix\CrudGenerator\CrudGeneratorServiceProvider" --tag="config"
```

## Credits

This package is inspired by the DarkaOnLine/L5-Swagger package.

## License

The Laravel CRUD Generator package is open-source software licensed under the MIT license.

This README file provides an overview of the package, including installation and usage instructions, as well as credits and license information. You can customize this file to suit your specific needs and add additional sections if necessary.
