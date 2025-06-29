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

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['article_id'])) {
        $article_id = intval($_POST['article_id']);
        $action = $_POST['action'];

        try {
            // Check if article exists and get its price
            $stmt = $pdo->prepare("SELECT prix FROM articles WHERE article_id = ?");
            $stmt->execute([$article_id]);
            $article = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$article) {
                $error = 'Article introuvable.';
            } else {
                if ($action === 'add') {
                    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

                    if ($quantity <= 0) {
                        $error = 'Quantité invalide.';
                    } else {
                        // Check available stock
                        $stmt = $pdo->prepare("SELECT quantite FROM stocks WHERE article_id = ?");
                        $stmt->execute([$article_id]);
                        $stock = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$stock || $stock['quantite'] < $quantity) {
                            $error = 'Quantité demandée non disponible en stock.';
                        } else {
                            // Check if article is already in cart
                            $stmt = $pdo->prepare("SELECT cart_id, quantity FROM cart WHERE client_id = ? AND article_id = ?");
                            $stmt->execute([$client_id, $article_id]);
                            $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);

                            // Calculate total quantity (existing + new)
                            $total_quantity = $quantity;
                            if ($cart_item) {
                                $total_quantity += $cart_item['quantity'];
                            }

                            // Verify total quantity doesn't exceed stock
                            if ($total_quantity > $stock['quantite']) {
                                $error = 'La quantité totale demandée dépasse le stock disponible.';
                            } else {
                                if ($cart_item) {
                                    // Update quantity if article already in cart
                                    $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE cart_id = ?");
                                    $stmt->execute([$quantity, $cart_item['cart_id']]);
                                } else {
                                    // Add new article to cart
                                    $stmt = $pdo->prepare("INSERT INTO cart (client_id, article_id, quantity) VALUES (?, ?, ?)");
                                    $stmt->execute([$client_id, $article_id, $quantity]);
                                }

                                $success = 'Article ajouté au panier !';
                            }
                        }
                    }
                } elseif ($action === 'remove') {
                    // Remove article from cart
                    if (isset($_POST['cart_id'])) {
                        $cart_id = intval($_POST['cart_id']);
                        $stmt = $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND client_id = ?");
                        $stmt->execute([$cart_id, $client_id]);
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM cart WHERE client_id = ? AND article_id = ?");
                        $stmt->execute([$client_id, $article_id]);
                    }

                    $success = 'Article retiré du panier !';
                }
            }
        } catch (PDOException $e) {
            error_log("Error managing cart: " . $e->getMessage());
            $error = 'Erreur lors de la gestion du panier.';
        }
    }
}

// Get all unique brands for the filter
$brands = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT marque FROM articles ORDER BY marque");
    $brands = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching brands: " . $e->getMessage());
}

// Get min and max prices for the price filter
$price_range = [];
try {
    $stmt = $pdo->query("SELECT MIN(prix) as min_price, MAX(prix) as max_price FROM articles");
    $price_range = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching price range: " . $e->getMessage());
    $price_range = ['min_price' => 0, 'max_price' => 1000]; // Default values
}

// Initialize filter variables
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_brand = isset($_GET['brand']) ? $_GET['brand'] : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : $price_range['min_price'];
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : $price_range['max_price'];

// Build the SQL query with filters
$sql = "SELECT a.article_id, a.reference, a.marque, a.modele, a.prix, a.disponible, s.quantite, a.image, a.description
        FROM articles a
        JOIN stocks s ON a.article_id = s.article_id
        WHERE s.quantite > 0";

$params = [];

if (!empty($search_term)) {
    $sql .= " AND (a.reference LIKE ? OR a.description LIKE ? OR a.marque LIKE ? OR a.modele LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($selected_brand)) {
    $sql .= " AND a.marque = ?";
    $params[] = $selected_brand;
}

$sql .= " AND a.prix BETWEEN ? AND ?";
$params[] = $min_price;
$params[] = $max_price;

$sql .= " ORDER BY a.marque, a.prix";

// Fetch filtered articles
$articles = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching articles: " . $e->getMessage());
    $error = 'Erreur lors du chargement des articles.';
}

// Fetch cart details
$cart_items = [];
$total = 0;
try {
    $stmt = $pdo->prepare("SELECT c.cart_id, c.article_id, c.quantity, a.description, a.prix
                           FROM cart c
                           JOIN articles a ON c.article_id = a.article_id
                           WHERE c.client_id = ?");
    $stmt->execute([$client_id]);
    $cart_articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cart_articles as $article) {
        $subtotal = $article['prix'] * $article['quantity'];
        $total += $subtotal;
        $cart_items[] = [
            'id' => $article['article_id'],
            'name' => $article['description'],
            'price' => $article['prix'],
            'quantity' => $article['quantity'],
            'subtotal' => $subtotal,
            'cart_id' => $article['cart_id']
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching cart articles: " . $e->getMessage());
    $error = 'Erreur lors du chargement du panier.';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rechercher des Pièces - AutoParts</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }
        h2 {
            font-weight: 700;
            font-size: 2.25rem;
            color: #2d3748;
            text-align: center;
            margin-bottom: 2rem;
            background: #ffffff;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 2px solid #e5e7eb;
            transition: transform 0.3s ease, color 0.3s ease;
        }
        h2:hover {
            transform: scale(1.05);
            color: #4b5563;
        }
        .error-message, .success-message {
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #991b1b;
        }
        .success-message {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #065f46;
        }
        .error-message:hover, .success-message:hover {
            transform: scale(1.02);
        }
        .cart-container {
            background: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            border: 3px dashed #000000;
            border-top: 5px solid #4b5563;
            border-bottom: 5px solid #6b7280;
            margin-bottom: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            color: #2d3748;
            font-weight: 600;
            border-bottom: 2px solid #000000;
            transition: color 0.3s ease;
        }
        th:hover {
            color: #000000;
        }
        td {
            background: #ffffff;
            color: #4b5563;
        }
        tr:hover td {
            background: #f1f5f9;
        }
        .cart-actions button {
            background: #f87171;
            border: 2px solid #000000;
            color: #ffffff;
            font-weight: 500;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        .cart-actions button:hover {
            background: #dc2626;
            transform: scale(1.05);
        }
        .checkout-button {
            background: #4ade80;
            border: 2px solid #000000;
            color: #2d3748;
            font-weight: 500;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            transition: background-color 0.3s ease, transform 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }
        .checkout-button:hover {
            background: #065f46;
            color: #ffffff;
            transform: scale(1.05);
        }
        .articles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        .article-box {
            background: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            border: 2px solid #e5e7eb;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .article-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        .article-box img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .article-box h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
            transition: color 0.3s ease;
        }
        .article-box h3:hover {
            color: #4b5563;
        }
        .article-box p {
            color: #4b5563;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        .article-box .price {
            font-size: 1.125rem;
            font-weight: 500;
            color: #065f46;
            margin-bottom: 1rem;
        }
        .article-box .details-button {
            background: #4b5563;
            border: 2px solid #000000;
            color: #ffffff;
            font-weight: 500;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            width: 100%;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        .article-box .details-button:hover {
            background: #2d3748;
            transform: scale(1.05);
        }
        .search-container {
            background: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border-left: 5px solid #3b82f6;
        }
        .search-bar {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .search-bar input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f9fafb;
        }
        .search-bar input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
            outline: none;
        }
        .search-bar i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 1.25rem;
            transition: color 0.3s ease;
        }
        .search-bar input:focus + i {
            color: #3b82f6;
        }
        .filters-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .filter-group {
            margin-bottom: 1rem;
        }
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4b5563;
        }
        .filter-group select,
        .filter-group input[type="number"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            background-color: #f9fafb;
            transition: all 0.3s ease;
        }
        .filter-group select:focus,
        .filter-group input[type="number"]:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
            outline: none;
        }
        .filter-button {
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1.5rem;
        }
        .filter-button:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .reset-button {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 0.5rem;
        }
        .reset-button:hover {
            background: #e5e7eb;
        }
        .price-slider {
            margin-top: 1rem;
        }
        .price-inputs {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
        }
        .price-inputs input {
            width: 45%;
        }
        .price-display {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2>Rechercher des Pièces</h2>

        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <div class="search-container">
            <form action="" method="GET">
                <div class="search-bar">
                    <input type="text" name="search" placeholder="Rechercher par référence, marque, modèle ou description..." value="<?php echo htmlspecialchars($search_term); ?>">
                    <i class="fas fa-search"></i>
                </div>

                <div class="filters-container">
                    <div class="filter-group">
                        <label for="brand">Marque:</label>
                        <select name="brand" id="brand">
                            <option value="">Toutes les marques</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo htmlspecialchars($brand); ?>" <?php echo $selected_brand === $brand ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brand); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="price-range">Plage de prix:</label>
                        <div class="price-inputs">
                            <input type="number" name="min_price" id="min-price" placeholder="Min" value="<?php echo $min_price; ?>" min="<?php echo $price_range['min_price']; ?>" max="<?php echo $price_range['max_price']; ?>">
                            <input type="number" name="max_price" id="max-price" placeholder="Max" value="<?php echo $max_price; ?>" min="<?php echo $price_range['min_price']; ?>" max="<?php echo $price_range['max_price']; ?>">
                        </div>
                        <div class="price-display">
                            <span><?php echo number_format($price_range['min_price'], 2); ?> DT</span>
                            <span><?php echo number_format($price_range['max_price'], 2); ?> DT</span>
                        </div>
                    </div>

                    <div class="filter-group">
                        <button type="submit" class="filter-button">
                            <i class="fas fa-filter mr-2"></i> Filtrer
                        </button>
                        <a href="search_parts.php" class="reset-button block text-center">Réinitialiser</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="cart-container">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Votre Panier</h3>
            <?php if (empty($cart_items)): ?>
                <p class="text-gray-600">Votre panier est vide.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Prix Unitaire</th>
                            <th>Quantité</th>
                            <th>Sous-total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo number_format($item['price'], 2); ?> DT</td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo number_format($item['subtotal'], 2); ?> DT</td>
                                <td class="cart-actions">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="article_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <button type="submit">Retirer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="mt-4 text-right">
                    <p class="text-lg font-semibold">Total: <?php echo number_format($total, 2); ?> DT</p>
                    <a href="paiement.php" class="checkout-button">Commander</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="articles-grid">
            <?php if (empty($articles)): ?>
                <p class="text-center text-gray-600">Aucun article disponible.</p>
            <?php else: ?>
                <?php foreach ($articles as $article): ?>
                    <div class="article-box">
                        <img src="<?php echo htmlspecialchars($article['image'] ?? 'https://via.placeholder.com/150'); ?>" alt="<?php echo htmlspecialchars($article['description']); ?>">
                        <h3><?php echo htmlspecialchars($article['reference']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($article['description'], 0, 100)) . (strlen($article['description']) > 100 ? '...' : ''); ?></p>
                        <p class="price"><?php echo number_format($article['prix'], 2); ?> DT</p>
                        <form method="POST" class="mb-2">
                            <input type="hidden" name="article_id" value="<?php echo $article['article_id']; ?>">
                            <input type="hidden" name="action" value="add">
                            <div class="flex items-center mb-2">
                                <label for="quantity-<?php echo $article['article_id']; ?>" class="mr-2 text-sm">Qté:</label>
                                <input type="number" id="quantity-<?php echo $article['article_id']; ?>" name="quantity" value="1" min="1" max="<?php echo $article['quantite']; ?>" class="w-full p-2 border rounded">
                            </div>
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded w-full hover:bg-blue-600 transition-all duration-300 mb-2">
                                <i class="fas fa-cart-plus mr-2"></i>Ajouter au panier
                            </button>
                        </form>
                        <a href="fiche_produit.php?article_id=<?php echo $article['article_id']; ?>" class="details-button">Voir Détails</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>