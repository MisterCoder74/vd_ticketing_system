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
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <script>
        window.APP_CONFIG = {
            userId: <?php echo $user ? $user['id'] : 'null'; ?>,
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
                        <span>Welcome, <strong><?php echo htmlspecialchars($user['name']); ?></strong> (<?php echo htmlspecialchars($user['role']); ?>)</span>
                        <a href="index.php?action=logout" class="btn-logout">Logout</a>
                    </nav>
                <?php endif; ?>
            </div>
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
                        <button type="submit" class="btn">Login</button>
                    </form>
                </section>

            <?php elseif ($action === 'dashboard'): ?>
                <section class="dashboard">
                    <div class="section-header">
                        <h2>Dashboard</h2>
                        <button id="show-create-form" class="btn btn-primary">Create New Ticket</button>
                    </div>

                    <div id="create-ticket-section" class="hidden">
                        <h3>Create New Ticket</h3>
                        <form id="create-ticket-form">
                            <div class="form-group">
                                <label for="title">Title</label>
                                <input type="text" id="title" name="title" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" required></textarea>
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
                                <button type="button" id="hide-create-form" class="btn btn-secondary">Cancel</button>
                            </div>
                        </form>
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
                                <!-- Filled by JS -->
                                <tr><td colspan="8">Loading tickets...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

            <?php elseif ($action === 'view_ticket'): ?>
                <section id="ticket-details" class="ticket-details">
                    <div class="section-header">
                        <h2 id="ticket-title-display">Loading ticket...</h2>
                        <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                    
                    <div class="ticket-grid">
                        <div class="ticket-main">
                            <div class="card">
                                <h3>Description</h3>
                                <p id="ticket-description-display"></p>
                            </div>

                            <div class="card">
                                <h3>Comments</h3>
                                <div id="comments-list">
                                    <!-- Comments go here -->
                                </div>
                                <form id="add-comment-form">
                                    <div class="form-group">
                                        <textarea id="comment-text" placeholder="Add a comment..." required></textarea>
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
                                    <span id="ticket-status-badge" class="badge"></span>
                                    <?php if (hasRole(['admin', 'technician'])): ?>
                                        <select id="change-status-select" class="mt-1">
                                            <option value="open">Open</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="resolved">Resolved</option>
                                            <option value="closed">Closed</option>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="info-row">
                                    <span class="label">Priority:</span>
                                    <span id="ticket-priority-badge" class="badge"></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Created By:</span>
                                    <span id="ticket-creator"></span>
                                </div>
                                <div class="info-row">
                                    <span class="label">Assigned To:</span>
                                    <span id="ticket-assignee"></span>
                                    <?php if (hasRole(['admin', 'technician'])): ?>
                                        <div id="assign-section" class="mt-1">
                                            <select id="assign-user-select">
                                                <option value="">Unassigned</option>
                                            </select>
                                            <button id="btn-assign" class="btn btn-sm">Assign</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="info-row">
                                    <span class="label">Created At:</span>
                                    <span id="ticket-date"></span>
                                </div>
                            </div>

                            <div class="card">
                                <h3>Attachments</h3>
                                <div id="file-list" class="file-list">
                                    <!-- Files go here -->
                                </div>
                                <hr>
                                <form id="upload-form">
                                    <input type="file" id="file-input" name="file" required>
                                    <button type="submit" class="btn btn-sm mt-1">Upload</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> VD Ticketing System</p>
        </footer>
    </div>
</body>
</html>
