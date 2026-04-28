<?php
class Task {
    public static function getByProjectFiltered(int $projectId, array $filters = []) {
        $db = Database::getConnection();

        // Komplexní dotaz s JOINy a GROUP_CONCAT pro tagy
        $query = "
            SELECT t.*, u.username as assignee_name, s.name as status_name,
                   GROUP_CONCAT(tg.name SEPARATOR ', ') as tags_string
            FROM tasks t
            LEFT JOIN users u ON t.assignee_id = u.id
            LEFT JOIN statuses s ON t.status_id = s.id
            LEFT JOIN task_tags tt ON t.id = tt.task_id
            LEFT JOIN tags tg ON tt.tag_id = tg.id
            WHERE t.project_id = :project_id
        ";
        $params = [':project_id' => $projectId];

        // Dynamické přidávání podmínek podle filtrů
        if (!empty($filters['status_id'])) {
            $query .= " AND t.status_id = :status_id";
            $params[':status_id'] = $filters['status_id'];
        }
        if (!empty($filters['assignee_id'])) {
            $query .= " AND t.assignee_id = :assignee_id";
            $params[':assignee_id'] = $filters['assignee_id'];
        }
        if (!empty($filters['priority_weight'])) {
            $query .= " AND t.priority_weight = :priority_weight";
            $params[':priority_weight'] = $filters['priority_weight'];
        }
        if (!empty($filters['tag_id'])) {
            // Subquery, aby se nerozbil hlavní GROUP_CONCAT
            $query .= " AND t.id IN (SELECT task_id FROM task_tags WHERE tag_id = :tag_id)";
            $params[':tag_id'] = $filters['tag_id'];
        }

        // Seskupení pro GROUP_CONCAT a abecední seřazení (splnění požadavku)
        $query .= " GROUP BY t.id, u.username, s.name ORDER BY t.title ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function delete(int $id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getById(int $id) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
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

    public static function update(int $taskId, string $title, ?string $description, ?int $assigneeId, int $reporterId, ?int $statusId, int $priorityWeight, int $timeSpent, ?string $dueDate) {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE tasks 
            SET title = ?, description = ?, assignee_id = ?, reporter_id = ?, status_id = ?, priority_weight = ?, time_spent_minutes = ?, due_date = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$title, $description, $assigneeId, $reporterId, $statusId, $priorityWeight, $timeSpent, $dueDate, $taskId]);
    }
}