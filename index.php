<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ETSTMC Inventory Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0d6efd;
            --secondary: #6c757d;
            --success: #198754;
            --light-gray: #f8f9fa;
            --dark-blue: #0b5ed7;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        .navbar-brand img {
            height: 40px;
        }
        
        .hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--dark-blue) 100%);
            color: white;
            padding: 6rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero::after {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E") repeat;
            top: -50%;
            left: -50%;
            opacity: 0.1;
            z-index: 0;
            animation: animateBackground 60s linear infinite;
        }
        
        @keyframes animateBackground {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            100% {
                transform: translateY(-20%) rotate(360deg);
            }
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .feature-icon {
            width: 4rem;
            height: 4rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            background-color: var(--light-gray);
            color: var(--primary);
            font-size: 1.75rem;
        }
        
        .feature-card {
            padding: 2rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid transparent;
        }
        
        .feature-card:hover {
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }
        
        .stat-card {
            text-align: center;
            padding: 2rem;
            border-radius: 0.5rem;
            background-color: var(--light-gray);
            height: 100%;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .demo-section {
            position: relative;
            overflow: hidden;
        }
        
        .demo-device {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            border-radius: 1rem;
            overflow: hidden;
        }
        
        .testimonial-card {
            padding: 2rem;
            border-radius: 0.5rem;
            background-color: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            height: 100%;
        }
        
        .testimonial-avatar {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .pricing-card {
            border-radius: 0.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid #dee2e6;
        }
        
        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .pricing-header {
            background-color: var(--light-gray);
            padding: 2rem;
            text-align: center;
        }
        
        .pricing-popular {
            border-color: var(--primary);
            position: relative;
        }
        
        .pricing-popular .pricing-header {
            background-color: var(--primary);
            color: white;
        }
        
        .pricing-popular::before {
            content: 'MOST POPULAR';
            position: absolute;
            top: 0;
            right: 0;
            background-color: var(--success);
            color: white;
            padding: 0.25rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            transform: translateY(-50%);
            border-radius: 1rem;
        }
        
        .pricing-price {
            font-size: 3rem;
            font-weight: 700;
        }
        
        .pricing-currency {
            font-size: 1.5rem;
            vertical-align: super;
        }
        
        .pricing-period {
            font-size: 1rem;
            color: var(--secondary);
        }
        
        .pricing-features {
            padding: 2rem;
            text-align: center;
        }
        
        .pricing-cta {
            padding: 0 2rem 2rem;
            text-align: center;
        }
        
        .footer {
            background-color: #212529;
            color: white;
            padding: 4rem 0 2rem;
        }
        
        .footer-brand img {
            height: 40px;
            margin-bottom: 1rem;
        }
        
        .footer-link {
            color: rgba(255, 255, 255, 0.75);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer-link:hover {
            color: white;
        }
        
        .social-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            margin-right: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .social-icon:hover {
            background-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .btn-primary {
            padding: 0.75rem 1.5rem;
            border-radius: 2rem;
        }
        
        .btn-outline-primary {
            border-radius: 2rem;
            padding: 0.75rem 1.5rem;
        }
        
        .demo-carousel .carousel-indicators {
            bottom: -3rem;
        }
        
        .demo-carousel .carousel-indicators [data-bs-target] {
            background-color: var(--primary);
        }
        
        .demo-carousel .carousel-inner {
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .count-animation {
            visibility: hidden;
        }
        
        .nav-link {
            color: #212529;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            color: var(--primary);
        }
        
        .sticky-top {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        
        @media (max-width: 992px) {
            .hero {
                padding: 4rem 0;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top py-3 shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-cubes text-primary me-2"></i>
                <span class="fw-bold">ETSTMC Systems</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#demo">Demo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonials">Testimonials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                <div class="ms-lg-4 mt-3 mt-lg-0">
                    <a href="#demo" class="btn btn-outline-primary me-2">Request Demo</a>
                    <a href="#contact" class="btn btn-primary">Get Started</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <h1 class="display-4 fw-bold mb-4">Aluminum Inventory Management System</h1>
                    <p class="lead mb-4">Streamline your construction materials inventory with our comprehensive solution designed specifically for aluminum product management.</p>
                    <div class="d-flex flex-wrap">
                        <a href="#features" class="btn btn-light btn-lg me-3 mb-3 mb-lg-0">
                            <i class="fas fa-play-circle me-2"></i>
                            Watch Video
                        </a>
                        <a href="#contact" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Schedule Demo
                        </a>
                    </div>
                    <div class="mt-4">
                        <span class="text-white opacity-75 me-3">Trusted by:</span>
                        <img src="/api/placeholder/100/30" alt="Client logo" class="me-3 mt-2 opacity-75">
                        <img src="/api/placeholder/100/30" alt="Client logo" class="me-3 mt-2 opacity-75">
                        <img src="/api/placeholder/100/30" alt="Client logo" class="mt-2 opacity-75">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="position-relative">
                        <img src="/api/placeholder/600/400" alt="Dashboard preview" class="img-fluid rounded shadow-lg">
                        <div class="position-absolute top-0 end-0 bg-white p-2 rounded-circle shadow-sm" style="transform: translate(25%, -25%);">
                            <i class="fas fa-chart-line text-primary fa-2x"></i>
                        </div>
                        <div class="position-absolute bottom-0 start-0 bg-white p-2 rounded-circle shadow-sm" style="transform: translate(-25%, 25%);">
                            <i class="fas fa-tasks text-success fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number count-animation" data-count="98">0</div>
                        <p class="mb-0 text-muted">Customer Satisfaction</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number count-animation" data-count="500">0</div>
                        <p class="mb-0 text-muted">Active Users</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number count-animation" data-count="25000">0</div>
                        <p class="mb-0 text-muted">Products Tracked</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number count-animation" data-count="35">0</div>
                        <p class="mb-0 text-muted">Time Saved (%)</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 py-lg-6">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Powerful Features</h2>
                <p class="lead text-muted">Everything you need to manage your aluminum construction materials effectively</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-barcode"></i>
                        </div>
                        <h3 class="h5">Barcode Scanning</h3>
                        <p class="text-muted">Scan and update inventory instantly with our mobile app integration for real-time tracking.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="h5">Analytics Dashboard</h3>
                        <p class="text-muted">Get actionable insights with custom reports and analytics to optimize your inventory levels.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3 class="h5">Automated Alerts</h3>
                        <p class="text-muted">Receive notifications when stock levels are low or when it's time to reorder materials.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3 class="h5">Mobile Access</h3>
                        <p class="text-muted">Access your inventory data from anywhere with our responsive mobile application.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-sync"></i>
                        </div>
                        <h3 class="h5">Seamless Integration</h3>
                        <p class="text-muted">Connect with your existing systems including ERP, accounting, and e-commerce platforms.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="h5">Secure Data</h3>
                        <p class="text-muted">Enterprise-grade security with role-based access control and encrypted data storage.</p>
                    </div>
                </div>
            </div>
            <div class="text-center mt-5">
                <a href="#contact" class="btn btn-primary btn-lg">
                    <i class="fas fa-list-alt me-2"></i>
                    See All Features
                </a>
            </div>
        </div>
    </section>

    <!-- Demo Section -->
    <section id="demo" class="py-5 py-lg-6 bg-light demo-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-5 mb-5 mb-lg-0">
                    <h2 class="fw-bold mb-4">See Our System in Action</h2>
                    <p class="mb-4">Watch how ETSTMC's Aluminum Inventory Management System helps streamline your operations, reduce errors, and save time.</p>
                    <ul class="list-unstyled mb-4">
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-primary me-2"></i>
                            Real-time inventory tracking
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-primary me-2"></i>
                            Automated purchase orders
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-primary me-2"></i>
                            Comprehensive reporting
                        </li>
                        <li>
                            <i class="fas fa-check-circle text-primary me-2"></i>
                            User-friendly interface
                        </li>
                    </ul>
                    <a href="#contact" class="btn btn-primary">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Schedule a Live Demo
                    </a>
                </div>
                <div class="col-lg-7">
                    <div id="demoCarousel" class="carousel slide demo-carousel" data-bs-ride="carousel">
                        <div class="carousel-indicators">
                            <button type="button" data-bs-target="#demoCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                            <button type="button" data-bs-target="#demoCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                            <button type="button" data-bs-target="#demoCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                        </div>
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <img src="/api/placeholder/800/450" class="d-block w-100" alt="Dashboard">
                                <div class="carousel-caption d-none d-md-block">
                                    <h5>Intuitive Dashboard</h5>
                                    <p>Get a complete overview of your inventory at a glance</p>
                                </div>
                            </div>
                            <div class="carousel-item">
                                <img src="/api/placeholder/800/450" class="d-block w-100" alt="Mobile App">
                                <div class="carousel-caption d-none d-md-block">
                                    <h5>Mobile Application</h5>
                                    <p>Access your inventory from anywhere, anytime</p>
                                </div>
                            </div>
                            <div class="carousel-item">
                                <img src="/api/placeholder/800/450" class="d-block w-100" alt="Reports">
                                <div class="carousel-caption d-none d-md-block">
                                    <h5>Advanced Reporting</h5>
                                    <p>Generate detailed reports with just a few clicks</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-5 py-lg-6">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">What Our Clients Say</h2>
                <p class="lead text-muted">Trusted by construction companies across the industry</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-4">
                            <img src="/api/placeholder/80/80" alt="Client" class="testimonial-avatar me-3">
                            <div>
                                <h5 class="mb-0">Sarah Johnson</h5>
                                <p class="text-muted mb-0">Inventory Manager, BuildRite Inc.</p>
                            </div>
                        </div>
                        <p class="mb-0">"The ETSTMC system has revolutionized how we manage our aluminum inventory. We've reduced waste by 30% and improved order accuracy significantly."</p>
                        <div class="mt-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-4">
                            <img src="/api/placeholder/80/80" alt="Client" class="testimonial-avatar me-3">
                            <div>
                                <h5 class="mb-0">Michael Lee</h5>
                                <p class="text-muted mb-0">Operations Director, Skyline Builders</p>
                            </div>
                        </div>
                        <p class="mb-0">"Implementation was seamless and the support team was exceptional. We now have complete visibility of our aluminum stock across all locations."</p>
                        <div class="mt-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star-half-alt text-warning"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-4">
                            <img src="/api/placeholder/80/80" alt="Client" class="testimonial-avatar me-3">
                            <div>
                                <h5 class="mb-0">Jennifer Martinez</h5>
                                <p class="text-muted mb-0">CEO, Precision Construction</p>
                            </div>
                        </div>
                        <p class="mb-0">"The analytics capabilities have helped us optimize our purchasing decisions. We've seen a 25% reduction in holding costs since implementing the system."</p>
                        <div class="mt-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-5">
                <a href="#contact" class="btn btn-outline-primary">
                    <i class="fas fa-comments me-2"></i>
                    Read More Testimonials
                </a>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-5 py-lg-6 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Simple, Transparent Pricing</h2>
                <p class="lead text-muted">Choose the plan that works best for your business</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card bg-white h-100">
                        <div class="pricing-header">
                            <h3>Starter</h3>
                            <div class="pricing-price">
                                <span class="pricing-currency">$</span>99
                                <span class="pricing-period">/month</span>
                            </div>
                            <p class="mb-0">Perfect for small businesses</p>
                        </div>
                        <div class="pricing-features">
                            <ul class="list-unstyled">
                                <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Up to 5 users</li>
                                <li class="mb-3"><i class="fas fa-check text-success me-2"></i>1,000 items tracking</li>
                                <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Basic reporting</li>
                                <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Mobile app access</li>
                                <li class="mb-3"><i class="fas fa-times text-danger me-2"></i>API integration</li>
                                <li class="mb-3"><i class="fas fa-times text-danger me-2"></i>Advanced analytics</li>
                            </ul>
                        </div>