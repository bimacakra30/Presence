<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Presence! Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Animated background elements */
        .bg-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .shape {
            position: absolute;
            background: rgba(99, 102, 241, 0.05);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 70%;
            right: 15%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        .shape:nth-child(4) {
            width: 100px;
            height: 100px;
            top: 10%;
            right: 30%;
            animation-delay: 1s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .welcome-container {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 3rem 2.5rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 450px;
            width: 90%;
            position: relative;
            z-index: 2;
        }

        .logo-container {
            margin-bottom: 2rem;
            position: relative;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
            transform: rotate(-5deg);
            transition: transform 0.3s ease;
        }

        .logo-icon:hover {
            transform: rotate(0deg) scale(1.05);
        }

        .logo-icon i {
            font-size: 2.5rem;
            color: white;
        }

        .app-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #f8fafc;
            margin-bottom: 0.5rem;
        }

        .app-subtitle {
            color: #94a3b8;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .welcome-message {
            color: #cbd5e1;
            font-size: 1.1rem;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .progress-container {
            margin: 2rem 0;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #334155;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-radius: 4px;
            width: 0%;
            transition: width 0.3s ease;
            position: relative;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .progress-text {
            text-align: center;
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .loading-dots {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .dot {
            width: 8px;
            height: 8px;
            background: #8b5cf6;
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out;
        }

        .dot:nth-child(1) { animation-delay: -0.32s; }
        .dot:nth-child(2) { animation-delay: -0.16s; }
        .dot:nth-child(3) { animation-delay: 0s; }

        @keyframes bounce {
            0%, 80%, 100% { 
                transform: scale(0.8);
                opacity: 0.5;
            }
            40% { 
                transform: scale(1.2);
                opacity: 1;
            }
        }

        .version-info {
            position: absolute;
            bottom: 1rem;
            right: 1rem;
            color: #475569;
            font-size: 0.8rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .welcome-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }

            .app-title {
                font-size: 2rem;
            }

            .welcome-message {
                font-size: 1rem;
            }

            .progress-bar {
                height: 6px;
            }
        }

        /* Loading animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            transition: opacity 0.5s ease;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #334155;
            border-top: 3px solid #8b5cf6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Animated Background -->
    <div class="bg-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="welcome-container">
        <div class="logo-container">
            <div class="logo-icon">
                <i class="fas fa-user-clock"></i>
            </div>
            <h1 class="app-title">Presence!</h1>
            <p class="app-subtitle">Admin Panel</p>
        </div>

        <div class="welcome-message">
            <strong>Selamat datang di Admin Panel</strong><br>
            Sistem akan mengarahkan Anda ke halaman login dalam beberapa detik...
        </div>

        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <div class="progress-text" id="progressText">Memuat... 0%</div>
        </div>

        <div class="loading-dots">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>

        <div class="version-info">
            v1.0.0 - Admin Panel
        </div>
    </div>

    <script>
        // Splash screen functionality
        let progress = 0;
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        const loadingOverlay = document.getElementById('loadingOverlay');

        // Hide initial loading overlay
        window.addEventListener('load', function() {
            setTimeout(() => {
                loadingOverlay.style.opacity = '0';
                setTimeout(() => {
                    loadingOverlay.style.display = 'none';
                    startSplashScreen();
                }, 500);
            }, 800);
        });

        function startSplashScreen() {
            const interval = setInterval(() => {
                progress += 2;
                progressFill.style.width = progress + '%';
                progressText.textContent = `Memuat... ${progress}%`;

                if (progress >= 100) {
                    clearInterval(interval);
                    progressText.textContent = 'Mengarahkan ke halaman login...';
                    
                    // Show loading overlay again
                    setTimeout(() => {
                        loadingOverlay.style.display = 'flex';
                        loadingOverlay.style.opacity = '1';
                        
                        // Redirect to login
                        setTimeout(() => {
                            window.location.href = '/admin/login';
                        }, 1000);
                    }, 1000);
                }
            }, 100);
        }

        // Add some interactive animations
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.shape');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 0.3;
                const xPos = (x - 0.5) * speed * 30;
                const yPos = (y - 0.5) * speed * 30;
                shape.style.transform = `translate(${xPos}px, ${yPos}px)`;
            });
        });

        // Add click to skip splash screen
        document.addEventListener('click', function() {
            if (progress < 100) {
                progress = 100;
                progressFill.style.width = '100%';
                progressText.textContent = 'Mengarahkan ke halaman login...';
                
                setTimeout(() => {
                    loadingOverlay.style.display = 'flex';
                    loadingOverlay.style.opacity = '1';
                    
                    setTimeout(() => {
                        window.location.href = '/admin/login';
                    }, 500);
                }, 500);
            }
        });
    </script>
</body>
</html>