<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Google\Cloud\Storage\StorageClient;
use Slim\Http\UploadedFile;
use Respect\Validation\Validator as v;

use Slim\App;

require './vendor/autoload.php';

$settings = require_once  __DIR__ ."/settings.php";

require_once __DIR__."/customrequesthandler.php";

require_once __DIR__."/customresponsehandler.php";

// Create app
$app = new App($settings);

// Load configuration with dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// This is the middleware
// It will add the Access-Control-Allow-Methods header to every request

$app->add(function($request, $response, $next) {
    $route = $request->getAttribute("route");

    $methods = [];

    if (!empty($route)) {
        $pattern = $route->getPattern();

        foreach ($this->router->getRoutes() as $route) {
            if ($pattern === $route->getPattern()) {
                $methods = array_merge_recursive($methods, $route->getMethods());
            }
        }
        //Methods holds all of the HTTP Verbs that a particular route handles.
    } else {
        $methods[] = $request->getMethod();
    }

    $response = $next($request, $response);


    return $response->withHeader("Access-Control-Allow-Methods", implode(",", $methods));
});


// Get Container
$container = $app->getContainer();

// Define app routes
$app->get('/', function (Request $request,Response $response) {
    return $response->write("Hi");
});

// Define app routes
$app->get('/hello/{name}', function (Request $request,Response $response, array $args) {
    return $response->write("Hello " . $args['name']);
});

// Define google cloud upload route (Single File Upload)
$app->post('/google-cloud-api/upload-single', function (Request $request,Response $response) {
    putenv('GOOGLE_APPLICATION_CREDENTIALS='.$_ENV['GOOGLE_APPLICATION_CREDENTIALS']); 

    $projectId = $_ENV['GOOGLE_CLOUD_PROJECT_ID'];

    $storage = new StorageClient([
        'projectID' => $projectId
    ]);

    $retVal = "";
    $isValid = true;
    $status = 400;

    // User-submitted RAW data
    //$bucketName = "nbi-photos";
    $uploadedFiles = $request->getUploadedFiles(); //HTTPFile
    $arrayOfFileTypes = getParam($request, "file_types"); //JSON string
    $bucketName = getParam($request, "bucket_name"); //string


    // VALIDATION Validate & Process User-sent data
    // =============================================
    // VALIDATION 1: Make sure file is not empty & is a file
        // Get the 'file' feild, check if empty
        $uploadedFile = isset($uploadedFiles['file']) ? $uploadedFiles['file'] : null; 
        if($isValid && $uploadedFile == null){
            $retVal = "Empty or Invalid file format";
            $isValid = false;
        }
        if (empty($uploadedFiles['file'])) {
            $retVal = "Empty or Invalid file format";
            $isValid = false;
        }


        // Check if no errors on upload
        if($isValid && $uploadedFile->getError()){
            $retVal = "The was an error uploading the file, please try again.";
            $isValid = false;
        }

    // VALIDATION 2: Make sure file_type is an array & not empty
        // Check if empty
        if($isValid && $arrayOfFileTypes == null){
            $retVal = "An array of file types should be specified (file_type)";
            $isValid = false;
        }     

        // Convert string into PHP array if it isnt one already
        $decodedarray_file_type = gettype($arrayOfFileTypes) !== "object" ? json_decode($arrayOfFileTypes) : $arrayOfFileTypes;
        if($isValid && $decodedarray_file_type == null){
            $retVal = "Parse error on file_type, please check if array";
            $isValid = false;
        }

        // Check if input is numeric
        if($isValid && is_numeric($decodedarray_file_type)){
            $retVal = "file_type should be an array or JSON string array";
            $isValid = false;
        }

    // VALIDATION 3: bucket_name is a atring and not empty
        // Check if input is empty
        if($isValid && $bucketName == ""){
            $retVal = "bucket_name should not be empty";
            $isValid = false;
        }

        // Check if input is empty
        if($isValid && $bucketName == null){
            $retVal = "bucket_name should not be empty";
            $isValid = false;
        }


    // VALIDATION Check Validity of Data within Context of use
    // =============================================
        // get file's current extension
        $extension = strtolower(pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION));

    // VALIDATION 4: check if file type is one of the files in the array
    if($isValid && !in_array($extension, $decodedarray_file_type)){
        $retVal = "Invalid file type. Please upload only ";
        for($y = 0; $y < count($decodedarray_file_type); $y++){
            $retVal = $retVal." ".$decodedarray_file_type[$y].", ";
        }
        $retVal =  $retVal."file types.";
        $isValid = false;
    }

    // VALIDATION 5: check if file type exceeds 5MB, 5000000   //max size 5000 kb 5MB
    if($isValid && $uploadedFile->getSize() > 5000000){
        $retVal = "File upload should not exceed 5MB.";
        $isValid = false;
    }

    // VALIDATION 6: check if bucket name is a valid bucket name
        // get list of buckets from google api
        $buckets = $storage->buckets();
        $bucket_list = [];
        foreach( $buckets as $bucket){
            array_push($bucket_list,  $bucket->name());
        }
        // check array
        if($isValid && !in_array($bucketName, $bucket_list)){
            $retVal = "Invalid bucket name. $bucketName is not in list of buckets.";
            $isValid = false;
        }

    // PREPARE FILES for upload
    // =============================================
    // when everything is good to go, we upload the files
    // Function that reformats the file name to random name
    function renameFile($ext) {
        $basename = bin2hex(random_bytes(50)); // see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('%s.%0.8s', $basename, $ext);
        return $filename;
    }

    // Function that Uploads the bucket into the Google Cloud Storage location
    function uploadObject($bucketName, $objectName, $source)
    {
        // $bucketName = 'my-bucket';
        // $objectName = 'my-object';
        // $source = '/path/to/your/file';

        $storage = new StorageClient();
        $file = fopen($source, 'r');
        $bucket = $storage->bucket($bucketName);

        try{
            $object = $bucket->upload($file, [
                'name' => $objectName
            ]);
        } catch (GoogleException $e){
            $response = [];
            $response['message'] = $e->getMessage();
            $response['status'] = 500;
            return $response;
        }

        $response = [];
        $response['message'] = "Uploaded ".basename($source)." to gs://".$bucketName."/".$objectName;
        $response['status'] = 200;
        return $response;
    }

    $fileLocation =  "";
    $objectName = "";
    // Upload the file
    if($isValid){ 

        $objectName = renameFile($extension);
        $source = $uploadedFile->file;

        $retVal = uploadObject($bucketName, $objectName, $source);

        if($retVal['status'] == 200){
            $fileLocation = "https://storage.googleapis.com/$bucketName/";
            $status = 200;
        } else {
            $status = 500;
        }
    }

    // Return 400 error on invalid request
    if($isValid == false && $status == 400){
        return is400Response($response,$retVal);
    } 

    // Return 500 error on invalid request
    if($isValid == false && $status == 500){
        return is500Response($response,$retVal);
    } 

    // Succeed if everything is good
    $data = [];
    $data['file_location'] = $fileLocation;
    $data['message'] = $retVal;
    $data ['newFileName'] = $objectName;
    return is200Response($response,$data);
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