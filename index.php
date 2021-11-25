<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Google\Cloud\Storage\StorageClient;
use Slim\Http\UploadedFile;

use Slim\App;

require './vendor/autoload.php';

require './settings.php';

// Create app
$app = new App($settings);

// Define app routes
$app->get('/hello/{name}', function ($request, $response, $args) {
    return $response->write("Hello " . $args['name']);
});

// Define app routes
$app->get('/', function ($request, $response) {
    return $response->write("Hi");
});

// Run app
$app->run();