<?php
require_once 'config.php';

// Create database connection
function connectDB($createIfNotExists = true) {
    try {
        // First try connecting without database selection
        $baseConn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($baseConn->connect_error) {
            throw new Exception("Connection failed: " . $baseConn->connect_error);
        }
        
        // Check if database exists
        $result = $baseConn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        $dbExists = ($result && $result->num_rows > 0);
        
        // If database doesn't exist and we're allowed to create it
        if (!$dbExists && $createIfNotExists) {
            if (!$baseConn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME)) {
                throw new Exception("Error creating database: " . $baseConn->error);
            }
            $dbExists = true;
            
            // Close the base connection to reconnect with the new database
            $baseConn->close();
        } else if (!$dbExists) {
            // Database doesn't exist and we're not creating it
            $baseConn->close();
            return false;
        } else {
            // Database exists, close the base connection
            $baseConn->close();
        }
        
        // Connect with database selected
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection to database failed: " . $conn->connect_error);
        }
        
        return $conn;
    } catch (Exception $e) {
        if (defined('INSTALLATION_IN_PROGRESS')) {
            // During installation, return the error message
            return $e->getMessage();
        } else {
            // In normal operation, redirect to installation
            header("Location: " . BASE_URL . "/install.php?error=" . urlencode($e->getMessage()));
            exit();
        }
    }
}

// Close database connection
function closeDB($conn) {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
?> 