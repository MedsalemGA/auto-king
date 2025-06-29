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

// Fetch cart details for display and total calculation
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
            'name' => $article['description'] ?? 'Article #' . $article['article_id'],
            'price' => $article['prix'],
            'quantity' => $article['quantity'],
            'subtotal' => $subtotal,
            'cart_id' => $article['cart_id']
        ];
    }

    // Check if cart is empty
    if (empty($cart_items)) {
        header('Location: search_parts.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching cart articles: " . $e->getMessage());
    $error = 'Erreur lors du chargement du panier.';
}

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = trim(strip_tags($_POST['payment_method']));
    $card_number = trim(strip_tags($_POST['card_number']));
    $cvv = trim(strip_tags($_POST['cvv']));
    $delivery_address = trim(strip_tags($_POST['delivery_address']));

    // Basic validation
    if (empty($payment_method) || empty($card_number) || empty($cvv) || empty($delivery_address)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (!preg_match('/^\d{16}$/', $card_number)) {
        $error = 'Numéro de carte invalide (doit contenir 16 chiffres).';
    } elseif (!preg_match('/^\d{3,4}$/', $cvv)) {
        $error = 'Numéro secret invalide (3 ou 4 chiffres).';
    } else {
        try {
            // Start a transaction
            $pdo->beginTransaction();

            // 1. Create the order in the commandes table
            $masked_card_number = preg_replace('/(\d{12})(\d{4})/', '****-****-****-$2', $card_number);
            $payment_ref = 'REF-' . time();
            $stmt = $pdo->prepare("INSERT INTO commandes (client_id, date_commande, montant_total, statut, adresse_livraison, methode_paiement, numero_carte, reference_paiement)
                                  VALUES (?, NOW(), ?, 'En attente', ?, ?, ?, ?)");
            $stmt->execute([$client_id, $total, $delivery_address, $payment_method, $masked_card_number, $payment_ref]);
            $commande_id = $pdo->lastInsertId();

            // 2. Insert order items into commande_articles table
            $stmt = $pdo->prepare("INSERT INTO commande_articles (commande_id, article_id, quantite, prix_unitaire)
                                  VALUES (?, ?, ?, ?)");
            foreach ($cart_items as $item) {
                $stmt->execute([$commande_id, $item['id'], $item['quantity'], $item['price']]);

                // 3. Update stock quantity
                $update_stock = $pdo->prepare("UPDATE stocks SET quantite = quantite - ? WHERE article_id = ?");
                $update_stock->execute([$item['quantity'], $item['id']]);

                // Check if stock update was successful
                $stmt_check = $pdo->prepare("SELECT quantite FROM stocks WHERE article_id = ?");
                $stmt_check->execute([$item['id']]);
                $new_stock = $stmt_check->fetchColumn();
                if ($new_stock < 0) {
                    throw new PDOException("Stock insuffisant pour l'article ID: " . $item['id']);
                }
            }

            // 4. Clear the cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE client_id = ?");
            $stmt->execute([$client_id]);

            // Commit the transaction
            $pdo->commit();

            // Store only the order ID in session for facture.php
            $_SESSION['payment_details'] = ['commande_id' => $commande_id];

            // Redirect to invoice page
            header('Location: facture.php');
            exit();
        } catch (PDOException $e) {
            // Rollback the transaction in case of error
            $pdo->rollBack();
            error_log("Error processing order: " . $e->getMessage());
            $error = 'Une erreur est survenue lors du traitement de votre commande. Veuillez réessayer.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - AutoParts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', 'Arial', sans-serif;
            background: #e5e7eb;
            color: #333;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            max-width: 800px;
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
        .payment-container {
            background: #ffffff;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            border: 2px solid #e5e7eb;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        .form-group input, .form-group select {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            color: #2d3748;
            border-radius: 0.5rem;
            padding: 0.75rem;
            width: 100%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
            outline: none;
        }
        .order-button {
            background: #4ade80;
            border: 2px solid #000000;
            color: #2d3748;
            font-weight: 500;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            transition: background-color 0.3s ease, transform 0.3s ease;
            width: 100%;
        }
        .order-button:hover {
            background: #065f46;
            color: #ffffff;
            transform: scale(1.05);
        }
        .cart-summary {
            background: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            border: 2px solid #e5e7eb;
            margin-top: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
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
        }
        td {
            background: #ffffff;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2>Paiement</h2>

        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <div class="payment-container">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Détails de Paiement</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="payment_method">Méthode de Paiement</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Sélectionnez une méthode</option>
                        <option value="credit_card">Carte de Crédit</option>
                        <option value="paypal">PayPal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="card_number">Numéro de Carte</label>
                    <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" required>
                </div>
                <div class="form-group">
                    <label for="cvv">Numéro Secret (CVV)</label>
                    <input type="text" id="cvv" name="cvv" placeholder="123" required>
                </div>
                <div class="form-group">
                    <label for="delivery_address">Adresse de Livraison</label>
                    <textarea id="delivery_address" name="delivery_address" rows="3" placeholder="Entrez votre adresse" required></textarea>
                </div>
                <button type="submit" class="order-button">Commander</button>
            </form>
        </div>

        <div class="cart-summary">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Résumé du Panier</h3>
            <?php if (empty($cart_items)): ?>
                <p class="text-gray-600">Aucun article dans le panier.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Prix Unitaire</th>
                            <th>Quantité</th>
                            <th>Sous-total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo number_format($item['price'], 2); ?> DT</td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo number_format($item['subtotal'], 2); ?> DT</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="text-right text-lg font-semibold mt-2">Total: <?php echo number_format($total, 2); ?> DT</p>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>