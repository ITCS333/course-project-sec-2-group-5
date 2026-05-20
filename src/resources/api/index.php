<?php
/**
 * Course Resources API
 * * This is a RESTful API that handles all CRUD operations for course resources 
 * and their associated comments/discussions.
 * It uses PDO to interact with a MySQL database.
 * * Database Table Structures (for reference):
 * * Table: resources
 * Columns:
 * - id (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 * - title (VARCHAR(255), NOT NULL)
 * - description (TEXT, nullable)
 * - link (VARCHAR(500), NOT NULL)
 * - created_at (TIMESTAMP)
 * * Table: comments_resource
 * Columns:
 * - id (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 * - resource_id (INT UNSIGNED, FOREIGN KEY references resources.id, CASCADE DELETE)
 * - author (VARCHAR(100), NOT NULL)
 * - text (TEXT, NOT NULL)
 * - created_at (TIMESTAMP)
 * * HTTP Methods Supported:
 * - GET:    Retrieve resource(s) or comment(s)
 * - POST:   Create a new resource or comment
 * - PUT:    Update an existing resource
 * - DELETE: Delete a resource (associated comments in comments_resource are
 * removed automatically by the ON DELETE CASCADE constraint)
 * * Response Format: JSON
 * All responses follow the structure:
 * { "success": true,  "data": ...    }  (on success)
 * { "success": false, "message": ... }  (on error)
 * * API Endpoints:
 * * Resources:
 * GET    /resources/api/index.php                         - Get all resources
 * GET    /resources/api/index.php?id={id}                 - Get single resource by ID
 * POST   /resources/api/index.php                         - Create new resource
 * PUT    /resources/api/index.php                         - Update resource
 * DELETE /resources/api/index.php?id={id}                 - Delete resource
 * * Comments:
 * GET    /resources/api/index.php?resource_id={id}&action=comments
 * - Get all comments for a resource
 * POST   /resources/api/index.php?action=comment          - Create a new comment
 * DELETE /resources/api/index.php?comment_id={id}&action=delete_comment
 * - Delete a single comment
 * * Query Parameters for GET all resources:
 * - search: Optional. Filter resources by title or description using LIKE.
 * - sort:   Optional. Sort field — allowed values: title, created_at (default: created_at).
 * - order:  Optional. Sort direction — allowed values: asc, desc (default: desc).
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// Set headers for JSON response and CORS
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the database connection file
require_once './config/Database.php';

// Get the PDO database connection
$database = new Database();
$db = $database->getConnection();

// Get the HTTP request method
$method = $_SERVER['REQUEST_METHOD'];

// Get the request body for POST and PUT requests
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);
if (!is_array($data)) {
    $data = [];
}

// Parse query parameters from $_GET
$action      = isset($_GET['action']) ? trim($_GET['action']) : null;
$id          = isset($_GET['id']) ? trim($_GET['id']) : null;
$resource_id = isset($_GET['resource_id']) ? trim($_GET['resource_id']) : null;
$comment_id  = isset($_GET['comment_id']) ? trim($_GET['comment_id']) : null;


// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================

/**
 * Function: Get all resources
 * Method: GET (no id or action parameter)
 */
function getAllResources($db) {
    // Initialize the base SQL query
    $sql = "SELECT id, title, description, link, created_at FROM resources";
    $conditions = [];
    $params = [];

    // Check if search parameter exists in $_GET
    if (isset($_GET['search']) && trim($_GET['search']) !== '') {
        $search = trim($_GET['search']);
        $conditions[] = "(title LIKE :search OR description LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    // Validate the sort parameter
    $allowedSortFields = ['title', 'created_at'];
    $sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'created_at';
    if (!in_array($sort, $allowedSortFields)) {
        $sort = 'created_at';
    }

    // Validate the order parameter
    $allowedOrder = ['asc', 'desc'];
    $order = isset($_GET['order']) ? strtolower(trim($_GET['order'])) : 'desc';
    if (!in_array($order, $allowedOrder)) {
        $order = 'desc';
    }

    // Add ORDER BY clause securely using validated whitelisted variables
    $sql .= " ORDER BY {$sort} {$order}";

    // Prepare and execute the statement
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    // Fetch all results as an associative array
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON response using sendResponse()
    sendResponse(['success' => true, 'data' => $resources], 200);
}


/**
 * Function: Get a single resource by ID
 * Method: GET with ?id={id}
 */
function getResourceById($db, $resourceId) {
    // Validate that $resourceId is provided and is numeric
    if ($resourceId === null || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing Resource ID.'], 400);
    }

    // Prepare SQL query
    $sql = "SELECT id, title, description, link, created_at FROM resources WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$resourceId]);

    // Fetch the result as an associative array
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    // If found, return success response with resource data; otherwise 404
    if ($resource) {
        sendResponse(['success' => true, 'data' => $resource], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
}


/**
 * Function: Create a new resource
 * Method: POST (no action parameter)
 */
function createResource($db, $data) {
    // Validate required fields
    $validation = validateRequiredFields($data, ['title', 'link']);
    if (!$validation['valid']) {
        sendResponse([
            'success' => false, 
            'message' => 'Missing required fields: ' . implode(', ', $validation['missing'])
        ], 400);
    }

    // Sanitize input
    $title       = sanitizeInput($data['title']);
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';
    $link        = trim($data['link']);

    // Validate the link syntax
    if (!validateUrl($link)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL resource format specified.'], 400);
    }

    // Prepare INSERT query
    $sql = "INSERT INTO resources (title, description, link) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute([$title, $description, $link])) {
        sendResponse([
            'success' => true,
            'message' => 'Resource created successfully.',
            'id' => (int)$db->lastInsertId()
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Database error occurred while writing resource record.'], 500);
    }
}


/**
 * Function: Update an existing resource
 * Method: PUT
 */
function updateResource($db, $data) {
    // Validate that id is provided in $data
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Valid resource ID parameter is required for updates.'], 400);
    }

    $resourceId = (int)$data['id'];

    // Check if the resource exists
    $checkSql = "SELECT id FROM resources WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$resourceId]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    // Build UPDATE query dynamically for only the fields provided
    $updateFields = [];
    $bindParams = [];

    if (isset($data['title'])) {
        $updateFields[] = "title = ?";
        $bindParams[] = sanitizeInput($data['title']);
    }
    if (isset($data['description'])) {
        $updateFields[] = "description = ?";
        $bindParams[] = sanitizeInput($data['description']);
    }
    if (isset($data['link'])) {
        $link = trim($data['link']);
        if (!validateUrl($link)) {
            sendResponse(['success' => false, 'message' => 'Invalid URL resource format specified.'], 400);
        }
        $updateFields[] = "link = ?";
        $bindParams[] = $link;
    }

    // If no fields to update, return error response
    if (empty($updateFields)) {
        sendResponse(['success' => false, 'message' => 'No field updates modifications were provided.'], 400);
    }

    // Build the final SQL string and append ID target
    $sql = "UPDATE resources SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $bindParams[] = $resourceId;

    $stmt = $db->prepare($sql);
    if ($stmt->execute($bindParams)) {
        sendResponse(['success' => true, 'message' => 'Resource updated successfully.'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to write entity update modifications to backend storage.'], 500);
    }
}


/**
 * Function: Delete a resource
 * Method: DELETE with ?id={id}
 */
function deleteResource($db, $resourceId) {
    // Validate target resource ID parameter
    if ($resourceId === null || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing Resource ID parameters.'], 400);
    }

    // Check if the resource exists
    $checkSql = "SELECT id FROM resources WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$resourceId]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    // Prepare and execute DELETE query
    $sql = "DELETE FROM resources WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute([$resourceId])) {
        sendResponse(['success' => true, 'message' => 'Resource deleted successfully.'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to drop structural record from storage index table.'], 500);
    }
}


// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================

/**
 * Function: Get all comments for a specific resource
 * Method: GET with ?resource_id={id}&action=comments
 */
function getCommentsByResourceId($db, $resourceId) {
    // Validate resource target mapping ID
    if ($resourceId === null || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Numeric Resource reference ID is mandatory.'], 400);
    }

    // Prepare SQL query
    $sql = "SELECT id, resource_id, author, text, created_at 
            FROM comments_resource 
            WHERE resource_id = ? 
            ORDER BY created_at ASC";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([$resourceId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return success response — always return an array even if empty
    sendResponse(['success' => true, 'data' => $comments], 200);
}


/**
 * Function: Create a new comment
 * Method: POST with ?action=comment
 */
function createComment($db, $data) {
    // Validate required fields
    $validation = validateRequiredFields($data, ['resource_id', 'author', 'text']);
    if (!$validation['valid']) {
        sendResponse([
            'success' => false, 
            'message' => 'Missing required fields: ' . implode(', ', $validation['missing'])
        ], 400);
    }

    if (!is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'Target Resource reference tracking ID must be numeric.'], 400);
    }

    $resourceId = (int)$data['resource_id'];

    // Check that the parent resource exists in the resources table
    $checkSql = "SELECT id FROM resources WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$resourceId]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Parent resource profile node targeted does not exist.'], 404);
    }

    // Sanitize author and text variables
    $author = sanitizeInput($data['author']);
    $text   = sanitizeInput($data['text']);

    // Prepare INSERT query
    $sql = "INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute([$resourceId, $author, $text])) {
        $newId = (int)$db->lastInsertId();
        
        // Fetch the fully inserted record to provide standard structural entities back to client applications
        $fetchSql = "SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE id = ?";
        $fetchStmt = $db->prepare($fetchSql);
        $fetchStmt->execute([$newId]);
        $insertedComment = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        sendResponse([
            'success' => true,
            'message' => 'Comment published successfully.',
            'id' => $newId,
            'data' => $insertedComment
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Database exception failed while posting comment message thread.'], 500);
    }
}


/**
 * Function: Delete a comment
 * Method: DELETE with ?comment_id={id}&action=delete_comment
 */
function deleteComment($db, $commentId) {
    // Validate incoming query primary identifiers
    if ($commentId === null || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Target identifier must be present and numeric.'], 400);
    }

    // Check if the comment exists
    $checkSql = "SELECT id FROM comments_resource WHERE id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$commentId]);
    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment log entry profile not found.'], 404);
    }

    // Prepare DELETE query
    $sql = "DELETE FROM comments_resource WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute([$commentId])) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.'], 200);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to remove target comment resource item trace from data maps.'], 500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByResourceId($db, $resource_id);
        } elseif ($id !== null) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateResource($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $comment_id);
        } else {
            deleteResource($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'HTTP Method Not Allowed.'], 405);
    }

} catch (PDOException $e) {
    // Log the actual server runtime exception securely on system error files
    error_log("Database Error Code: [" . $e->getCode() . "] - Description Trace: " . $e->getMessage());
    // Abstract diagnostic metrics away to keep client interaction surface safe
    sendResponse(['success' => false, 'message' => 'Internal database service connectivity exception encountered.'], 500);

} catch (Exception $e) {
    error_log("Standard Global Controller Runtime Interruption Exception: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An unrecognized processing failure has occurred.'], 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Helper: Send a JSON response and stop execution.
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if (!is_array($data)) {
        $data = ['payload' => $data];
    }
    echo json_encode($data);
    exit;
}


/**
 * Helper: Validate a URL string.
 */
function validateUrl($url) {
    return (bool)filter_var($url, FILTER_VALIDATE_URL);
}


/**
 * Helper: Sanitize a single input string.
 */
function sanitizeInput($data) {
    if ($data === null) return '';
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}


/**
 * Helper: Check that all required fields exist and are non-empty in $data.
 */
function validateRequiredFields($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $missing[] = $field;
        }
    }
    return [
        'valid' => (count($missing) === 0),
        'missing' => $missing
    ];
}
?>
