<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $postData = [];
    parse_str($input, $postData);
    
    $action = $postData['action'] ?? '';
    $token = $postData['token'] ?? '';
    $username = $postData['username'] ?? '';
    $repo = $postData['repo'] ?? '';

    if (empty($token)) {
        echo json_encode(['error' => 'Token GitHub diperlukan']);
        exit;
    }

    switch ($action) {
        case 'fetch_repos':
            fetchRepositories($token, $username);
            break;
        case 'delete_repo':
            deleteRepository($token, $username, $repo);
            break;
        default:
            echo json_encode(['error' => 'Aksi tidak valid']);
            break;
    }
}

function fetchRepositories($token, $username) {
    $url = "https://api.github.com/user/repos?per_page=100";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: PHP-Script',
        'Authorization: token ' . $token,
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        echo json_encode(['error' => $error['message'] ?? 'Gagal mengambil repository']);
        return;
    }
    
    $repos = json_decode($response, true);
    
    if (!empty($username)) {
        $repos = array_filter($repos, function($repo) use ($username) {
            return $repo['owner']['login'] === $username;
        });
        $repos = array_values($repos);
    }
    
    $formattedRepos = array_map(function($repo) {
        return [
            'name' => $repo['name'],
            'description' => $repo['description'],
            'private' => $repo['private'],
            'stargazers_count' => $repo['stargazers_count'],
            'forks_count' => $repo['forks_count']
        ];
    }, $repos);
    
    echo json_encode(['repositories' => $formattedRepos]);
}

function deleteRepository($token, $username, $repo) {
    if (empty($repo)) {
        echo json_encode(['error' => 'Nama repository diperlukan']);
        return;
    }
    
    $url = "https://api.github.com/repos/{$username}/{$repo}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: PHP-Script',
        'Authorization: token ' . $token,
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 204) {
        echo json_encode(['success' => true]);
    } else {
        $error = json_decode($response, true);
        echo json_encode(['error' => $error['message'] ?? 'Gagal menghapus repository']);
    }
}
?>