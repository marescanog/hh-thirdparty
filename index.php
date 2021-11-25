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
$app->post('/google-cloud-api/upload-single', function ($request, $response) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS='.$_ENV['GOOGLE_APPLICATION_CREDENTIALS']); 

    $projectId = $_ENV['GOOGLE_CLOUD_PROJECT_ID'];

    $storage = new StorageClient([
        'projectID' => $projectId
    ]);

    $retVal = "";
    $isValid = true;

    // User-submitted data
    $bucketName = "nbi-photos";
    $uploadedFiles = $request->getUploadedFiles();

    // handle single input with single file upload
    $uploadedFile = $uploadedFiles['file'];

    $extension = strtolower(pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION));
    $allowed = array("jpg", "jpeg", "png", "pdf");
    $newFileName = "";

    // Function that reformats the file name to random name
    function renameFile($ext) {
        $basename = bin2hex(random_bytes(50)); // see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('%s.%0.8s', $basename, $ext);
        return $filename;
    }

    // Uploads the bucket into the Google Cloud Storage location
    function uploadObject($bucketName, $objectName, $source)
    {
        // $bucketName = 'my-bucket';
        // $objectName = 'my-object';
        // $source = '/path/to/your/file';

        $storage = new StorageClient();
        $file = fopen($source, 'r');
        $bucket = $storage->bucket($bucketName);
        $object = $bucket->upload($file, [
            'name' => $objectName
        ]);
        printf('Uploaded %s to gs://%s/%s' . PHP_EOL, basename($source), $bucketName, $objectName);
    }

    // Check if bucket name empty 
    if($isValid && $bucketName == null){
        $retVal = "No bucket name was provided. Please specify a bucket";
        $isValid = false;
    }

    // Check if bucket name exists 
    if($isValid){

    }

    // Check if file name exists in the bucket
    if($isValid){

    }

    // Upload the file
    if($isValid){ 
        // $status = $result == false? 400 : 200;
        // $retVal = $result == false? "There was something wrong with the file transfer" : "File was uploaded successfully.";

        $objectName = renameFile($extension);
        $source = $uploadedFile->file;

        uploadObject($bucketName, $objectName, $source);

        // $fileLocation = "https://storage.googleapis.com/$bucketName/$objectName";
        $status = 200;
        $retval = "File successfully uploaded to $bucketName";
    }

    $response->getBody()->write($retVal);


    return $response;
});

// Define message bird SMS route
$app->get('/messagebird/test', function (Request $request, Response $response) {
    // Load and initialize MessageBird SDK
    $messagebird = new MessageBird\Client($_ENV['MESSAGEBIRD_API_KEY']);

    // Create verify object
    $verify = new \MessageBird\Objects\Verify();
    $verify->recipient = $_ENV['TEST_NUMBER'];

    $extraOptions = [
        'originator' => '',
        'timeout' => 60*5,
        'type' => 'sms',
    ];

    // Make Request to Verify API
    try{
        $result = $verifyResult = $messagebird->verify->create($verify, $extraOptions);
    } catch (Exception $e){
        // Request has failed
        return $response->getBody()->write("SMS request has failed".$e->getMessage());
    }

    // Request was successful
    $response->getBody()->write("SMS Request Successful");

    return $response;
});


// Run app
$app->run();