<?php
session_start();
include_once 'config_db.php';

// Check if user is logged in and a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['payment_details']) || !isset($_SESSION['payment_details']['commande_id'])) {
    header('Location: search_parts.php');
    exit();
}

$client_id = $_SESSION['user_id'];
$commande_id = $_SESSION['payment_details']['commande_id'];
$cart_items = [];
$total = 0;
$commande = null;
$error = '';

// Fetch order details from commandes and commande_articles
try {
    // Verify the commande belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM commandes WHERE commande_id = ? AND client_id = ?");
    $stmt->execute([$commande_id, $client_id]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        throw new Exception("Commande introuvable ou non autorisée.");
    }

    // Fetch items from commande_articles
    $stmt = $pdo->prepare("SELECT ca.article_id, ca.quantite, ca.prix_unitaire, a.description, a.reference
                           FROM commande_articles ca
                           JOIN articles a ON ca.article_id = a.article_id
                           WHERE ca.commande_id = ?");
    $stmt->execute([$commande_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $subtotal = $item['prix_unitaire'] * $item['quantite'];
        $total += $subtotal;
        $cart_items[] = [
            'id' => $item['article_id'],
            'name' => $item['description'] ?? $item['reference'] ?? 'Article #' . $item['article_id'],
            'price' => $item['prix_unitaire'],
            'quantity' => $item['quantite'],
            'subtotal' => $subtotal
        ];
    }

    // If total is still 0, use the total from the commande table
    if ($total == 0 && isset($commande['montant_total'])) {
        $total = $commande['montant_total'];
    }
} catch (Exception $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    $error = 'Erreur lors du chargement de la facture.';
}

// Clear session data
unset($_SESSION['payment_details']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture - AutoParts</title>
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
        .invoice-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo {
            width: 150px;
            height: auto;
            margin-bottom: 1rem;
        }
        h2 {
            font-weight: 700;
            font-size: 2.25rem;
            color: #2d3748;
            margin-bottom: 1rem;
        }
        .invoice-details {
            background: #ffffff;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            border: 2px solid #e5e7eb;
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
        .print-button {
            background: #4b5563;
            border: 2px solid #000000;
            color: #ffffff;
            font-weight: 500;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            transition: background-color 0.3s ease, transform 0.3s ease;
            display: block;
            margin: 1rem auto;
        }
        .print-button:hover {
            background: #2d3748;
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="invoice-header">
            <img src="https://via.placeholder.com/150?text=AutoParts+Logo" alt="AutoParts Logo" class="logo">
            <h2>Facture</h2>
            <p>Numéro de Commande: <?php echo htmlspecialchars($commande_id); ?></p>
            <p>Date: <?php echo isset($commande['date_commande']) ? date('d/m/Y H:i:s', strtotime($commande['date_commande'])) : date('d/m/Y H:i:s'); ?></p>
        </div>

        <div class="invoice-details">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Détails de la Commande</h3>
            <p><strong>Client ID:</strong> <?php echo htmlspecialchars($client_id); ?></p>
            <p><strong>Statut de la Commande:</strong> <span class="px-2 py-1 rounded-full bg-green-100 text-green-800"><?php echo htmlspecialchars($commande['statut'] ?? 'En attente'); ?></span></p>
            <p><strong>Méthode de Paiement:</strong> <?php echo htmlspecialchars($commande['methode_paiement'] ?? 'Non spécifiée'); ?></p>
            <p><strong>Numéro de Carte:</strong> <?php echo htmlspecialchars($commande['numero_carte'] ?? 'Non spécifié'); ?></p>
            <p><strong>Référence de Paiement:</strong> <?php echo htmlspecialchars($commande['reference_paiement'] ?? 'Non spécifiée'); ?></p>
            <p><strong>Adresse de Livraison:</strong> <?php echo htmlspecialchars($commande['adresse_livraison'] ?? 'Non spécifiée'); ?></p>

            <h4 class="text-md font-semibold mt-4 mb-2">Articles Commandés</h4>
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
        </div>

        <button onclick="window.print()" class="print-button">Imprimer la Facture</button>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>