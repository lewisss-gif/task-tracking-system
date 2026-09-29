<?php
/**
 * API: Get milestones for a project
 */
require_once '../includes/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$projectId = $_GET['project_id'] ?? null;

if (!$projectId) {
    echo json_encode([]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT milestone_id, title FROM milestones 
          WHERE project_id = :project_id 
          ORDER BY due_date ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':project_id', $projectId);
$stmt->execute();

echo json_encode($stmt->fetchAll());
?>
