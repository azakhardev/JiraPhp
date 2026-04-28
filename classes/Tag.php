<?php
class Tag {
    public static function getTags(int $projectId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM tags WHERE project_id = ?");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function addTag(int $projectId, string $name) {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO tags (project_id, name) VALUES (?, ?)");
        return $stmt->execute([$projectId, $name]);
    }
}