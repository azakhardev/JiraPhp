<?php

class User
{
    public static function findByEmail(string $email)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(); // Vrací pole nebo false
    }

    public static function createLocal(string $username, string $email, string $passwordHash)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, oauth_provider) 
            VALUES (?, ?, ?, 'local')
        ");
        $stmt->execute([$username, $email, $passwordHash]);
        return $db->lastInsertId();
    }
    public static function createGlobal(string $username, string $email, string $oauthId, string $provider = 'google')
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO users (username, email, oauth_id, oauth_provider) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$username, $email, $oauthId, $provider]);
        return $db->lastInsertId(); // Vrátí ID nově vytvořeného uživatele
    }

    public static function getById(int $id)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, username, email FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function getAllExcept(int $excludeUserId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, username, email FROM users WHERE id != ? ORDER BY username ASC");
        $stmt->execute([$excludeUserId]);
        return $stmt->fetchAll();
    }

}