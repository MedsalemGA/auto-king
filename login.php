<?php
session_start();
include_once 'config_db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Check role and certification
                if ($user['role'] === 'mecanicien' && !$user['is_certified']) {
                    $error = 'Your mechanic account is not certified. Please contact support.';
                } else {
                    // Start session and store user data
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];

                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header('Location: admin.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit();
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AutoParts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
        }
        .login-container {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            border-radius: 1rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        .input-field {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #1e3a8a;
            border-radius: 0.5rem;
        }
        .input-field::placeholder {
            color: #6b7280;
        }
        .input-field:focus {
            border-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Include Header -->
    <?php include 'header.php'; ?>

    <!-- Login Form -->
    <section class="container mx-auto py-12">
        <div class="max-w-md mx-auto login-container p-8">
            <h2 class="text-3xl font-bold text-center mb-6">Login to Your Account</h2>
            <?php if ($error): ?>
                <p class="text-red-300 text-center mb-4"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium mb-2">Email</label>
                    <input type="email" id="email" name="email" required
                           class="w-full p-3 input-field focus:outline-none"
                           placeholder="Enter your email">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium mb-2">Password</label>
                    <input type="password" id="password" name="password" required
                           class="w-full p-3 input-field focus:outline-none"
                           placeholder="Enter your password">
                </div>
                <button type="submit"
                        class="w-full bg-blue-700 hover:bg-blue-900 text-white font-semibold py-3 rounded-lg transition duration-300">
                    Login
                </button>
            </form>
            <p class="text-center mt-4">
                Don't have an account? <a href="signup.php" class="text-blue-200 hover:underline">Sign up here</a>.
            </p>
            <p class="text-center mt-2">
                <a href="login_admin.php" class="text-blue-200 hover:underline">Admin Login (Separate Page)</a>
            </p>
        </div>
    </section>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>
</body>
</html>