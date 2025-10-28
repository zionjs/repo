<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['token'] ?? '';
    $username = $_POST['username'] ?? '';
    $repo = $_POST['repo'] ?? '';

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
        'User-Agent: PHP Script',
        'Authorization: token ' . $token,
        'Accept: application/vnd.github.v3+json'
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
    
    // Filter repository berdasarkan username jika diberikan
    if (!empty($username)) {
        $repos = array_filter($repos, function($repo) use ($username) {
            return $repo['owner']['login'] === $username;
        });
        $repos = array_values($repos); // Reset array keys
    }
    
    // Format data repository
    $formattedRepos = array_map(function($repo) {
        return [
            'name' => $repo['name'],
            'full_name' => $repo['full_name'],
            'description' => $repo['description'],
            'private' => $repo['private'],
            'html_url' => $repo['html_url'],
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
        'User-Agent: PHP Script',
        'Authorization: token ' . $token,
        'Accept: application/vnd.github.v3+json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 204) {
        echo json_encode(['success' => true, 'message' => 'Repository berhasil dihapus']);
    } else {
        $error = json_decode($response, true);
        echo json_encode(['error' => $error['message'] ?? 'Gagal menghapus repository']);
    }
}
?>