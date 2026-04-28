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
            Status::add($projectId, 'To Do');
            Status::add($projectId, 'In Progress');
            Status::add($projectId, 'Test');
            Status::add($projectId, 'Done');

            $db->commit(); // Potvrzení změn
            return $projectId;

        } catch (Exception $e) {
            $db->rollBack(); // Pokud něco selže, zruš obě akce
            throw $e;
        }
    }

    public static function getById(int $id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(); // Vrací pole s daty projektu nebo false
    }

    public static function update(int $id, string $name, string $description, string $color) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE projects SET name = ?, description = ?, color_hex = ? WHERE id = ?");
        return $stmt->execute([$name, $description, $color, $id]);
    }

    public static function delete(int $id) {
        $db = Database::getConnection();
        // Díky FOREIGN KEY ... ON DELETE CASCADE ve tvé databázi
        // tohle smaže i všechny úkoly, tagy a statusy navázané na tento projekt.
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getMembers(int $projectId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.email, pm.role 
            FROM users u 
            JOIN project_members pm ON u.id = pm.user_id 
            WHERE pm.project_id = ?
            ORDER BY pm.role ASC, u.username ASC
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function addMember(int $projectId, int $userId, string $role = 'viewer') {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role) VALUES (?, ?, ?)");
        return $stmt->execute([$projectId, $userId, $role]);
    }

    public static function removeMember(int $projectId, int $userId) {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM project_members WHERE project_id = ? AND user_id = ?");
        return $stmt->execute([$projectId, $userId]);
    }

    public static function updateMemberRole(int $projectId, int $userId, string $role) {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE project_members SET role = ? WHERE project_id = ? AND user_id = ?");
        return $stmt->execute([$role, $projectId, $userId]);
    }
}