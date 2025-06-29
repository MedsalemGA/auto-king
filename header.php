<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'config_db.php';

$userLoggedIn = isset($_SESSION['user_id']) ? true : false;
$userName = $userLoggedIn ? $_SESSION['user_name'] : '';
$userRole = $userLoggedIn ? $_SESSION['user_role'] : null;

// Check for pending orders for mechanics
$pendingCount = 0;
$pendingRequester = '';
if ($userLoggedIn && $userRole === 'mecanicien') {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count, GROUP_CONCAT(DISTINCT u.nom SEPARATOR ', ') as requesters FROM orders o JOIN utilisateurs u ON o.client_id = u.id WHERE o.mechanic_id = ? AND o.status = 'pending'");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        $pendingCount = $result['count'];
        $pendingRequester = $pendingCount > 0 ? $result['requesters'] : '';
    } catch (PDOException $e) {
        error_log("Error checking pending orders: " . $e->getMessage());
    }
}
?>

<nav class="bg-gray-800 text-white p-4 sticky top-0 z-20 shadow-lg transition-all duration-300" id="header">
    <div class="container mx-auto flex justify-between items-center">
        <!-- Logo -->
        <a href="index.php" class="text-2xl font-bold flex items-center">
            <span>AutoParts</span>
        </a>
        <!-- Desktop Menu -->
        <div class="hidden md:flex items-center space-x-6">
            <a href="index.php" class="hover:text-gray-300 transition duration-300">Home</a>

            <?php
            $restrictAccess = !$userLoggedIn || $userRole === 'mecanicien';
            $padlock = $restrictAccess ? '🔒 ' : '';
            $searchPartsLink = $restrictAccess ? "javascript:void(0)" : "search_parts.php";
            $findmechanic = $restrictAccess ? "javascript:void(0)" : "find_mechanic.php";
            ?>

            <a href="<?php echo $searchPartsLink; ?>"
               class="hover:text-gray-300 transition duration-300 <?php echo $restrictAccess ? 'restrict-link' : ''; ?>"
               <?php echo $restrictAccess ? 'onclick="showPopup()"' : ''; ?>>
                <?php echo $padlock; ?>Search Parts
            </a>

            <a href="<?php echo $findmechanic; ?>"
               class="hover:text-gray-300 transition duration-300 <?php echo $restrictAccess ? 'restrict-link' : ''; ?>"
               <?php echo $restrictAccess ? 'onclick="showPopup()"' : ''; ?>>
                <?php echo $padlock; ?>Find Mechanic
            </a>

            <!-- Search Icon -->
            <a href="<?php echo $searchPartsLink; ?>"
               class="hover:text-gray-300 transition duration-300 <?php echo $restrictAccess ? 'restrict-link' : ''; ?>"
               title="Search"
               <?php echo $restrictAccess ? 'onclick="showPopup()"' : ''; ?>>
                <i class="fas fa-search"></i>
            </a>

            <?php if ($userLoggedIn): ?>
                <!-- User Dropdown -->
                <div class="relative group">
                    <button class="flex items-center hover:text-gray-300 transition duration-300">
                        <span>Welcome, <?php echo htmlspecialchars($userName); ?></span>
                        <i class="fas fa-chevron-down ml-2"></i>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 bg-white text-gray-800 rounded-md shadow-lg opacity-0 group-hover:opacity-100 transform group-hover:scale-100 scale-95 transition-all duration-300 hidden group-hover:block">
                        <a href="profile.php" class="block px-4 py-2 hover:bg-gray-100">Profile</a>
                        <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100">Orders</a>
                        <?php if ($userRole === 'mecanicien'): ?>
                            <a href="historiqueprest.php" class="block px-4 py-2 hover:bg-gray-100">Service History</a>
                        <?php endif; ?>
                        <a href="logout.php" class="block px-4 py-2 hover:bg-gray-100">Logout</a>
                    </div>
                </div>

                <?php if ($userRole === 'mecanicien' && $pendingCount > 0): ?>
                    <!-- Notification Icon -->
                    <div class="relative group">
                        <a href="orders.php" class="hover:text-gray-300 transition duration-300">
                            <i class="fas fa-bell"></i>
                            <?php if ($pendingCount > 0): ?>
                                <span class="absolute top-0 right-0 inline-block w-2 h-2 bg-red-500 rounded-full"></span>
                            <?php endif; ?>
                        </a>
                        <div class="absolute right-0 mt-2 w-48 bg-white text-gray-800 rounded-md shadow-lg opacity-0 group-hover:opacity-100 transform group-hover:scale-100 scale-95 transition-all duration-300 hidden group-hover:block">
                            <p class="px-4 py-2">Nouvelle demande de <?php echo htmlspecialchars($pendingRequester); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <a href="login.php" class="hover:text-gray-300 transition duration-300">Login</a>
                <a href="signup.php" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-md transition duration-300">Signup</a>
            <?php endif; ?>
        </div>
        <!-- Mobile Menu Toggle -->
        <button id="menu-toggle" class="md:hidden focus:outline-none">
            <i class="fas fa-bars text-2xl"></i>
        </button>
    </div>
    <!-- Mobile Menu -->
    <div id="mobile-menu" class="md:hidden bg-gray-700 p-4 transform -translate-y-full opacity-0 transition-all duration-300 absolute w-full left-0 top-16">
        <a href="index.php" class="block py-2 hover:text-gray-300">Home</a>

        <a href="<?php echo $searchPartsLink; ?>"
           class="block py-2 hover:text-gray-300 <?php echo $restrictAccess ? 'restrict-link' : ''; ?>"
           <?php echo $restrictAccess ? 'onclick="showPopup()"' : ''; ?>>
            <?php echo $padlock; ?>Search Parts
        </a>
        <a href="<?php echo $findmechanic; ?>"
           class="block py-2 hover:text-gray-300 <?php echo $restrictAccess ? 'restrict-link' : ''; ?>"
           <?php echo $restrictAccess ? 'onclick="showPopup()"' : ''; ?>>
            <?php echo $padlock; ?>Find Mechanic
        </a>

        <?php if ($userLoggedIn): ?>
            <a href="profile.php" class="block py-2 hover:text-gray-300">Profile</a>
            <a href="orders.php" class="block py-2 hover:text-gray-300">Orders</a>
            <?php if ($userRole === 'mecanicien'): ?>
                <a href="historiqueprest.php" class="block py-2 hover:text-gray-300">Service History</a>
            <?php endif; ?>
            <a href="logout.php" class="block py-2 hover:text-gray-300">Logout</a>
            <?php if ($userRole === 'mecanicien' && $pendingCount > 0): ?>
                <a href="orders.php" class="block py-2 hover:text-gray-300 relative">
                    <i class="fas fa-bell"></i>
                    <?php if ($pendingCount > 0): ?>
                        <span class="absolute top-0 right-0 inline-block w-2 h-2 bg-red-500 rounded-full"></span>
                        <span class="ml-2">Nouvelle demande de <?php echo htmlspecialchars($pendingRequester); ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php else: ?>
            <a href="login.php" class="block py-2 hover:text-gray-300">Login</a>
            <a href="signup.php" class="block py-2 hover:text-gray-300">Signup</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Popup for Restricted Access -->
<div id="popup-overlay" class="fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 flex justify-center items-center z-50 hidden">
    <div class="bg-gray-800 text-white p-6 rounded-lg shadow-lg max-w-sm w-full text-center">
        <p class="mb-4">Tu dois être connecté comme client.</p>
        <p class="mb-4">Pas encore inscrit ? <a href="signup.php" class="underline hover:text-gray-300">Crée un compte client ici</a>.</p>
        <button onclick="hidePopup()" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-md">Fermer</button>
    </div>
</div>

<!-- Font Awesome for Icons -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<!-- JavaScript for Mobile Menu, Scroll Effect, and Popup -->
<script>
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    menuToggle.addEventListener('click', () => {
        mobileMenu.classList.toggle('-translate-y-full');
        mobileMenu.classList.toggle('opacity-0');
        mobileMenu.classList.toggle('translate-y-0');
        mobileMenu.classList.toggle('opacity-100');
    });

    window.addEventListener('scroll', () => {
        const header = document.getElementById('header');
        if (window.scrollY > 50) {
            header.classList.add('bg-opacity-90');
        } else {
            header.classList.remove('bg-opacity-90');
        }
    });

    const popupOverlay = document.getElementById('popup-overlay');
    function showPopup() {
        popupOverlay.classList.remove('hidden');
    }
    function hidePopup() {
        popupOverlay.classList.add('hidden');
    }
</script>