<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

function sendJson($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($method === 'GET') {

    if (isset($_GET['action']) && $_GET['action'] === 'comments') {

        $weekId = $_GET['week_id'] ?? null;

        if (!$weekId) {
            sendJson(['success' => false, 'message' => 'Missing week_id'], 400);
        }

        $stmt = $db->prepare("
            SELECT id, week_id, author, text, created_at
            FROM comments_week
            WHERE week_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$weekId]);
        $comments = $stmt->fetchAll();

        sendJson(['success' => true, 'data' => $comments]);
    }

    if (isset($_GET['id'])) {

        $id = $_GET['id'];

        $stmt = $db->prepare("
            SELECT id, title, start_date, description, links, created_at
            FROM weeks
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $week = $stmt->fetch();

        if (!$week) {
            sendJson(['success' => false, 'message' => 'Week not found'], 404);
        }

        $week['links'] = json_decode($week['links'], true) ?? [];

        sendJson(['success' => true, 'data' => $week]);
    }

    $query = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];

    if (!empty($_GET['search'])) {
        $query .= " WHERE title LIKE ? OR description LIKE ?";
        $search = '%' . $_GET['search'] . '%';
        $params[] = $search;
        $params[] = $search;
    }

    $sort = $_GET['sort'] ?? 'start_date';
    $allowedSorts = ['title', 'start_date'];
    if (!in_array($sort, $allowedSorts)) {
        $sort = 'start_date';
    }

    $order = strtolower($_GET['order'] ?? 'asc');
    if (!in_array($order, ['asc', 'desc'])) {
        $order = 'asc';
    }

    $query .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $weeks = $stmt->fetchAll();

    foreach ($weeks as &$week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
    }

    sendJson(['success' => true, 'data' => $weeks]);
}

if ($method === 'POST') {

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if (isset($_GET['action']) && $_GET['action'] === 'comment') {

        $weekId = $input['week_id'] ?? null;
        $author = trim($input['author'] ?? '');
        $text   = trim($input['text'] ?? '');

        if (!$weekId || !$author || !$text) {
            sendJson(['success' => false, 'message' => 'Missing required fields'], 400);
        }

        $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
        $checkStmt->execute([$weekId]);
        if (!$checkStmt->fetch()) {
            sendJson(['success' => false, 'message' => 'Week not found'], 404);
        }

        $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
        $stmt->execute([$weekId, $author, $text]);

        $newComment = [
            'id'         => $db->lastInsertId(),
            'week_id'    => $weekId,
            'author'     => $author,
            'text'       => $text,
            'created_at' => date('Y-m-d H:i:s')
        ];

        sendJson(['success' => true, 'data' => $newComment], 201);
    }

    $title       = trim($input['title'] ?? '');
    $startDate   = trim($input['start_date'] ?? '');
    $description = trim($input['description'] ?? '');
    $links       = $input['links'] ?? [];

    if (!$title) {
        sendJson(['success' => false, 'message' => 'Title is required'], 400);
    }

    if (!$startDate) {
        sendJson(['success' => false, 'message' => 'Start date is required'], 400);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        sendJson(['success' => false, 'message' => 'Invalid date format'], 400);
    }

    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $startDate, $description, json_encode($links)]);

    sendJson(['success' => true, 'id' => $db->lastInsertId()], 201);
}

if ($method === 'PUT') {

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $id = $input['id'] ?? null;

    if (!$id) {
        sendJson(['success' => false, 'message' => 'Missing id'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendJson(['success' => false, 'message' => 'Week not found'], 404);
    }

    if (
        isset($input['start_date']) &&
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['start_date'])
    ) {
        sendJson(['success' => false, 'message' => 'Invalid date format'], 400);
    }

    $stmt = $db->prepare("
        UPDATE weeks
        SET title = ?, start_date = ?, description = ?, links = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $input['title']       ?? '',
        $input['start_date']  ?? '',
        $input['description'] ?? '',
        json_encode($input['links'] ?? []),
        $id
    ]);

    sendJson(['success' => true]);
}

if ($method === 'DELETE') {

    $action    = $_GET['action']     ?? null;
    $commentId = $_GET['comment_id'] ?? null;
    $weekId    = $_GET['id']         ?? null;

    if ($action === 'delete_comment') {

        if (!$commentId) {
            sendJson(['success' => false, 'message' => 'Missing comment_id'], 400);
        }

        $checkStmt = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
        $checkStmt->execute([$commentId]);
        if (!$checkStmt->fetch()) {
            sendJson(['success' => false, 'message' => 'Comment not found'], 404);
        }

        $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
        $stmt->execute([$commentId]);

        sendJson(['success' => true]);
    }

    if (!$weekId) {
        sendJson(['success' => false, 'message' => 'Missing id'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$weekId]);
    if (!$checkStmt->fetch()) {
        sendJson(['success' => false, 'message' => 'Week not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$weekId]);

    sendJson(['success' => true]);
}

sendJson(['success' => false, 'message' => 'Method not allowed'], 405);
