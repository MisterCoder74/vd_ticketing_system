<?php
require_once __DIR__ . '/includes/auth.php';

$action = $_GET['action'] ?? 'dashboard';

// Handle Login POST
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($username, $password)) {
        redirect('index.php');
    } else {
        $error = "Invalid credentials";
    }
}

// Handle Logout
if ($action === 'logout') {
    logout();
    redirect('index.php?action=login');
}

// Ensure logged in for other actions
if ($action !== 'login') {
    requireLogin();
}

$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VD Ticketing System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <script>
        window.APP_CONFIG = {
            userId: <?php echo $user ? $user['id'] : 'null'; ?>,
            userName: '<?php echo $user ? addslashes($user['name']) : ''; ?>',
            userRole: '<?php echo $user ? $user['role'] : ''; ?>'
        };
    </script>
    <script src="<?php echo asset('js/app.js'); ?>" defer></script>
</head>
<body data-action="<?php echo htmlspecialchars($action); ?>" data-ticket-id="<?php echo htmlspecialchars($_GET['id'] ?? ''); ?>">
    <div class="container">
        <header>
            <div class="header-content">
                <h1><a href="index.php">VD Ticketing System</a></h1>
                <?php if (isLoggedIn()): ?>
                    <nav>
                        <a href="index.php?action=dashboard" id="nav-dashboard">Dashboard</a>
                        <?php if (hasRole(['admin', 'technician'])): ?>
                            <a href="#" id="nav-logs">Logs</a>
                        <?php endif; ?>
                        <?php if (hasRole('admin')): ?>
                            <a href="#" id="nav-stats">Stats</a>
                        <?php endif; ?>
                        <span>Welcome, <strong><?php echo htmlspecialchars($user['name']); ?></strong> (<?php echo htmlspecialchars($user['role']); ?>)</span>
                        <a href="index.php?action=logout" class="btn-logout">Logout</a>
                    </nav>
                <?php endif; ?>

        </header>

        <main>
            <?php if ($action === 'login'): ?>
                <section class="login-form">
                    <h2>Login</h2>
                    <?php if (isset($error)): ?>
                        <p class="error"><?php echo $error; ?></p>
                    <?php endif; ?>
                    <form action="index.php?action=login" method="post">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%">Login</button>
                    </form>
                    <div style="margin-top: 20px; text-align: center;">
                        <a href="documentation.html" target="_blank" style="color: var(--primary-color); text-decoration: none; font-size: 0.9rem; font-weight: 600;">
                            Hai bisogno di aiuto? Consulta il Manuale Utente
                        </a>
                    </div>
                </section>

            <?php else: ?>
                <!-- Main App Container for Dynamic Views -->
                <div id="app-viewport">
                    
                    <!-- Dashboard Section -->
                    <section id="ticket-dashboard" class="dynamic-section">
                        <div class="section-header">
                            <h2>Dashboard</h2>
                            <div class="header-actions">
                                <select id="filter-status" class="btn btn-secondary" style="background: white; color: black; border: 1px solid #ccc;">
                                    <option value="all">All Statuses</option>
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                                <button id="btn-open-create" class="btn btn-primary">Create New Ticket</button>
                            </div>
                        </div>

                        <div id="ticket-list-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Created By</th>
                                        <th>Assigned To</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="ticket-table-body">
                                    <tr><td colspan="8">Loading tickets...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Create Ticket Section -->
                    <section id="ticket-create" class="dynamic-section hidden">
                        <div class="section-header">
                            <h2>Create New Ticket</h2>
                            <button class="btn btn-secondary btn-back-to-list">Back to List</button>
                        </div>
                        <div class="card">
                            <form id="create-ticket-form">
                                <div class="form-group">
                                    <label for="title">Title</label>
                                    <input type="text" id="title" name="title" required placeholder="Brief summary of the issue">
                                </div>
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" required placeholder="Describe the problem in detail"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="priority">Priority</label>
                                    <select id="priority" name="priority">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                                    <button type="button" class="btn btn-secondary btn-back-to-list">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <!-- Ticket Details Section -->
                    <section id="ticket-details" class="dynamic-section hidden">
                        <div class="section-header">
                            <h2 id="det-ticket-title">Ticket Details</h2>
                            <button class="btn btn-secondary btn-back-to-list">Back to List</button>
                        </div>
                        
                        <div class="ticket-grid">
                            <div class="ticket-main">
                                <div class="card">
                                    <h3>Description</h3>
                                    <p id="det-description" style="white-space: pre-wrap;"></p>
                                </div>

                                <div class="card">
                                    <h3>Comments</h3>
                                    <div id="det-comments-list">
                                        <!-- Comments go here -->
                                    </div>
                                    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
                                    <form id="det-add-comment-form">
                                        <div class="form-group">
                                            <textarea id="det-comment-text" placeholder="Add a comment..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Add Comment</button>
                                    </form>
                                </div>
                            </div>

                            <div class="ticket-sidebar">
                                <div class="card">
                                    <h3>Ticket Info</h3>
                                    <div class="info-row">
                                        <span class="label">Status:</span>
                                        <span id="det-status-badge" class="badge"></span>
                                    </div>
                                    <?php if (hasRole(['admin', 'technician'])): ?>
                                    <div class="info-row" style="padding-top: 0;">
                                        <select id="det-change-status" class="form-group" style="margin-bottom: 0;">
                                            <option value="open">Open</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="resolved">Resolved</option>
                                            <option value="closed">Closed</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="info-row">
                                        <span class="label">Priority:</span>
                                        <span id="det-priority-badge" class="badge"></span>
                                    </div>
                                    <?php if (hasRole(['admin', 'technician'])): ?>
                                    <div class="info-row" style="padding-top: 0;">
                                        <select id="det-change-priority" class="form-group" style="margin-bottom: 0;">
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                            <option value="urgent">Urgent</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    <div class="info-row">
                                        <span class="label">Created By:</span>
                                        <span id="det-creator"></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Assigned To:</span>
                                        <span id="det-assignee"></span>
                                    </div>
                                    <?php if (hasRole(['admin', 'technician'])): ?>
                                    <div id="det-assign-section" class="info-row" style="padding-top: 0; gap: 5px;">
                                        <select id="det-assign-user" style="flex: 1; padding: 8px;">
                                            <option value="">Unassigned</option>
                                        </select>
                                        <button id="det-btn-assign" class="btn btn-sm btn-primary">Set</button>
                                    </div>
                                    <?php endif; ?>
                                    <div class="info-row">
                                        <span class="label">Created At:</span>
                                        <span id="det-date" style="font-size: 0.8rem;"></span>
                                    </div>
                                </div>

                                <div class="card">
                                    <h3>Attachments</h3>
                                    <div id="det-file-list" class="file-list">
                                        <!-- Files go here -->
                                    </div>
                                    <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
                                    <form id="det-upload-form">
                                        <div class="form-group">
                                            <input type="file" id="det-file-input" name="file" required>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary" style="width: 100%">Upload File</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Activity Logs Section -->
                    <section id="activity-logs" class="dynamic-section hidden">
                        <div class="section-header">
                            <h2>Activity Logs</h2>
                            <button class="btn btn-secondary btn-back-to-list">Back to List</button>
                        </div>
                        <div id="logs-container" class="card">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody id="logs-table-body">
                                    <tr><td colspan="4">Loading logs...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Admin Stats Section -->
                    <section id="admin-stats" class="dynamic-section hidden">
                        <div class="section-header">
                            <h2>Statistics</h2>
                            <div class="header-actions">
                                <button id="btn-export-csv" class="btn btn-success">Export CSV</button>
                                <button class="btn btn-secondary btn-back-to-list">Back to List</button>
                            </div>
                        </div>
                        <div class="stats-grid">
                            <div class="card stats-card">
                                <h3>By Status</h3>
                                <div id="stats-status-list"></div>
                            </div>
                            <div class="card stats-card">
                                <h3>By Priority</h3>
                                <div id="stats-priority-list"></div>
                            </div>
                            <div class="card stats-card">
                                <h3>Overall</h3>
                                <div class="info-row">
                                    <span class="label">Total Tickets:</span>
                                    <span id="stats-total" style="font-weight: bold; font-size: 1.5rem;">0</span>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            <?php endif; ?>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> VD Ticketing System</p>
        </footer>
    </div>
</body>
</html>
