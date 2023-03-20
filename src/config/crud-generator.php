<?php

return [
    'swagger_enabled' => false,
    'model_path' => app_path('Models'),
    'request_path' => app_path('Http/Requests/Api'),
    'controller_path' => app_path('Http/Controllers/Api'),
    'routes' => [
        'file' => base_path('routes/api.php'),
        'seperate' => true,
        'seperate_folder' => base_path('routes/api'),
    ]
];
