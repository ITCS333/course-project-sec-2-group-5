<?php
/**
 * User Management API
 *
 * A RESTful API that handles all CRUD operations for user management
 * and password changes for the Admin Portal.
 * Uses PDO to interact with a MySQL database.
 *
 * Database Table (ground truth: see schema.sql):
 * Table: users
 * Columns:
 *   - id         (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 *   - name       (VARCHAR(100), NOT NULL)
 *   - email      (VARCHAR(100), NOT NULL, UNIQUE)
 *   - password   (VARCHAR(255), NOT NULL) - bcrypt hash
 *   - is_admin   (TINYINT(1), NOT NULL, DEFAULT 0)
 *   - created_at (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
 *
 * HTTP Methods Supported:
 *   - GET    : Retrieve all users (with optional search/sort query params)
 *   - GET    : Retrieve a single user by id (?id=1)
 *   - POST   : Create a new user
 *   - POST   : Change a user's password (?action=change_password)
 *   - PUT    : Update an existing user's name, email, or is_admin
 *   - DELETE : Delete a user by id (?id=1)
 *
 * Response Format: JSON
 * All responses have the shape:
 *   { "success": true,  "data": ... }
 *   { "success": false, "message": "..." }
 */


// TODO: Set headers for JSON response and CORS.
// Set Content-Type to application/json.
// Allow cross-origin requests (CORS) if needed.
// Allow specific HTTP methods: GET, POST, PUT, DELETE, OPTIONS.
// Allow specific headers: Content-Type, Authorization.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


// TODO: Handle preflight OPTIONS request.
// If the request method is OPTIONS, return HTTP 200 and exit.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


// TODO: Include the database connection file.
// Assume a function getDBConnection() is available that returns a PDO instance
// configured for the 'course' database (see schema.sql).
require_once '../../common/db.php';


// TODO: Get the PDO database connection by calling getDBConnection().
$db = getDBConnection();


// TODO: Read the HTTP request method from $_SERVER['REQUEST_METHOD'].
$method = $_SERVER['REQUEST_METHOD'];


// TODO: Read the raw request body for POST and PUT requests.
// Use file_get_contents('php://input') and decode with json_decode($raw, true).
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];


// TODO: Read query string parameters.
// Relevant parameters:
//   - id            (int)    : identifies a specific user by primary key
//   - action        (string) : 'change_password' to route password-change requests
//   - search        (string) : free-text filter for GET requests
//   - sort          (string) : field name to sort by
//   - order         (string) : 'asc' or 'desc'
$id     = isset($_GET['id'])     ? (int) $_GET['id']       : null;
$action = isset($_GET['action']) ? trim($_GET['action'])    : null;
$search = isset($_GET['search']) ? trim($_GET['search'])    : null;
$sort   = isset($_GET['sort'])   ? trim($_GET['sort'])      : null;
$order  = isset($_GET['order'])  ? strtolower(trim($_GET['order'])) : 'asc';


/**
 * Function: Get all users, or search/filter users.
 * Method: GET (no ?id parameter)
 *
 * Supported query parameters:
 *   - search (string) : filters rows where name LIKE or email LIKE the term
 *   - sort   (string) : column to sort by; allowed values: name, email, is_admin
 *   - order  (string) : sort direction; allowed values: asc, desc (default: asc)
 *
 * Notes:
 *   - Never return the password column in the response.
 *   - Validate the 'sort' value against the whitelist (name, email, is_admin)
 *     to prevent SQL injection before interpolating it into the ORDER BY clause.
 *   - Validate the 'order' value; only accept 'asc' or 'desc'.
 */
function getUsers($db) {
    global $search, $sort, $order;

    // TODO: Build a SELECT query for id, name, email, is_admin, created_at.
    //       Do NOT select the password column.
    $sql    = 'SELECT id, name, email, is_admin, created_at FROM users';
    $params = [];

    // TODO: If the 'search' query parameter is present, append a WHERE clause:
    //       WHERE name LIKE :search OR email LIKE :search
    //       Wrap the search term with '%' wildcards when binding.
    if ($search !== null && $search !== '') {
        $sql           .= ' WHERE name LIKE :search OR email LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    // TODO: If the 'sort' query parameter is present and is one of the allowed
    //       fields (name, email, is_admin), append an ORDER BY clause.
    //       If 'order' is 'desc', use DESC; otherwise default to ASC.
    $allowedSort  = ['name', 'email', 'is_admin'];
    $allowedOrder = ['asc', 'desc'];

    if ($sort !== null && in_array($sort, $allowedSort, true)) {
        $dir  = in_array($order, $allowedOrder, true) ? strtoupper($order) : 'ASC';
        $sql .= ' ORDER BY ' . $sort . ' ' . $dir;
    }

    // TODO: Prepare the statement, bind any parameters, and execute.
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // TODO: Fetch all rows as an associative array.
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // TODO: Call sendResponse() with the array and HTTP status 200.
    sendResponse($rows, 200);
}


/**
 * Function: Get a single user by primary key.
 * Method: GET with ?id=<int>
 *
 * Query parameters:
 *   - id (int, required) : the user's primary key in the users table
 */
function getUserById($db, $id) {
    // TODO: Prepare SELECT query: SELECT id, name, email, is_admin, created_at
    //       FROM users WHERE id = :id
    //       Do NOT select the password column.
    $stmt = $db->prepare(
        'SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id'
    );

    // TODO: Bind :id and execute.
    $stmt->execute([':id' => $id]);

    // TODO: Fetch one row.
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // TODO: If no row is found, call sendResponse() with an error message and HTTP 404.
    //       If found, call sendResponse() with the row and HTTP 200.
    if (!$user) {
        sendResponse('User not found', 404);
    }
    sendResponse($user, 200);
}


/**
 * Function: Create a new user.
 * Method: POST (no ?action parameter)
 *
 * Expected JSON body:
 *   - name     (string, required)
 *   - email    (string, required) - must be a valid email address and unique
 *   - password (string, required) - plaintext; will be hashed before storage
 *   - is_admin (int, optional)    - 0 (student) or 1 (admin); defaults to 0
 */
function createUser($db, $data) {
    // TODO: Check that name, email, and password are all present and non-empty.
    //       If any are missing, call sendResponse() with HTTP 400.
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        sendResponse('Name, email, and password are required', 400);
    }

    // TODO: Trim whitespace from name, email, and password.
    //       Validate email format with filter_var(FILTER_VALIDATE_EMAIL).
    //       If invalid, call sendResponse() with HTTP 400.
    $name     = sanitizeInput($data['name']);
    $email    = sanitizeInput($data['email']);
    $password = trim($data['password']);

    if (!validateEmail($email)) {
        sendResponse('Invalid email format', 400);
    }

    // TODO: Validate that password is at least 8 characters.
    //       If not, call sendResponse() with HTTP 400.
    if (strlen($password) < 8) {
        sendResponse('Password must be at least 8 characters', 400);
    }

    // TODO: Check whether the email already exists in the users table.
    //       If it does, call sendResponse() with an appropriate message and HTTP 409.
    $check = $db->prepare('SELECT id FROM users WHERE email = :email');
    $check->execute([':email' => $email]);
    if ($check->fetch()) {
        sendResponse('Email address is already registered', 409);
    }

    // TODO: Hash the password using password_hash($password, PASSWORD_DEFAULT).
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // TODO: Read is_admin from $data; default to 0 if not provided.
    //       Accept only the values 0 or 1.
    $isAdmin = isset($data['is_admin']) && $data['is_admin'] == 1 ? 1 : 0;

    // TODO: Prepare and execute an INSERT INTO users (name, email, password, is_admin)
    //       VALUES (:name, :email, :password, :is_admin).
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password, is_admin) VALUES (:name, :email, :password, :is_admin)'
    );
    $stmt->execute([
        ':name'     => $name,
        ':email'    => $email,
        ':password' => $hashed,
        ':is_admin' => $isAdmin,
    ]);

    // TODO: If the insert succeeds, call sendResponse() with the new user's id and HTTP 201.
    //       If it fails, call sendResponse() with HTTP 500.
    $newId = $db->lastInsertId();
    sendResponse(['id' => (int) $newId], 201);
}


/**
 * Function: Update an existing user.
 * Method: PUT
 *
 * Expected JSON body:
 *   - id       (int, required)    : primary key of the user to update
 *   - name     (string, optional) : new name
 *   - email    (string, optional) : new email (must remain unique)
 *   - is_admin (int, optional)    : 0 or 1
 *
 * Note: password changes are handled by the separate changePassword endpoint.
 */
function updateUser($db, $data) {
    // TODO: Check that id is present in $data.
    //       If not, call sendResponse() with HTTP 400.
    if (empty($data['id'])) {
        sendResponse('User id is required', 400);
    }

    $id = (int) $data['id'];

    // TODO: Look up the user by id. If not found, call sendResponse() with HTTP 404.
    $existing = $db->prepare('SELECT id FROM users WHERE id = :id');
    $existing->execute([':id' => $id]);
    if (!$existing->fetch()) {
        sendResponse('User not found', 404);
    }

    // TODO: Dynamically build the SET clause for only the fields provided
    //       (name, email, is_admin). Skip any field not present in $data.
    $setClauses = [];
    $params     = [':id' => $id];

    if (isset($data['name']) && $data['name'] !== '') {
        $setClauses[]    = 'name = :name';
        $params[':name'] = sanitizeInput($data['name']);
    }

    if (isset($data['email']) && $data['email'] !== '') {
        $newEmail = sanitizeInput($data['email']);

        if (!validateEmail($newEmail)) {
            sendResponse('Invalid email format', 400);
        }

        // TODO: If email is being updated, check it is not already used by another user
        //       (exclude the current user's id from the duplicate check).
        //       If a duplicate is found, call sendResponse() with HTTP 409.
        $dupCheck = $db->prepare(
            'SELECT id FROM users WHERE email = :email AND id != :id'
        );
        $dupCheck->execute([':email' => $newEmail, ':id' => $id]);
        if ($dupCheck->fetch()) {
            sendResponse('Email address is already in use', 409);
        }

        $setClauses[]     = 'email = :email';
        $params[':email'] = $newEmail;
    }

    if (isset($data['is_admin'])) {
        $setClauses[]       = 'is_admin = :is_admin';
        $params[':is_admin'] = ($data['is_admin'] == 1) ? 1 : 0;
    }

    if (empty($setClauses)) {
        // Nothing to update — still a success
        sendResponse('No changes provided', 200);
    }

    // TODO: Prepare the UPDATE statement, bind parameters, and execute.
    $sql  = 'UPDATE users SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // TODO: If successful, call sendResponse() with a success message and HTTP 200.
    //       If no rows were affected, still return HTTP 200 (no change is not an error).
    //       If the query fails, call sendResponse() with HTTP 500.
    sendResponse('User updated successfully', 200);
}


/**
 * Function: Delete a user by primary key.
 * Method: DELETE
 *
 * Query parameter:
 *   - id (int, required) : primary key of the user to delete
 */
function deleteUser($db, $id) {
    // TODO: Check that $id is present and non-zero.
    //       If not, call sendResponse() with HTTP 400.
    if (!$id) {
        sendResponse('User id is required', 400);
    }

    // TODO: Check that a user with this id exists.
    //       If not, call sendResponse() with HTTP 404.
    $check = $db->prepare('SELECT id FROM users WHERE id = :id');
    $check->execute([':id' => $id]);
    if (!$check->fetch()) {
        sendResponse('User not found', 404);
    }

    // TODO: Prepare and execute: DELETE FROM users WHERE id = :id
    $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);

    // TODO: If successful, call sendResponse() with a success message and HTTP 200.
    //       If the query fails, call sendResponse() with HTTP 500.
    sendResponse('User deleted successfully', 200);
}


/**
 * Function: Change a user's password.
 * Method: POST with ?action=change_password
 *
 * Expected JSON body:
 *   - id               (int, required)    : primary key of the user whose password is changing
 *   - current_password (string, required) : must match the stored bcrypt hash
 *   - new_password     (string, required) : plaintext; will be hashed before storage
 */
function changePassword($db, $data) {
    // TODO: Check that id, current_password, and new_password are all present.
    //       If any are missing, call sendResponse() with HTTP 400.
    if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
        sendResponse('id, current_password, and new_password are required', 400);
    }

    $id          = (int) $data['id'];
    $currentPass = $data['current_password'];
    $newPass     = $data['new_password'];

    // TODO: Validate that new_password is at least 8 characters.
    //       If not, call sendResponse() with HTTP 400.
    if (strlen($newPass) < 8) {
        sendResponse('Password must be at least 8 characters', 400);
    }

    // TODO: SELECT password FROM users WHERE id = :id to retrieve the current hash.
    //       If no user is found, call sendResponse() with HTTP 404.
    $stmt = $db->prepare('SELECT password FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse('User not found', 404);
    }

    // TODO: Call password_verify($current_password, $hash).
    //       If verification fails, call sendResponse() with HTTP 401 (Unauthorized).
    if (!password_verify($currentPass, $user['password'])) {
        sendResponse('Current password is incorrect', 401);
    }

    // TODO: Hash the new password: password_hash($new_password, PASSWORD_DEFAULT).
    $hashed = password_hash($newPass, PASSWORD_DEFAULT);

    // TODO: Prepare and execute: UPDATE users SET password = :password WHERE id = :id
    $update = $db->prepare('UPDATE users SET password = :password WHERE id = :id');
    $update->execute([':password' => $hashed, ':id' => $id]);

    // TODO: If successful, call sendResponse() with a success message and HTTP 200.
    //       If the query fails, call sendResponse() with HTTP 500.
    sendResponse('Password updated successfully', 200);
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {
        // TODO: If the 'id' query parameter is present and non-empty, call getUserById($db, $id).
        // TODO: Otherwise, call getUsers($db) (supports optional search/sort parameters).
        if ($id) {
            getUserById($db, $id);
        } else {
            getUsers($db);
        }

    } elseif ($method === 'POST') {
        // TODO: If the 'action' query parameter equals 'change_password', call changePassword($db, $data).
        // TODO: Otherwise, call createUser($db, $data).
        if ($action === 'change_password') {
            changePassword($db, $data);
        } else {
            createUser($db, $data);
        }

    } elseif ($method === 'PUT') {
        // TODO: Call updateUser($db, $data).
        //       The user id to update comes from the JSON body, not the query string.
        updateUser($db, $data);

    } elseif ($method === 'DELETE') {
        // TODO: Read the 'id' query parameter.
        // TODO: Call deleteUser($db, $id).
        deleteUser($db, $id);

    } else {
        // TODO: Return HTTP 405 (Method Not Allowed) with a JSON error message.
        sendResponse('Method not allowed', 405);
    }

} catch (PDOException $e) {
    // TODO: Log the error (e.g. error_log($e->getMessage())).
    // TODO: Call sendResponse() with a generic "Database error" message and HTTP 500.
    //       Do NOT expose the raw exception message to the client.
    error_log($e->getMessage());
    sendResponse('A database error occurred. Please try again later.', 500);

} catch (Exception $e) {
    // TODO: Call sendResponse() with the exception message and HTTP 500.
    sendResponse($e->getMessage(), 500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Sends a JSON response and terminates execution.
 *
 * @param mixed $data       Data to include in the response.
 *                          On success, pass the payload directly.
 *                          On error, pass a string message.
 * @param int   $statusCode HTTP status code (default 200).
 */
function sendResponse($data, $statusCode = 200) {
    // TODO: Call http_response_code($statusCode).
    http_response_code($statusCode);

    // TODO: If $statusCode indicates success (< 400), echo:
    //         json_encode(['success' => true, 'data' => $data])
    //       Otherwise echo:
    //         json_encode(['success' => false, 'message' => $data])
    if ($statusCode < 400) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => $data]);
    }

    // TODO: Call exit to stop further execution.
    exit;
}


/**
 * Validates an email address.
 *
 * @param  string $email
 * @return bool   True if the email passes FILTER_VALIDATE_EMAIL, false otherwise.
 */
function validateEmail($email) {
    // TODO: return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}


/**
 * Sanitizes a string input value.
 * Use this before inserting user-supplied strings into the database.
 *
 * @param  string $data
 * @return string Trimmed, tag-stripped, and HTML-escaped string.
 */
function sanitizeInput($data) {
    // TODO: trim($data)
    // TODO: strip_tags(...)
    // TODO: htmlspecialchars(..., ENT_QUOTES, 'UTF-8')
    // TODO: Return the sanitized value.
    $data = trim($data);
    $data = strip_tags($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

?>
