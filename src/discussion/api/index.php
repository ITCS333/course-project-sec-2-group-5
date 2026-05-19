<?php
/**
 * Discussion Board API
 *
 * RESTful API for CRUD operations on discussion topics and their replies.
 * Uses PDO to interact with the MySQL database defined in schema.sql.
 *
 * Database Tables (ground truth: schema.sql):
 *
 * Table: topics
 *   id         INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   subject    VARCHAR(255)  NOT NULL
 *   message    TEXT          NOT NULL
 *   author     VARCHAR(100)  NOT NULL
 *   created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
 *
 * Table: replies
 *   id         INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   topic_id   INT UNSIGNED  NOT NULL — FK → topics.id (ON DELETE CASCADE)
 *   text       TEXT          NOT NULL
 *   author     VARCHAR(100)  NOT NULL
 *   created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
 *
 * HTTP Methods Supported:
 *   GET    — Retrieve topic(s) or replies
 *   POST   — Create a new topic or reply
 *   PUT    — Update an existing topic
 *   DELETE — Delete a topic (cascade removes its replies) or a reply
 *
 * URL scheme (all requests go to index.php):
 *
 *   Topics:
 *     GET    ./api/index.php                   — list all topics
 *     GET    ./api/index.php?id={id}           — get one topic by integer id
 *     POST   ./api/index.php                   — create a new topic
 *     PUT    ./api/index.php                   — update a topic (id in JSON body)
 *     DELETE ./api/index.php?id={id}           — delete a topic
 *
 *   Replies (action parameter selects the replies sub-resource):
 *     GET    ./api/index.php?action=replies&topic_id={id}
 *                                              — list replies for a topic
 *     POST   ./api/index.php?action=reply      — create a reply
 *     DELETE ./api/index.php?action=delete_reply&id={id}
 *                                              — delete a single reply
 *
 * Query parameters for GET all topics:
 *   search — filter rows where subject LIKE or message LIKE or author LIKE
 *   sort   — column to sort by; allowed: subject, author, created_at
 *            (default: created_at)
 *   order  — sort direction; allowed: asc, desc (default: desc)
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// Set headers for JSON response and CORS.
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS request.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the shared database connection file.
require_once __DIR__ . '/../../common/db.php';

// Get the PDO database connection.
$db = getDBConnection();

// Read the HTTP request method.
$method = $_SERVER['REQUEST_METHOD'];

// Read and decode the request body for POST and PUT requests.
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

// Read query parameters.
$action  = $_GET['action']   ?? null;  // 'replies', 'reply', 'delete_reply'
$id      = $_GET['id']       ?? null;  // integer topic or reply id
$topicId = $_GET['topic_id'] ?? null;  // integer topic id for replies queries

// ============================================================================
// TOPICS FUNCTIONS
// ============================================================================

/**
 * Get all topics (with optional search and sort).
 * Method: GET (no ?id or ?action parameter).
 */
function getAllTopics(PDO $db): void
{
    // Build the base SELECT query.
    $sql = "SELECT id, subject, message, author, created_at FROM topics";
    $params = [];

    // If search parameter is provided and non-empty, append filter clauses safely.
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    if ($search !== '') {
        $sql .= " WHERE subject LIKE :search OR message LIKE :search OR author LIKE :search";
        $params['search'] = '%' . $search . '%';
    }

    // Validate sort parameter against a structural whitelist.
    $allowedSort = ['subject', 'author', 'created_at'];
    $sort = isset($_GET['sort']) ? strtolower(trim($_GET['sort'])) : 'created_at';
    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'created_at';
    }

    // Validate order direction parameter against a whitelist.
    $allowedOrder = ['asc', 'desc'];
    $order = isset($_GET['order']) ? strtolower(trim($_GET['order'])) : 'desc';
    if (!in_array($order, $allowedOrder, true)) {
        $order = 'desc';
    }

    // Append safe structural keywords directly to the query statement string.
    $sql .= " ORDER BY $sort $order";

    // Prepare and execute statement
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $topics]);
}

/**
 * Get a single topic by its integer primary key.
 * Method: GET with ?id={id}.
 */
function getTopicById(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Missing or invalid topic ID parameter.'], 400);
    }

    $stmt = $db->prepare("SELECT id, subject, message, author, created_at FROM topics WHERE id = ?");
    $stmt->execute([(int)$id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($topic) {
        sendResponse(['success' => true, 'data' => $topic]);
    } else {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }
}

/**
 * Create a new topic.
 * Method: POST (no ?action parameter).
 */
function createTopic(PDO $db, array $data): void
{
    if (empty($data['subject']) || empty($data['message']) || empty($data['author'])) {
        sendResponse(['success' => false, 'message' => 'Required fields (subject, message, author) are missing or empty.'], 400);
    }

    $subject = sanitizeInput($data['subject']);
    $message = sanitizeInput($data['message']);
    $author  = sanitizeInput($data['author']);

    if ($subject === '' || $message === '' || $author === '') {
        sendResponse(['success' => false, 'message' => 'Fields cannot contain only whitespace or invalid tags.'], 400);
    }

    $stmt = $db->prepare("INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)");
    $success = $stmt->execute([$subject, $message, $author]);

    if ($success && $stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Topic created successfully.',
            'id' => (int)$db->lastInsertId()
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to store the topic in the database.'], 500);
    }
}

/**
 * Update an existing topic.
 * Method: PUT.
 */
function updateTopic(PDO $db, array $data): void
{
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Missing or invalid topic ID within request body.'], 400);
    }

    $id = (int)$data['id'];

    // Ensure the target topic exists first
    $checkStmt = $db->prepare("SELECT 1 FROM topics WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    // Dynamically build SET clauses based on properties present in the request body
    $setClauses = [];
    $params = [];

    if (isset($data['subject'])) {
        $subject = sanitizeInput($data['subject']);
        if ($subject === '') {
            sendResponse(['success' => false, 'message' => 'Subject cannot be empty.'], 400);
        }
        $setClauses[] = "subject = :subject";
        $params['subject'] = $subject;
    }

    if (isset($data['message'])) {
        $message = sanitizeInput($data['message']);
        if ($message === '') {
            sendResponse(['success' => false, 'message' => 'Message cannot be empty.'], 400);
        }
        $setClauses[] = "message = :message";
        $params['message'] = $message;
    }

    if (empty($setClauses)) {
        sendResponse(['success' => false, 'message' => 'No updatable fields (subject or message) were provided.'], 400);
    }

    $sql = "UPDATE topics SET " . implode(", ", $setClauses) . " WHERE id = :id";
    $params['id'] = $id;

    $stmt = $db->prepare($sql);
    if ($stmt->execute($params)) {
        sendResponse(['success' => true, 'message' => 'Topic updated successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update topic context.'], 500);
    }
}

/**
 * Delete a topic by integer id.
 * Method: DELETE with ?id={id}.
 */
function deleteTopic(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Missing or invalid topic ID parameter.'], 400);
    }

    $id = (int)$id;

    $checkStmt = $db->prepare("SELECT 1 FROM topics WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Topic not found.'], 404);
    }

    $stmt = $db->prepare("DELETE FROM topics WHERE id = ?");
    if ($stmt->execute([$id]) && $stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Topic and all matching replies deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete the topic from storage.'], 500);
    }
}

// ============================================================================
// REPLIES FUNCTIONS
// ============================================================================

/**
 * Get all replies for a specific topic.
 * Method: GET with ?action=replies&topic_id={id}.
 */
function getRepliesByTopicId(PDO $db, $topicId): void
{
    if ($topicId === null || !is_numeric($topicId)) {
        sendResponse(['success' => false, 'message' => 'Missing or invalid topic_id parameter.'], 400);
    }

    $stmt = $db->prepare("SELECT id, topic_id, text, author, created_at FROM replies WHERE topic_id = ? ORDER BY created_at ASC");
    $stmt->execute([(int)$topicId]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $replies]);
}

/**
 * Create a new reply.
 * Method: POST with ?action=reply.
 */
function createReply(PDO $db, array $data): void
{
    if (!isset($data['topic_id']) || empty($data['text']) || empty($data['author'])) {
        sendResponse(['success' => false, 'message' => 'Required fields (topic_id, text, author) are missing or empty.'], 400);
    }

    if (!is_numeric($data['topic_id'])) {
        sendResponse(['success' => false, 'message' => 'The topic_id field must be an integer.'], 400);
    }

    $topicId = (int)$data['topic_id'];
    $text    = sanitizeInput($data['text']);
    $author  = sanitizeInput($data['author']);

    if ($text === '' || $author === '') {
        sendResponse(['success' => false, 'message' => 'Fields cannot contain only whitespace or invalid tags.'], 400);
    }

    // Verify parent topic exists before committing cascading row dependencies
    $checkStmt = $db->prepare("SELECT 1 FROM topics WHERE id = ?");
    $checkStmt->execute([$topicId]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Target parent topic does not exist.'], 404);
    }

    $stmt = $db->prepare("INSERT INTO replies (topic_id, text, author) VALUES (?, ?, ?)");
    $success = $stmt->execute([$topicId, $text, $author]);

    if ($success && $stmt->rowCount() > 0) {
        $newReplyId = (int)$db->lastInsertId();
        
        // Fetch the fresh record state to return to client
        $fetchStmt = $db->prepare("SELECT id, topic_id, text, author, created_at FROM replies WHERE id = ?");
        $fetchStmt->execute([$newReplyId]);
        $newReply = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        sendResponse([
            'success' => true,
            'message' => 'Reply added successfully.',
            'id' => $newReplyId,
            'data' => $newReply
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to store the reply record.'], 500);
    }
}

/**
 * Delete a single reply.
 * Method: DELETE with ?action=delete_reply&id={id}.
 */
function deleteReply(PDO $db, $replyId): void
{
    if ($replyId === null || !is_numeric($replyId)) {
        sendResponse(['success' => false, 'message' => 'Missing or invalid reply ID parameter.'], 400);
    }

    $replyId = (int)$replyId;

    $checkStmt = $db->prepare("SELECT 1 FROM replies WHERE id = ?");
    $checkStmt->execute([$replyId]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Reply not found.'], 404);
    }

    $stmt = $db->prepare("DELETE FROM replies WHERE id = ?");
    if ($stmt->execute([$replyId]) && $stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Reply deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to remove the reply from storage.'], 500);
    }
}

// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        if ($action === 'replies') {
            getRepliesByTopicId($db, $topicId);
        } elseif ($id !== null) {
            getTopicById($db, $id);
        } else {
            getAllTopics($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'reply') {
            createReply($db, $data);
        } else {
            createTopic($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateTopic($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_reply') {
            deleteReply($db, $id);
        } else {
            deleteTopic($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed.'], 405);
    }

} catch (PDOException $e) {
    // Encapsulate production debugging risks: Log detailed runtime states locally, hide details from client.
    error_log("Database Exception in Discussion Board API: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    sendResponse(['success' => false, 'message' => 'An internal database management error occurred.'], 500);

} catch (Exception $e) {
    error_log("General Exception in Discussion Board API: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    sendResponse(['success' => false, 'message' => 'A fatal app processing system anomaly occurred.'], 500);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Send a JSON response and stop execution.
 *
 * @param array $data        Must include a 'success' key.
 * @param int   $statusCode  HTTP status code (default 200).
 */
function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Sanitize a string input.
 *
 * @param  string $data
 * @return string  Trimmed, tag-stripped, HTML-encoded string.
 */
function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}