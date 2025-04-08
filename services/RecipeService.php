<?php
/**
 * Purpose: To handle API requests.
 * 
 * Will create the following APIs:
 * 1. POST /recipes -> to create a recipe.
 * 2. GET  /recipes -> to get(read) all the recipes.
 * 3. GET  /recipes/{id} -> to get(read) a single recipe.
 * 4. PUT  /recipes/{id} -> to update a recipe.
 * 5. DELETE /recipes/{id} -> to delete a recipe.
 * 6. POST /recipes/{id}/rating -> to rate a recipe.
 */

class RecipeService{
    private $conn;

    public function __construct(PDO $db){
        $this->conn = $db;
    }

    public function listRecipes(): array{
        try{
            $stmt = $this->conn->query('SELECT * FROM recipes');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch(PDOException $e){
            http_response_code(500); // Internal Server Error
            return ["error" => "Database error:". $e->getMessage()];
        }
    }

    public function getRecipe($id): array{
        try{
            $stmt = $this->conn->prepare('SELECT * FROM recipes WHERE id = ?');
            $stmt->execute([$id]);
            $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$recipe){
                http_response_code(404);
                return ["error" => "Recipe not found"];
            }

            return $recipe;
        }catch(PDOException $e){
            http_response_code(500);
            return ["error" => "Database error:". $e->getMessage()];
        }
    }
    
    public function createRecipe(array $data): array{

        // Check for required fields
        if(!isset($data['recipe_name'], $data['prep_time'], $data['difficulty'], $data['vegetarian'])){
            http_response_code(400);
            return ["error" => "All fields are required"];
        }

        // Sanitize and  validate
        $validatedData = $this->validateRecipeData($data, false);

        // Make insert into the 'recipes' table with try-catch
        try{
            $stmt = $this->conn->prepare("INSERT INTO recipes (recipe_name, prep_time, difficulty, vegetarian) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['recipe_name'], $data['prep_time'], $data['difficulty'], $data['vegetarian']]);

            http_response_code(201);
            return ["message" => "Recipe created successfully"];

        } catch (PDOException $e){
            http_response_code(500); // Internal Server Error
            return ["error" => "Database error:". $e->getMessage()];
        }
    }

    public function updateRecipe($id, array $data): array{
        if(empty($data)){
            http_response_code(400);
            return ["error" => "No data provided for the update"];
        }

        // Validate and sanitize input
        $validatedData = $this->validateRecipeData($data, true);

        // Prepare dynamic SQL fields and values (for update)
        $fields = [];
        $values = [];

        foreach($validatedData as $key => $value){
            $fields[] = "$key = ?";
            $values[] = $value;
        }

        $values[] = $id;

        // Execute update with try-catch
        try{
            $stmt = $this->conn->prepare("UPDATE recipes SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($values);
            return ["message" => "Recipe updated successfully"];
        } catch (PDOException $e) {
            http_response_code(500);
            return ["error" => "Database error: " . $e->getMessage()];
        }
    }

    public function deleteRecipe($id): array {
        try {
            // Delete ratings associated with the recipe
            $stmt = $this->conn->prepare("DELETE FROM ratings WHERE recipe_id = ?");
            $stmt->execute([$id]);

            // Delete a recipe record with it's id
            $stmt = $this->conn->prepare("DELETE FROM recipes WHERE id = ?");
            $stmt->execute([$id]);

            return ["message" => "Recipe deleted"];
        } catch (PDOException $e) {
            http_response_code(500);
            return ["error" => "Database error: " . $e->getMessage()];
        }
    }

    public function rateRecipe($id, $rating): array{
        // Validate the rating value
        if($rating < 1 || $rating > 5){
            http_response_code(422);
            return ["error" => "Rating must be between 1 and 5"];
        }

        try{
            $recipe = $this->getRecipe($id);
            if (isset($recipe['error'])) {
                return $recipe; // Already contains error response
            }

            // Make insert into the "ratings" table
            $stmt = $this->conn->prepare("INSERT INTO ratings (recipe_id, rating) VALUES (?, ?)");
            $stmt->execute([$id, $rating]);

            return ["message" => "Rating submitted"];

        }catch(PDOException $e){
            http_response_code(500);
            return ["error" => "Database error: " . $e->getMessage()];
        }
    }

    /**
     * Searches for recipes that match the given query in the recipe_name.
     *
     * @param string $query The search term to look for.
     * @return array Matching recipes or an error array on failure.
     */
    public function searchRecipes(string $query): array{
        try{
            // Prepare a query using LIKE operator to perform a partial match search
            $stmt = $this->conn->prepare('SELECT * FROM recipes WHERE recipe_name LIKE ?');
            $stmt->execute(["%".$query."%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }catch(PDOException $e){
            http_response_code(500);
            return ["error" => "Database error: " . $e->getMessage()];
        }
    }
    
    /**
     * Validates and sanitizes recipe data
     * 
     * @param array $data - Input data from client
     * @param bool $partial - if true, allows partial updates (for update); if false, requires all fields (for create)
     * @return array - Validated array or error array
     */
    private function validateRecipeData(array $data, bool $partial = false): array{
        $validated = [];

        // Validate recipe_name
        if(isset($data['recipe_name'])){    
            $recipe_name = trim($data['recipe_name']);
            if($recipe_name === ''){
                http_response_code(422);
                return ["error" => "Recipe Name cannot be empty"];
            }
            $validated['recipe_name'] = $recipe_name;
        }else if(!$partial){ 
            return ["error" => "Recipe Name is required"];
        }

        // Validate prep_time
        if(isset($data['prep_time'])){    
            $prep_time = (int)$data['prep_time'];
            if($prep_time <= 0){
                http_response_code(422);
                return ["error" => "Preparation time should be greater than zero minutes"];
            }
            $validated['prep_time'] = $prep_time;
        }else if(!$partial){ 
            return ["error" => "Preparation time is required"];
        }

        // Validate difficulty
        if(isset($data['difficulty'])){    
            $difficulty = (int)$data['difficulty'];
            if($difficulty < 1 || $difficulty > 3){
                http_response_code(422);
                return ["error" => "Difficulty must be between 1 and 3"];
            }
            $validated['difficulty'] = $difficulty;
        }else if(!$partial){ 
            return ["error" => "Difficulty is required"];
        }

        // Validate vegetarian
        if(isset($data['vegetarian'])){    
            $vegetarian = filter_var($data['vegetarian'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if($vegetarian === null){
                http_response_code(422);
                return ["error" => "Invalid value for vegetarian (must be true or false)"];
            }
            $validated['vegetarian'] = $vegetarian;
        }else if(!$partial){ 
            return ["error" => "Vegetarian is required"];
        }

        return $validated;
    }
}
?>