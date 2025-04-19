<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Existing meta tags -->
    <meta name="description" content="ETSTMC Inventory Management System - Advanced aluminum inventory tracking solution for construction businesses">
    <meta name="keywords" content="inventory management, aluminum tracking, construction materials, warehouse management">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📦</text></svg>">
    
    <style>
        /* Additional CSS improvements */
        .hero h1 {
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .feature-card {
            background: white;
        }
        
        .carousel-control-prev,
        .carousel-control-next {
            width: 5%;
        }
        
        .contact-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.25);
        }
        
        .scroll-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: none;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <!-- Existing navigation -->

    <!-- Add scroll to top button -->
    <button class="btn btn-primary scroll-top rounded-circle p-2">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Existing sections -->

    <!-- Enhanced Contact Section -->
    <section id="contact" class="py-5 py-lg-6">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="contact-card p-4 p-md-5">
                        <div class="text-center mb-5">
                            <h2 class="fw-bold">Get Started Today</h2>
                            <p class="lead text-muted">Schedule a demo or request more information</p>
                        </div>
                        <form id="contactForm" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" required>
                                    <div class="invalid-feedback">
                                        Please enter your name
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" required>
                                    <div class="invalid-feedback">
                                        Please enter a valid email
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="company" class="form-label">Company Name</label>
                                    <input type="text" class="form-control" id="company" required>
                                    <div class="invalid-feedback">
                                        Please enter your company name
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea class="form-control" id="message" rows="4" required></textarea>
                                    <div class="invalid-feedback">
                                        Please enter your message
                                    </div>
                                </div>
                                <div class="col-12 text-center">
                                    <button type="submit" class="btn btn-primary btn-lg px-5">
                                        Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-4">
                    <div class="footer-brand">
                        <a href="#" class="d-flex align-items-center text-white text-decoration-none">
                            <i class="fas fa-cubes text-primary me-2"></i>
                            <span class="h4 mb-0">ETSTMC</span>
                        </a>
                    </div>
                    <p class="mt-3 text-muted">Streamlining aluminum inventory management since 2020</p>
                    <div class="social-links">
                        <a href="#" class="social-icon">
                            <i class="fab fa-linkedin"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-icon">
                            <i class="fab fa-facebook"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <h5 class="text-white mb-3">Solutions</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="footer-link">Inventory Tracking</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Warehouse Management</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Mobile App</a></li>
                        <li><a href="#" class="footer-link">Analytics</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-6">
                    <h5 class="text-white mb-3">Company</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="footer-link">About Us</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Careers</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Blog</a></li>
                        <li><a href="#" class="footer-link">Partners</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h5 class="text-white mb-3">Legal</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="footer-link">Privacy Policy</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Terms of Service</a></li>
                        <li class="mb-2"><a href="#" class="footer-link">Cookie Policy</a></li>
                        <li><a href="#" class="footer-link">Security</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-top mt-5 pt-4 text-center">
                <p class="text-muted mb-0">&copy; 2023 ETSTMC Systems. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript Enhancements -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Number Animation
        function animateNumbers() {
            const counters = document.querySelectorAll('.count-animation');
            counters.forEach(counter => {
                const updateCount = () => {
                    const target = +counter.getAttribute('data-count');
                    const count = +counter.innerText;
                    const increment = target / 100;
                    if(count < target) {
                        counter.innerText = Math.ceil(count + increment);
                        setTimeout(updateCount, 20);
                    } else {
                        counter.innerText = target;
                    }
                }
                updateCount();
            });
        }

        // Intersection Observer for animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if(entry.isIntersecting) {
                    animateNumbers();
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        document.querySelectorAll('.stat-card').forEach(card => {
            observer.observe(card);
        });

        // Smooth Scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Scroll to Top Button
        window.addEventListener('scroll', function() {
            const scrollTop = document.querySelector('.scroll-top');
            if(window.scrollY > 300) {
                scrollTop.style.display = 'block';
            } else {
                scrollTop.style.display = 'none';
            }
        });

        document.querySelector('.scroll-top').addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Form Validation
        (function () {
            'use strict'
            const form = document.getElementById('contactForm');
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })()
    </script>
</body>
</html>