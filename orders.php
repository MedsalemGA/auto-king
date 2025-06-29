<?php
session_start();
include_once 'config_db.php';

// Vérifier si l'utilisateur est connecté et un mécanicien
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'mecanicien') {
    header('Location: login.php');
    exit();
}

$mechanic_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Gérer l'acceptation/refus d'une demande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['action'])) {
    $order_id = intval($_POST['order_id']);
    $action = $_POST['action'];

    try {
        if ($action === 'complete') {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND mechanic_id = ? AND status = 'accepted'");
            $stmt->execute([$order_id, $mechanic_id]);
            $success = 'Travail marqué comme terminé avec succès !';
        } else {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND mechanic_id = ? AND status = 'pending'");
            $stmt->execute([$action === 'accept' ? 'accepted' : 'rejected', $order_id, $mechanic_id]);
            $success = 'Demande ' . ($action === 'accept' ? 'acceptée' : 'rejetée') . ' avec succès !';
        }
    } catch (PDOException $e) {
        error_log("Erreur de mise à jour : " . $e->getMessage());
        $error = 'Une erreur est survenue.';
    }
}

// Récupérer les demandes en attente
$pending_orders = [];
try {
    $stmt = $pdo->prepare("SELECT o.*, u.nom, u.prenom FROM orders o JOIN utilisateurs u ON o.client_id = u.id WHERE o.mechanic_id = ? AND o.status = 'pending'");
    $stmt->execute([$mechanic_id]);
    $pending_orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur de récupération des demandes : " . $e->getMessage());
    $error = 'Erreur lors du chargement des demandes.';
}

// Récupérer toutes les commandes acceptées pour le calendrier (y compris terminées)
$accepted_orders = [];
try {
    $stmt = $pdo->prepare("SELECT id, description, start_date, end_date, status FROM orders WHERE mechanic_id = ? AND (status = 'accepted' OR status = 'completed')");
    $stmt->execute([$mechanic_id]);
    $accepted_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur de récupération des commandes : " . $e->getMessage());
    $error = 'Erreur lors du chargement du calendrier.';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes - AutoParts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js'></script>
    <script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', 'Arial', sans-serif;
            background:rgb(147, 176, 235);
            color: #333;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }
        h2, h3 {
            font-weight: 700;
            color: #2d3748;
            transition: transform 0.3s ease, color 0.3s ease;
            background: #ffffff;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        h2 {
            font-size: 2.25rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        h2:hover {
            transform: scale(1.05);
            color: #4b5563;
        }
        h3 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }
        h3:hover {
            color: #6b7280;
        }
        .notification-card {
            background: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 2px solid #e5e7eb;
        }
        .notification-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        .notification-card p {
            margin: 0.75rem 0;
            font-size: 1rem;
            color: #4b5563;
            transition: color 0.3s ease;
            background: #f9fafb;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .notification-card p:hover {
            color: #2d3748;
        }
        .notification-card p strong {
            color: #2d3748;
            transition: color 0.3s ease;
        }
        .notification-card p strong:hover {
            color: #000000;
        }
        .notification-card button {
            font-size: 0.875rem;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.3s ease;
            border: 2px solid #000000;
            background: #ffffff;
            color: #000000;
        }
        .notification-card button .accepter:hover {
            transform: scale(1.05);
            background:rgb(16, 77, 4);
            color:rgb(11, 11, 11);
        }
        .notification-card button .refuser:hover {
            transform: scale(1.05);
            background:rgb(213, 16, 16);
            color:rgb(11, 11, 11);
        }
        #calendar {
            background: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            margin-top: 2rem;
            min-height: 500px;
            border: 3px dashed #000000;
            border-top: 5px solid #4b5563;
            border-bottom: 5px solid #6b7280;
        }
        .fc {
            font-family: 'Inter', 'Arial', sans-serif;
        }
        .fc .fc-daygrid-day-number {
            font-size: 1rem;
            color: #4b5563;
            transition: color 0.3s ease;
            background: #f9fafb;
            border-radius: 50%;
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        .fc .fc-daygrid-day-number:hover {
            color: #2d3748;
            background: #e5e7eb;
        }
        .fc .fc-daygrid-day-top {
            padding: 0.5rem;
        }
        .fc .fc-col-header-cell-cushion {
            font-weight: 600;
            color: #2d3748;
            padding: 0.75rem;
            transition: color 0.3s ease;
            background: #ffffff;
            border-bottom: 2px solid #000000;
        }
        .fc .fc-col-header-cell-cushion:hover {
            color: #000000;
        }
        .fc .fc-event {
            border-radius: 0.5rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s ease, background-color 0.3s ease;
            border: 1px solid #000000;
            background: #f9fafb;
        }
        .fc .fc-event:hover {
            transform: scale(1.05);
            background-color: #e5e7eb;
        }
        .fc .fc-button {
            background: #4b5563;
            border: 2px solid #000000;
            color: white;
            font-weight: 500;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        .fc .fc-button:hover {
            background: #2d3748;
            transform: scale(1.05);
        }
        .fc .fc-button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.3);
        }
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 2px solid #065f46;
        }
        .success-message:hover {
            transform: scale(1.02);
        }
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 2px solid #991b1b;
        }
        .error-message:hover {
            transform: scale(1.02);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2>Vos Commandes</h2>
        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <!-- Notifications des demandes en attente -->
        <div class="mb-12">
            <h3>Nouvelles Demandes</h3>
            <?php if (empty($pending_orders)): ?>
                <p class="text-gray-600">Aucune demande en attente.</p>
            <?php else: ?>
                <?php foreach ($pending_orders as $order): ?>
                    <div class="notification-card">
                        <p><strong>Client :</strong> <?php echo htmlspecialchars($order['nom'] . ' ' . $order['prenom']); ?></p>
                        <p><strong>Description :</strong> <?php echo htmlspecialchars($order['description']); ?></p>
                        <p><strong>Date de début :</strong> <?php echo htmlspecialchars($order['start_date']); ?></p>
                        <p><strong>Date de fin :</strong> <?php echo htmlspecialchars($order['end_date']); ?></p>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <button type="submit" name="action" value="accept" class="accepter">Accepter</button>
                            <button type="submit" name="action" value="reject" class="refuser">Refuser</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Calendrier des commandes -->
        <div>
            <h3>Calendrier des Commandes</h3>
            <div id="calendar"></div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            if (calendarEl) {
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    events: <?php echo json_encode(array_map(function($order) {
                        return [
                            'id' => $order['id'],
                            'title' => $order['description'],
                            'start' => $order['start_date'],
                            'end' => $order['end_date'],
                            'color' => $order['status'] === 'completed' ? '#9ca3af' : '#4ade80',
                            'extendedProps' => [
                                'status' => $order['status']
                            ]
                        ];
                    }, $accepted_orders)); ?>,
                    editable: false,
                    height: 'auto',
                    eventClick: function(info) {
                        var event = info.event;
                        var status = event.extendedProps.status;
                        var message = 'Détails : ' + event.title + '\nDu : ' + event.start.toLocaleString() + '\nAu : ' + (event.end ? event.end.toLocaleString() : 'Sans fin');
                        if (status === 'accepted' && event.end < new Date()) {
                            message += '\n\nCe travail est terminé. Voulez-vous le marquer comme terminé ?';
                            if (confirm(message)) {
                                fetch('', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'order_id=' + event.id + '&action=complete'
                                }).then(response => {
                                    window.location.reload();
                                }).catch(error => {
                                    console.error('Error:', error);
                                });
                            }
                        } else {
                            alert(message);
                        }
                    },
                    eventDidMount: function(info) {
                        if (info.event.extendedProps.status === 'completed') {
                            info.el.style.opacity = '0.7';
                        }
                    }
                });
                calendar.render();
            } else {
                console.error('Calendar element not found');
            }
        });
    </script>
</body>
</html>