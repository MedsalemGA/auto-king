<?php
session_start();
include_once 'config_db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get the mechanic's ID from the URL
if (!isset($_GET['mechanic_id']) || !is_numeric($_GET['mechanic_id'])) {
    header('Location: find_mechanic.php');
    exit();
}

$mechanic_id = (int)$_GET['mechanic_id'];

// Fetch mechanic data
try {
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ? AND role = 'mecanicien'");
    $stmt->execute([$mechanic_id]);
    $mechanic = $stmt->fetch();

    if (!$mechanic) {
        header('Location: find_mechanic.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching mechanic: " . $e->getMessage());
    $error = 'An error occurred while fetching the mechanic\'s profile.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mechanic Profile - AutoParts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #2d3748, #4a5568);
            position: relative;
            min-height: 100vh;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" opacity="0.1"><circle cx="50" cy="50" r="30" fill="none" stroke="#e53e3e" stroke-width="2"/><path d="M50 20a30 30 0 0 1 0 60 30 30 0 0 1 0-60m0-10a40 40 0 0 0 0 80 40 40 0 0 0 0-80" fill="none" stroke="#1e3a8a" stroke-width="2"/></svg>') repeat;
            z-index: -1;
        }
        .auto-element {
            position: absolute;
            color: rgba(229, 62, 62, 0.3);
            font-size: 2rem;
            animation: drift 20s infinite linear;
            z-index: -1;
        }
        .auto-element.car::before {
            content: '\1F697'; /* Car emoji */
        }
        .auto-element.wrench::before {
            content: '\1F527'; /* Wrench emoji */
        }
        .auto-element:nth-child(1) {
            top: 15%;
            left: 5%;
            animation-delay: 0s;
        }
        .auto-element:nth-child(2) {
            top: 60%;
            left: 10%;
            animation-delay: 5s;
        }
        .auto-element:nth-child(3) {
            top: 30%;
            right: 5%;
            animation-delay: 10s;
        }
        .auto-element:nth-child(4) {
            top: 80%;
            right: 15%;
            animation-delay: 15s;
        }
        @keyframes drift {
            0% {
                transform: translateX(0) translateY(0);
                opacity: 0.3;
            }
            50% {
                opacity: 0.5;
            }
            100% {
                transform: translateX(100vw) translateY(20px);
                opacity: 0.3;
            }
        }
        .profile-container {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            border-radius: 1rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
            padding: 2rem;
        }
        .id-card {
            background: linear-gradient(145deg, #ffffff, #e6e6e6);
            border: 2px solid transparent;
            border-image: linear-gradient(135deg, #1e3a8a, #3b82f6) 1;
            border-radius: 15px;
            padding: 20px;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            position: relative;
            font-family: 'Roboto Mono', monospace;
            color: #1e3a8a;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeIn 0.5s ease forwards;
        }
        .id-card:hover {
            transform: scale(1.02);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
            border-image: linear-gradient(135deg, #e53e3e, #3b82f6) 1;
        }
        .id-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" opacity="0.1"><path d="M0 0h100v100H0z" fill="none"/><path d="M10 10h80v80H10z" fill="none" stroke="#1e3a8a" stroke-width="2"/></svg>') repeat;
            pointer-events: none;
        }
        .id-card-header {
            background: #1e3a8a;
            color: white;
            padding: 10px;
            border-radius: 10px 10px 0 0;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            margin: -20px -20px 20px -20px;
        }
        .id-card-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .id-card-image img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 3px solid #1e3a8a;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .id-card-details {
            flex: 1;
        }
        .id-card-details p {
            margin: 5px 0;
            font-size: 0.9rem;
        }
        .id-card-details strong {
            color: #3b82f6;
        }
        .cert-button {
            display: inline-block;
            background: linear-gradient(135deg, #e53e3e, #b91c1c);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-size: 0.85rem;
            transition: background 0.3s ease;
        }
        .cert-button:hover {
            background: linear-gradient(135deg, #b91c1c, #e53e3e);
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        .action-buttons a {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        .action-buttons a:hover {
            background: linear-gradient(135deg, #e53e3e, #3b82f6);
        }
        .action-buttons .book-button:disabled {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            cursor: not-allowed;
        }
        .action-buttons .book-button:disabled:hover {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }
        .decorative-element {
            position: absolute;
            width: 100px;
            height: 100px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" opacity="0.2"><path d="M50 10a40 40 0 0 0 0 80 40 40 0 0 0 0-80" fill="none" stroke="#3b82f6" stroke-width="4"/></svg>');
            z-index: 0;
            animation: rotate 15s infinite linear;
        }
        .decorative-element.top-left {
            top: 2rem;
            left: 2rem;
        }
        .decorative-element.bottom-right {
            bottom: 2rem;
            right: 2rem;
        }
        @keyframes rotate {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Background Auto Elements -->
    <div class="auto-element car"></div>
    <div class="auto-element wrench"></div>
    <div class="auto-element car"></div>
    <div class="auto-element wrench"></div>

    <!-- Include Header -->
    <?php include 'header.php'; ?>

    <!-- Mechanic Profile Section -->
    <section class="container mx-auto py-12 relative">
        <!-- Decorative Elements -->
        <div class="decorative-element top-left"></div>
        <div class="decorative-element bottom-right"></div>

        <div class="max-w-2xl mx-auto profile-container">
            <h2 class="text-3xl font-bold text-center mb-6">Mechanic Profile</h2>
            <?php if (isset($error)): ?>
                <p class="text-red-300 text-center mb-4"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <!-- ID Card Display -->
            <div class="id-card mb-8">
                <div class="id-card-header">
                    AutoParts Mechanic ID
                </div>
                <div class="id-card-content">
                    <div class="id-card-image">
                        <img src="<?php echo htmlspecialchars($mechanic['profile_image'] ?? 'images/default_profile.jpg'); ?>" alt="Profile Image">
                    </div>
                    <div class="id-card-details">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($mechanic['nom'] ?? '') . ' ' . htmlspecialchars($mechanic['prenom'] ?? ''); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($mechanic['email'] ?? ''); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($mechanic['N_Telephone'] ?? 'N/A'); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($mechanic['Adresse'] ?? 'N/A'); ?></p>
                        <p>
                            <strong>Certification:</strong>
                            <?php if (!empty($mechanic['certification_file'])): ?>
                                <a href="<?php echo htmlspecialchars($mechanic['certification_file']); ?>" target="_blank" class="cert-button">View Certification</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </p>
                        <p><strong>Competencies:</strong> <?php echo htmlspecialchars($mechanic['competencies'] ?? 'N/A'); ?></p>
                        <p><strong>Rate:</strong> <?php echo htmlspecialchars($mechanic['tarif'] ?? 'N/A'); ?> DT/hour</p>
                        <p><strong>Availability:</strong> <?php echo ($mechanic['is_available'] ?? false) ? 'Available' : 'Not Available'; ?></p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="find_mechanic.php">Back to Find Mechanic</a>
                <?php if ($user_role === 'client'): ?>
                    <a href="book_mechanic.php?mechanic_id=<?php echo $mechanic['id']; ?>"
                       class="book-button"
                       <?php echo ($mechanic['is_available'] ?? false) ? '' : 'disabled'; ?>>
                        Book Appointment
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>
</body>
</html>