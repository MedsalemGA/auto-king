<?php
session_start();
include_once 'config_db.php';

$error = '';
$success = '';

// Ensure upload directories exist
$upload_dir = 'uploads/';
$cert_upload_dir = 'uploads/certifications/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
if (!is_dir($cert_upload_dir)) {
    mkdir($cert_upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize string inputs
    $nom = trim(strip_tags($_POST['nom'] ?? ''));
    $prenom = trim(strip_tags($_POST['prenom'] ?? ''));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $N_Telephone = trim(strip_tags($_POST['N_Telephone'] ?? ''));
    $Adresse = trim(strip_tags($_POST['Adresse'] ?? ''));
    $role = $_POST['role'];
    $certification_file = null;
    $profile_image = null;
    $is_certified = $role === 'mecanicien' ? 1 : 0; // Explicitly set as integer (0 or 1)

    // Handle mechanic-specific fields
    if ($role === 'mecanicien') {
        // Profile image upload
        if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Profile image is required for mechanics.';
        } else {
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Invalid image format. Only JPEG, PNG, and GIF are allowed.';
            } elseif ($file['size'] > $max_size) {
                $error = 'Image size exceeds 2MB limit.';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = uniqid() . '.' . $ext;
                $destination = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $profile_image = $destination;
                } else {
                    $error = 'Failed to upload image. Please try again.';
                }
            }
        }

        // Certification file upload
        if (!isset($_FILES['certification_file']) || $_FILES['certification_file']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = 'Certification file is required for mechanics.';
        } else {
            $cert_file = $_FILES['certification_file'];
            $allowed_cert_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $max_cert_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($cert_file['type'], $allowed_cert_types)) {
                $error = 'Invalid certification file format. Only PDF, JPEG, and PNG are allowed.';
            } elseif ($cert_file['size'] > $max_cert_size) {
                $error = 'Certification file size exceeds 5MB limit.';
            } else {
                $cert_ext = pathinfo($cert_file['name'], PATHINFO_EXTENSION);
                $cert_filename = uniqid() . '.' . $cert_ext;
                $cert_destination = $cert_upload_dir . $cert_filename;

                if (move_uploaded_file($cert_file['tmp_name'], $cert_destination)) {
                    $certification_file = $cert_destination;
                } else {
                    $error = 'Failed to upload certification file. Please try again.';
                }
            }
        }
    }

    // Validate other inputs
    if (empty($error)) {
        if (empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($role)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!in_array($role, ['client', 'mecanicien'])) {
            $error = 'Invalid role selected.';
        } else {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Email already registered.';
                } else {
                    // Log the values for debugging
                    error_log("Inserting user with values: nom=$nom, prenom=$prenom, email=$email, N_Telephone=$N_Telephone, Adresse=$Adresse, role=$role, certification_file=$certification_file, is_certified=$is_certified, profile_image=$profile_image");

                    // Insert user into database
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, prenom, email, password, N_Telephone, Adresse, role, certification_file, is_certified, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nom, $prenom, $email, $hashed_password, $N_Telephone, $Adresse, $role, $certification_file, $is_certified, $profile_image]);

                    $success = 'Registration successful! Please login.';
                    header('Location: login.php');
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Signup error: " . $e->getMessage());
                $error = 'An error occurred: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - AutoParts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
        }
        .signup-container {
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
        select.input-field {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg fill='#1e3a8a' height='24' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><path d='M7 10l5 5 5-5z'/><path d='M0 0h24v24H0z' fill='none'/></svg>");
            background-repeat: no-repeat;
            background-position-x: 98%;
            background-position-y: 50%;
        }
        #certification-field, #image-field {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Include Header -->
    <?php include 'header.php'; ?>

    <!-- Signup Form -->
    <section class="container mx-auto py-12">
        <div class="max-w-md mx-auto signup-container p-8">
            <h2 class="text-3xl font-bold text-center mb-6">Create Your Account</h2>
            <?php if ($error): ?>
                <p class="text-red-300 text-center mb-4"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="text-green-300 text-center mb-4"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label for="nom" class="block text-sm font-medium mb-2">Last Name</label>
                    <input type="text" id="nom" name="nom" required
                           class="w-full p-3 input-field focus:outline-none"
                           placeholder="Enter your last name">
                </div>
                <div>
                    <label for="prenom" class="block text-sm font-medium mb-2">First Name</label>
                    <input type="text" id="prenom" name="prenom" required
                           class="w-full p-3 input-field focus:outline-none"
                           placeholder="Enter your first name">
                </div>
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
                <div>
                    <label for="N_Telephone" class="block text-sm font-medium mb-2">Phone Number (Optional)</label>
                    <input type="text" id="N_Telephone" name="N_Telephone"
                           class="w-full p-3 input-field focus:outline-none"
                           placeholder="Enter your phone number">
                </div>
                <div>
                    <label for="Adresse" class="block text-sm font-medium mb-2">Address (Optional)</label>
                    <input type="text" id="Adresse" name="Adresse"
                           class="w-full p-3 input-field focus:outline-none"
                           placeholder="Enter your address">
                </div>
                <div>
                    <label for="role" class="block text-sm font-medium mb-2">I am a</label>
                    <select id="role" name="role" required
                            class="w-full p-3 input-field focus:outline-none"
                            onchange="toggleAdditionalFields()">
                        <option value="client">Client</option>
                        <option value="mecanicien">Mechanic</option>
                    </select>
                </div>
                <div id="certification-field">
                    <label for="certification_file" class="block text-sm font-medium mb-2">Certification File (PDF, JPEG, PNG)</label>
                    <input type="file" id="certification_file" name="certification_file"
                           class="w-full p-3 text-gray-700"
                           accept="application/pdf,image/jpeg,image/png">
                </div>
                <div id="image-field">
                    <label for="profile_image" class="block text-sm font-medium mb-2">Profile Image</label>
                    <input type="file" id="profile_image" name="profile_image"
                           class="w-full p-3 text-gray-700"
                           accept="image/jpeg,image/png,image/gif">
                </div>
                <button type="submit"
                        class="w-full bg-blue-700 hover:bg-blue-900 text-white font-semibold py-3 rounded-lg transition duration-300">
                    Sign Up
                </button>
            </form>
            <p class="text-center mt-4">
                Already have an account? <a href="login.php" class="text-blue-200 hover:underline">Login here</a>.
            </p>
        </div>
    </section>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>

    <!-- JavaScript to Show/Hide Additional Fields -->
    <script>
        function toggleAdditionalFields() {
            const role = document.getElementById('role').value;
            const certificationField = document.getElementById('certification-field');
            const imageField = document.getElementById('image-field');
            
            if (role === 'mecanicien') {
                certificationField.style.display = 'block';
                imageField.style.display = 'block';
                document.getElementById('certification_file').required = true;
                document.getElementById('profile_image').required = true;
            } else {
                certificationField.style.display = 'none';
                imageField.style.display = 'none';
                document.getElementById('certification_file').required = false;
                document.getElementById('profile_image').required = false;
            }
        }

        // Run on page load to set initial state
        toggleAdditionalFields();
    </script>
</body>
</html>