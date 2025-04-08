<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService{
    private $secret; // JWT Secret Key

    public function __construct(){
        $this->secret = $_ENV['JWT_SECRET'] ?? null;  
    }

    // Generates the encoded JWT token
    public function generateToken($userId) {
        $payload = [
            "iss" => "localhost",        // Issuer
            "aud" => "localhost",        // Audience
            "iat" => time(),             // Issued at (current time)
            "exp" => time() + (60 * 60), // Expiration Time - 1 hour expiry
            "user_id" => $userId         // Custom data based on your app
        ];

        return JWT::encode($payload, $this->secret, 'HS256'); // gives a JWT string to send to the client
    }

    // Validates the JWT token by decoding it
    public function validateToken($token){

        try{
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return (array)$decoded;
        }catch(Exception $e){
            return ["error" => "Invalid token: " . $e->getMessage()];
        }

    }
}
?>