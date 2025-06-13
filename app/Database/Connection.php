<?php
namespace App\Database;

use PDO;
use PDOException;

class Connection {
    private static $instance = null;
    private $connection;
    private $host = '127.0.0.1'; // Або 'localhost', спробуйте обидва
    private $port = '3306';
    private $dbname = 'social_network_db';
    private $user = 'vlada';
    private $pass = 'adalv';

    private function __construct() {
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
        error_log("Connection::__construct() called. DSN: " . $dsn . ", User: " . $this->user);
        try {
            $this->connection = new PDO($dsn, $this->user, $this->pass);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            error_log("Connection::__construct(): PDO connection successful.");
        } catch (PDOException $e) {
            error_log("Connection::__construct(): PDOException: " . $e->getMessage());
            die("Помилка підключення до бази даних (конструктор): " . $e->getMessage());
        }
    }

    public static function getInstance() {
        error_log("Connection::getInstance() called.");
        if (self::$instance === null) {
            error_log("Connection::getInstance(): Creating new instance.");
            self::$instance = new Connection();
        } else {
            error_log("Connection::getInstance(): Returning existing instance.");
        }
        return self::$instance;
    }

    public function getConnection() {
        error_log("Connection::getConnection() called.");
        return $this->connection;
    }
}