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
$error = '';
$success = '';

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'User not found.';
    }
} catch (PDOException $e) {
    error_log("Error fetching user: " . $e->getMessage());
    $error = 'An error occurred while fetching your profile.';
}

// Fetch user orders if client
$orders = [];
if ($user_role === 'client') {
    try {
        $stmt = $pdo->prepare("SELECT c.commande_id, c.date_commande, c.montant_total, c.statut, c.adresse_livraison,
                              c.methode_paiement, c.reference_paiement, COUNT(ca.article_id) as total_items
                              FROM commandes c
                              LEFT JOIN commande_articles ca ON c.commande_id = ca.commande_id
                              WHERE c.client_id = ?
                              GROUP BY c.commande_id
                              ORDER BY c.date_commande DESC");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching orders: " . $e->getMessage());
        $error = 'An error occurred while fetching your orders.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // Sanitize string inputs by trimming and removing HTML tags
    $nom = trim(strip_tags($_POST['nom']));
    $prenom = trim(strip_tags($_POST['prenom']));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $N_Telephone = trim(strip_tags($_POST['N_Telephone']));
    $Adresse = trim(strip_tags($_POST['Adresse']));

    // Mechanic-specific fields
    $competencies = $user_role === 'mecanicien' ? trim(strip_tags($_POST['competencies'])) : null;
    $tarif = $user_role === 'mecanicien' ? floatval($_POST['tarif']) : null;
    $is_available = $user_role === 'mecanicien' ? (isset($_POST['is_available']) && $_POST['is_available'] === '1' ? 1 : 0) : 0;

    // Validate inputs
    if (empty($nom) || empty($prenom) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($user_role === 'mecanicien' && ($tarif === null || $tarif < 0)) {
        $error = 'Please enter a valid rate (tarif).';
    } else {
        try {
            // Check if email is already used by another user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Email is already in use by another user.';
            } else {
                // Update user data
                if ($user_role === 'mecanicien') {
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, N_Telephone = ?, Adresse = ?, competencies = ?, tarif = ?, is_available = ? WHERE id = ?");
                    $stmt->execute([$nom, $prenom, $email, $N_Telephone, $Adresse, $competencies, $tarif, $is_available, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, N_Telephone = ?, Adresse = ? WHERE id = ?");
                    $stmt->execute([$nom, $prenom, $email, $N_Telephone, $Adresse, $user_id]);
                }
                $success = 'Profile updated successfully!';
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        } catch (PDOException $e) {
            error_log("Update error: " . $e->getMessage());
            $error = 'An error occurred while updating your profile.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - AutoParts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
        }
        .profile-container {
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
        .id-card {
            background: linear-gradient(145deg, #ffffff, #e6e6e6);
            border: 2px solid #1e3a8a;
            border-radius: 15px;
            padding: 20px;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            position: relative;
            font-family: 'Roboto Mono', monospace;
            color: #1e3a8a;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .id-card:hover {
            transform: scale(1.02);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
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
        .slider {
            width: 60px;
            height: 30px;
            background-color: #ccc;
            position: relative;
            border-radius: 15px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .slider.active {
            background-color: #4ade80;
        }
        .slider-knob {
            width: 26px;
            height: 26px;
            background-color: white;
            position: absolute;
            top: 2px;
            left: 2px;
            border-radius: 50%;
            transition: transform 0.3s;
        }
        .slider.active .slider-knob {
            transform: translateX(30px);
        }
        .mechanic-section {
            display: <?php echo $user_role === 'mecanicien' ? 'block' : 'none'; ?>;
        }

        /* Orders Section Styles */
        .orders-section {
            display: <?php echo $user_role === 'client' ? 'block' : 'none'; ?>;
            margin-top: 2rem;
        }
        .orders-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .order-card {
            border-left: 5px solid #3b82f6;
            margin-bottom: 1rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        .order-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-body {
            padding: 1rem;
        }
        .order-footer {
            padding: 1rem;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-processing {
            background: #dbeafe;
            color: #1e40af;
        }
        .status-shipped {
            background: #d1fae5;
            color: #065f46;
        }
        .status-delivered {
            background: #bbf7d0;
            color: #166534;
        }
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        .view-details-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .view-details-btn:hover {
            background: #2563eb;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Include Header -->
    <?php include 'header.php'; ?>

    <!-- Profile Section -->
    <section class="container mx-auto py-12">
        <div class="max-w-2xl mx-auto profile-container p-8">
            <h2 class="text-3xl font-bold text-center mb-6">Your Profile</h2>
            <?php if ($error): ?>
                <p class="text-red-300 text-center mb-4"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="text-green-300 text-center mb-4"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>

            <!-- ID Card Display -->
            <div class="id-card mb-8">
                <div class="id-card-header">
                    AutoParts <?php echo htmlspecialchars($user_role === 'mecanicien' ? 'Mechanic' : 'Client'); ?> ID
                </div>
                <div class="id-card-content">
                    <div class="id-card-image">
                        <img src="<?php echo htmlspecialchars($user['profile_image'] ?? 'images/default_profile.jpg'); ?>" alt="Profile Image">
                    </div>
                    <div class="id-card-details">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['nom'] ?? '') . ' ' . htmlspecialchars($user['prenom'] ?? ''); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['N_Telephone'] ?? 'N/A'); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($user['Adresse'] ?? 'N/A'); ?></p>
                        <?php if ($user_role === 'mecanicien'): ?>
                            <p><strong>Certification:</strong> <?php echo htmlspecialchars($user['certification'] ?? 'N/A'); ?></p>
                            <p><strong>Competencies:</strong> <?php echo htmlspecialchars($user['competencies'] ?? 'N/A'); ?></p>
                            <p><strong>Rate:</strong> <?php echo htmlspecialchars($user['tarif'] ?? 'N/A'); ?> DT/hour</p>
                            <p><strong>Availability:</strong> <?php echo ($user['is_available'] ?? false) ? 'Available' : 'Not Available'; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Update Form -->
            <h3 class="text-2xl font-semibold text-center mb-6">Update Your Information</h3>
            <form method="POST" class="space-y-6">
                <div>
                    <label for="nom" class="block text-sm font-medium mb-2">Last Name</label>
                    <input type="text" id="nom" name="nom" required
                           value="<?php echo htmlspecialchars($user['nom'] ?? ''); ?>"
                           class="w-full p-3 input-field focus:outline-none"
                           placeholder="Enter your last name">
                </div>
                <div>
                    <label for="prenom" class="block text-sm font-medium mb-2">First Name</label>
                    <input type="text" id="prenom" name="prenom" required
                           value="<?php echo htmlspecialchars($user['prenom'] ?? ''); ?>"
                           class="w-full p-3 input-field focus:outline-none"
                           placeholder="Enter your first name">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium mb-2">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                           class="w-full p-3 input-field focus:outline-none"
                           placeholder="Enter your email">
                </div>
                <div>
                    <label for="N_Telephone" class="block text-sm font-medium mb-2">Phone Number (Optional)</label>
                    <input type="text" id="N_Telephone" name="N_Telephone"
                           value="<?php echo htmlspecialchars($user['N_Telephone'] ?? ''); ?>"
                           class="w-full p-3 input-field focus:outline-none"
                           placeholder="Enter your phone number">
                </div>
                <div>
                    <label for="Adresse" class="block text-sm font-medium mb-2">Address (Optional)</label>
                    <input type="text" id="Adresse" name="Adresse"
                           value="<?php echo htmlspecialchars($user['Adresse'] ?? ''); ?>"
                           class="w-full p-3 input-field focus:outline-none"
                           placeholder="Enter your address">
                </div>

                <!-- Mechanic-Specific Fields -->
                <div class="mechanic-section">
                    <h3 class="text-xl font-semibold mb-4">Mechanic Information</h3>
                    <div>
                        <label for="competencies" class="block text-sm font-medium mb-2">Competencies (e.g., Engine Repair, Brake Systems)</label>
                        <input type="text" id="competencies" name="competencies"
                               value="<?php echo htmlspecialchars($user['competencies'] ?? ''); ?>"
                               class="w-full p-3 input-field focus:outline-none"
                               placeholder="Enter your competencies">
                    </div>
                    <div>
                        <label for="tarif" class="block text-sm font-medium mb-2">Hourly Rate (Tarif)</label>
                        <input type="number" id="tarif" name="tarif" step="0.01"
                               value="<?php echo htmlspecialchars($user['tarif'] ?? ''); ?>"
                               class="w-full p-3 input-field focus:outline-none"
                               placeholder="Enter your hourly rate">
                    </div>
                    <div>
                        <label for="is_available" class="block text-sm font-medium mb-2">Availability</label>
                        <div class="flex items-center space-x-3">
                            <div id="availability-slider" class="slider <?php echo ($user['is_available'] ?? false) ? 'active' : ''; ?>">
                                <div class="slider-knob"></div>
                            </div>
                            <span id="availability-text" class="text-sm">
                                <?php echo ($user['is_available'] ?? false) ? 'Available' : 'Not Available'; ?>
                            </span>
                            <input type="hidden" id="is_available" name="is_available" value="<?php echo ($user['is_available'] ?? false) ? '1' : '0'; ?>">
                        </div>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-blue-700 hover:bg-blue-900 text-white font-semibold py-3 rounded-lg transition duration-300">
                    Update Profile
                </button>
            </form>
        </div>
    </section>

    <!-- Orders Section (Client Only) -->
    <section class="container mx-auto py-8 orders-section">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-3xl font-bold text-center mb-6 text-gray-800">Your Orders</h2>

            <?php if (empty($orders)): ?>
                <div class="bg-white p-8 rounded-lg shadow-md text-center">
                    <p class="text-gray-600 text-lg">You haven't placed any orders yet.</p>
                    <a href="search_parts.php" class="mt-4 inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-300">
                        Browse Products
                    </a>
                </div>
            <?php else: ?>
                <div class="orders-container p-6">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <h3 class="font-semibold text-gray-800">Order #<?php echo htmlspecialchars($order['commande_id']); ?></h3>
                                    <p class="text-sm text-gray-500">
                                        <?php echo date('d/m/Y H:i', strtotime($order['date_commande'])); ?>
                                    </p>
                                </div>
                                <div>
                                    <?php
                                        $statusClass = '';
                                        switch (strtolower($order['statut'])) {
                                            case 'en attente':
                                                $statusClass = 'status-pending';
                                                break;
                                            case 'en cours':
                                                $statusClass = 'status-processing';
                                                break;
                                            case 'expédiée':
                                                $statusClass = 'status-shipped';
                                                break;
                                            case 'livrée':
                                                $statusClass = 'status-delivered';
                                                break;
                                            case 'annulée':
                                                $statusClass = 'status-cancelled';
                                                break;
                                            default:
                                                $statusClass = 'status-pending';
                                        }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($order['statut']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="order-body">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-sm text-gray-600">Payment Method</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($order['methode_paiement']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600">Delivery Address</p>
                                        <p class="font-medium"><?php echo htmlspecialchars($order['adresse_livraison']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="order-footer">
                                <div>
                                    <p class="text-sm text-gray-600">Total Items: <?php echo $order['total_items']; ?></p>
                                    <p class="font-semibold text-gray-800">Total: <?php echo number_format($order['montant_total'], 2); ?> DT</p>
                                </div>
                                <a href="order_details.php?id=<?php echo $order['commande_id']; ?>" class="view-details-btn">
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>

    <!-- JavaScript for Availability Slider -->
    <script>
        const slider = document.getElementById('availability-slider');
        const hiddenInput = document.getElementById('is_available');
        const availabilityText = document.getElementById('availability-text');

        slider.addEventListener('click', () => {
            slider.classList.toggle('active');
            const isActive = slider.classList.contains('active');
            hiddenInput.value = isActive ? '1' : '0';
            availabilityText.textContent = isActive ? 'Available' : 'Not Available';
        });
    </script>
</body>
</html>