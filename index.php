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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VD Ticketing System</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>
</head>
<body>
    <div class="container">
        <header>
            <h1>VD Ticketing System</h1>
            <?php if (isLoggedIn()): ?>
                <nav>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['user']['name']); ?> (<?php echo htmlspecialchars($_SESSION['user']['role']); ?>)</span>
                    <a href="index.php?action=logout">Logout</a>
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
                        <button type="submit">Login</button>
                    </form>
                </section>
            <?php else: ?>
                <section class="dashboard">
                    <h2>Dashboard</h2>
                    <div id="ticket-list">
                        <!-- Tickets will be loaded here via JS or simple PHP -->
                        <?php
                        $tickets = loadJson('tickets');
                        if (hasRole('user')) {
                            $tickets = array_filter($tickets, function($t) {
                                return $t['created_by'] == $_SESSION['user']['id'];
                            });
                        }
                        ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td><?php echo $ticket['id']; ?></td>
                                        <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                        <td><span class="status-<?php echo $ticket['status']; ?>"><?php echo $ticket['status']; ?></span></td>
                                        <td><span class="priority-<?php echo $ticket['priority']; ?>"><?php echo $ticket['priority']; ?></span></td>
                                        <td><?php echo $ticket['created_at']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <footer>
            <p>&copy; 2023 VD Ticketing System</p>
        </footer>
    </div>
</body>
</html>
