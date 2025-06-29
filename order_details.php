<?php
session_start();
include_once 'config_db.php';

// Check if user is logged in and a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: login.php');
    exit();
}

$client_id = $_SESSION['user_id'];
$error = '';
$success = '';
$order = null;
$order_items = [];

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: profile.php');
    exit();
}

$order_id = intval($_GET['id']);

// Fetch order details
try {
    // Verify the order belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM commandes WHERE commande_id = ? AND client_id = ?");
    $stmt->execute([$order_id, $client_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $error = 'Order not found or you do not have permission to view it.';
    } else {
        // Fetch order items
        $stmt = $pdo->prepare("SELECT ca.*, a.reference, a.description, a.image 
                               FROM commande_articles ca 
                               JOIN articles a ON ca.article_id = a.article_id 
                               WHERE ca.commande_id = ?");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    $error = 'An error occurred while fetching order details.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - AutoParts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', 'Arial', sans-serif;
            background: #e5e7eb;
            color: #333;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .order-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .order-header {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-body {
            padding: 2rem;
        }
        .order-section {
            margin-bottom: 2rem;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1.5rem;
        }
        .order-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        .section-title i {
            margin-right: 0.5rem;
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
        .item-card {
            display: flex;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            overflow: hidden;
            margin-bottom: 1rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .item-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
        }
        .item-details {
            flex: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .item-price {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            background: #4b5563;
            color: white;
            font-weight: 500;
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .back-button i {
            margin-right: 0.5rem;
        }
        .back-button:hover {
            background: #374151;
            transform: translateX(-5px);
        }
        .tracking-timeline {
            position: relative;
            margin: 2rem 0;
            padding-left: 2rem;
        }
        .tracking-line {
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        .tracking-step {
            position: relative;
            padding-bottom: 1.5rem;
        }
        .tracking-step:last-child {
            padding-bottom: 0;
        }
        .tracking-dot {
            position: absolute;
            left: -2rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: #e5e7eb;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #e5e7eb;
        }
        .tracking-step.completed .tracking-dot {
            background: #10b981;
            box-shadow: 0 0 0 2px #d1fae5;
        }
        .tracking-step.active .tracking-dot {
            background: #3b82f6;
            box-shadow: 0 0 0 2px #dbeafe;
        }
        .tracking-content {
            background: #f9fafb;
            border-radius: 0.5rem;
            padding: 1rem;
            border-left: 3px solid #e5e7eb;
        }
        .tracking-step.completed .tracking-content {
            border-left-color: #10b981;
        }
        .tracking-step.active .tracking-content {
            border-left-color: #3b82f6;
        }
        .tracking-date {
            font-size: 0.75rem;
            color: #6b7280;
        }
        .tracking-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        .tracking-description {
            font-size: 0.875rem;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <a href="profile.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php elseif ($order): ?>
            <div class="order-container">
                <div class="order-header">
                    <div>
                        <h1 class="text-2xl font-bold">Order #<?php echo htmlspecialchars($order['commande_id']); ?></h1>
                        <p class="text-sm opacity-80">Placed on <?php echo date('d/m/Y H:i', strtotime($order['date_commande'])); ?></p>
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
                    <!-- Order Information Section -->
                    <div class="order-section">
                        <h2 class="section-title"><i class="fas fa-info-circle"></i> Order Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Payment Method</p>
                                <p class="font-medium"><?php echo htmlspecialchars($order['methode_paiement']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Payment Reference</p>
                                <p class="font-medium"><?php echo htmlspecialchars($order['reference_paiement']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Delivery Address</p>
                                <p class="font-medium"><?php echo htmlspecialchars($order['adresse_livraison']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Total Amount</p>
                                <p class="font-medium"><?php echo number_format($order['montant_total'], 2); ?> DT</p>
                            </div>
                        </div>
                    </div>

                    <!-- Order Tracking Section -->
                    <div class="order-section">
                        <h2 class="section-title"><i class="fas fa-truck"></i> Order Tracking</h2>
                        
                        <div class="tracking-timeline">
                            <div class="tracking-line"></div>
                            
                            <?php
                                $status = strtolower($order['statut']);
                                $orderDate = strtotime($order['date_commande']);
                                
                                // Define tracking steps
                                $steps = [
                                    'ordered' => [
                                        'title' => 'Order Placed',
                                        'description' => 'Your order has been received and is being processed.',
                                        'date' => date('d/m/Y H:i', $orderDate),
                                        'completed' => true,
                                        'active' => $status === 'en attente'
                                    ],
                                    'processing' => [
                                        'title' => 'Processing',
                                        'description' => 'Your order is being prepared for shipping.',
                                        'date' => date('d/m/Y H:i', $orderDate + 86400), // +1 day
                                        'completed' => in_array($status, ['en cours', 'expédiée', 'livrée']),
                                        'active' => $status === 'en cours'
                                    ],
                                    'shipped' => [
                                        'title' => 'Shipped',
                                        'description' => 'Your order has been shipped and is on its way.',
                                        'date' => date('d/m/Y H:i', $orderDate + 172800), // +2 days
                                        'completed' => in_array($status, ['expédiée', 'livrée']),
                                        'active' => $status === 'expédiée'
                                    ],
                                    'delivered' => [
                                        'title' => 'Delivered',
                                        'description' => 'Your order has been delivered.',
                                        'date' => date('d/m/Y H:i', $orderDate + 432000), // +5 days
                                        'completed' => $status === 'livrée',
                                        'active' => $status === 'livrée'
                                    ]
                                ];
                                
                                // If order is cancelled, override steps
                                if ($status === 'annulée') {
                                    $steps = [
                                        'ordered' => [
                                            'title' => 'Order Placed',
                                            'description' => 'Your order has been received.',
                                            'date' => date('d/m/Y H:i', $orderDate),
                                            'completed' => true,
                                            'active' => false
                                        ],
                                        'cancelled' => [
                                            'title' => 'Order Cancelled',
                                            'description' => 'Your order has been cancelled.',
                                            'date' => date('d/m/Y H:i', $orderDate + 3600), // +1 hour
                                            'completed' => false,
                                            'active' => true
                                        ]
                                    ];
                                }
                                
                                foreach ($steps as $step) {
                                    $stepClass = $step['completed'] ? 'completed' : ($step['active'] ? 'active' : '');
                                    echo '<div class="tracking-step ' . $stepClass . '">';
                                    echo '<div class="tracking-dot"></div>';
                                    echo '<div class="tracking-content">';
                                    echo '<div class="tracking-date">' . $step['date'] . '</div>';
                                    echo '<div class="tracking-title">' . $step['title'] . '</div>';
                                    echo '<div class="tracking-description">' . $step['description'] . '</div>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            ?>
                        </div>
                    </div>

                    <!-- Order Items Section -->
                    <div class="order-section">
                        <h2 class="section-title"><i class="fas fa-shopping-cart"></i> Order Items</h2>
                        
                        <?php if (empty($order_items)): ?>
                            <p class="text-gray-600">No items found for this order.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($order_items as $item): ?>
                                    <div class="item-card">
                                        <img src="<?php echo htmlspecialchars($item['image'] ?? 'https://via.placeholder.com/100'); ?>" alt="<?php echo htmlspecialchars($item['reference']); ?>" class="item-image">
                                        <div class="item-details">
                                            <div>
                                                <h3 class="font-semibold"><?php echo htmlspecialchars($item['reference']); ?></h3>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($item['description'] ?? 'No description available'); ?></p>
                                            </div>
                                            <div class="item-price">
                                                <span><?php echo $item['quantite']; ?> × <?php echo number_format($item['prix_unitaire'], 2); ?> DT</span>
                                                <span><?php echo number_format($item['prix_unitaire'] * $item['quantite'], 2); ?> DT</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="text-right font-semibold text-lg mt-4">
                                    Total: <?php echo number_format($order['montant_total'], 2); ?> DT
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
