<?php
session_start();
include_once 'config_db.php';

// Check if user is logged in and a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: login.php');
    exit();
}

$client_id = $_SESSION['user_id'];
$article_id = isset($_GET['article_id']) ? intval($_GET['article_id']) : null;
$article = null;
$error = '';
$success = '';

// Fetch article details
if ($article_id) {
    try {
        $stmt = $pdo->prepare("SELECT a.article_id, a.reference, a.marque, a.modele, a.description, a.prix, a.image, s.quantite 
                               FROM articles a 
                               JOIN stocks s ON a.article_id = s.article_id 
                               WHERE a.article_id = ? AND s.quantite > 0");
        $stmt->execute([$article_id]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$article) {
            $error = 'Article non trouvé ou en rupture de stock.';
        }
    } catch (PDOException $e) {
        error_log("Error fetching article: " . $e->getMessage());
        $error = 'Erreur lors du chargement de l\'article.';
    }
}

// Fetch related products (same brand or model)
$related_products = [];
if ($article) {
    try {
        $stmt = $pdo->prepare("SELECT a.article_id, a.reference, a.marque, a.modele, a.prix, a.image 
                               FROM articles a 
                               JOIN stocks s ON a.article_id = s.article_id 
                               WHERE (a.marque = ? OR a.modele = ?) 
                               AND a.article_id != ? 
                               AND s.quantite > 0 
                               LIMIT 4");
        $stmt->execute([$article['marque'], $article['modele'], $article_id]);
        $related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching related products: " . $e->getMessage());
    }
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart']) && $article) {
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    if ($quantity <= 0 || $quantity > $article['quantite']) {
        $error = 'Quantité invalide ou supérieure au stock disponible.';
    } else {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (isset($_SESSION['cart'][$article_id])) {
            $_SESSION['cart'][$article_id] += $quantity;
        } else {
            $_SESSION['cart'][$article_id] = $quantity;
        }
        $success = 'Article ajouté au panier avec succès!';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de l'Article - AutoParts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/product_styles.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <a href="search_parts.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Retour aux articles
        </a>
        
        <h2>Détails de l'Article</h2>

        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <?php if ($article): ?>
            <div class="product-container">
                <div class="product-image">
                    <img src="<?php echo htmlspecialchars($article['image'] ?? 'https://via.placeholder.com/400'); ?>" alt="<?php echo htmlspecialchars($article['reference']); ?>">
                </div>
                
                <div class="product-details">
                    <h1 class="product-title"><?php echo htmlspecialchars($article['reference']); ?></h1>
                    
                    <div>
                        <span class="product-brand"><?php echo htmlspecialchars($article['marque']); ?></span>
                        <span class="product-model"><?php echo htmlspecialchars($article['modele']); ?></span>
                    </div>
                    
                    <p class="product-description"><?php echo htmlspecialchars($article['description']); ?></p>
                    
                    <div class="product-price"><?php echo number_format($article['prix'], 2); ?> DT</div>
                    
                    <div class="product-stock">
                        <i class="fas fa-check-circle"></i>
                        <span>En stock: <?php echo $article['quantite']; ?> unités disponibles</span>
                    </div>
                    
                    <form method="POST" class="add-to-cart-form">
                        <div class="quantity-control">
                            <label for="quantity">Quantité:</label>
                            <div class="quantity-input">
                                <button type="button" class="quantity-btn" onclick="decrementQuantity()">-</button>
                                <input type="number" id="quantity" name="quantity" min="1" max="<?php echo $article['quantite']; ?>" value="1" required>
                                <button type="button" class="quantity-btn" onclick="incrementQuantity()">+</button>
                            </div>
                        </div>
                        <button type="submit" name="add_to_cart" class="add-to-cart-button">
                            <i class="fas fa-cart-plus"></i> Ajouter au Panier
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Product Specifications -->
            <div class="product-specs">
                <h3>Spécifications</h3>
                <div class="specs-grid">
                    <div class="spec-item">
                        <div class="spec-label">Référence</div>
                        <div class="spec-value"><?php echo htmlspecialchars($article['reference']); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Marque</div>
                        <div class="spec-value"><?php echo htmlspecialchars($article['marque']); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Modèle</div>
                        <div class="spec-value"><?php echo htmlspecialchars($article['modele']); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Prix</div>
                        <div class="spec-value"><?php echo number_format($article['prix'], 2); ?> DT</div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">Disponibilité</div>
                        <div class="spec-value"><?php echo $article['quantite'] > 0 ? 'En stock' : 'Rupture de stock'; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Related Products -->
            <?php if (!empty($related_products)): ?>
                <div class="related-products">
                    <h3>Produits Similaires</h3>
                    <div class="related-grid">
                        <?php foreach ($related_products as $related): ?>
                            <div class="related-item">
                                <img src="<?php echo htmlspecialchars($related['image'] ?? 'https://via.placeholder.com/150'); ?>" alt="<?php echo htmlspecialchars($related['reference']); ?>">
                                <h4><?php echo htmlspecialchars($related['reference']); ?></h4>
                                <p><?php echo htmlspecialchars($related['marque'] . ' ' . $related['modele']); ?></p>
                                <p class="price"><?php echo number_format($related['prix'], 2); ?> DT</p>
                                <a href="fiche_produit.php?article_id=<?php echo $related['article_id']; ?>" class="view-button">Voir Détails</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <p class="text-center text-gray-600">Article non disponible.</p>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
    
    <script>
        function incrementQuantity() {
            const input = document.getElementById('quantity');
            const max = parseInt(input.getAttribute('max'));
            const currentValue = parseInt(input.value);
            if (currentValue < max) {
                input.value = currentValue + 1;
            }
        }
        
        function decrementQuantity() {
            const input = document.getElementById('quantity');
            const currentValue = parseInt(input.value);
            if (currentValue > 1) {
                input.value = currentValue - 1;
            }
        }
    </script>
</body>
</html>
