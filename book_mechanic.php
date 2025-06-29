<?php
session_start();
include_once 'config_db.php';

// Check if user is logged in and a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: login.php');
    exit();
}

$client_id = $_SESSION['user_id'];
$mechanic_id = isset($_GET['mechanic_id']) ? intval($_GET['mechanic_id']) : null;
$error = '';
$success = '';
$mechanic = null;
$selected_date = isset($_POST['selected_date']) ? $_POST['selected_date'] : date('Y-m-d');
$available_slots = [];

// Fetch mechanic details
if ($mechanic_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ? AND role = 'mecanicien'");
        $stmt->execute([$mechanic_id]);
        $mechanic = $stmt->fetch();
        if (!$mechanic) {
            $error = 'Mechanic not found.';
        }
    } catch (PDOException $e) {
        error_log("Error fetching mechanic: " . $e->getMessage());
        $error = 'An error occurred while fetching mechanic details.';
    }
}

// Fetch available time slots for the selected date
if ($mechanic && !$error) {
    if (!$mechanic['is_available']) {
        $error = 'This mechanic is not available for bookings.';
    } else {
        $working_hours = range(9, 16); // 9:00 AM to 4:00 PM
        $booked_slots = [];

        try {
            $stmt = $pdo->prepare("SELECT start_date, end_date FROM orders WHERE mechanic_id = ? AND DATE(start_date) = ? AND status = 'accepted'");
            $stmt->execute([$mechanic_id, $selected_date]);
            $bookings = $stmt->fetchAll();

            foreach ($bookings as $booking) {
                $start_hour = (int)date('H', strtotime($booking['start_date']));
                $end_hour = (int)date('H', strtotime($booking['end_date']));
                for ($hour = $start_hour; $hour < $end_hour; $hour++) {
                    $booked_slots[] = $hour;
                }
            }

            foreach ($working_hours as $hour) {
                if (!in_array($hour, $booked_slots)) {
                    $time = sprintf("%02d:00", $hour);
                    $available_slots[] = $time;
                }
            }
        } catch (PDOException $e) {
            error_log("Error fetching bookings: " . $e->getMessage());
            $error = 'An error occurred while fetching available slots.';
        }
    }
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book']) && !$error) {
    $selected_date = $_POST['selected_date'];
    $selected_time = $_POST['selected_time'];
    $description = trim(strip_tags($_POST['description']));

    if (empty($selected_date) || empty($selected_time) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } else {
        $start_datetime = "$selected_date $selected_time:00";
        $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime . ' +1 hour'));

        try {
            $stmt = $pdo->prepare("INSERT INTO orders (mechanic_id, client_id, description, status, start_date, end_date) VALUES (?, ?, ?, 'pending', ?, ?)");
            $stmt->execute([$mechanic_id, $client_id, $description, $start_datetime, $end_datetime]);
            $success = 'Booking request submitted successfully! The mechanic will review your request.';
        } catch (PDOException $e) {
            error_log("Error submitting booking: " . $e->getMessage());
            $error = 'An error occurred while submitting your booking.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Mechanic - AutoParts</title>
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
        h2, h3 {
            font-weight: 700;
            color: #2d3748;
            transition: transform 0.3s ease, color 0.3s ease;
            background: #ffffff;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 2px solid #e5e7eb;
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
        .mechanic-details, .time-slots, .form-group {
            background: #ffffff;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            border: 2px solid #e5e7eb;
        }
        .mechanic-details p, .time-slots p, .form-group label {
            margin: 0.75rem 0;
            font-size: 1rem;
            color: #4b5563;
            transition: color 0.3s ease;
            background: #f9fafb;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .mechanic-details p:hover, .time-slots p:hover, .form-group label:hover {
            color: #2d3748;
        }
        .mechanic-details p strong {
            color: #2d3748;
            transition: color 0.3s ease;
        }
        .mechanic-details p strong:hover {
            color: #000000;
        }
        .time-slots {
            border: 3px dashed #000000;
            border-top: 5px solid #4b5563;
            border-bottom: 5px solid #6b7280;
        }
        .time-slot {
            background: #f9fafb;
            border: 1px solid #000000;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            cursor: pointer;
            transition: transform 0.2s ease, background-color 0.3s ease;
            display: inline-block;
        }
        .time-slot:hover {
            transform: scale(1.05);
            background-color: #e5e7eb;
        }
        .time-slot.selected {
            background: #4ade80;
            color: #2d3748;
            border-color: #065f46;
        }
        .input-field {
            background-color: #f9fafb;
            border: 2px solid #e5e7eb;
            color: #2d3748;
            border-radius: 0.5rem;
            padding: 0.75rem;
            width: 100%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .input-field:focus {
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
            outline: none;
        }
        button {
            background: #4ade80;
            border: 2px solid #000000;
            color: #2d3748;
            font-weight: 500;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            transition: background-color 0.3s ease, transform 0.3s ease;
            width: 100%;
        }
        button:hover {
            background: #065f46;
            color: #ffffff;
            transform: scale(1.05);
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
        <h2>Book a Mechanic</h2>

        <?php if ($error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success-message"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <?php if ($mechanic): ?>
            <div class="mechanic-details">
                <h3>Mechanic Details</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($mechanic['nom'] . ' ' . $mechanic['prenom']); ?></p>
                <p><strong>Competencies:</strong> <?php echo htmlspecialchars($mechanic['competencies'] ?? 'N/A'); ?></p>
                <p><strong>Hourly Rate:</strong> <?php echo htmlspecialchars($mechanic['tarif'] ?? 'N/A'); ?> DT/hour</p>
            </div>

            <form method="POST" class="space-y-6">
                <div class="form-group">
                    <label for="selected_date">Select Date</label>
                    <input type="date" id="selected_date" name="selected_date" required
                           value="<?php echo htmlspecialchars($selected_date); ?>"
                           min="<?php echo date('Y-m-d'); ?>"
                           class="input-field"
                           onchange="this.form.submit()">
                </div>

                <?php if (!empty($available_slots)): ?>
                    <div class="time-slots">
                        <label>Select Time Slot</label>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($available_slots as $slot): ?>
                                <div class="time-slot <?php echo isset($_POST['selected_time']) && $_POST['selected_time'] === $slot ? 'selected' : ''; ?>" onclick="selectTimeSlot('<?php echo $slot; ?>')">
                                    <?php echo $slot; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="selected_time" name="selected_time" value="<?php echo isset($_POST['selected_time']) ? htmlspecialchars($_POST['selected_time']) : ''; ?>">
                    </div>
                <?php else: ?>
                    <p class="text-gray-600">No available time slots for this date.</p>
                <?php endif; ?>

                <div class="form-group">
                    <label for="description">Work Description</label>
                    <textarea id="description" name="description" required
                              class="input-field h-32 resize-none"
                              placeholder="Describe the work needed (e.g., oil change, brake repair)"></textarea>
                </div>

                <button type="submit" name="book">Submit Booking Request</button>
            </form>
        <?php else: ?>
            <p class="text-center text-gray-600">Please select a mechanic to book.</p>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        function selectTimeSlot(slot) {
            document.querySelectorAll('.time-slot').forEach(slotEl => slotEl.classList.remove('selected'));
            event.target.classList.add('selected');
            document.getElementById('selected_time').value = slot;
        }
    </script>
</body>
</html>