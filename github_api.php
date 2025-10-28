<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to send JSON response
function sendResponse($data) {
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Function to handle API errors
function handleError($message, $httpCode = 400) {
    http_response_code($httpCode);
    sendResponse(['error' => $message]);
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw POST data
    $input = file_get_contents('php://input');
    $postData = [];
    parse_str($input, $postData);
    
    $action = $postData['action'] ?? '';
    $token = $postData['token'] ?? '';
    $username = $postData['username'] ?? '';
    $repo = $postData['repo'] ?? '';

    if (empty($token)) {
        handleError('Token GitHub diperlukan');
    }

    switch ($action) {
        case 'fetch_repos':
            fetchRepositories($token, $username);
            break;
        case 'delete_repo':
            deleteRepository($token, $username, $repo);
            break;
        default:
            handleError('Aksi tidak valid');
            break;
    }
} else {
    handleError('Metode request tidak diizinkan', 405);
}

function fetchRepositories($token, $username) {
    // URL untuk mengambil repository user
    $url = "https://api.github.com/user/repos?per_page=100&sort=updated";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: PHP-GitHub-Client',
            'Authorization: token ' . $token,
            'Accept: application/vnd.github.v3+json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        handleError('CURL Error: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['message'] ?? 'Gagal mengambil repository. HTTP Code: ' . $httpCode;
        handleError($errorMessage, $httpCode);
    }
    
    $repos = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        handleError('Error parsing response dari GitHub API');
    }
    
    // Filter repository berdasarkan username jika diberikan
    if (!empty($username)) {
        $repos = array_filter($repos, function($repo) use ($username) {
            return isset($repo['owner']['login']) && $repo['owner']['login'] === $username;
        });
        $repos = array_values($repos); // Reset array keys
    }
    
    // Format data repository
    $formattedRepos = array_map(function($repo) {
        return [
            'name' => $repo['name'] ?? 'Unknown',
            'full_name' => $repo['full_name'] ?? 'Unknown',
            'description' => $repo['description'] ?? '',
            'private' => $repo['private'] ?? false,
            'html_url' => $repo['html_url'] ?? '',
            'stargazers_count' => $repo['stargazers_count'] ?? 0,
            'forks_count' => $repo['forks_count'] ?? 0,
            'updated_at' => $repo['updated_at'] ?? ''
        ];
    }, $repos);
    
    sendResponse([
        'success' => true,
        'repositories' => $formattedRepos,
        'count' => count($formattedRepos)
    ]);
}

function deleteRepository($token, $username, $repo) {
    if (empty($repo)) {
        handleError('Nama repository diperlukan');
    }
    
    if (empty($username)) {
        handleError('Username diperlukan untuk menghapus repository');
    }
    
    $url = "https://api.github.com/repos/{$username}/{$repo}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'User-Agent: PHP-GitHub-Client',
            'Authorization: token ' . $token,
            'Accept: application/vnd.github.v3+json'
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        handleError('CURL Error: ' . $curlError);
    }
    
    if ($httpCode === 204) {
        sendResponse([
            'success' => true, 
            'message' => 'Repository ' . $repo . ' berhasil dihapus'
        ]);
    } else {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['message'] ?? 'Gagal menghapus repository. HTTP Code: ' . $httpCode;
        handleError($errorMessage, $httpCode);
    }
}
?>