<?php

ob_start();


header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, *");
header("Access-Control-Expose-Headers: Authorization");


if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    while (ob_get_level()) ob_end_clean();
    exit();
}


function getAuthorizationHeader() {
    $headers = null;
    
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    return $headers;
}

function getBearerToken() {
    $headers = getAuthorizationHeader();
    
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}


require_once 'auth/Auth.php';
require_once 'models/User.php';
require_once 'models/Task.php';

$auth = new Auth();


$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'], '/'));
$endpoint = $request[0] ?? '';


if ($method === 'GET' && $endpoint === 'test-token') {
    $token = getBearerToken();
    
    if (empty($token)) {
        http_response_code(400);
        while (ob_get_level()) ob_end_clean();
        echo json_encode([
            'status' => 'error', 
            'message' => 'No token provided'
        ]);
        exit();
    }
    
    $userId = $auth->validateToken($token);
    
    if ($userId) {
        while (ob_get_level()) ob_end_clean();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Token is valid',
            'user_id' => $userId
        ]);
    } else {
        while (ob_get_level()) ob_end_clean();
        echo json_encode([
            'status' => 'error', 
            'message' => 'Token validation failed'
        ]);
    }
    exit();
}

try {
    $response = [];
    
    switch ($method) {
        case 'POST':
            if ($endpoint == 'register') {
                $input = json_decode(file_get_contents("php://input"), true);
                
                if (!isset($input['name']) || empty($input['name'])) {
                    throw new Exception("Name is required");
                }
                if (!isset($input['email']) || empty($input['email'])) {
                    throw new Exception("Email is required");
                }
                if (!isset($input['password']) || empty($input['password'])) {
                    throw new Exception("Password is required");
                }
                if (strlen($input['password']) < 6) {
                    throw new Exception("Password must be at least 6 characters long");
                }
                if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format");
                }

                $user = new User();
                $user->name = $input['name'];
                $user->email = $input['email'];
                $user->password = $auth->hashPassword($input['password']);

                if ($user->register()) {
                    $token = $auth->generateToken($user->id);
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'User registered successfully',
                        'token' => $token,
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email
                        ]
                    ];
                } else {
                    throw new Exception("Failed to register user");
                }

            } elseif ($endpoint == 'login') {
                $input = json_decode(file_get_contents("php://input"), true);
                
                if (!isset($input['email']) || !isset($input['password'])) {
                    throw new Exception("Email and password are required");
                }

                $user = new User();
                $user->email = $input['email'];

                if ($user->emailExists() && $auth->verifyPassword($input['password'], $user->password)) {
                    $token = $auth->generateToken($user->id);
                    $response = [
                        'status' => 'success',
                        'message' => 'Login successful',
                        'token' => $token,
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email
                        ]
                    ];
                } else {
                    throw new Exception("Invalid email or password");
                }
            } elseif ($endpoint == 'tasks') {
                $token = getBearerToken();

                $user_id = $auth->validateToken($token);
                if (!$user_id) {
                    throw new Exception("Invalid or expired token");
                }

                $input = json_decode(file_get_contents("php://input"), true);
                
                if (!isset($input['title']) || empty($input['title'])) {
                    throw new Exception("Title is required");
                }

                $task = new Task();
                $task->user_id = $user_id;
                $task->title = $input['title'];
                $task->description = $input['description'] ?? '';
                $task->status = $input['status'] ?? 'pending';

                if ($task->create()) {
                    $response = [
                        'status' => 'success',
                        'message' => 'Task created successfully'
                    ];
                } else {
                    throw new Exception("Failed to create task");
                }
            }
            break;

        case 'GET':
            if ($endpoint == 'tasks') {
                $token = getBearerToken();

                $user_id = $auth->validateToken($token);
                if (!$user_id) {
                    throw new Exception("Invalid or expired token");
                }

                $task = new Task();
                $stmt = $task->read($user_id);
                $num = $stmt->rowCount();

                $tasks_arr = array();
                if ($num > 0) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $task_item = array(
                            'id' => $row['id'],
                            'title' => $row['title'],
                            'description' => $row['description'],
                            'status' => $row['status'],
                            'created_at' => $row['created_at']
                        );
                        array_push($tasks_arr, $task_item);
                    }
                }

                $response = [
                    'status' => 'success',
                    'data' => $tasks_arr
                ];
            }
            break;

        case 'PUT':
            if ($endpoint == 'tasks' && isset($request[1])) {
                $token = getBearerToken();

                $user_id = $auth->validateToken($token);
                if (!$user_id) {
                    throw new Exception("Invalid or expired token");
                }

                $task_id = $request[1];
                $input = json_decode(file_get_contents("php://input"), true);

                $task = new Task();
                $task->id = $task_id;
                $task->user_id = $user_id;

                if (!$task->readOne()) {
                    throw new Exception("Task not found");
                }

                $task->title = $input['title'] ?? $task->title;
                $task->description = $input['description'] ?? $task->description;
                $task->status = $input['status'] ?? $task->status;

                if ($task->update()) {
                    $response = [
                        'status' => 'success',
                        'message' => 'Task updated successfully'
                    ];
                } else {
                    throw new Exception("Failed to update task");
                }
            }
            break;

        case 'DELETE':
            if ($endpoint == 'tasks' && isset($request[1])) {
                $token = getBearerToken();

                $user_id = $auth->validateToken($token);
                if (!$user_id) {
                    throw new Exception("Invalid or expired token");
                }

                $task_id = $request[1];
                $task = new Task();
                $task->id = $task_id;
                $task->user_id = $user_id;

                if ($task->delete()) {
                    $response = [
                        'status' => 'success',
                        'message' => 'Task deleted successfully'
                    ];
                } else {
                    throw new Exception("Failed to delete task");
                }
            }
            break;

        default:
            throw new Exception("Method not allowed");
    }


    http_response_code(200);
    while (ob_get_level()) ob_end_clean();
    echo json_encode($response);
    exit();

} catch (Exception $e) {
    http_response_code(400);
    while (ob_get_level()) ob_end_clean();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit();
}

?>