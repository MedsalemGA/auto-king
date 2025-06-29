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
$mechanics = [];
$error = '';
$success = '';
$show_available_only = isset($_GET['available_only']) && $_GET['available_only'] === '1';
$min_rate = isset($_GET['min_rate']) && $_GET['min_rate'] !== '' ? floatval($_GET['min_rate']) : null;
$max_rate = isset($_GET['max_rate']) && $_GET['max_rate'] !== '' ? floatval($_GET['max_rate']) : null;
$adresse_filter = isset($_GET['adresse']) && $_GET['adresse'] !== '' ? trim($_GET['adresse']) : '';

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_role === 'client' && isset($_POST['submit_rating'])) {
    $mechanic_id = intval($_POST['mechanic_id']);
    $rating = intval($_POST['rating']);
    $comment = trim(strip_tags($_POST['comment']));

    if ($rating < 1 || $rating > 5) {
        $error = 'Le rating doit être entre 1 et 5.';
    } else {
        try {
            // Check if client has already rated this mechanic
            $stmt = $pdo->prepare("SELECT rating_id FROM mechanic_ratings WHERE mechanic_id = ? AND client_id = ?");
            $stmt->execute([$mechanic_id, $user_id]);
            $existing_rating = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_rating) {
                // Update existing rating
                $stmt = $pdo->prepare("UPDATE mechanic_ratings SET rating = ?, comment = ?, date_rated = NOW() WHERE rating_id = ?");
                $stmt->execute([$rating, $comment, $existing_rating['rating_id']]);
                $success = 'Votre évaluation a été mise à jour !';
            } else {
                // Insert new rating
                $stmt = $pdo->prepare("INSERT INTO mechanic_ratings (mechanic_id, client_id, rating, comment) VALUES (?, ?, ?, ?)");
                $stmt->execute([$mechanic_id, $user_id, $rating, $comment]);
                $success = 'Votre évaluation a été enregistrée !';
            }
        } catch (PDOException $e) {
            error_log("Error submitting/updating rating: " . $e->getMessage());
            $error = 'Erreur lors de l\'enregistrement de votre évaluation.';
        }
    }
}

// Fetch mechanics based on user role and filters
try {
    $query = "SELECT u.*, 
                     (SELECT AVG(rating) FROM mechanic_ratings WHERE mechanic_id = u.id) as avg_rating,
                     (SELECT COUNT(*) FROM mechanic_ratings WHERE mechanic_id = u.id) as rating_count
              FROM utilisateurs u
              WHERE u.role = 'mecanicien'";
    $params = [];

    if ($user_role !== 'client') {
        $query .= " AND u.id != ?";
        $params[] = $user_id;
    }

    if ($show_available_only) {
        $query .= " AND u.is_available = 1";
    }

    if ($min_rate !== null) {
        $query .= " AND u.tarif >= ?";
        $params[] = $min_rate;
    }

    if ($max_rate !== null) {
        $query .= " AND u.tarif <= ?";
        $params[] = $max_rate;
    }

    if (!empty($adresse_filter)) {
        $query .= " AND u.adresse LIKE ?";
        $params[] = "%$adresse_filter%";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $mechanics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get min and max rates for filter range
    $rate_range = $pdo->query("SELECT MIN(tarif) as min_rate, MAX(tarif) as max_rate FROM utilisateurs WHERE role = 'mecanicien'")->fetch(PDO::FETCH_ASSOC);
    $min_rate_default = $rate_range['min_rate'] ?? 0;
    $max_rate_default = $rate_range['max_rate'] ?? 100;

    // Fetch comments for each mechanic
    $mechanic_comments = [];
    foreach ($mechanics as $mechanic) {
        $stmt = $pdo->prepare("SELECT mr.rating, mr.comment, mr.date_rated, u.nom, u.prenom
                               FROM mechanic_ratings mr
                               JOIN utilisateurs u ON mr.client_id = u.id
                               WHERE mr.mechanic_id = ?
                               ORDER BY mr.date_rated DESC");
        $stmt->execute([$mechanic['id']]);
        $mechanic_comments[$mechanic['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching mechanics: " . $e->getMessage());
    $error = 'Une erreur est survenue lors de la récupération des mécaniciens.';
}

// Check if client has already rated a mechanic to prefill the form
$client_ratings = [];
if ($user_role === 'client') {
    try {
        $stmt = $pdo->prepare("SELECT mechanic_id, rating, comment FROM mechanic_ratings WHERE client_id = ?");
        $stmt->execute([$user_id]);
        $client_ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $client_ratings_map = [];
        foreach ($client_ratings as $rating) {
            $client_ratings_map[$rating['mechanic_id']] = $rating;
        }
    } catch (PDOException $e) {
        error_log("Error fetching client ratings: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trouver des Mécaniciens - AutoParts</title>
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
        .mechanic-card {
            background: linear-gradient(145deg, #ffffff, #e6e6e6);
            border: 2px solid transparent;
            border-image: linear-gradient(135deg, #1e3a8a, #3b82f6) 1;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            z-index: 1;
            opacity: 0;
            animation: fadeIn 0.5s ease forwards;
        }
        .mechanic-card:nth-child(odd) {
            animation-delay: 0.2s;
        }
        .mechanic-card:nth-child(even) {
            animation-delay: 0.4s;
        }
        .mechanic-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border-image: linear-gradient(135deg, #e53e3e, #3b82f6) 1;
        }
        .mechanic-card img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 2px solid #1e3a8a;
            border-radius: 10px;
            margin: 0 auto;
            display: block;
        }
        .mechanic-card h3 {
            color: #1e3a8a;
            font-family: 'Roboto Mono', monospace;
            font-size: 1.25rem;
            margin: 0.5rem 0;
            text-align: center;
        }
        .mechanic-card p {
            color: #4a5568;
            font-size: 0.9rem;
            margin: 0.25rem 0;
        }
        .mechanic-card .availability {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        .mechanic-card .availability.available {
            background-color: #4ade80;
            color: white;
        }
        .mechanic-card .availability.not-available {
            background-color: #f87171;
            color: white;
        }
        .mechanic-card .view-profile-btn {
            display: block;
            text-align: center;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            padding: 0.5rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        .mechanic-card .view-profile-btn:hover {
            background: linear-gradient(135deg, #e53e3e, #3b82f6);
        }
        .filter-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .filter-section label {
            color: white;
            margin-right: 0.5rem;
        }
        .filter-section input[type="checkbox"] {
            accent-color: #3b82f6;
        }
        .filter-section input[type="number"],
        .filter-section input[type="text"] {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid #3b82f6;
            color: white;
            padding: 0.5rem;
            border-radius: 5px;
            margin-right: 1rem;
        }
        .filter-section input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .filter-section button {
            background: #3b82f6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            border: none;
            transition: background 0.3s ease;
        }
        .filter-section button:hover {
            background: #2563eb;
        }
        .filter-section .reset-btn {
            background: #e53e3e;
        }
        .filter-section .reset-btn:hover {
            background: #c53030;
        }
        .rating-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        .rating-form select,
        .rating-form textarea {
            width: 100%;
            padding: 0.5rem;
            border-radius: 5px;
            border: 1px solid #e5e7eb;
            margin-top: 0.5rem;
        }
        .rating-form button {
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            border: none;
            margin-top: 0.5rem;
            transition: background 0.3s ease;
        }
        .rating-form button:hover {
            background: #059669;
        }
        .rating-display {
            margin-top: 0.5rem;
            color: #1e3a8a;
            font-size: 0.9rem;
        }
        .rating-display .stars {
            color: #fbbf24;
        }
        .comments-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            display: none;
        }
        .comments-toggle {
            display: block;
            text-align: center;
            background: #6b7280;
            color: white;
            padding: 0.5rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .comments-toggle:hover {
            background: #4b5563;
        }
        .comment {
            background: #f9fafb;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .comment p {
            margin: 0;
            font-size: 0.85rem;
        }
        .comment .comment-meta {
            color: #6b7280;
            font-size: 0.8rem;
            margin-bottom: 0.25rem;
        }
        .comment .stars {
            color: #fbbf24;
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

    <!-- Find Mechanics Section -->
    <section class="container mx-auto py-12">
        <h2 class="text-3xl font-bold text-center mb-8 text-white">Trouver des Mécaniciens</h2>
        <?php if ($error): ?>
            <p class="text-red-300 text-center mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="text-green-300 text-center mb-4"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="flex flex-wrap gap-4 items-center justify-center">
                <div>
                    <label for="available-only">Disponible uniquement :</label>
                    <input type="checkbox" id="available-only" name="available_only" value="1" <?php echo $show_available_only ? 'checked' : ''; ?>>
                </div>
                <div>
                    <label for="min-rate">Tarif min (DT/heure) :</label>
                    <input type="number" id="min-rate" name="min_rate" value="<?php echo htmlspecialchars($min_rate ?? ''); ?>" min="0" step="0.01" placeholder="<?php echo number_format($min_rate_default, 2); ?>">
                </div>
                <div>
                    <label for="max-rate">Tarif max (DT/heure) :</label>
                    <input type="number" id="max-rate" name="max_rate" value="<?php echo htmlspecialchars($max_rate ?? ''); ?>" min="0" step="0.01" placeholder="<?php echo number_format($max_rate_default, 2); ?>">
                </div>
                <div>
                    <label for="adresse">Adresse :</label>
                    <input type="text" id="adresse" name="adresse" value="<?php echo htmlspecialchars($adresse_filter); ?>" placeholder="Ex: Tunis">
                </div>
                <button type="submit">Filtrer</button>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?available_only=0&min_rate=&max_rate=&adresse='; ?>" class="reset-btn inline-block text-center px-4 py-2">Réinitialiser</a>
            </form>
        </div>

        <?php if (empty($mechanics)): ?>
            <p class="text-white text-center">Aucun mécanicien trouvé.</p>
        <?php else: ?>
            <!-- Mechanic Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($mechanics as $mechanic): ?>
                    <div class="mechanic-card">
                        <img src="<?php echo htmlspecialchars($mechanic['profile_image'] ?? 'images/default_profile.jpg'); ?>" alt="Image de Profil">
                        <h3><?php echo htmlspecialchars($mechanic['nom'] . ' ' . $mechanic['prenom']); ?></h3>
                        <p><strong>Compétences :</strong> <?php echo htmlspecialchars($mechanic['competencies'] ?? 'N/A'); ?></p>
                        <p><strong>Tarif :</strong> <?php echo htmlspecialchars($mechanic['tarif'] ?? 'N/A'); ?> DT/heure</p>
                        <p><strong>Adresse :</strong> <?php echo htmlspecialchars($mechanic['adresse'] ?? 'N/A'); ?></p>
                        <p class="availability <?php echo ($mechanic['is_available'] ?? false) ? 'available' : 'not-available'; ?>">
                            <?php echo ($mechanic['is_available'] ?? false) ? 'Disponible' : 'Non disponible'; ?>
                        </p>
                        <!-- Display Average Rating -->
                        <p class="rating-display">
                            <strong>Évaluation :</strong> 
                            <?php 
                            $avg_rating = $mechanic['avg_rating'] ?? 0;
                            $rating_count = $mechanic['rating_count'] ?? 0;
                            echo number_format($avg_rating, 1) . '/5 ';
                            echo '<span class="stars">' . str_repeat('★', round($avg_rating)) . str_repeat('☆', 5 - round($avg_rating)) . '</span>';
                            echo " ($rating_count avis)";
                            ?>
                        </p>
                        <a href="view_mechanic_profile.php?mechanic_id=<?php echo $mechanic['id']; ?>" class="view-profile-btn">Voir le Profil</a>

                        <!-- Comments Section -->
                        <?php if (!empty($mechanic_comments[$mechanic['id']])): ?>
                            <div class="comments-toggle" onclick="toggleComments(this, 'comments-<?php echo $mechanic['id']; ?>')">Voir les commentaires</div>
                            <div class="comments-section" id="comments-<?php echo $mechanic['id']; ?>">
                                <?php foreach ($mechanic_comments[$mechanic['id']] as $comment): ?>
                                    <div class="comment">
                                        <p class="comment-meta">
                                            <strong><?php echo htmlspecialchars($comment['nom'] . ' ' . $comment['prenom']); ?></strong> 
                                            - <?php echo date('d/m/Y H:i', strtotime($comment['date_rated'])); ?>
                                            - <span class="stars"><?php echo str_repeat('★', $comment['rating']) . str_repeat('☆', 5 - $comment['rating']); ?></span>
                                        </p>
                                        <p><?php echo htmlspecialchars($comment['comment'] ?? 'Aucun commentaire.'); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-sm text-gray-500 mt-2">Aucun commentaire pour ce mécanicien.</p>
                        <?php endif; ?>

                        <!-- Rating Form for Clients -->
                        <?php if ($user_role === 'client'): ?>
                            <?php
                            $existing_rating = isset($client_ratings_map[$mechanic['id']]) ? $client_ratings_map[$mechanic['id']] : null;
                            $prefilled_rating = $existing_rating ? $existing_rating['rating'] : '';
                            $prefilled_comment = $existing_rating ? $existing_rating['comment'] : '';
                            ?>
                            <form method="POST" class="rating-form">
                                <input type="hidden" name="mechanic_id" value="<?php echo $mechanic['id']; ?>">
                                <div>
                                    <label for="rating-<?php echo $mechanic['id']; ?>" class="block text-sm">Votre évaluation (1-5) :</label>
                                    <select id="rating-<?php echo $mechanic['id']; ?>" name="rating" required>
                                        <option value="">Sélectionnez...</option>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $prefilled_rating == $i ? 'selected' : ''; ?>>
                                                <?php echo $i; ?> étoile<?php echo $i > 1 ? 's' : ''; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="comment-<?php echo $mechanic['id']; ?>" class="block text-sm mt-2">Commentaire (optionnel) :</label>
                                    <textarea id="comment-<?php echo $mechanic['id']; ?>" name="comment" rows="2" placeholder="Votre commentaire..."><?php echo htmlspecialchars($prefilled_comment); ?></textarea>
                                </div>
                                <button type="submit" name="submit_rating"><?php echo $existing_rating ? 'Mettre à jour l\'évaluation' : 'Soumettre l\'évaluation'; ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>

    <!-- JavaScript for Toggle Comments -->
    <script>
        function toggleComments(button, sectionId) {
            const section = document.getElementById(sectionId);
            if (section.style.display === 'block') {
                section.style.display = 'none';
                button.textContent = 'Voir les commentaires';
            } else {
                section.style.display = 'block';
                button.textContent = 'Masquer les commentaires';
            }
        }
    </script>
</body>
</html>