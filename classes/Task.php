<?php
class Task {
    public static function getByProject(int $projectId, array $filters = []) {
        $db = Database::getConnection();

        // Základní query s joiny pro jména přiřazených lidí a stavů
        $query = "
            SELECT t.*, u.username as assignee_name, s.name as status_name 
            FROM tasks t
            LEFT JOIN users u ON t.assignee_id = u.id
            LEFT JOIN statuses s ON t.status_id = s.id
            WHERE t.project_id = ?
        ";
        $params = [$projectId];

        // Tady pak můžeme přidávat podmínky z $filters (např. if isset filter status, přidej WHERE)
        // Pro teď to necháme tahat všechno
        $query .= " ORDER BY t.created_at DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(int $projectId, string $title, string $description, int $reporterId, int $statusId, ?int $assigneeId = null) {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO tasks (project_id, title, description, reporter_id, status_id, assignee_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$projectId, $title, $description, $reporterId, $statusId, $assigneeId]);
        return $db->lastInsertId();
    }
}