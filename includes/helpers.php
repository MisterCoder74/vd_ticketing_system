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

/**
 * Log an activity to data/logs.json.
 *
 * @param int $userId
 * @param string $action
 * @param string $details
 */
function logActivity($userId, $action, $details) {
    $logs = loadJson('logs');
    $usersMap = getUsersMap();
    $userName = $usersMap[$userId] ?? 'Unknown';
    
    $logs[] = [
        'id' => empty($logs) ? 1 : max(array_column($logs, 'id')) + 1,
        'user_id' => $userId,
        'user_name' => $userName,
        'action' => $action,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    saveJson('logs', $logs);
}

/**
 * Send email notifications for ticket events.
 *
 * @param int $ticketId
 * @param string $type ('new_comment' or 'status_change')
 * @param array $data
 */
function sendNotification($ticketId, $type, $data) {
    $tickets = loadJson('tickets');
    $ticket = null;
    foreach ($tickets as $t) {
        if ($t['id'] == $ticketId) {
            $ticket = $t;
            break;
        }
    }
    if (!$ticket) return;

    $users = loadJson('users');
    $creator = null;
    $technician = null;
    $admins = [];

    foreach ($users as $user) {
        if ($user['id'] == $ticket['created_by']) $creator = $user;
        if (isset($ticket['assigned_to']) && $user['id'] == $ticket['assigned_to']) $technician = $user;
        if ($user['role'] == 'admin') $admins[] = $user;
    }

    $to = [];
    $subject = "";
    $message = "";

    if ($type === 'new_comment') {
        $subject = "Nuovo commento sul ticket #{$ticketId}: {$ticket['title']}";
        $message = "E' stato aggiunto un nuovo commento al ticket #{$ticketId}.\n\n";
        $message .= "Autore: " . ($data['user_name'] ?? 'Qualcuno') . "\n";
        $message .= "Commento: " . ($data['comment'] ?? '') . "\n";
        
        // Notify creator, assigned tech and admins
        if ($creator) $to[] = $creator['email'];
        if ($technician) $to[] = $technician['email'];
        foreach ($admins as $admin) $to[] = $admin['email'];
    } elseif ($type === 'status_change') {
        $subject = "Cambio stato ticket #{$ticketId}: {$ticket['title']}";
        $message = "Lo stato del ticket #{$ticketId} e' cambiato in: {$data['new_status']}.\n";
        
        // Notify creator and admins
        if ($creator) $to[] = $creator['email'];
        foreach ($admins as $admin) $to[] = $admin['email'];
    }

    $to = array_unique(array_filter($to));
    if (empty($to)) return;

    $headers = "From: no-reply@ticketing-system.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    foreach ($to as $email) {
        mail($email, $subject, $message, $headers);
    }
}
