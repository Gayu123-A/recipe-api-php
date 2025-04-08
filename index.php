<?php
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;  // use the Dotenv class from the "vlucas/phpdotenv" library

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once 'database.php';
require_once __DIR__ .'/services/RecipeService.php';
require_once __DIR__ .'/services/AuthService.php';

// Create an instance of RecipeService
$service = new RecipeService($conn);

// Sets response content type to JSON for all responses
// header("Content-Type: application/json");

// Dummy user credentials
$dummyUser = [
    'email' => 'admin@example.com',
    'password' => 'admin123'
];

$method = $_SERVER['REQUEST_METHOD'];
$path = explode('/', trim($_SERVER['PATH_INFO'] ?? '/', '/'));

try{
    if($path[0] === 'recipes'){
        $id = $path[1] ?? null; // recipe Id
        $subPath = $path[2] ?? null;

        // API Routes. NOTE: "php://input" is used to read raw data from the request body (used in APIs).
        switch($method){
            // Read recipes by ID
            // List all the recipes
            case 'GET':
                if($id){
                    // http://localhost/index.php/recipes/1
                    echo json_encode($service->getRecipe($id));
                }else{
                    // http://localhost/index.php/recipes
                    echo json_encode($service->listRecipes());
                }
                break;
            // Create a new recipe
            // Rate a recipe from a scale of 1 to 5
            case 'POST':
                $inputData = json_decode(file_get_contents("php://input"), true);
                if($id && $subPath){
                    // http://localhost/index.php/recipes/10/rating
                    echo json_encode($service->rateRecipe($id, $inputData['rating']));
                }else{
                    // http://localhost/index.php/recipes
                    authenticateRequest();
                    echo json_encode($service->createRecipe($inputData));
                }
                break;
            // Update a recipe
            case 'PUT':
            case 'PATCH':
                if($id){
                    // http://localhost/index.php/recipes/10
                    authenticateRequest();
                    $updateData = json_decode(file_get_contents("php://input"), true);
                    echo json_encode($service->updateRecipe($id, $updateData));
                }else{
                    http_response_code(400);
                    echo json_encode(["error" => "Missing Recipe ID"]);
                }
                break;
            // Delete a recipe
            case 'DELETE':
                if($id){
                    // http://localhost/index.php/recipes/10
                    authenticateRequest();
                    echo json_encode($service->deleteRecipe($id));
                }else{
                    http_response_code(400);
                    echo json_encode(["error" => "Missing Recipe ID"]);
                }
                break;
            default:
                http_response_code(405);
                echo json_encode(["error" => "Method not allowed"]);
                break;
        }

    }elseif($method === 'POST' && $path[0] === 'login'){
        $loginData = json_decode(file_get_contents("php://input"), true);

        if(isset($loginData['email'], $loginData['password']) && $loginData['email'] === $dummyUser['email'] && $loginData['password'] === $dummyUser['password']){

            $authService = new AuthService();
            $token = $authService->generateToken(1); // assume user_id = 1

            echo json_encode([
                "token" => $token,
                "message" => "Login successful"
            ]);
        }else{
            http_response_code(401);
            echo json_encode(["error" => "Invalid credentials"]);
        }
    }else{
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
    }
}catch(Exception $e){
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['error' => $e->getMessage()]);
}

function authenticateRequest() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(["error" => "Missing Authorization header"]);
        exit;
    }

    $authHeader = $headers['Authorization'];
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid Authorization format"]);
        exit;
    }

    $token = $matches[1];
    $authService = new AuthService();
    $validation = $authService->validateToken($token);
    if (isset($validation['error'])) {
        http_response_code(401);
        echo json_encode($validation);
        exit;
    }

    return $validation; // contains user_id
}
?>