<?php
class Comment {
    public static function getByTask(int $taskId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT c.*, u.username 
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.task_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public static function create(int $taskId, int $userId, string $content) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO comments (task_id, user_id, content) VALUES (?, ?, ?)");
        return $stmt->execute([$taskId, $userId, $content]);
    }

    public static function getById(int $id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function update(int $id, string $content) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE comments SET content = ? WHERE id = ?");
        return $stmt->execute([$content, $id]);
    }

    public static function delete(int $id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
        return $stmt->execute([$id]);
    }
}