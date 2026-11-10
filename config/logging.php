<?php

use Monolog\Handler\StreamHandler;

return ['default' => env('LOG_CHANNEL', 'stack'), 'channels' => ['stack' => ['driver' => 'stack', 'channels' => explode(',', env('LOG_STACK', 'single'))], 'single' => ['driver' => 'single', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'debug')], 'stderr' => ['driver' => 'monolog', 'handler' => StreamHandler::class, 'with' => ['stream' => 'php://stderr']]]];

