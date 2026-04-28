<?php
class Database {
    private static $instance = null;

    public static function getConnection() {
        if (self::$instance === null) {
            // Předpokládá, že konstanty jsou definovány v config.php
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                // Na produkci by se chyba logovala, pro vývoj ji vypíšeme
                die("Failed to connect to the database: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}