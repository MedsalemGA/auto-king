<?php
session_start();
include_once 'config_db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    try {
        // Try matching by nom first
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE role = 'administrateur' AND nom = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // If not found by nom, try email
        if (!$admin) {
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE role = 'administrateur' AND email = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
        }

        if ($admin) {
            // Temporarily bypass password_verify for testing
            if ($password === 'admin123') {
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['user_role'] = $admin['role'];
                $_SESSION['user_name'] = $admin['prenom'] . ' ' . $admin['nom'];
                header('Location: admin.php');
                exit();
            } else {
                $error = "Password does not match. Entered: " . htmlspecialchars($password);
            }
        } else {
            $error = "No admin user found with username or email: " . htmlspecialchars($username);
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $error = "An error occurred: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="admin.css" />
    <title>Admin Login - AutoParts</title>
</head>
<body>
    <div class="container">
        <div class="main-content" style="display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Admin Login</h2>
                </div>
                <form method="POST" class="modal-form">
                    <?php if (!empty($error)): ?>
                        <p style="color: red; text-align: center; margin-bottom: 15px;"><?php echo htmlspecialchars($error); ?></p>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <input type="text" id="username" name="username" required value="admin">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required value="admin123">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Login</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>