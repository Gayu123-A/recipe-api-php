<?php
require_once "database.php";

/* SQL statements to create tables:

1. Table: recipes (to store the data about recipes)
    Column	        Data Type	                                                Description
    id	            INT AUTO_INCREMENT PRIMARY KEY	                            Unique identifier for each recipe, automatically increments.
    recipe_name	    VARCHAR(255) NOT NULL	                                    Name of the recipe (max 255 characters). Cannot be NULL.
    prep_time	    INT NOT NULL	                                            Preparation time in minutes. Cannot be NULL.
    difficulty	    TINYINT NOT NULL CHECK (difficulty BETWEEN 1 AND 3)	        Difficulty level (1 to 3). A constraint ensures values stay in this range.
    vegetarian	    BOOLEAN NOT NULL	                                        Whether the recipe is vegetarian (true/false). Cannot be NULL.
    created_at	    TIMESTAMP DEFAULT CURRENT_TIMESTAMP                         Stores the date and time of creation. Defaults to the current timestamp.

2. Table: ratings (to give ratings to the recipes)
    Column	        Data Type	                                        Description
    id	            INT AUTO_INCREMENT PRIMARY KEY	                    Unique ID for each rating. Automatically increments.
    recipe_id	    INT NOT NULL	                                    Foreign key linking to recipes.id (each rating belongs to a recipe).
    rating	        TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5)	    Rating value between 1 (worst) and 5 (best). The constraint ensures the value is valid.
    created_at	    TIMESTAMP DEFAULT CURRENT_TIMESTAMP	                Timestamp of when the rating was added. Defaults to the current time.

3. FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
    This ensures that "recipe_id" in the ratings table must exist in the recipes table.
    ON DELETE CASCADE → If a recipe is deleted from the recipes table, all its ratings will be deleted automatically.

4. The ENGINE=InnoDB specifies that the InnoDB storage engine is used. This supports transactions and foreign keys.
*/
$sql = "
CREATE TABLE IF NOT EXISTS recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_name VARCHAR(255) NOT NULL,
    prep_time INT NOT NULL, -- in minutes,
    difficulty TINYINT NOT NULL CHECK (difficulty BETWEEN 1 AND 3),
    vegetarian BOOLEAN NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";

// Execute the queries
try{
    $conn->exec($sql);
    echo "Tables 'recipes' and 'ratings' created successfully!  \n";
}catch(PDOException $e){
    echo "Error creating tables: " . $conn->error;
}

// Close the connection (PDO does not have close(), use null instead)
$conn = null;
?>