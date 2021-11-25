<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Google\Cloud\Storage\StorageClient;
use Slim\Http\UploadedFile;

use Slim\App;

require './vendor/autoload.php';

$settings = require_once  __DIR__ ."/settings.php";

// Create app
$app = new App($settings);

// Load configuration with dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Get Container
$container = $app->getContainer();

// Define app routes
$app->get('/', function ($request, $response) {
    return $response->write("Hi");
});

// Define app routes
$app->get('/hello/{name}', function ($request, $response, $args) {
    return $response->write("Hello " . $args['name']);
});

// Define google cloud upload route
$app->get('/google-cloud-api/upload-single', function ($request, $response) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS='.$_ENV['GOOGLE_APPLICATION_CREDENTIALS']); 
    
    $projectId = $_ENV['GOOGLE_CLOUD_PROJECT_ID'];

    $storage = new StorageClient([
        'projectID' => $projectId
    ]);

    return $response->write("This is the google cloud route");
});

// Run app
$app->run();