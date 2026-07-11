<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Landing Page - Best of Both (Typewriter + Better Colors)
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';

// If logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('employee/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dairy Farm Management System - Smart Farming Solutions</title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
            background: #a8c9a0;
        }
        
        /* ===== HEADER ===== */
        .landing-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            padding: 1.5rem 4rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            background: transparent;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .logo-section img {
            height: 45px;
            width: auto;
        }
        
        .logo-text {
            font-size: 1.4rem;
            font-weight: 400;
            color: #2d5a45;
        }
        
        .logo-text .accent {
            color: #6b9080;
            font-style: italic;
        }
        
        .nav-links {
            display: flex;
            gap: 2.5rem;
            list-style: none;
            align-items: center;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #2d5a45;
            font-weight: 400;
            font-size: 0.95rem;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: #1a3d2e;
            font-weight: bold;
        }
        
        .header-btn {
            padding: 0.6rem 1.5rem;
            background: transparent;
            border: 1.5px solid #6b9080;
            color: #2d5a45;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .header-btn:hover {
            background: #6b9080;
            color: white;
        }
        
        /* ===== HERO SECTION WITH CURVED SHAPE ===== */
        .hero-wrapper {
            background: #a8c9a0;
            min-height: 100vh;
            padding-top: 80px;
        }
        
        .hero-section {
            background: #f5f0e8;
            border-radius: 0 0 50% 50% / 0 0 10% 10%;
            padding: 8rem 4rem 12rem;
            position: relative;
            overflow: hidden;
            background-image: url('assets/images/hero-farm.png');
            background-size: cover;
            background-position: center bottom;
            background-repeat: no-repeat;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, rgba(245,240,232,0.85) 0%, rgba(245,240,232,0.6) 50%, rgba(245,240,232,0.3) 100%);
            z-index: 1;
        }
        
        .hero-container {
            max-width: 1400px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 10;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 400;
            color: #2d3e2e;
            line-height: 1.3;
            margin-bottom: 0.5rem;
            margin-top: 0;
            text-shadow: 1px 1px 3px rgba(255,255,255,0.8);
        }
        
        .hero-title .highlight {
            color: #6b9080;
            display: block;
        }
        
        .hero-subtitle {
            font-size: 2rem;
            font-weight: 400;
            color: #2d5a45;
            margin-bottom: 1.5rem;
            min-height: 70px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.8);
        }
        
        .typewriter {
            border-right: 3px solid #2d5a45;
            animation: blink 0.7s step-end infinite;
        }
        
        @keyframes blink {
            50% { border-color: transparent; }
        }
        
        .hero-description {
            font-size: 1.1rem;
            color: #3a3a3a;
            margin-bottom: 2.5rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.8);
        }
        
        .hero-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            margin-bottom: 0;
        }
        
        .btn-primary {
            padding: 0.9rem 2.2rem;
            background: #4a7c5d;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary:hover {
            background: #3a6349;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }
        
        .btn-outline {
            padding: 0.9rem 2.2rem;
            background: transparent;
            color: #4a7c5d;
            border: 1.5px solid #4a7c5d;
            border-radius: 8px;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-outline:hover {
            background: #88988e;
            border-color: #3a6349;
            color: #3a6349;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        /* ===== ABOUT DFMS ===== */
        .about-section {
            background: #ffffff;
            padding: 5rem 2rem;
        }

        .about-container {
            max-width: 1200px;
            margin: auto;
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        /* TEXT */
        .about-text h2 {
            font-size: 2.3rem;
            color: #2d3e2e;
            margin-bottom: 1.2rem;
        }

        .about-text p {
            font-size: 1rem;
            line-height: 1.7;
            color: #4a4a4a;
            margin-bottom: 1rem;
        }

        /* STATS */
        .about-stats {
            display: flex;
            gap: 1.5rem;
            justify-content: space-between;
        }

        .stat-card {
            flex: 1;
            background: #f7f7f7;
            border-radius: 14px;
            padding: 1.8rem 1rem;
            text-align: center;
            transition: transform 0.25s ease;
        }

        .stat-card:hover {
            transform: translateY(-6px);
        }

        .stat-number {
            font-size: 2.6rem;
            font-weight: 700;
            color: #2d3e2e;
            display: block;
        }

        .stat-label {
            font-size: 0.95rem;
            color: #6b9080;
            margin-top: 0.4rem;
            display: block;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .about-container {
                grid-template-columns: 1fr;
            }

            .about-stats {
                margin-top: 2rem;
            }
        }

        @media (max-width: 600px) {
            .about-stats {
                flex-direction: column;
            }
        }

        
        /* ===== SERVICES SECTION ===== */
       .services-section {
            /* Increased top and bottom padding for better breathing room */
            padding: 80px 20px; 
            background: #f5f0e8;
        }
        .services-container {
            /* Ensures content stays centered and doesn't hit the screen edges */
            max-width: 1200px; 
            margin: 0 auto;
            /* Added padding-bottom to ensure space before the next section */
            padding-bottom: 60px; 
        }
        
        .services-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .services-header h2 {
            font-size: 2.5rem;
            color: #2d3e2e;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .services-header p {
            color: #5a5a5a;
            font-size: 1.1rem;
        }
        
        .services-grid {
            display: grid;
            /* responsive: 3 columns on desktop, shifts to 1 on mobile */
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2.5rem;
        }
        
        .service-card {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            transition: all 0.3s;
            border: 1px solid #e8e8e8;
        }
        
        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #6b9080;
        }
        
        .service-icon {
            width: 65px;
            height: 65px;
            background: #e8f4e8;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
        }
        
        .service-card h3 {
            font-size: 1.3rem;
            color: #2d3e2e;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .service-card p {
            color: #5a5a5a;
            line-height: 1.7;
            font-size: 0.95rem;
        }
        
        .service-link {
            display: inline-block;
            margin-top: 1rem;
            color: #4a7c5d;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .service-link:hover {
            color: #3a6349;
        }
        
        /* ===== CTA SECTION ===== */
        .cta-section {
            background: #4a7c5d;
            padding: 5rem 4rem;
            text-align: center;
            color: white;
        }
        
        .cta-content h2 {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .cta-content p {
            font-size: 1.1rem;
            margin-bottom: 2.5rem;
            opacity: 0.95;
        }
        
        .cta-btn {
            padding: 1rem 2.5rem;
            background: white;
            color: #4a7c5d;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.05rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .cta-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        
        /* ===== FOOTER ===== */
        .footer {
            background: #2d3e2e;
            color: white;
            padding: 4rem 4rem 2rem;
        }
        
        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 4rem;
            margin-bottom: 3rem;
        }
        /* Logo Styling */
        .footer-logo img {
            max-width: 400px; /* Adjust this value to fit your logo's proportions */
            height: 300px;
            margin-bottom: 1.5rem;
            display: block;
        }
        .footer-section h3 {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .footer-section p {
            color: rgba(255,255,255,0.7);
            line-height: 1.8;
            font-size: 0.95rem;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 0.9rem;
        }
        
        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s ease; /* Smooth transition for color and weight */
            font-size: 0.95rem;
            display: inline-block; /* Helps with the bold transition layout */
        }

        .footer-links a:hover {
            color: #ffffff;      /* Changes color to pure white */
            font-weight: 700;   /* Makes text bold */
            transform: translateX(5px); /* Optional: slight nudge to the right for flair */
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .commitment-container,
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-title {
                font-size: 2.8rem;
            }
            
            .hero-subtitle {
                font-size: 1.6rem;
            }
        }
        
        @media (max-width: 768px) {
            .landing-header {
                padding: 1rem 2rem;
            }
            
            .nav-links {
                display: none;
            }
            
            .hero-section,
            .commitment-section,
            .services-section,
            .cta-section,
            .footer {
                padding: 2rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header class="landing-header">
        <div class="logo-section">
            <!-- <img src="assets/images/logo2.png" alt="DFMS Logo"> -->
            <div class="logo-text">Dairy<span class="accent">Farm</span></div>
        </div>
        <nav>
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#about">About Us</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="login.php" class="header-btn">Login</a></li>
            </ul>
        </nav>
    </header>

    <!-- HERO WRAPPER -->
    <div class="hero-wrapper">
        <!-- HERO SECTION WITH CURVED BOTTOM -->
        <section id="home" class="hero-section">
            <div class="hero-container">
                <h1 class="hero-title">
                    Where Healthy Cows Produce
                    <span class="highlight">Pure & Fresh Milk</span>
                </h1>
                
                <!-- TYPEWRITER EFFECT -->
                <h2 class="hero-subtitle">
                    <span class="typewriter" id="typewriter"></span>
                </h2>
                
                <p class="hero-description">
                    Manage your dairy farm in a simple, digital and natural way.
                    Keep records of cattle, daily milk collection, and sales all in one place.
                </p>
                <div class="hero-buttons">
                    <a href="login.php" class="btn-outline">Get Started</a>
                    <a href="#services" class="btn-outline">Learn More</a>
                </div>
            </div>
        </section>
    </div>

    <!-- ABOUT US SECTION -->
    <section class="about-section" id="about">
        <div class="about-container">

            <!-- Left Content -->
            <div class="about-text">
                <h2>About DFMS</h2>

                <p>
                    The <strong>Dairy Farm Management System (DFMS)</strong> is built to help dairy farms
                    manage their daily operations in a simple and organized way. It helps in handling
                    cattle records, milk collection, sales, payments, and inventory with better accuracy.
                </p>

                <p>
                    DFMS reduces paperwork and manual errors by keeping all farm data in one place.
                    It supports both farm owners and employees in making day-to-day work easier and
                    more reliable.
                </p>
            </div>

            <!-- Stats -->
            <div class="about-extra">
    <p>
        DFMS is designed around the real working environment of dairy farms.
        From early morning milk collection to daily sales and inventory tracking,
        the system supports every essential farm activity in a practical way.
    </p>

    <p>
        Each cattle record is maintained with care, ensuring accurate tracking of
        health status, milk yield, and lifecycle changes such as pregnancy, sale,
        or loss.
    </p>

    <p>
        By combining simplicity with reliability, DFMS helps farmers focus more
        on animal care and productivity rather than paperwork and manual records.
    </p>
</div>


        </div>
    </section>


    <!-- SERVICES SECTION start -->
    <section class="services-section" id="features">
        <div class="services-container">
            <div class="services-header">
                <h2>System Features</h2>
                <p>Simple tools to manage daily dairy farm activities in an organized way</p>
            </div>

            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">🐄</div>
                    <h3>Cattle Management</h3>
                    <p>
                        Maintain records of cattle including tag ID, breed, type, age, health status, and birth details.
                    </p>
                </div>

                <div class="service-card">
                    <div class="service-icon">🥛</div>
                    <h3>Milk Collection</h3>
                    <p>
                        Record daily milk collection from each cattle for morning and evening shifts with quantity details.
                    </p>
                </div>

                <div class="service-card">
                    <div class="service-icon">💰</div>
                    <h3>Sales & Payment</h3>
                    <p>
                        Manage milk sales, customer payments, partial dues, and payment history in a simple manner.
                    </p>
                </div>

                <div class="service-card">
                    <div class="service-icon">👥</div>
                    <h3>Customer Management</h3>
                    <p>
                        Store customer details, track milk purchases, and monitor advance and due balances.
                    </p>
                </div>

                <div class="service-card">
                    <div class="service-icon">📦</div>
                    <h3>Inventory Management</h3>
                    <p>
                        Manage feed, medicine, dung, and other items with stock updates and low-stock alerts.
                    </p>
                </div>

                <div class="service-card">
                    <div class="service-icon">📑</div>
                    <h3>Reports & Summary</h3>
                    <p>
                        View daily and monthly reports of milk collection, sales, payments, and inventory status.
                    </p>
                </div>
            </div>
        </div>
    </section>
    <!-- SERVICES SECTION end -->


    <!-- CTA SECTION -->
    <section class="cta-section">
        <div class="cta-content">
            <h2>Ready to Transform Your Dairy Farm?</h2>
            <p>Join hundreds of forward-thinking farmers using DFMS for efficient farm management</p>
            <a href="login.php" class="cta-btn">Get Started Today →</a>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo">
                <img src="assets/images/logo2.png" alt="DFMS Logo">
            </div>
            <!-- <p>Innovative solutions for modern dairy farming. Manage your cattle, production, sales, and operations with ease and efficiency.</p> -->
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="login.php">Login</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Services</h3>
                <ul class="footer-links">
                    <li><a href="#services">Cattle Management</a></li>
                    <li><a href="#services">Milk Production</a></li>
                    <li><a href="#services">Customer, Sales & Payments</a></li>
                    <li><a href="#services">Reports summary</a></li>
                </ul>
            </div>
            
            <div class="footer-section" id="contact">
                <h3>Contact us</h3>
                <ul class="footer-links">
                    <li>📧 anandi@dfms.com</li>
                    <li>📧 rishikesh@dfms.com</li>
                    <li>📞 +977-9812351022</li>
                    <li>📞 +977-9822543916</li>
                    <li>📍 Biratnagar, Koshi, Nepal</li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Dairy Farm Management System. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // TYPEWRITER EFFECT
        const phrases = [
            "Easy Cattle Records",
            "Daily Milk Collection",
            "Simple Sales Tracking",
            "Farm Inventory Records",
            "Easy Farm Reports"

        ];
        
        let phraseIndex = 0;
        let charIndex = 0;
        let isDeleting = false;
        const typewriterElement = document.getElementById('typewriter');
        
        function type() {
            const currentPhrase = phrases[phraseIndex];
            
            if (isDeleting) {
                typewriterElement.textContent = currentPhrase.substring(0, charIndex - 1);
                charIndex--;
            } else {
                typewriterElement.textContent = currentPhrase.substring(0, charIndex + 1);
                charIndex++;
            }
            
            let typeSpeed = isDeleting ? 50 : 100;
            
            if (!isDeleting && charIndex === currentPhrase.length) {
                typeSpeed = 2000;
                isDeleting = true;
            } else if (isDeleting && charIndex === 0) {
                isDeleting = false;
                phraseIndex = (phraseIndex + 1) % phrases.length;
                typeSpeed = 500;
            }
            
            setTimeout(type, typeSpeed);
        }
        
        // Start typewriter
        setTimeout(type, 1000);
        
        // SMOOTH SCROLL
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>