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
 * Get a map of user IDs to "Name (Role)".
 */
function getUsersMapWithRoles() {
    $users = loadJson('users');
    $map = [];
    foreach ($users as $user) {
        $map[$user['id']] = $user['name'] . " (" . ($user['role'] ?? 'user') . ")";
    }
    return $map;
}

/**
 * Get the base URL of the application.
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = str_replace(basename($scriptName), '', $scriptName);
    $path = rtrim($path, '/');
    return $protocol . $domainName . $path . '/';
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
    
    $baseUrl = getBaseUrl();
    $timestamp = date('Y-m-d H:i:s');
    $author = $data['user_name'] ?? 'System';
    
    if ($type === 'new_comment') {
        $subject = "Nuovo commento sul ticket #{$ticketId}: {$ticket['title']}";
        $contentTitle = "Nuovo Commento";
        $contentText = "È stato aggiunto un nuovo commento al ticket #{$ticketId}.";
        $detailLabel = "Testo Commento";
        $detailValue = $data['comment'] ?? '';
        
        // Notify creator, assigned tech and admins
        if ($creator) $to[] = $creator['email'];
        if ($technician) $to[] = $technician['email'];
        foreach ($admins as $admin) $to[] = $admin['email'];
    } elseif ($type === 'status_change') {
        $subject = "Cambio stato ticket #{$ticketId}: {$ticket['title']}";
        $contentTitle = "Cambio Stato";
        $contentText = "Lo stato del ticket #{$ticketId} è cambiato.";
        $detailLabel = "Nuovo Stato";
        $detailValue = $data['new_status'] ?? 'Unknown';
        
        // Notify creator and admins
        if ($creator) $to[] = $creator['email'];
        foreach ($admins as $admin) $to[] = $admin['email'];
    }

    $to = array_unique(array_filter($to));
    if (empty($to)) return;

    $hasAttachments = !empty($ticket['attachments']);
    $attachmentsLabel = $hasAttachments ? "Yes" : "No";
    $attachmentPreviews = "";
    if ($hasAttachments) {
        foreach ($ticket['attachments'] as $att) {
            $ext = strtolower(pathinfo($att['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $absUrl = $baseUrl . $att['url'];
                $attachmentPreviews .= "<div style='margin-bottom: 10px;'><img src='{$absUrl}' style='max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;' alt='{$att['name']}'></div>";
            }
        }
    }

    $body = "
    <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
        <div style='background-color: #007bff; color: #ffffff; padding: 20px; text-align: center;'>
            <h1 style='margin: 0; font-size: 24px;'>{$contentTitle}</h1>
        </div>
        <div style='padding: 20px;'>
            <p style='font-size: 16px; margin-top: 0;'>{$contentText}</p>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; width: 120px;'>ID Ticket:</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>#{$ticketId}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>Titolo:</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$ticket['title']}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>Data/Ora:</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$timestamp}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>Autore:</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$author}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>{$detailLabel}:</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; white-space: pre-wrap;'>{$detailValue}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>Attachments:</td>
                    <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$attachmentsLabel}</td>
                </tr>
            </table>
            " . ($attachmentPreviews ? "
            <div style='margin-top: 20px;'>
                <h3 style='font-size: 18px; margin-bottom: 10px;'>Anteprime Allegati:</h3>
                {$attachmentPreviews}
            </div>" : "") . "
        </div>
        <div style='background-color: #f8f9fa; color: #6c757d; padding: 15px; text-align: center; font-size: 12px;'>
            Questa è una notifica automatica dal Sistema Ticketing. Si prega di non rispondere a questa email.
        </div>
    </div>
    ";

    $headers = "From: no-reply@ticketing-system.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    foreach ($to as $email) {
        mail($email, $subject, "<html><body>" . $body . "</body></html>", $headers);
    }
}
