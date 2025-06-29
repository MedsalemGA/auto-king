<?php
session_start();
include_once 'config_db.php';

// Vérifier si l'utilisateur est connecté et un mécanicien
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'mecanicien') {
    header('Location: login.php');
    exit();
}

$mechanic_id = $_SESSION['user_id'];
$services = [];
$error = '';

try {
    $stmt = $pdo->prepare("SELECT o.id, o.description, o.start_date, o.end_date, o.status, u.nom, u.prenom 
                           FROM orders o 
                           JOIN utilisateurs u ON o.client_id = u.id 
                           WHERE o.mechanic_id = ? AND (o.status = 'accepted' OR o.status = 'completed') 
                           ORDER BY o.start_date DESC");
    $stmt->execute([$mechanic_id]);
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur de récupération des prestations : " . $e->getMessage());
    $error = 'Erreur lors du chargement de l’historique des prestations.';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service History - AutoParts</title>
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
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 2px solid #991b1b;
            transition: transform 0.3s ease;
        }
        .error-message:hover {
            transform: scale(1.02);
        }
        .table-container {
            background: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            border: 3px dashed #000000;
            border-top: 5px solid #4b5563;
            border-bottom: 5px solid #6b7280;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
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
            transition: background-color 0.3s ease;
        }
        tr:hover td {
            background: #f1f5f9;
        }
        .status-accepted {
            color: #065f46;
            background: #d1fae5;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            display: inline-block;
        }
        .status-completed {
            color: #4b5563;
            background: #e5e7eb;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2>Historique des Prestations</h2>

        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if (empty($services)): ?>
            <p class="text-center text-gray-600">Aucune prestation acceptée ou terminée pour le moment.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Description</th>
                            <th>Date de Début</th>
                            <th>Date de Fin</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($service['nom'] . ' ' . $service['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($service['description']); ?></td>
                                <td><?php echo htmlspecialchars($service['start_date']); ?></td>
                                <td><?php echo htmlspecialchars($service['end_date']); ?></td>
                                <td>
                                    <span class="status-<?php echo $service['status']; ?>">
                                        <?php echo ucfirst($service['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>