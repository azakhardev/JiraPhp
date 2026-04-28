<?php
class Project {
    public static function getUserProjects(int $userId) {
        $db = Database::getConnection();
        // Spojíme tabulky, abychom viděli jen projekty, kde má uživatel přístup
        $stmt = $db->prepare("
            SELECT p.*, pm.role 
            FROM projects p
            JOIN project_members pm ON p.id = pm.project_id
            WHERE pm.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function create(string $name, string $description, string $color, int $creatorId) {
        $db = Database::getConnection();

        try {
            $db->beginTransaction(); // Začátek transakce

            // 1. Vytvoření projektu
            $stmt = $db->prepare("INSERT INTO projects (created_by, name, description, color_hex) VALUES (?, ?, ?, ?)");
            $stmt->execute([$creatorId, $name, $description, $color]);
            $projectId = $db->lastInsertId();

            // 2. Přiřazení tvůrce jako admina
            $stmt = $db->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, 'admin')");
            $stmt->execute([$projectId, $creatorId]);

            // 3. Vytvoření výchozích stavů (aby projekt nebyl prázdný)
            Status::addStatus($projectId, 'To Do');
            Status::addStatus($projectId, 'In Progress');
            Status::addStatus($projectId, 'Test');
            Status::addStatus($projectId, 'Done');

            $db->commit(); // Potvrzení změn
            return $projectId;

        } catch (Exception $e) {
            $db->rollBack(); // Pokud něco selže, zruš obě akce
            throw $e;
        }
    }

    public static function addMember(int $projectId, int $userId, string $role = 'viewer') {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role) VALUES (?, ?, ?)");
        return $stmt->execute([$projectId, $userId, $role]);
    }

    // Zde by pak mohla být metoda getMembers($projectId)
}