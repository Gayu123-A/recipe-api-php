<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../services/RecipeService.php';

class RecipeApiTests extends TestCase{
    private $baseUrl;

    protected function setUp(): void{

        // This special hostname points from inside the container → to your host machine
        $this->baseUrl = "http://host.docker.internal:8080/index.php";

    }

    // Helper to get status code from response
    protected function getStatusCode(array $http_response_header): int{
        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 000 Unknown'; // e.g., "HTTP/1.1 200 OK"
        preg_match('{HTTP/\S+ (\d{3})}', $statusLine, $match);
        return isset($match[1]) ? (int) $match[1] : 0;
    }

    // Helper to get JWT token
    private function getJwtToken(): string
    {
        $loginUrl = $this->baseUrl . "/login";
        $loginPayload = json_encode([
            "email" => "admin@example.com",
            "password" => "admin123" 
        ]);

        $loginContext = stream_context_create([
            'http' => [
                'method' => "POST",
                'header' => "Content-Type: application/json",
                'content' => $loginPayload
            ]
        ]);

        $response = file_get_contents($loginUrl, false, $loginContext);
        $responseData = json_decode($response, true);

        $this->assertIsArray($responseData, "Login response is not JSON");
        $this->assertArrayHasKey('token', $responseData, "Token not returned from login");

        return $responseData['token'];
    }

    /**
     * Purpose:
     * Make a request to your API endpoint GET /recipes, and assert:
     * The status code is 200
     * The response is valid JSON
     * The first item of the response has 'recipe_name'
     */
    public function testListRecipes(){
        // This is the URL that lists all the recipes
        $url = $this->baseUrl."/recipes";

        // Send HTTP /GET request
        $response = file_get_contents($url);

        // Assert response status code is 200
        $statusCode = $this->getStatusCode($http_response_header);
        $this->assertEquals(200, $statusCode, "Expected status code 200, got $statusCode");

        // Assert response is not empty
        $this->assertNotEmpty($response, "API response is not empty");

        // Assert response is valid JSON
        $data = json_decode($response, true);
        $this->assertIsArray($data, "Response is not valid JSON");

        // Check response structure [If the $data is not empty, checks if the first item has a key called "recipe_name"]
        if(!empty($data)){
            $this->assertArrayHasKey('recipe_name', $data[0]);
        }
    }

    /**
     * Purpose:
     * Test the getRecipe() method of "RecipeService" class without using a real database — instead, it "mocks" the database response: * testGetRecipeReturnsDataIfFound() => to check if the method behaves correctly when a recipe is found.
     * testGetRecipeReturnsErrorIfNotFound() => to check if the method behaves correctly when a recipe is NOT found.
     */
    public function testGetRecipeReturnsDataIfFound() {
        // Sample recipe data
        $sampleRecipe = [
            "id" => 1,
            "recipe_name" => "Paneer Masala",
            "prep_time" => 30,
            "difficulty" => 2,
            "vegetarian" => true,
            "created_at" => "2025-04-08 12:00:00"
        ];

        // Mock the database query
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->with([1])->willReturn(true);
        $stmtMock->method('fetch')->willReturn($sampleRecipe);

        // Mock the database connection - PDO
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('prepare')->with('SELECT * FROM recipes WHERE id = ?')->willReturn($stmtMock);

        // Inject mock into service
        $service = new RecipeService($pdoMock);
        $result = $service->getRecipe(1); // Here, the recipe id is considered to be 1

        $this->assertIsArray($result);
        $this->assertEquals("Paneer Masala", $result['recipe_name']);
    }

    public function testGetRecipeReturnsErrorIfNotFound() {
        // Mock the database query
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->with([999])->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false); // fetch() returns false (i.e) no recipe with id: 999 was found in the DB

        // Mock the database connection
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('prepare')->willReturn($stmtMock);

        // Inject mock into service
        $service = new RecipeService($pdoMock);
        $result = $service->getRecipe(999);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals("Recipe not found", $result['error']);
    }

    private function createTestRecipe(array $recipeData = []): ?int
    {
        $jwt = $this->getJwtToken();

        $defaultData = [
            "recipe_name" => "Test Recipe",
            "prep_time" => 10,
            "difficulty" => 1,
            "vegetarian" => true
        ];

        $data = array_merge($defaultData, $recipeData);

        $createUrl = $this->baseUrl . "/recipes";
        $payload = json_encode($data);

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer $jwt"
        ];

        $context = stream_context_create([
            'http' => [
                'method' => "POST",
                'header' => implode("\r\n", $headers),
                'content' => $payload
            ]
        ]);

        $response = file_get_contents($createUrl, false, $context);
        $responseData = json_decode($response, true);

        $this->assertIsArray($responseData, "Recipe creation failed");
        $this->assertArrayHasKey('data', $responseData, "Response missing data key");
        $this->assertArrayHasKey('id', $responseData['data'], "Recipe ID not found in response");

        return $responseData['data']['id'];
    }

    /**
     * Purpose:
     * Make a request to your API endpoint POST /recipes, and gets the recipe id.
     * Uses that newly created recipe id to test the API endpoint: GET /recipe/{id}
     *  The status code is 200
     *  The response is valid JSON
     *  The returned item of the response has 'recipe_name'
     */
    public function testGetRecipe() {

        // Get JWT token from the helper method
        $jwt = $this->getJwtToken();

        $newRecipeId = $this->createTestRecipe([
            "recipe_name" => "Original Dish",
            "prep_time" => 15,
            "difficulty" => 1,
            "vegetarian" => true
        ]);

        // Now test GET /recipes/{id}
        $url = $this->baseUrl . "/recipes/" . $newRecipeId;
        $response = file_get_contents($url);

        // Assert response status code is 200
        $statusCode = $this->getStatusCode($http_response_header);
        $this->assertEquals(200, $statusCode, "Expected status code 200, got $statusCode");

        // Assert response is valid JSON
        $data = json_decode($response, true);
        $this->assertIsArray($data, "Response is not valid JSON");

        $this->assertArrayHasKey('recipe_name', $data);
    }

    public function testUpdateRecipe()
    {
        $jwt = $this->getJwtToken();
        $recipeId = $this->createTestRecipe([
            "recipe_name" => "Original Dish",
            "prep_time" => 15,
            "difficulty" => 1,
            "vegetarian" => true
        ]);

        $updateUrl = $this->baseUrl . "/recipes/" . $recipeId;
        $updatePayload = json_encode([
            "recipe_name" => "Updated Dish",
            "prep_time" => 20
        ]);

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer $jwt"
        ];

        $context = stream_context_create([
            'http' => [
                'method' => "PUT",
                'header' => implode("\r\n", $headers),
                'content' => $updatePayload
            ]
        ]);

        $response = file_get_contents($updateUrl, false, $context);
        $data = json_decode($response, true);

        $this->assertIsArray($data);
        $this->assertEquals("Recipe updated successfully", $data['message'] ?? '');
    }

    public function testDeleteRecipe()
    {
        $jwt = $this->getJwtToken();
        $recipeId = $this->createTestRecipe([
            "recipe_name" => "Delete Me",
            "prep_time" => 8,
            "difficulty" => 2,
            "vegetarian" => false
        ]);

        $deleteUrl = $this->baseUrl . "/recipes/" . $recipeId;
        $headers = [
            "Authorization: Bearer $jwt"
        ];

        $context = stream_context_create([
            'http' => [
                'method' => "DELETE",
                'header' => implode("\r\n", $headers)
            ]
        ]);

        $deleteResponse = file_get_contents($deleteUrl, false, $context);
        $data = json_decode($deleteResponse, true);

        $this->assertIsArray($data);
        $this->assertEquals("Recipe deleted", $data['message'] ?? '');

        // Confirm it's gone
        $getResponse = @file_get_contents($deleteUrl); // suppress warning
        $getData = json_decode($getResponse, true);
        $this->assertEquals("Recipe not found", $getData['error'] ?? '');
    }
}
?>