<?php
session_start(); // Ensure session_start() is called here
include_once 'config_db.php'; // Use include_once to prevent multiple inclusions
$userLoggedIn = isset($_SESSION['user_id']) ? true : false;
$userName = $userLoggedIn ? $_SESSION['user_name'] : '';
$userRole = $userLoggedIn ? $_SESSION['user_role'] : null;
// Fetch the number of users from the database
try {
    $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM utilisateurs");
    $user_count = $stmt->fetch()['user_count'];
} catch (PDOException $e) {
    error_log("Error fetching user count: " . $e->getMessage());
    $user_count = 0; // Fallback in case of error
}

// Fetch the number of mechanics (role = 'mecanicien')
try {
    $stmt = $pdo->query("SELECT COUNT(*) as mechanic_count FROM utilisateurs WHERE role = 'mecanicien'");
    $mechanic_count = $stmt->fetch()['mechanic_count'];
} catch (PDOException $e) {
    error_log("Error fetching mechanic count: " . $e->getMessage());
    $mechanic_count = 0; // Fallback in case of error
}

// Fetch offers for the slider from the database
try {
    $stmt = $pdo->query("SELECT image_path, title, description FROM offers WHERE is_active = TRUE");
    $slider_images = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching offers: " . $e->getMessage());
    $slider_images = []; // Fallback in case of error
}

// Static data for creators with photos (hardcoded for static upload)
$creators = [
    ['name' => 'Gafsi Mohamed Salem', 'role' => 'Lead Developer', 'photo' => '/images/salem.png'],
    ['name' => 'Mohamed Chebil Mahjoub', 'role' => 'Backend developer', 'photo' => '/images/jane_smith.jpg'],
    ['name' => 'Fadi Alhwishet', 'role' => 'Front-End Developer', 'photo' => '/images/fedi.jpg'],
    ['name' => 'Abdelkarim Sassi', 'role' => 'Manager', 'photo' => '/images/kraiem.jpg'],
    ['name' => 'Dhia Louati', 'role' => 'Testeur', 'photo' => '/images/dhia_louati.jpg'],
    ['name' => 'Mohamed Amin Zayeni', 'role' => 'UI/UX DESIGNER', 'photo' => '/images/mohamed_amin_zayeni.jpg']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoParts & Mechanics - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
        }
        .slider-container {
            position: relative;
            height: 24rem; /* h-96 equivalent */
            overflow: hidden;
        }
        .slider-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1s ease-in-out;
        }
        .slider-image.active {
            opacity: 1;
        }
        .slider-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 0.5rem 1rem;
            cursor: pointer;
            user-select: none;
        }
        .slider-nav-prev {
            left: 1rem;
        }
        .slider-nav-next {
            right: 1rem;
        }
        .user-count-container {
            background: linear-gradient(135deg, #3b82f6, #1e3a8a);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        .user-count {
            font-size: 3rem;
            font-weight: bold;
            transition: all 1s ease-in-out;
        }
        .founders-container {
            display: flex;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: #3b82f6 #e5e7eb;
        }
        .founders-container::-webkit-scrollbar {
            height: 8px;
        }
        .founders-container::-webkit-scrollbar-thumb {
            background-color: #3b82f6;
            border-radius: 4px;
        }
        .founders-container::-webkit-scrollbar-track {
            background: #e5e7eb;
        }
        .founder-card {
            flex: 0 0 auto;
            scroll-snap-align: start;
            width: 150px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .founder-card:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        .founder-card img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border: 3px solid #3b82f6;
            margin: 0 auto;
        }
        /* Cookie Popup Styles */
        .cookie-popup {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            padding: 1rem;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
            flex-direction: column;
            gap: 1rem;
        }
        .cookie-popup.active {
            display: flex;
        }
        .cookie-message {
            font-size: 0.9rem;
            text-align: center;
        }
        .cookie-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        .cookie-btn {
            padding: 0.5rem 1rem;
            background-color: #ffffff;
            color: #1e3a8a;
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .cookie-btn:hover {
            background-color: #e53e3e;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Include Header -->
    <?php include 'header.php'; ?>

    <!-- Hero Section with Slider -->
    <section class="slider-container">
        <?php if (empty($slider_images)): ?>
            <!-- Fallback if no images -->
            <div class="slider-image active" style="background-image: url('/images/pexels-mikebirdy-120049.jpg');"></div>
        <?php else: ?>
            <?php foreach ($slider_images as $index => $image): ?>
                <div class="slider-image <?php echo $index === 0 ? 'active' : ''; ?>" 
                     style="background-image: url('<?php echo htmlspecialchars($image['image_path']); ?>');">
                    <div class="absolute inset-0 flex items-center justify-center text-center text-white bg-black bg-opacity-50">
                        <div class="p-8">
                            <h1 class="text-4xl md:text-5xl font-bold mb-4"><?php echo htmlspecialchars($image['title']); ?></h1>
                            <p class="text-lg mb-6"><?php echo htmlspecialchars($image['description']); ?></p>
                            <?php if ($userRole !== 'mecanicien' ):
                            
                                 ?>
                                 <a href="search_parts.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded">Shop Now</a>
                                 <a href="find_mechanic.php" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded ml-4">Find a Mechanic</a>
                            <?php endif; ?>
                            
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <!-- Navigation Buttons -->
        <div class="slider-nav slider-nav-prev">←</div>
        <div class="slider-nav slider-nav-next">→</div>
    </section>

    <!-- Features Section -->
    <section class="container mx-auto py-12 features-section">
        <h2 class="text-3xl font-bold text-center mb-8 text-white">Why Choose Us?</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="feature-card bg-white p-6 rounded-lg shadow-md text-center">
                <div class="feature-icon search-icon"></div>
                <h3 class="text-xl font-semibold mb-2 text-blue-900">Search Parts</h3>
                <p class="text-gray-600">Find vehicle parts by reference, brand, or model with advanced filters.</p>
            </div>
            <div class="feature-card bg-white p-6 rounded-lg shadow-md text-center">
                <div class="feature-icon mechanic-icon"></div>
                <h3 class="text-xl font-semibold mb-2 text-blue-900">Book Mechanics</h3>
                <p class="text-gray-600">Locate skilled mechanics by location and specialization, book appointments easily.</p>
            </div>
            <div class="feature-card bg-white p-6 rounded-lg shadow-md text-center">
                <div class="feature-icon order-icon"></div>
                <h3 class="text-xl font-semibold mb-2 text-blue-900">Manage Orders</h3>
                <p class="text-gray-600">Track your orders in real-time and handle returns effortlessly.</p>
            </div>
        </div>
    </section>

    <style>
        .features-section {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.1), rgba(59, 130, 246, 0.1));
            position: relative;
            border-radius: 1rem;
            padding: 3rem 1rem;
            margin: 0 1rem;
        }
        .features-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50" opacity="0.05"><circle cx="25" cy="25" r="15" fill="none" stroke="#e53e3e" stroke-width="1"/><path d="M25 10a15 15 0 0 1 0 30 15 15 0 0 1 0-30m0-5a20 20 0 0 0 0 40 20 20 0 0 0 0-40" fill="none" stroke="#1e3a8a" stroke-width="1"/></svg>') repeat;
            z-index: 0;
        }
        .feature-card {
            background: linear-gradient(145deg, #ffffff, #e6e6e6);
            border: 2px solid transparent;
            border-image: linear-gradient(135deg, #1e3a8a, #3b82f6) 1;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            z-index: 1;
            opacity: 0;
            animation: fadeIn 0.5s ease forwards;
        }
        .feature-card:nth-child(1) {
            animation-delay: 0.1s;
        }
        .feature-card:nth-child(2) {
            animation-delay: 0.3s;
        }
        .feature-card:nth-child(3) {
            animation-delay: 0.5s;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border-image: linear-gradient(135deg, #e53e3e, #3b82f6) 1;
        }
        .feature-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 1rem;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }
        .search-icon {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>');
        }
        .mechanic-icon {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>');
        }
        .order-icon {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>');
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

    <!-- Community and Creators Section -->
    <section class="container mx-auto py-12 bg-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Number of Users -->
            <div class="user-count-container text-center relative">
                <div class="flex justify-center items-center mb-4">
                    <i class="fas fa-users text-4xl mr-3"></i>
                    <h3 class="text-2xl font-semibold">Our Community</h3>
                </div>
                <p id="user-count" class="user-count">0</p>
                <p class="text-lg mt-2">Users trust AutoParts for their vehicle needs.</p>
                <div class="flex justify-center space-x-6 mt-4">
                    <div class="flex items-center">
                        <i class="fas fa-wrench text-2xl mr-2"></i>
                        <div>
                            <p class="text-sm">Mechanics</p>
                            <p class="font-bold"><?php echo htmlspecialchars($mechanic_count); ?></p>
                        </div>
                    </div>
                </div>
                <div class="absolute top-0 right-0 -mt-4 -mr-4">
                    <svg width="80" height="80" viewBox="0 0 100 100" class="opacity-20">
                        <circle cx="50" cy="50" r="40" fill="none" stroke="#ffffff" stroke-width="10" stroke-dasharray="20,10"/>
                    </svg>
                </div>
            </div>
            <!-- Founders of the App -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-xl font-semibold mb-6 text-center">Meet the Founders</h3>
                <div class="founders-container space-x-4 py-4">
                    <?php foreach ($creators as $creator): ?>
                        <div class="founder-card">
                            <img src="<?php echo htmlspecialchars($creator['photo']); ?>" alt="<?php echo htmlspecialchars($creator['name']); ?>" class="rounded-full">
                            <p class="font-semibold text-gray-800 mt-3 text-sm"><?php echo htmlspecialchars($creator['name']); ?></p>
                            <p class="text-gray-600 text-xs"><?php echo htmlspecialchars($creator['role']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>

    <!-- Cookie Popup -->
    <div id="cookie-popup" class="cookie-popup">
        <div class="cookie-message">
            We use cookies to enhance your experience and analyze site usage. By continuing, you agree to our <a href="/privacy-policy.php" class="underline">Privacy Policy</a>.
        </div>
        <div class="cookie-buttons">
            <button id="accept-cookies" class="cookie-btn">Accept</button>
            <button id="decline-cookies" class="cookie-btn">Decline</button>
        </div>
    </div>

    <!-- JavaScript for Slider, User Count Animation, and Cookie Popup -->
    <script>
        // Slider Functionality
        const sliderImages = document.querySelectorAll('.slider-image');
        const prevButton = document.querySelector('.slider-nav-prev');
        const nextButton = document.querySelector('.slider-nav-next');
        let currentIndex = 0;

        function showSlide(index) {
            sliderImages.forEach((img, i) => {
                img.classList.toggle('active', i === index);
            });
        }

        function nextSlide() {
            currentIndex = (currentIndex + 1) % sliderImages.length;
            showSlide(currentIndex);
        }

        function prevSlide() {
            currentIndex = (currentIndex - 1 + sliderImages.length) % sliderImages.length;
            showSlide(currentIndex);
        }

        // Auto-slide every 5 seconds
        let autoSlide = setInterval(nextSlide, 5000);

        // Manual navigation
        nextButton.addEventListener('click', () => {
            clearInterval(autoSlide);
            nextSlide();
            autoSlide = setInterval(nextSlide, 5000);
        });

        prevButton.addEventListener('click', () => {
            clearInterval(autoSlide);
            prevSlide();
            autoSlide = setInterval(nextSlide, 5000);
        });

        // User Count Animation
        const userCountElement = document.getElementById('user-count');
        const targetCount = <?php echo $user_count; ?>;
        let currentCount = 0;

        function animateCount() {
            if (currentCount < targetCount) {
                currentCount += Math.ceil(targetCount / 50); // Increment in steps
                if (currentCount > targetCount) currentCount = targetCount;
                userCountElement.textContent = currentCount;
                setTimeout(animateCount, 20);
            }
        }

        // Start animation when the section is in view
        const userCountSection = document.querySelector('.user-count-container');
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                animateCount();
                observer.disconnect();
            }
        }, { threshold: 0.5 });

        observer.observe(userCountSection);

        // Cookie Popup Functionality
        const cookiePopup = document.getElementById('cookie-popup');
        const acceptCookies = document.getElementById('accept-cookies');
        const declineCookies = document.getElementById('decline-cookies');

        // Check if cookie exists
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null;
        }

        // Set cookie
        function setCookie(name, value, days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = `${name}=${value}; expires=${date.toUTCString}; path=/`;
        }

        // Show popup if no cookie consent
        if (!getCookie('cookieConsent')) {
            cookiePopup.classList.add('active');
        }

        // Accept cookies
        acceptCookies.addEventListener('click', () => {
            setCookie('cookieConsent', 'true', 365); // Set for 1 year
            cookiePopup.classList.remove('active');
        });

        // Decline cookies
        declineCookies.addEventListener('click', () => {
            setCookie('cookieConsent', 'false', 365); // Set for 1 year
            cookiePopup.classList.remove('active');
            // Optionally redirect to a no-cookies page or disable tracking
            // window.location.href = '/no-cookies.php';
        });
    </script>
</body>
</html>