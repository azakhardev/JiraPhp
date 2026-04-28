<?php
class Tag {
    public static function getByProject(int $projectId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM tags WHERE project_id = ?");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function add(int $projectId, string $name) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO tags (project_id, name) VALUES (?, ?)");
        return $stmt->execute([$projectId, $name]);
    }

    public static function delete(int $id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM tags WHERE id = ?");
        return $stmt->execute([$id]);
    }
}