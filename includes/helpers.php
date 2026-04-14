<?php

/**
 * Load data from a JSON file.
 *
 * @param string $filename The name of the file in the data/ directory.
 * @return array The decoded data.
 */
function loadJson($filename) {
    $path = __DIR__ . '/../data/' . $filename . '.json';
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    return json_decode($json, true) ?: [];
}

/**
 * Save data to a JSON file.
 *
 * @param string $filename The name of the file in the data/ directory.
 * @param array $data The data to save.
 * @return bool True on success, false on failure.
 */
function saveJson($filename, $data) {
    $path = __DIR__ . '/../data/' . $filename . '.json';
    $json = json_encode($data, JSON_PRETTY_PRINT);
    return file_put_contents($path, $json) !== false;
}

/**
 * Redirect to a given URL.
 *
 * @param string $url
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Simple JSON response helper.
 *
 * @param mixed $data
 * @param int $status
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get an asset URL with versioning based on file modification time.
 */
function asset($path) {
    $fullPath = __DIR__ . '/../' . $path;
    if (file_exists($fullPath)) {
        return $path . '?v=' . filemtime($fullPath);
    }
    return $path;
}

/**
 * Get the next ID for a JSON data file.
 */
function getNextId($filename) {
    $data = loadJson($filename);
    if (empty($data)) {
        return 1;
    }
    $ids = array_column($data, 'id');
    return max($ids) + 1;
}

/**
 * Get a map of user IDs to names.
 */
function getUsersMap() {
    $users = loadJson('users');
    $map = [];
    foreach ($users as $user) {
        $map[$user['id']] = $user['name'];
    }
    return $map;
}
