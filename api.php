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
        $usersMap = getUsersMapWithRoles();
        
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
            
            $usersMap = getUsersMapWithRoles();
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
            $ticket['files'] = $ticket['attachments'] ?? [];
            foreach ($ticket['files'] as &$file) {
                if (!isset($file['url'])) {
                    $file['url'] = "uploads/{$id}/uploads/" . $file['name'];
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
            'created_at' => date('Y-m-d H:i:s'),
            'attachments' => []
        ];
        
        $tickets[] = $newTicket;
        saveJson('tickets', $tickets);
        
        logActivity($_SESSION['user']['id'], 'create_ticket', "Creato ticket #{$newTicket['id']}: {$newTicket['title']}");
        
        jsonResponse($newTicket, 201);
        break;

    case 'update_ticket':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!hasRole(['admin', 'technician'])) jsonResponse(['error' => 'Forbidden'], 403);

        $ticketId = $_POST['ticket_id'] ?? 0;
        $status = $_POST['status'] ?? null;
        $assigneeId = $_POST['assigned_to'] ?? null;
        $priority = $_POST['priority'] ?? null;

        $tickets = loadJson('tickets');
        $found = false;
        $changes = [];
        foreach ($tickets as &$t) {
            if ($t['id'] == $ticketId) {
                if ($status !== null && in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
                    if ($t['status'] !== $status) {
                        $changes[] = "Stato cambiato da {$t['status']} a {$status}";
                        $t['status'] = $status;
                        sendNotification($ticketId, 'status_change', [
                            'new_status' => $status,
                            'user_name' => $_SESSION['user']['name']
                        ]);
                    }
                }
                if ($assigneeId !== null) {
                    $newAssigneeId = $assigneeId === '' ? null : (int)$assigneeId;
                    if ($t['assigned_to'] !== $newAssigneeId) {
                        $usersMap = getUsersMap();
                        $oldName = $usersMap[$t['assigned_to'] ?? 0] ?? 'Unassigned';
                        $newName = $usersMap[$newAssigneeId ?? 0] ?? 'Unassigned';
                        $changes[] = "Assegnato cambiato da {$oldName} a {$newName}";
                        $t['assigned_to'] = $newAssigneeId;
                    }
                }
                if ($priority !== null && in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
                    if ($t['priority'] !== $priority) {
                        $changes[] = "Priorità cambiata da {$t['priority']} a {$priority}";
                        $t['priority'] = $priority;
                    }
                }
                $found = true;
                break;
            }
        }

        if ($found) {
            saveJson('tickets', $tickets);
            if (!empty($changes)) {
                logActivity($_SESSION['user']['id'], 'update_ticket', "Aggiornato ticket #{$ticketId}: " . implode(", ", $changes));
            }
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Ticket not found'], 404);
        }
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
        
        logActivity($_SESSION['user']['id'], 'add_comment', "Aggiunto commento al ticket #{$ticketId}");
        sendNotification($ticketId, 'new_comment', ['user_name' => $_SESSION['user']['name'], 'comment' => $commentText]);
        
        $newComment['user_name'] = $_SESSION['user']['name'];
        jsonResponse($newComment, 201);
        break;

    case 'delete_ticket':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!hasRole('admin')) jsonResponse(['error' => 'Forbidden'], 403);

        $ticketId = $_POST['ticket_id'] ?? 0;
        $tickets = loadJson('tickets');
        
        $initialCount = count($tickets);
        $tickets = array_filter($tickets, function($t) use ($ticketId) {
            return $t['id'] != $ticketId;
        });
        
        if (count($tickets) < $initialCount) {
            saveJson('tickets', array_values($tickets));
            // Also delete comments
            $comments = loadJson('comments');
            $comments = array_filter($comments, function($c) use ($ticketId) {
                return $c['ticket_id'] != $ticketId;
            });
            saveJson('comments', array_values($comments));
            
            logActivity($_SESSION['user']['id'], 'delete_ticket', "Eliminato ticket #{$ticketId}");
            
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
            $attachment = [
                'name' => $fileName,
                'url' => "uploads/{$ticketId}/uploads/{$fileName}",
                'uploaded_at' => date('Y-m-d H:i:s')
            ];

            // Update tickets.json with the new attachment
            $allTickets = loadJson('tickets');
            foreach ($allTickets as &$t) {
                if ($t['id'] == $ticketId) {
                    if (!isset($t['attachments'])) $t['attachments'] = [];
                    $t['attachments'][] = $attachment;
                    break;
                }
            }
            saveJson('tickets', $allTickets);

            logActivity($_SESSION['user']['id'], 'upload_file', "Caricato file per il ticket #{$ticketId}: {$fileName}");

            jsonResponse(['success' => true, 'file' => $attachment]);
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

    case 'get_logs':
        if (!hasRole(['admin', 'technician'])) jsonResponse(['error' => 'Forbidden'], 403);
        $logs = loadJson('logs');
        // Return logs sorted by timestamp desc
        usort($logs, function($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });
        jsonResponse($logs);
        break;

    case 'get_stats':
        if (!hasRole('admin')) jsonResponse(['error' => 'Forbidden'], 403);
        $tickets = loadJson('tickets');
        $stats = [
            'status' => [
                'open' => 0,
                'in_progress' => 0,
                'resolved' => 0,
                'closed' => 0
            ],
            'priority' => [
                'low' => 0,
                'medium' => 0,
                'high' => 0,
                'urgent' => 0
            ],
            'total' => count($tickets)
        ];
        foreach ($tickets as $t) {
            if (isset($stats['status'][$t['status']])) {
                $stats['status'][$t['status']]++;
            }
            if (isset($stats['priority'][$t['priority']])) {
                $stats['priority'][$t['priority']]++;
            }
        }
        jsonResponse($stats);
        break;

    case 'export_csv':
        if (!hasRole('admin')) jsonResponse(['error' => 'Forbidden'], 403);
        $tickets = loadJson('tickets');
        $usersMap = getUsersMap();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="tickets_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Titolo', 'Stato', 'Priorita', 'Creato da', 'Assegnato a', 'Data Creazione']);
        
        foreach ($tickets as $t) {
            fputcsv($output, [
                $t['id'],
                $t['title'],
                $t['status'],
                $t['priority'],
                $usersMap[$t['created_by']] ?? 'Unknown',
                $usersMap[$t['assigned_to'] ?? 0] ?? 'Unassigned',
                $t['created_at']
            ]);
        }
        fclose($output);
        exit;
        break;

    case 'get_users':
        if (!hasRole('admin')) jsonResponse(['error' => 'Forbidden'], 403);
        $users = loadJson('users');
        // Remove passwords
        foreach ($users as &$u) unset($u['password']);
        jsonResponse($users);
        break;

    case 'update_user_role':
        if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
        if (!hasRole('admin')) jsonResponse(['error' => 'Forbidden'], 403);

        $targetUserId = $_POST['user_id'] ?? 0;
        $newRole = $_POST['role'] ?? '';

        if (!in_array($newRole, ['user', 'technician', 'admin'])) {
            jsonResponse(['error' => 'Invalid role'], 400);
        }

        $users = loadJson('users');
        $found = false;
        foreach ($users as &$u) {
            if ($u['id'] == $targetUserId) {
                $oldRole = $u['role'] ?? 'user';
                $u['role'] = $newRole;
                $found = true;
                logActivity($_SESSION['user']['id'], 'update_user_role', "Cambiato ruolo utente {$u['username']} da {$oldRole} a {$newRole}");
                break;
            }
        }

        if ($found) {
            saveJson('users', $users);
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'User not found'], 404);
        }
        break;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
        break;
}
