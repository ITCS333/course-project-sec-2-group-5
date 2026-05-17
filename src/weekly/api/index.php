<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$weekId = $_GET['week_id'] ?? null;
$commentId = $_GET['comment_id'] ?? null;

function getAllWeeks(PDO $db): void
{
    $sql = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];
    
    $search = $_GET['search'] ?? '';
    if (!empty($search)) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }
    
    $sort = $_GET['sort'] ?? 'start_date';
    $allowedSorts = ['title', 'start_date'];
    if (!in_array($sort, $allowedSorts)) {
        $sort = 'start_date';
    }
    
    $order = $_GET['order'] ?? 'asc';
    $order = strtolower($order);
    if ($order !== 'asc' && $order !== 'desc') {
        $order = 'asc';
    }
    
    $sql .= " ORDER BY $sort $order";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    $weeks = $stmt->fetchAll();
    
    foreach ($weeks as &$week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
    }
    
    sendResponse(['success' => true, 'data' => $weeks]);
}

function getWeekById(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Valid week ID is required'], 400);
    }
    
    $stmt = $db->prepare("SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    $week = $stmt->fetch();
    
    if (!$week) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }
    
    $week['links'] = json_decode($week['links'], true) ?? [];
    
    sendResponse(['success' => true, 'data' => $week]);
}

function createWeek(PDO $db, array $data): void
{
    $title = trim($data['title'] ?? '');
    $start_date = trim($data['start_date'] ?? '');
    
    if (empty($title) || empty($start_date)) {
        sendResponse(['success' => false, 'message' => 'Title and start_date are required'], 400);
    }
    
    if (!validateDate($start_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid start_date format. Use YYYY-MM-DD'], 400);
    }
    
    $description = trim($data['description'] ?? '');
    
    $links = $data['links'] ?? [];
    if (!is_array($links)) {
        $links = [];
    }
    $linksJson = json_encode($links);
    
    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
    $result = $stmt->execute([$title, $start_date, $description, $linksJson]);
    
    if ($result && $stmt->rowCount() > 0) {
        $newId = $db->lastInsertId();
        sendResponse(['success' => true, 'message' => 'Week created successfully', 'id' => $newId], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create week'], 500);
    }
}

function updateWeek(PDO $db, array $data): void
{
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Valid week ID is required'], 400);
    }
    
    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$data['id']]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }
    
    $updates = [];
    $params = [];
    
    if (isset($data['title'])) {
        $updates[] = "title = ?";
        $params[] = trim($data['title']);
    }
    
    if (isset($data['start_date'])) {
        if (!validateDate($data['start_date'])) {
            sendResponse(['success' => false, 'message' => 'Invalid start_date format. Use YYYY-MM-DD'], 400);
        }
        $updates[] = "start_date = ?";
        $params[] = trim($data['start_date']);
    }
    
    if (isset($data['description'])) {
        $updates[] = "description = ?";
        $params[] = trim($data['description']);
    }
    
    if (isset($data['links'])) {
        $links = is_array($data['links']) ? $data['links'] : [];
        $updates[] = "links = ?";
        $params[] = json_encode($links);
    }
    
    if (empty($updates)) {
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }
    
    $params[] = $data['id'];
    $sql = "UPDATE weeks SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        sendResponse(['success' => true, 'message' => 'Week updated successfully'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update week'], 500);
    }
}

function deleteWeek(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Valid week ID is required'], 400);
    }
    
    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }
    
    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result && $stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week deleted successfully'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete week'], 500);
    }
}

function getCommentsByWeek(PDO $db, $weekId): void
{
    if ($weekId === null || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Valid week_id is required'], 400);
    }
    
    $stmt = $db->prepare("SELECT id, week_id, author, text, created_at FROM comments_week WHERE week_id = ? ORDER BY created_at ASC");
    $stmt->execute([$weekId]);
    $comments = $stmt->fetchAll();
    
    sendResponse(['success' => true, 'data' => $comments]);
}

function createComment(PDO $db, array $data): void
{
    $week_id = $data['week_id'] ?? null;
    $author = trim($data['author'] ?? '');
    $text = trim($data['text'] ?? '');
    
    if ($week_id === null || !is_numeric($week_id) || empty($author) || empty($text)) {
        sendResponse(['success' => false, 'message' => 'week_id, author, and text are required'], 400);
    }
    
    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$week_id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found'], 404);
    }
    
    $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
    $result = $stmt->execute([$week_id, $author, $text]);
    
    if ($result && $stmt->rowCount() > 0) {
        $newId = $db->lastInsertId();
        $comment = [
            'id' => $newId,
            'week_id' => $week_id,
            'author' => $author,
            'text' => $text,
            'created_at' => date('Y-m-d H:i:s')
        ];
        sendResponse(['success' => true, 'message' => 'Comment created successfully', 'id' => $newId, 'data' => $comment], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment'], 500);
    }
}

function deleteComment(PDO $db, $commentId): void
{
    if ($commentId === null || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Valid comment_id is required'], 400);
    }
    
    $checkStmt = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
    $checkStmt->execute([$commentId]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }
    
    $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
    $result = $stmt->execute([$commentId]);
    
    if ($result && $stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment'], 500);
    }
}

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif ($id !== null) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateWeek($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Database error occurred'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'An error occurred'], 500);
}

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
