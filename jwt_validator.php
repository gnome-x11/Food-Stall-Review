<?php
require_once "../config/db_config.php";
require_once "../vendor/autoload.php";
require_once "../config.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Validates a JWT token from a cookie.
 *
 * @param string $cookieName Name of the cookie containing the JWT.
 * @param string $redirectURL URL to redirect to if validation fails.
 * @return object The decoded JWT payload.
 */

 function validateToken($cookieName, $redirectURL = "login.php") {
     $secret_key = JWT_WEB_TOKEN;

     if (!isset($_COOKIE[$cookieName])) {
         header("Location: $redirectURL");
         exit();
     }

     try {
         $decoded = JWT::decode($_COOKIE[$cookieName], new Key($secret_key, 'HS256'));
         return $decoded;
     } catch (Exception $e) {
         // You can log the error for debugging: error_log($e->getMessage());
         header("Location: $redirectURL");
         exit();
     }
 }
