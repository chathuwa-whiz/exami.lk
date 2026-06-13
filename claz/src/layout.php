<?php
function render_header(string $title, array $nav = [], ?array $user = null): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    // Get user from parameter, global, or fetch from current session
    if ($user === null) {
        $user = $GLOBALS['current_user'] ?? null;
        if ($user === null && isset($_SESSION['user_id']) && function_exists('current_user')) {
            $user = current_user();
        }
    }
    $isAdmin = $user && $user['user_type'] === 'admin';
    $isTeacher = $user && $user['user_type'] === 'teacher';
    $isStudent = $user && $user['user_type'] === 'student';
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta http-equiv='Content-Type' content='text/html; charset=UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
    echo "<title>" . htmlspecialchars($title) . " - Exami.lk</title>";
    // Google Fonts + Bootstrap & Icons CDN
    echo "<link rel='preconnect' href='https://fonts.googleapis.com'>";
    echo "<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>";
    echo "<link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Sinhala:wght@400;500;600;700&display=swap' rel='stylesheet'>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet' integrity='sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN' crossorigin='anonymous'>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css' rel='stylesheet'>";
    echo "<link rel='stylesheet' href='" . app_href('assets/style.css') . "'>";
    echo "<link rel='stylesheet' href='" . app_href('assets/responsive.css') . "'>";
    echo "</head><body class='app-body'>";
    // Skip link for keyboard navigation
    echo "<a href='#main' class='skip-link'>Skip to content</a>";
    echo "<div class='app-shell min-vh-100 d-flex flex-column'>";
    // Responsive Navbar
    echo "<header class='app-navbar border-bottom'>";
    echo "<div class='container-xxl py-2'>";
    echo "<nav class='navbar navbar-expand-lg navbar-light px-0' style='background: transparent;'>";
    echo "<a class='brand fw-semibold text-decoration-none d-flex align-items-center gap-2' href='" . htmlspecialchars(function_exists('app_href')?app_href(''):'/') . "'><div class='d-flex align-items-center gap-2'><img src='" . app_href('assets/logoexami.png') . "' alt='Exami.lk' style='height: 40px; width: auto;'><div><span class='brand-text' style='display: block;'>Exami.lk</span><span style='font-size: 0.7rem; color: #999; font-weight: 400; display: block; line-height: 1;'>The smart way to learn</span></div></div></a>";
    echo "<button class='navbar-toggler ms-auto' type='button' data-bs-toggle='collapse' data-bs-target='#navbarContent' aria-controls='navbarContent' aria-expanded='false' aria-label='Toggle navigation'><i class='bi bi-list'></i></button>";
    echo "<div class='collapse navbar-collapse' id='navbarContent'>";
    echo "<ul class='navbar-nav me-auto mb-2 mb-lg-0'>";
    
    // Build nav items
    if ($isAdmin) {
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('admin/dashboard.php') . "' title='Dashboard'><i class='bi bi-speedometer2'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('admin/teachers.php') . "' title='Teachers'><i class='bi bi-people'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('admin/payouts.php') . "' title='Payouts'><i class='bi bi-wallet2'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('admin/logs.php') . "' title='Audit Logs'><i class='bi bi-file-text'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('admin/profile.php') . "' title='Profile'><i class='bi bi-person'></i></a></li>";
    }
    if ($isTeacher) {
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('teacher/manage_papers.php') . "' title='My Papers'><i class='bi bi-files'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('teacher/create_paper.php') . "' title='Create Paper'><i class='bi bi-plus-circle'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('teacher/exam_integrity.php') . "' title='Integrity Monitoring'><i class='bi bi-shield-exclamation'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('teacher/payouts.php') . "' title='Payment'><i class='bi bi-wallet2'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('teacher/payment_summary.php') . "' title='Payment Summary'><i class='bi bi-cash-stack'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('teacher/messages.php') . "' title='Messages'><i class='bi bi-chat-dots'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('teacher/profile.php') . "' title='Profile'><i class='bi bi-person'></i></a></li>";
    }
    if ($isStudent) {
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('student/dashboard.php') . "' title='Dashboard'><i class='bi bi-house-door'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('student/papers.php') . "' title='My Papers'><i class='bi bi-journal'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('student/messages.php') . "' title='Messages'><i class='bi bi-chat-dots'></i></a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('student/profile.php') . "' title='Profile'><i class='bi bi-person'></i></a></li>";
    }
    if (!$user) {
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('') . "'><i class='bi bi-house me-1'></i>Home</a></li>";
        echo "<li class='nav-item'><a class='nav-link' href='" . app_href('login.php') . "'><i class='bi bi-box-arrow-in-right me-1'></i>Login</a></li>";
    }
    
    echo "</ul>";
    echo "<div class='d-flex align-items-center gap-2'>";
    
    if ($user) {
        echo "<div class='dropdown'>";
        echo "<button class='btn btn-light rounded-pill d-flex align-items-center gap-2' type='button' id='userDropdown' data-bs-toggle='dropdown' aria-expanded='false' style='padding: 0.35rem 0.75rem;'>";
        
        // Display profile image if available, otherwise show avatar with initials
        if (!empty($user['profile_image'])) {
            echo "<img src='" . htmlspecialchars(app_href($user['profile_image'])) . "' class='rounded-circle' style='width: 32px; height: 32px; object-fit: cover; border: 2px solid white;' alt='Profile'>";
        } else {
            $initials = strtoupper(substr($user['name'] ?? 'U', 0, 1));
            echo "<span class='avatar-circle'>" . htmlspecialchars($initials) . "</span>";
        }
        
        echo "<span class='d-none d-md-inline fw-semibold small'>" . htmlspecialchars($user['name'] ?? 'User') . "</span>";
        echo "</button>";
        echo "<ul class='dropdown-menu dropdown-menu-end' aria-labelledby='userDropdown'>";
        echo "<li><h6 class='dropdown-header'>" . htmlspecialchars($user['name'] ?? 'User') . "</h6></li>";
        if (!empty($user['email'])) {
            echo "<li><span class='dropdown-header small text-muted'>" . htmlspecialchars($user['email']) . "</span></li>";
        }
        echo "<li><hr class='dropdown-divider'></li>";
        
        if ($isAdmin) {
            echo "<li><a class='dropdown-item' href='" . app_href('admin/index.php') . "'><i class='bi bi-speedometer2 me-2'></i>Dashboard</a></li>";
        }
        if ($isTeacher) {
            echo "<li><a class='dropdown-item' href='" . app_href('teacher/profile.php') . "'><i class='bi bi-person me-2'></i>Profile</a></li>";
            echo "<li><a class='dropdown-item' href='" . app_href('teacher/exam_integrity.php') . "'><i class='bi bi-shield-exclamation me-2'></i>Integrity Monitoring</a></li>";
            echo "<li><a class='dropdown-item' href='" . app_href('teacher/payouts.php') . "'><i class='bi bi-wallet2 me-2'></i>Payment</a></li>";
            echo "<li><a class='dropdown-item' href='" . app_href('teacher/payment_summary.php') . "'><i class='bi bi-cash-stack me-2'></i>Payment Summary</a></li>";
        }
        if ($isStudent) {
            echo "<li><a class='dropdown-item' href='" . app_href('student/dashboard.php') . "'><i class='bi bi-house-door me-2'></i>Dashboard</a></li>";
            echo "<li><a class='dropdown-item' href='" . app_href('student/profile.php') . "'><i class='bi bi-person me-2'></i>Profile</a></li>";
        }
        echo "<li><hr class='dropdown-divider'></li>";
        echo "<li><a class='dropdown-item text-danger' href='" . app_href('logout.php') . "'><i class='bi bi-box-arrow-right me-2'></i>Sign Out</a></li>";
        echo "</ul>";
        echo "</div>";
    }
    
    echo "</div>";
    echo "</div>";
    echo "</nav>";
    echo "</div>";
    echo "</header>";
    echo "<main id='main' class='app-main flex-grow-1 py-5' role='main'>";
    echo "<div class='container-xxl'>";
    echo "<div class='page-heading d-flex flex-wrap gap-3 align-items-center justify-content-between mb-4'>";
    echo "<div><p class='eyebrow text-uppercase mb-1'>Dashboard</p><h1 class='page-title h3 mb-0 d-flex align-items-center gap-2'><i class='bi bi-stars text-primary'></i>" . htmlspecialchars($title) . "</h1></div>";
    if ($user) {
        echo "<div class='text-muted small'>Signed in as <strong>" . htmlspecialchars($user['name']) . "</strong></div>";
    }
    echo "</div>";
    // Live regions for screen reader announcements
    echo "<div class='sr-live' aria-live='polite' aria-atomic='true' style='position:absolute;left:-9999px;height:1px;width:1px;overflow:hidden;'></div>";
    echo "<div class='sr-live-assertive' aria-live='assertive' aria-atomic='true' style='position:absolute;left:-9999px;height:1px;width:1px;overflow:hidden;'></div>";
}

function render_auth_shell_start(string $title, string $subtitle = ''): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
    echo "<title>" . htmlspecialchars($title) . " - Exami.lk</title>";
    echo "<link rel='preconnect' href='https://fonts.googleapis.com'>";
    echo "<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>";
    echo "<link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet' integrity='sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN' crossorigin='anonymous'>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css' rel='stylesheet'>";
    echo "<link rel='stylesheet' href='" . app_href('assets/style.css') . "'>";
    echo "</head><body class='auth-body'>";

    // Compact navbar for auth pages (login/register), with dynamic links
    $authUser = null;
    if (function_exists('current_user')) {
        $authUser = current_user();
    } elseif (isset($_SESSION['user_id'])) {
        // Fallback minimal fetch if helper unavailable
        try {
            $stmt = db()->prepare('SELECT id, user_type, name FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $authUser = $stmt->fetch() ?: null;
        } catch (Throwable $e) { /* ignore */ }
    }
    $isAdmin = $authUser && $authUser['user_type'] === 'admin';
    $isTeacher = $authUser && $authUser['user_type'] === 'teacher';
    $isStudent = $authUser && $authUser['user_type'] === 'student';

    echo "<header class='border-bottom bg-white'>";
    echo "  <div class='container d-flex align-items-center justify-content-between py-2'>";
    echo "    <a class='text-decoration-none d-inline-flex align-items-center gap-2' href='" . htmlspecialchars(function_exists('app_href')?app_href(''):'/') . "'>";
    echo "      <div><img src='" . app_href('assets/logoexami.png') . "' alt='Exami.lk' style='height: 30px; width: auto;'></div><div><strong style='display: block;'>Exami.lk</strong><small style='color: #666; font-weight: 400;'>The smart way to learn</small></div>";
    echo "    </a>";
    echo "    <nav class='d-flex align-items-center gap-2'>";
    if ($authUser) {
        // Role home
        $home = $isStudent ? 'student/dashboard.php' : ($isTeacher ? 'teacher/manage_papers.php' : ($isAdmin ? 'admin/index.php' : ''));
        if ($home !== '') {
            echo "<a class='btn btn-sm btn-outline-primary' href='" . htmlspecialchars(app_href($home)) . "'><i class='bi bi-house-door'></i> Home</a>";
        }
        echo "<a class='btn btn-sm btn-outline-secondary' href='" . htmlspecialchars(app_href('logout.php')) . "'><i class='bi bi-box-arrow-right'></i> Logout</a>";
    } else {
        echo "<a class='btn btn-sm btn-outline-primary' href='" . htmlspecialchars(app_href('login.php')) . "'><i class='bi bi-box-arrow-in-right'></i> Login</a>";
        echo "<a class='btn btn-sm btn-primary' href='" . htmlspecialchars(app_href('register.php')) . "'><i class='bi bi-person-plus'></i> Register</a>";
    }
    echo "    </nav>";
    echo "  </div>";
    echo "</header>";

    echo "<div class='auth-shell d-flex flex-column flex-lg-row'>";
    echo "<section class='auth-illustration d-flex flex-column justify-content-between p-4 p-lg-5'>";
    echo "<div class='d-flex align-items-center gap-2 mb-3 mb-lg-4'><img src='" . app_href('assets/logoexami.png') . "' alt='Exami.lk' style='height: 40px; width: auto;'><div><div class='fw-semibold letter-spaced text-uppercase small text-light'>Exami.lk</div><div style='font-size: 0.75rem; color: rgba(255,255,255,0.7);'>The smart way to learn</div></div></div>";
    echo "<div class='flex-grow-1 d-flex align-items-center'>";
    echo "<div>";
    echo "<h1 class='display-5 fw-bold text-white mb-3'>" . htmlspecialchars($title) . "</h1>";
    if ($subtitle !== '') {
        echo "<p class='lead text-light opacity-90 mb-4'>" . htmlspecialchars($subtitle) . "</p>";
    }
    echo "<ul class='list-unstyled text-light small opacity-90'>";
    echo "<li class='d-flex align-items-center mb-2'><i class='bi bi-check-circle me-2'></i> Practice real exam-style MCQs</li>";
    echo "<li class='d-flex align-items-center mb-2'><i class='bi bi-check-circle me-2'></i> Track your progress across papers</li>";
    echo "<li class='d-flex align-items-center'><i class='bi bi-check-circle me-2'></i> Join your class with teacher codes</li>";
    echo "</ul>";
    echo "</div>";
    echo "</div>";
    echo "<div class='mt-4 text-light-50 small'>Made for modern Sri Lankan classrooms.</div>";
    echo "</section>";
    echo "<section class='auth-panel flex-fill d-flex align-items-center justify-content-center p-4 p-md-5'>";
    echo "<div class='w-100' style='max-width:480px;'>";
}

function render_auth_shell_end(): void {
    echo "</div></section>";
    echo "</div>";
    // Footer for auth pages
    echo "<footer class='auth-footer border-top py-3'>";
    echo "  <div class='container'>";
    echo "    <div class='d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 small'>";
    echo "      <div class='text-center text-md-start'>";
    echo "        <span>&copy; " . date('Y') . " Exami.lk</span>";
    echo "        <span class='d-none d-md-inline'> · Software developed by <a class='text-decoration-none' href='https://wa.me/94751534972' target='_blank' rel='noreferrer'><strong>Ecodez Digital Solution</strong></a></span>";
    echo "      </div>";
    echo "      <div class='d-flex gap-3'>";
    echo "        <a class='text-decoration-none' href='mailto:hello@exami.lk'><i class='bi bi-envelope me-1'></i>Support</a>";
    echo "        <a class='text-decoration-none' href='https://wa.me/94751534972' target='_blank' rel='noreferrer'><i class='bi bi-whatsapp me-1 text-success'></i>0751534972</a>";
    echo "      </div>";
    echo "    </div>";
    echo "  </div>";
    echo "</footer>";
    echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js' integrity='sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL' crossorigin='anonymous'></script>";
    echo "<script>(function(){function focusFirstAlert(){const alerts=document.querySelectorAll('.alert[role]');for(const alert of alerts){if(alert.offsetParent===null) continue;if(!alert.hasAttribute('tabindex')){alert.setAttribute('tabindex','-1');}alert.focus();break;}}document.addEventListener('DOMContentLoaded',focusFirstAlert);})();</script></body></html>";
}

function render_footer(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
    $user = null;
    if (isset($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT id, user_type FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    $isStudent = $user && $user['user_type'] === 'student';
    $isTeacher = $user && $user['user_type'] === 'teacher';
    $isAdmin = $user && $user['user_type'] === 'admin';
    
    echo "</div></main>";
    echo "<footer class='app-footer border-top py-5 mt-auto' style='background: #f8f9fa;'>";
    echo "<div class='container-xxl'>";
    echo "  <div class='row g-4 mb-4'>";
    
    // Brand & About
    echo "    <div class='col-12 col-md-4'>";
    echo "      <div class='d-flex align-items-center gap-2 mb-3'>";
    echo "        <img src='" . app_href('assets/logoexami.png') . "' alt='Exami.lk' style='height: 40px; width: auto;'>";
    echo "        <div>";
    echo "          <h5 class='mb-0 fw-bold' style='color: #ffffff;'>Exami.lk</h5>";
    echo "          <p class='text-muted small mb-0' style='color: #cccccc;'>The smart way to learn</p>";
    echo "        </div>";
    echo "      </div>";
    echo "    </div>";
    
    // Quick Links
    echo "    <div class='col-12 col-md-4'>";
    echo "      <h6 class='text-uppercase small mb-3' style='letter-spacing: 0.05em; font-weight: 600; color: #ffffff;'>Quick Links</h6>";
    echo "      <ul class='list-unstyled small'>";
    $homeLink = '/';
    if ($isStudent) { $homeLink = 'student/dashboard.php'; }
    elseif ($isTeacher) { $homeLink = 'teacher/manage_papers.php'; }
    elseif ($isAdmin) { $homeLink = 'admin/index.php'; }
    echo "        <li class='mb-2'><a class='text-decoration-none' href='" . htmlspecialchars(function_exists('app_href')?app_href($homeLink):$homeLink) . "' style='color: #ffffff;'><i class='bi bi-chevron-right' style='font-size: 0.8rem;'></i> Home</a></li>";
    echo "        <li class='mb-2'><a class='text-decoration-none' href='" . htmlspecialchars(function_exists('app_href')?app_href('student/papers.php'):'/student/papers.php') . "' style='color: #ffffff;'><i class='bi bi-chevron-right' style='font-size: 0.8rem;'></i> My Papers</a></li>";
    echo "        <li class='mb-2'><a class='text-decoration-none' href='" . htmlspecialchars(function_exists('app_href')?app_href('teacher/manage_papers.php'):'/teacher/manage_papers.php') . "' style='color: #ffffff;'><i class='bi bi-chevron-right' style='font-size: 0.8rem;'></i> Papers (Teacher)</a></li>";
    echo "      </ul>";
    echo "    </div>";
    
    // Support & Contact
    echo "    <div class='col-12 col-md-4'>";
    echo "      <h6 class='text-uppercase small mb-3' style='letter-spacing: 0.05em; font-weight: 600; color: #ffffff;'>Support</h6>";
    echo "      <ul class='list-unstyled small'>";
    echo "        <li class='mb-2'><a class='text-decoration-none' href='mailto:hello@exami.lk' style='color: #ffffff;'><i class='bi bi-envelope me-1'></i>Email Support</a></li>";
    echo "        <li class='mb-2'><a class='text-decoration-none' href='https://wa.me/94751534972' target='_blank' rel='noreferrer' style='color: #ffffff;'><i class='bi bi-whatsapp me-1' style='color: #25d366;'></i>WhatsApp</a></li>";
    echo "        <li><a class='text-decoration-none' href='https://github.com/' target='_blank' rel='noreferrer' style='color: #ffffff;'><i class='bi bi-github me-1'></i>GitHub</a></li>";
    echo "      </ul>";
    echo "    </div>";
    
    echo "  </div>";
    
    // Divider
    echo "  <hr class='my-4' style='border-color: rgba(255, 255, 255, 0.2);'>";
    
    // Bottom section
    echo "  <div class='row align-items-center'>";
    echo "    <div class='col-12 col-md-6 small' style='color: #ffffff;'>";
    echo "      <p class='mb-0' style='color: #ffffff;'>&copy; " . date('Y') . " Exami.lk. All rights reserved.</p>";
    echo "    </div>";
    echo "    <div class='col-12 col-md-6 text-md-end small mt-3 mt-md-0' style='color: #ffffff;'>";
    echo "      <span style='color: #ffffff;'>Built by <a class='text-decoration-none fw-semibold' href='https://wa.me/94751534972' target='_blank' rel='noreferrer' style='color: #ffffff;'>Ecodez Digital Solution</a></span>";
    echo "    </div>";
    echo "  </div>";
    echo "</div></footer>";
    echo "</div>";
    // Script placed before closing layout wrappers
    echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js' integrity='sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL' crossorigin='anonymous'></script>";
    // MathJax is now loaded per-page where required to avoid double-loading conflicts
    echo "<script>(function(){\n";
    echo "const tBtn=document.getElementById('toggleTheme');\n";
    echo "const cBtn=document.getElementById('toggleContrast');\n";
    echo "const root=document.body;\n";
    echo "function applyStored(){const m=localStorage.getItem('mode');if(m==='light'){root.classList.add('light');}if(m==='dark'){root.classList.remove('light');}const hc=localStorage.getItem('hc');if(hc==='1'){root.classList.add('hc');}}\n";
    echo "applyStored();\n";
    echo "function toggleMode(){const isLight=root.classList.toggle('light');localStorage.setItem('mode', isLight?'light':'dark');tBtn&&tBtn.setAttribute('aria-pressed', isLight);}\n";
    echo "function toggleContrast(){const isHC=root.classList.toggle('hc');localStorage.setItem('hc', isHC?'1':'0');cBtn&&cBtn.setAttribute('aria-pressed', isHC);}\n";
    echo "tBtn&&tBtn.addEventListener('click',toggleMode);cBtn&&cBtn.addEventListener('click',toggleContrast);\n";
    // Mirror .alert content into polite live region for SR and move focus to first alert
    echo "function announceAlerts(){const region=document.querySelector('.sr-live');if(!region) return;const alerts=document.querySelectorAll('.alert[role]');alerts.forEach(a=>{region.textContent=a.textContent;});}\n";
    echo "function focusFirstAlert(){const alerts=document.querySelectorAll('.alert[role]');for(const alert of alerts){if(alert.offsetParent===null) continue;if(!alert.hasAttribute('tabindex')){alert.setAttribute('tabindex','-1');}alert.focus();break;}}\n";
    echo "document.addEventListener('DOMContentLoaded',()=>{announceAlerts();focusFirstAlert();});\n";
    echo "})();</script></body></html>";
}

function nav_link(string $href, string $label): string {
    if (function_exists('app_href')) {
        $normalized = ltrim($href, '/');
        $href = app_href($normalized);
    }
    $current = $_SERVER['REQUEST_URI'] ?? '';
    $isActive = ($href !== '' && strpos($current, $href) === 0);
    $classes = 'nav-link d-flex align-items-center gap-1';
    if ($isActive) { $classes .= ' active'; }
    // If rendered inside legacy sidebar add sidebar styles automatically
    if (strpos($label, '<i') === false) {
        // Add default icon based on label keyword (fallback star)
        $iconMap = [
            'Home' => 'house', 'Login' => 'box-arrow-in-right', 'Logout' => 'box-arrow-right', 'My Papers' => 'journal',
            'Create Paper' => 'plus-circle', 'Preapproved' => 'people', 'Teachers' => 'person-badge', 'Audit Logs' => 'journal-text'
        ];
        $key = trim(strip_tags($label));
        $icon = $iconMap[$key] ?? 'star';
        $label = "<i class='bi bi-$icon'></i> " . htmlspecialchars($key);
    }
    return "<li class='nav-item'><a class='$classes' href='" . htmlspecialchars($href) . "'>$label</a></li>";
}

function render_welcome_banner(?array $user = null): void {
    if ($user === null && isset($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT id, name, user_type FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    if (!$user) return;
    
    $firstName = htmlspecialchars(explode(' ', $user['name'])[0]);
    
    // Set icon and message based on user type
    if ($user['user_type'] === 'teacher') {
        $icon = 'bi-easel';
        $message = "Your papers, students, and metrics at a glance";
    } elseif ($user['user_type'] === 'admin') {
        $icon = 'bi-shield-check';
        $message = "Manage users, monitor activity, and oversee the platform";
    } else {
        $icon = 'bi-hand-thumbs-up-fill';
        $message = "Your classes, teachers, and papers at a glance";
    }
    
    echo "<section style=\"background: #ffffff; border-radius: 8px; padding: 2rem; margin-bottom: 2rem; border-left: 4px solid #3b82f6;\">";
    echo "<div class=\"position-relative\" style=\"z-index: 1;\">";
    echo "<h1 class=\"mb-1\" style=\"font-size: 1.75rem; font-weight: 700; color: #1f2937;\">";
    echo "Welcome back, " . $firstName . "!";
    echo "</h1>";
    echo "<p style=\"font-size: 0.95rem; margin: 0; color: #6b7280;\">";
    echo $message;
    echo "</p>";
    echo "</div>";
    echo "</section>";
}

