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

    public static function getById(int $id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function getAllExcept(int $excludeUserId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, username, email FROM users WHERE id != ? ORDER BY username ASC");
        $stmt->execute([$excludeUserId]);
        return $stmt->fetchAll();
    }

    public static function updateUsername(int $id, string $username) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
        return $stmt->execute([$username, $id]);
    }

    public static function updatePassword(int $id, string $passwordHash) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$passwordHash, $id]);
    }

    public static function delete(int $id) {
        $db = Database::getConnection();
        // Díky kaskádovému mazání v DB se smažou i všechny komentáře
        // a vazby tohoto uživatele v projektech.
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

}