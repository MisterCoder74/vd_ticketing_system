<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'get_tickets':
        $tickets = loadJson('tickets');
        $usersMap = getUsersMap();
        
        // Filter tickets based on role
        if (hasRole('user')) {
            $tickets = array_filter($tickets, function($t) {
                return $t['created_by'] == $_SESSION['user']['id'];
            });
            $tickets = array_values($tickets);
        }

        // Add creator and assignee names
        foreach ($tickets as &$t) {
            $t['created_by_name'] = $usersMap[$t['created_by']] ?? 'Unknown';
            $t['assigned_to_name'] = $usersMap[$t['assigned_to'] ?? 0] ?? 'Unassigned';
        }
        
        jsonResponse($tickets);
        break;

    case 'get_ticket':
        $id = $_GET['id'] ?? 0;
        $tickets = loadJson('tickets');
        $ticket = null;
        foreach ($tickets as $t) {
            if ($t['id'] == $id) {
                $ticket = $t;
                break;
            }
        }
        if ($ticket) {
            // Check permissions
            if (hasRole('user') && $ticket['created_by'] != $_SESSION['user']['id']) {
                jsonResponse(['error' => 'Forbidden'], 403);
            }
            
            $usersMap = getUsersMap();
            $ticket['created_by_name'] = $usersMap[$ticket['created_by']] ?? 'Unknown';
            $ticket['assigned_to_name'] = $usersMap[$ticket['assigned_to'] ?? 0] ?? 'Unassigned';

            // Load comments for this ticket
            $comments = loadJson('comments');
            $ticket['comments'] = array_filter($comments, function($c) use ($id) {
                return $c['ticket_id'] == $id;
            });
            $ticket['comments'] = array_values($ticket['comments']);
            foreach ($ticket['comments'] as &$c) {
                $c['user_name'] = $usersMap[$c['user_id']] ?? 'Unknown';
            }

            // Load files
            $uploadDir = __DIR__ . "/uploads/{$id}/uploads/";
            $ticket['files'] = [];
            if (is_dir($uploadDir)) {
                $files = array_diff(scandir($uploadDir), ['.', '..']);
                foreach ($files as $file) {
                    $ticket['files'][] = [
                        'name' => $file,
                        'url' => "uploads/{$id}/uploads/{$file}"
                    ];
                }
            }
            
            jsonResponse($ticket);
        } else {
            jsonResponse(['error' => 'Ticket not found'], 404);
        }
        break;

    case 'create_ticket':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $priority = $_POST['priority'] ?? 'medium';

        if (empty($title) || empty($description)) {
            jsonResponse(['error' => 'Title and description are required'], 400);
        }

        $tickets = loadJson('tickets');
        $newTicket = [
            'id' => getNextId('tickets'),
            'title' => $title,
            'description' => $description,
            'status' => 'open',
            'priority' => $priority,
            'created_by' => $_SESSION['user']['id'],
            'assigned_to' => null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $tickets[] = $newTicket;
        saveJson('tickets', $tickets);
        
        jsonResponse($newTicket, 201);
        break;

    case 'add_comment':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $ticketId = $_POST['ticket_id'] ?? 0;
        $commentText = $_POST['comment'] ?? '';

        if (empty($commentText)) {
            jsonResponse(['error' => 'Comment cannot be empty'], 400);
        }

        $tickets = loadJson('tickets');
        $ticket = null;
        foreach ($tickets as $t) {
            if ($t['id'] == $ticketId) {
                $ticket = $t;
                break;
            }
        }

        if (!$ticket) jsonResponse(['error' => 'Ticket not found'], 404);

        // Permissions: Admin, tech, or creator
        if (hasRole('user') && $ticket['created_by'] != $_SESSION['user']['id']) {
            jsonResponse(['error' => 'Forbidden'], 403);
        }

        $comments = loadJson('comments');
        $newComment = [
            'id' => getNextId('comments'),
            'ticket_id' => (int)$ticketId,
            'user_id' => $_SESSION['user']['id'],
            'comment' => $commentText,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $comments[] = $newComment;
        saveJson('comments', $comments);
        
        $newComment['user_name'] = $_SESSION['user']['name'];
        jsonResponse($newComment, 201);
        break;

    case 'update_status':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!hasRole(['admin', 'technician'])) jsonResponse(['error' => 'Forbidden'], 403);

        $ticketId = $_POST['ticket_id'] ?? 0;
        $status = $_POST['status'] ?? '';

        if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
            jsonResponse(['error' => 'Invalid status'], 400);
        }

        $tickets = loadJson('tickets');
        $found = false;
        foreach ($tickets as &$t) {
            if ($t['id'] == $ticketId) {
                $t['status'] = $status;
                $found = true;
                break;
            }
        }

        if ($found) {
            saveJson('tickets', $tickets);
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Ticket not found'], 404);
        }
        break;

    case 'assign_ticket':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!hasRole(['admin', 'technician'])) jsonResponse(['error' => 'Forbidden'], 403);

        $ticketId = $_POST['ticket_id'] ?? 0;
        $assigneeId = $_POST['assigned_to'] ?? null;

        // Technicians can only assign to themselves if not admin
        if (!hasRole('admin') && $assigneeId != $_SESSION['user']['id']) {
             jsonResponse(['error' => 'Technicians can only assign to themselves'], 403);
        }

        $tickets = loadJson('tickets');
        $found = false;
        foreach ($tickets as &$t) {
            if ($t['id'] == $ticketId) {
                $t['assigned_to'] = $assigneeId ? (int)$assigneeId : null;
                $found = true;
                break;
            }
        }

        if ($found) {
            saveJson('tickets', $tickets);
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Ticket not found'], 404);
        }
        break;

    case 'upload_file':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        
        $ticketId = $_POST['ticket_id'] ?? 0;
        if (!$ticketId) jsonResponse(['error' => 'Ticket ID required'], 400);

        $tickets = loadJson('tickets');
        $ticket = null;
        foreach ($tickets as $t) {
            if ($t['id'] == $ticketId) {
                $ticket = $t;
                break;
            }
        }
        if (!$ticket) jsonResponse(['error' => 'Ticket not found'], 404);
        if (hasRole('user') && $ticket['created_by'] != $_SESSION['user']['id']) {
            jsonResponse(['error' => 'Forbidden'], 403);
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'File upload failed'], 400);
        }

        $uploadDir = __DIR__ . "/uploads/{$ticketId}/uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = basename($_FILES['file']['name']);
        // Sanitize filename
        $fileName = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $fileName);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            jsonResponse(['success' => true, 'file' => [
                'name' => $fileName,
                'url' => "uploads/{$ticketId}/uploads/{$fileName}"
            ]]);
        } else {
            jsonResponse(['error' => 'Failed to move uploaded file'], 500);
        }
        break;

    case 'get_technicians':
        if (!hasRole(['admin', 'technician'])) jsonResponse(['error' => 'Forbidden'], 403);
        $users = loadJson('users');
        $technicians = array_filter($users, function($u) {
            return in_array($u['role'], ['admin', 'technician']);
        });
        jsonResponse(array_values(array_map(function($u) {
            return ['id' => $u['id'], 'name' => $u['name']];
        }, $technicians)));
        break;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
        break;
}
