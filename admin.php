<?php
session_start();
include_once 'config_db.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'administrateur') {
    header('Location: admin_login.php');
    exit();
}

// Initialize error message variable
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update Order Status
        if (isset($_POST['update_order_status'])) {
            $order_id = intval($_POST['order_id']);
            $status = $_POST['status'];
            if (!in_array($status, ['En attente', 'En cours', 'Expédiée', 'livrée'])) {
                throw new Exception("Invalid order status: $status");
            }
            $stmt = $pdo->prepare("UPDATE commandes SET statut = ? WHERE commande_id = ?");
            $success = $stmt->execute([$status, $order_id]);
            if (!$success || $stmt->rowCount() === 0) {
                error_log("Failed to update order status for commande_id: $order_id, status: $status");
                $error = "Failed to update order status. Check logs for details.";
            }
        }

        // Process Return/Refund
        if (isset($_POST['process_return'])) {
            $return_id = intval($_POST['return_id']);
            $status = $_POST['status'];
            $refund_amount = $status === 'approved' ? floatval($_POST['refund_amount']) : NULL;
            $stmt = $pdo->prepare("UPDATE returns SET status = ?, refund_amount = ? WHERE id = ?");
            $success = $stmt->execute([$status, $refund_amount, $return_id]);
            if (!$success) {
                error_log("Failed to process return for return_id: $return_id, status: $status");
                $error = "Failed to process return. Check logs for details.";
            }
        }

        // Update Payment Status (simulated)
        if (isset($_POST['update_payment_status'])) {
            $payment_id = intval($_POST['payment_id']);
            $status = $_POST['status'];
            $stmt = $pdo->prepare("UPDATE payments SET status = ? WHERE id = ?");
            $success = $stmt->execute([$status, $payment_id]);
            if (!$success) {
                error_log("Failed to update payment status for payment_id: $payment_id, status: $status");
                $error = "Failed to update payment status. Check logs for details.";
            }
        }

        // Update Invoice Status
        if (isset($_POST['update_invoice_status'])) {
            $invoice_id = intval($_POST['invoice_id']);
            $status = $_POST['status'];
            $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
            $success = $stmt->execute([$status, $invoice_id]);
            if (!$success) {
                error_log("Failed to update invoice status for invoice_id: $invoice_id, status: $status");
                $error = "Failed to update invoice status. Check logs for details.";
            }
        }

        // Suspend/Ban User
        if (isset($_POST['update_user_status'])) {
            $user_id = intval($_POST['user_id']);
            $status = $_POST['status'];
            if (!in_array($status, ['active', 'suspended', 'banned'])) {
                throw new Exception("Invalid user status: $status");
            }
            $stmt = $pdo->prepare("UPDATE utilisateurs SET status = ? WHERE id = ?");
            $success = $stmt->execute([$status, $user_id]);
            if (!$success || $stmt->rowCount() === 0) {
                error_log("Failed to update user status for user_id: $user_id, status: $status");
                $error = "Failed to update user status. Check logs for details.";
            }
        }

        // Update Terms of Use
        if (isset($_POST['update_terms'])) {
            $content = trim(strip_tags($_POST['content'], '<p><br><strong><em><ul><ol><li>'));
            $stmt = $pdo->prepare("INSERT INTO terms_of_use (content) VALUES (?) ON DUPLICATE KEY UPDATE content = ?, updated_at = NOW()");
            $success = $stmt->execute([$content, $content]);
            if (!$success) {
                error_log("Failed to update terms of use");
                $error = "Failed to update terms of use. Check logs for details.";
            }
        }

        // Add New Article
        if (isset($_POST['add_article'])) {
            $reference = trim($_POST['reference']);
            $description = trim($_POST['nom_article']);
            $marque = trim($_POST['marque']);
            $modele = trim($_POST['modele']);
            $disponible = isset($_POST['disponible']) ? 1 : 0;
            $quantite = intval($_POST['quantite']);
            $prix = isset($_POST['prix']) ? floatval($_POST['prix']) : 0;
            $image_path = '';

            // Validate inputs
            if (empty($reference) || empty($description) || empty($marque) || empty($modele)) {
                throw new Exception("Invalid article data: reference, description, marque, and modele must be provided.");
            }
            if ($prix <= 0) {
                throw new Exception("Price must be greater than 0.");
            }

            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $image_name = time() . '_' . basename($_FILES['image']['name']);
                $image_path = $upload_dir . $image_name;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                    throw new Exception("Failed to upload image.");
                }
            } else {
                throw new Exception("Image is required.");
            }

            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert into articles table
            $stmt = $pdo->prepare("INSERT INTO articles (reference, description, marque, modele, disponible, prix, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $success = $stmt->execute([$reference, $description, $marque, $modele, $disponible, $prix, $image_path]);
            if (!$success) {
                $pdo->rollBack();
                error_log("Failed to add article to articles table: reference=$reference, description=$description, marque=$marque, modele=$modele, prix=$prix, image=$image_path");
                throw new Exception("Failed to add article to articles table. Check logs.");
            }
            
            // Get the newly inserted article_id
            $article_id = $pdo->lastInsertId();
            error_log("Inserted article_id: $article_id");
            
            // Insert into stocks table
            $stmt = $pdo->prepare("INSERT INTO stocks (article_id, quantite) VALUES (?, ?)");
            $success = $stmt->execute([$article_id, $quantite]);
            if (!$success) {
                $pdo->rollBack();
                error_log("Failed to add quantity to stocks table for article_id: $article_id, quantite: $quantite");
                throw new Exception("Failed to add quantity to stocks table.");
            }
            
            // Commit transaction
            $pdo->commit();
            error_log("Transaction committed for article_id: $article_id");
        }

        // Delete Article
        if (isset($_POST['delete_article'])) {
            $article_id = intval($_POST['article_id']);
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Delete from stocks table
            $stmt = $pdo->prepare("DELETE FROM stocks WHERE article_id = ?");
            $success = $stmt->execute([$article_id]);
            if (!$success) {
                $pdo->rollBack();
                error_log("Failed to delete stocks entry for article_id: $article_id");
                throw new Exception("Failed to delete stocks entry.");
            }
            
            // Delete from articles table
            $stmt = $pdo->prepare("DELETE FROM articles WHERE article_id = ?");
            $success = $stmt->execute([$article_id]);
            if (!$success || $stmt->rowCount() === 0) {
                $pdo->rollBack();
                error_log("Failed to delete article with article_id: $article_id");
                throw new Exception("Failed to delete article.");
            }
            
            // Commit transaction
            $pdo->commit();
            error_log("Deleted article_id: $article_id");
        }

        // Redirect to avoid form resubmission
        header('Location: admin.php');
        exit();
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        $error = "An error occurred: " . $e->getMessage();
    }
}

// Fetch dashboard statistics
$total_sales = $pdo->query("SELECT SUM(montant_total) FROM commandes")->fetchColumn() ?? 0;
$orders_by_status = $pdo->query("SELECT statut, COUNT(*) as count FROM commandes GROUP BY statut")->fetchAll();
$orders_by_status_map = array_column($orders_by_status, 'count', 'statut');

// Fetch data for tables
$orders = $pdo->query("SELECT c.*, u.nom, u.prenom FROM commandes c JOIN utilisateurs u ON c.client_id = u.id")->fetchAll();
$returns = $pdo->query("SELECT r.*, c.montant_total, u.nom, u.prenom FROM returns r JOIN commandes c ON r.order_id = c.commande_id JOIN utilisateurs u ON r.user_id = u.id")->fetchAll();
$payments = $pdo->query("SELECT p.*, u.nom, u.prenom FROM payments p JOIN utilisateurs u ON p.user_id = u.id")->fetchAll();
$invoices = $pdo->query("SELECT i.*, u.nom, u.prenom FROM invoices i JOIN utilisateurs u ON i.user_id = u.id")->fetchAll();
$users = $pdo->query("SELECT * FROM utilisateurs WHERE role != 'administrateur'")->fetchAll();
$terms = $pdo->query("SELECT * FROM terms_of_use ORDER BY updated_at DESC LIMIT 1")->fetch();
$metrics = $pdo->query("SELECT * FROM platform_metrics")->fetchAll();
$metrics_map = array_column($metrics, 'metric_value', 'metric_name');
$articles = $pdo->query("SELECT a.*, s.quantite FROM articles a LEFT JOIN stocks s ON a.article_id = s.article_id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
    <link rel="stylesheet" href="admin.css" />
    <title>AutoParts | Admin Dashboard</title>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar__logo">
                <img src="assets/admin-logo.svg" alt="AutoParts Admin" />
            </div>
            <div class="sidebar__nav">
                <ul>
                    <li><a href="#dashboard" class="active"><i class="ri-dashboard-line"></i> Dashboard</a></li>
                    <li><a href="#orders"><i class="ri-shopping-cart-line"></i> Orders</a></li>
                    <li><a href="#returns"><i class="ri-reply-line"></i> Returns & Refunds</a></li>
                    <li><a href="#payments"><i class="ri-money-dollar-circle-line"></i> Payments</a></li>
                    <li><a href="#invoices"><i class="ri-file-text-line"></i> Invoices</a></li>
                    <li><a href="#users"><i class="ri-user-line"></i> Users</a></li>
                    <li><a href="#terms"><i class="ri-file-list-line"></i> Terms of Use</a></li>
                    <li><a href="logout.php"><i class="ri-logout-box-line"></i> Logout</a></li>
                    <li><a href="#metrics"><i class="ri-bar-chart-line"></i> Platform Metrics</a></li>
                    <li><a href="#stock"><i class="ri-archive-line"></i> Stock Management</a></li>
                    <li><a href="logout.php"><i class="ri-logout-box-line"></i> Logout</a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="main-content">
            <nav class="nav__header">
                <div class="nav__header__left">
                    <h1>Admin Dashboard</h1>
                </div>
                <div class="nav__header__right">
                    <button class="dark-mode-toggle" id="dark-mode-toggle">
                        <i class="ri-sun-line" id="theme-icon"></i>
                    </button>
                </div>
            </nav>

            <!-- Display Error Message -->
            <?php if (!empty($error)): ?>
                <div class="error-message" style="color: red; padding: 10px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Dashboard Overview -->
            <div class="section__container dashboard__overview" id="dashboard">
                <h2 class="section__header">Sales Overview</h2>
                <div class="dashboard__cards">
                    <div class="card">
                        <div class="card__header">
                            <div class="card__icon card__icon--clients">
                                <i class="ri-money-dollar-box-line"></i>
                            </div>
                        </div>
                        <h3><?php echo number_format($total_sales, 2); ?> DT</h3>
                        <p>Total Sales</p>
                    </div>
                    <div class="card">
                        <div class="card__header">
                            <div class="card__icon card__icon--coaches">
                                <i class="ri-truck-line"></i>
                            </div>
                        </div>
                        <h3><?php echo $orders_by_status_map['Expédiée'] ?? 0; ?></h3>
                        <p>Shipped Orders</p>
                    </div>
                    <div class="card">
                        <div class="card__header">
                            <div class="card__icon card__icon--subscriptions">
                                <i class="ri-check-double-line"></i>
                            </div>
                        </div>
                        <h3><?php echo $orders_by_status_map['livrée'] ?? 0; ?></h3>
                        <p>Delivered Orders</p>
                    </div>
                </div>
            </div>

            <!-- Orders Management -->
            <div class="section__container" id="orders">
                <div class="table-container">
                    <div class="table-header">
                        <h2 class="section__header">Orders Management</h2>
                    </div>
                    <input type="text" id="order-filter" placeholder="Search orders..." class="filter-input" />
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>User</th>
                                    <th>Total Amount (DT)</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="orders-table">
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['commande_id']); ?></td>
                                        <td><?php echo htmlspecialchars($order['prenom'] . ' ' . $order['nom']); ?></td>
                                        <td><?php echo number_format($order['montant_total'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo isset($order['statut']) && $order['statut'] === 'livrée' ? 'active' : 'inactive'; ?>">
                                                <?php 
                                                $status_display = isset($order['statut']) ? $order['statut'] : 'N/A';
                                                $status_map = [
                                                    'En attente' => 'Pending',
                                                    'En cours' => 'In Progress',
                                                    'Expédiée' => 'Shipped',
                                                    'livrée' => 'Delivered'
                                                ];
                                                echo isset($status_map[$order['statut']]) ? $status_map[$order['statut']] : $status_display;
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="order_id" value="<?php echo $order['commande_id']; ?>">
                                                <select name="status" onchange="this.form.submit()">
                                                    <option value="En attente" <?php echo isset($order['statut']) && $order['statut'] === 'En attente' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="En cours" <?php echo isset($order['statut']) && $order['statut'] === 'En cours' ? 'selected' : ''; ?>>In Progress</option>
                                                    <option value="Expédiée" <?php echo isset($order['statut']) && $order['statut'] === 'Expédiée' ? 'selected' : ''; ?>>Shipped</option>
                                                    <option value="livrée" <?php echo isset($order['statut']) && $order['statut'] === 'livrée' ? 'selected' : ''; ?>>Delivered</option>
                                                </select>
                                                <input type="hidden" name="update_order_status">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Returns & Refunds Management -->
            <div class="section__container" id="returns">
                <div class="table-container">
                    <div class="table-header">
                        <h2 class="section__header">Returns & Refunds (SAV)</h2>
                    </div>
                    <input type="text" id="return-filter" placeholder="Search returns..." class="filter-input" />
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Return ID</th>
                                    <th>Order ID</th>
                                    <th>User</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Refund Amount (DT)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="returns-table">
                                <?php foreach ($returns as $return): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($return['id']); ?></td>
                                        <td><?php echo htmlspecialchars($return['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($return['prenom'] . ' ' . $return['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($return['reason']); ?></td>
                                        <td><span class="status-badge status-<?php echo isset($return['status']) && $return['status'] === 'approved' ? 'active' : (isset($return['status']) && $return['status'] === 'rejected' ? 'inactive' : 'pending'); ?>"><?php echo isset($return['status']) ? ucfirst($return['status']) : 'Pending'; ?></span></td>
                                        <td><?php echo $return['refund_amount'] ? number_format($return['refund_amount'], 2) : 'N/A'; ?></td>
                                        <td>
                                            <?php if (isset($return['status']) && $return['status'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="return_id" value="<?php echo $return['id']; ?>">
                                                    <select name="status" onchange="this.form.querySelector('input[name=refund_amount]').disabled = this.value !== 'approved'; this.form.submit();">
                                                        <option value="pending" <?php echo isset($return['status']) && $return['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="approved">Approve</option>
                                                        <option value="rejected">Reject</option>
                                                    </select>
                                                    <input type="number" name="refund_amount" placeholder="Refund Amount" step="0.01" max="<?php echo $return['montant_total']; ?>" disabled>
                                                    <input type="hidden" name="process_return">
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payments Management -->
            <div class="section__container" id="payments">
                <div class="table-container">
                    <div class="table-header">
                        <h2 class="section__header">Payments Management</h2>
                    </div>
                    <input type="text" id="payment-filter" placeholder="Search payments..." class="filter-input" />
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Order ID</th>
                                    <th>User</th>
                                    <th>Amount (DT)</th>
                                    <th>Transaction ID</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="payments-table">
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['id']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['prenom'] . ' ' . $payment['nom']); ?></td>
                                        <td><?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($payment['transaction_id']); ?></td>
                                        <td><span class="status-badge status-<?php echo isset($payment['status']) && $payment['status'] === 'completed' ? 'active' : (isset($payment['status']) && $payment['status'] === 'failed' ? 'inactive' : 'pending'); ?>"><?php echo isset($payment['status']) ? ucfirst($payment['status']) : 'Pending'; ?></span></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                <select name="status" onchange="this.form.submit()">
                                                    <option value="pending" <?php echo isset($payment['status']) && $payment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="completed" <?php echo isset($payment['status']) && $payment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="failed" <?php echo isset($payment['status']) && $payment['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                </select>
                                                <input type="hidden" name="update_payment_status">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Invoices Management -->
            <div class="section__container" id="invoices">
                <div class="table-container">
                    <div class="table-header">
                        <h2 class="section__header">Invoices Management</h2>
                    </div>
                    <input type="text" id="invoice-filter" placeholder="Search invoices..." class="filter-input" />
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Invoice Number</th>
                                    <th>Order ID</th>
                                    <th>User</th>
                                    <th>Amount (DT)</th>
                                    <th>Issued At</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="invoices-table">
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['order_id']); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['prenom'] . ' ' . $invoice['nom']); ?></td>
                                        <td><?php echo number_format($invoice['amount'], 2); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($invoice['issued_at'])); ?></td>
                                        <td><span class="status-badge status-<?php echo isset($invoice['status']) && $invoice['status'] === 'paid' ? 'active' : 'inactive'; ?>"><?php echo isset($invoice['status']) ? ucfirst($invoice['status']) : 'Unpaid'; ?></span></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                                <select name="status" onchange="this.form.submit()">
                                                    <option value="unpaid" <?php echo isset($invoice['status']) && $invoice['status'] === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                                    <option value="paid" <?php echo isset($invoice['status']) && $invoice['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                </select>
                                                <input type="hidden" name="update_invoice_status">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Users Management -->
            <div class="section__container" id="users">
                <div class="table-container">
                    <div class="table-header">
                        <h2 class="section__header">Users Management</h2>
                    </div>
                    <input type="text" id="user-filter" placeholder="Search users..." class="filter-input" />
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-table">
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo ucfirst($user['role']); ?></td>
                                        <td><span class="status-badge status-<?php echo isset($user['status']) && $user['status'] === 'active' ? 'active' : (isset($user['status']) && $user['status'] === 'banned' ? 'inactive' : 'pending'); ?>"><?php echo isset($user['status']) ? ucfirst($user['status']) : 'Pending'; ?></span></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <select name="status" onchange="this.form.submit()">
                                                    <option value="active" <?php echo isset($user['status']) && $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="suspended" <?php echo isset($user['status']) && $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                    <option value="banned" <?php echo isset($user['status']) && $user['status'] === 'banned' ? 'selected' : ''; ?>>Banned</option>
                                                </select>
                                                <input type="hidden" name="update_user_status">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Terms of Use Management -->
            <div class="section__container" id="terms">
                <div class="table-container">
                    <div class="table-header">
                        <h2 class="section__header">Terms of Use</h2>
                    </div>
                    <form method="POST" class="modal-form">
                        <div class="form-group">
                            <label for="terms-content">Terms of Use Content</label>
                            <textarea id="terms-content" name="content" rows="10" style="width: 100%; padding: 12px; border-radius: 10px;"><?php echo htmlspecialchars($terms['content'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="update_terms" class="btn-primary">Update Terms</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Platform Metrics -->
            <div class="section__container" id="metrics">
                <div class="table-container">
                    <div class="table-header">
                        <h2 class="section__header">Platform Stability Metrics</h2>
                    </div>
                    <div class="dashboard__cards">
                        <div class="card">
                            <div class="card__header">
                                <div class="card__icon card__icon--clients">
                                    <i class="ri-time-line"></i>
                                </div>
                            </div>
                            <h3><?php echo number_format($metrics_map['uptime_percentage'] ?? 0, 1); ?>%</h3>
                            <p>Uptime</p>
                        </div>
                        <div class="card">
                            <div class="card__header">
                                <div class="card__icon card__icon--coaches">
                                    <i class="ri-user-line"></i>
                                </div>
                            </div>
                            <h3><?php echo $metrics_map['active_users'] ?? 0; ?></h3>
                            <p>Active Users</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Management -->
            <div class="section__container" id="stock">
                <div class="table-container">
                    <div class="table-header">
                        <h2 class="section__header">Stock Management</h2>
                    </div>
                    <!-- Form to Add New Article -->
                    <form method="POST" class="modal-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="reference">Reference</label>
                            <input type="text" id="reference" name="reference" placeholder="Enter article reference" required>
                        </div>
                        <div class="form-group">
                            <label for="nom_article">Article Description</label>
                            <input type="text" id="nom_article" name="nom_article" placeholder="Enter article description" required>
                        </div>
                        <div class="form-group">
                            <label for="marque">Brand (Marque)</label>
                            <input type="text" id="marque" name="marque" placeholder="Enter brand" required>
                        </div>
                        <div class="form-group">
                            <label for="modele">Model (Modèle)</label>
                            <input type="text" id="modele" name="modele" placeholder="Enter model" required>
                        </div>
                        <div class="form-group">
                            <label for="disponible">Available (Disponible)</label>
                            <input type="checkbox" id="disponible" name="disponible" value="1">
                        </div>
                        <div class="form-group">
                            <label for="quantite">Quantity</label>
                            <input type="number" id="quantite" name="quantite" placeholder="Enter quantity" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="prix">Price (DT)</label>
                            <input type="number" id="prix" name="prix" placeholder="Enter price" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="image">Image</label>
                            <input type="file" id="image" name="image" accept="image/*" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="add_article" class="btn-primary">Add Article</button>
                        </div>
                    </form>

                    <!-- Articles Table -->
                    <input type="text" id="article-filter" placeholder="Search articles..." class="filter-input" />
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Article ID</th>
                                    <th>Reference</th>
                                    <th>Description</th>
                                    <th>Brand</th>
                                    <th>Model</th>
                                    <th>Available</th>
                                    <th>Price (DT)</th>
                                    <th>Image</th>
                                    <th>Quantity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="articles-table">
                                <?php foreach ($articles as $article): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($article['article_id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($article['reference'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($article['description'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($article['marque'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($article['modele'] ?? 'N/A'); ?></td>
                                        <td><?php echo ($article['disponible'] ?? 0) ? 'Yes' : 'No'; ?></td>
                                        <td><?php echo number_format($article['prix'] ?? 0, 2); ?></td>
                                        <td>
                                            <?php if (!empty($article['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="Article Image" style="max-width: 50px; max-height: 50px;">
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($article['quantite'] ?? 'N/A'); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="article_id" value="<?php echo $article['article_id'] ?? ''; ?>">
                                                <button type="submit" name="delete_article" class="btn-danger" onclick="return confirm('Are you sure you want to delete this article?');">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dark mode toggle
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const themeIcon = document.getElementById('theme-icon');
        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            themeIcon.className = document.body.classList.contains('dark-mode') ? 'ri-moon-line' : 'ri-sun-line';
        });

        // Search functionality
        function filterTable(inputId, tableId) {
            const input = document.getElementById(inputId);
            const filter = input.value.toLowerCase();
            const table = document.getElementById(tableId);
            const rows = table.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j] && cells[j].textContent.toLowerCase().includes(filter)) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        }

        document.getElementById('order-filter').addEventListener('input', () => filterTable('order-filter', 'orders-table'));
        document.getElementById('return-filter').addEventListener('input', () => filterTable('return-filter', 'returns-table'));
        document.getElementById('payment-filter').addEventListener('input', () => filterTable('payment-filter', 'payments-table'));
        document.getElementById('invoice-filter').addEventListener('input', () => filterTable('invoice-filter', 'invoices-table'));
        document.getElementById('user-filter').addEventListener('input', () => filterTable('user-filter', 'users-table'));
        document.getElementById('article-filter').addEventListener('input', () => filterTable('article-filter', 'articles-table'));

        // Sidebar navigation
        const sidebarLinks = document.querySelectorAll('.sidebar__nav a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                sidebarLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>