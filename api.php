<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_tickets':
        $tickets = loadJson('tickets');
        // Filter tickets based on role if necessary
        if (hasRole('user')) {
            $tickets = array_filter($tickets, function($t) {
                return $t['created_by'] == $_SESSION['user']['id'];
            });
            $tickets = array_values($tickets);
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
            
            // Load comments for this ticket
            $comments = loadJson('comments');
            $ticket['comments'] = array_filter($comments, function($c) use ($id) {
                return $c['ticket_id'] == $id;
            });
            $ticket['comments'] = array_values($ticket['comments']);
            
            jsonResponse($ticket);
        } else {
            jsonResponse(['error' => 'Ticket not found'], 404);
        }
        break;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
        break;
}
