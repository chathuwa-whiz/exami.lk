<?php
/* ============================================================
   Exami.lk — Layout Engine
   render_header()         → Sidebar + Topbar + Main (app pages)
   render_auth_shell_start() → Split auth layout (login/register)
   render_auth_shell_end()   → Close auth layout + scripts
   render_footer()           → Close main + mini footer + scripts
   ============================================================ */

/* ── Sidebar link helper ────────────────────────────────── */
function _sidebar_link(string $href, string $icon, string $label, bool $active = false): string {
    $cls = 'sidebar-link' . ($active ? ' active' : '');
    $safeHref = htmlspecialchars($href);
    $safeLabel = htmlspecialchars($label);
    return "<a class=\"{$cls}\" href=\"{$safeHref}\" data-label=\"{$safeLabel}\">
        <i class=\"bi bi-{$icon} sidebar-icon\"></i>
        <span class=\"sidebar-label\">{$safeLabel}</span>
    </a>";
}

/* ── Active page detection ──────────────────────────────── */
function _is_active(string $path): bool {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return str_contains($script, $path);
}

/* ── render_header ─────────────────────────────────────── */
function render_header(string $title, array $nav = [], ?array $user = null): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

    if ($user === null) {
        $user = $GLOBALS['current_user'] ?? null;
        if ($user === null && isset($_SESSION['user_id']) && function_exists('current_user')) {
            $user = current_user();
        }
    }

    $isAdmin   = $user && $user['user_type'] === 'admin';
    $isTeacher = $user && $user['user_type'] === 'teacher';
    $isStudent = $user && $user['user_type'] === 'student';

    /* ── HTML Head ─────────────────────────────────────── */
    echo "<!DOCTYPE html><html lang='en'><head>";
    echo "<meta charset='UTF-8'><meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";
    echo "<meta name='viewport' content='width=device-width,initial-scale=1'>";
    echo "<title>" . htmlspecialchars($title) . " — Exami.lk</title>";
    echo "<link rel='preconnect' href='https://fonts.googleapis.com'>";
    echo "<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>";
    echo "<link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@600;700;800&family=Noto+Sans+Sinhala:wght@400;500;600;700&display=swap' rel='stylesheet'>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet' integrity='sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN' crossorigin='anonymous'>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css' rel='stylesheet'>";
    echo "<link rel='stylesheet' href='" . app_href('assets/style.css') . "'>";
    echo "<link rel='stylesheet' href='" . app_href('assets/responsive.css') . "'>";
    echo "</head><body class='app-body'>";
    echo "<a href='#main' class='skip-link'>Skip to content</a>";

    /* ── Mobile overlay ────────────────────────────────── */
    echo "<div class='sidebar-overlay' id='sidebarOverlay'></div>";

    /* ── App Shell ─────────────────────────────────────── */
    echo "<div class='app-shell'>";

    /* ── SIDEBAR ───────────────────────────────────────── */
    echo "<aside class='app-sidebar' id='appSidebar'>";

    /* Sidebar Header */
    $homeHref = app_href('');
    echo "<div class='sidebar-header'>";
    echo "  <a href='" . htmlspecialchars($homeHref) . "' class='sidebar-brand'>";
    echo "    <img src='" . app_href('assets/logoexami.png') . "' class='sidebar-logo' alt='Exami.lk'>";
    echo "    <div class='sidebar-brand-text'>";
    echo "      <span class='sidebar-brand-name'>Exami.lk</span>";
    echo "      <span class='sidebar-brand-sub'>The Smart Way to Learn</span>";
    echo "    </div>";
    echo "  </a>";
    echo "  <button class='sidebar-toggle-btn' id='sidebarToggle' title='Collapse sidebar' aria-label='Toggle sidebar'>";
    echo "    <i class='bi bi-layout-sidebar-inset'></i>";
    echo "  </button>";
    echo "</div>";

    /* ── SIDEBAR NAV (per role) ─────────────────────────── */
    echo "<nav class='sidebar-nav' role='navigation' aria-label='Main navigation'>";

    if ($isStudent) {
        echo _sidebar_link(app_href('student/dashboard.php'), 'house-door', 'Dashboard',        _is_active('student/dashboard.php'));
        echo _sidebar_link(app_href('student/papers.php'),   'journal-text', 'My Papers',       _is_active('student/papers.php'));
        echo _sidebar_link(app_href('student/messages.php'), 'chat-dots',   'Messages',         _is_active('student/messages.php'));
        echo _sidebar_link(app_href('student/profile.php'),  'person-circle', 'Profile',        _is_active('student/profile.php'));
    }

    if ($isTeacher) {
        echo _sidebar_link(app_href('teacher/manage_papers.php'),  'files',             'My Papers',         _is_active('manage_papers.php'));
        echo _sidebar_link(app_href('teacher/create_paper.php'),   'plus-circle',       'Create Paper',      _is_active('create_paper.php'));
        echo "<p class='sidebar-section-label'>Monitoring</p>";
        echo _sidebar_link(app_href('teacher/exam_integrity.php'), 'shield-check',      'Exam Integrity',    _is_active('exam_integrity.php'));
        echo "<p class='sidebar-section-label'>Finance</p>";
        echo _sidebar_link(app_href('teacher/payouts.php'),        'wallet2',           'Payouts',           _is_active('teacher/payouts.php'));
        echo _sidebar_link(app_href('teacher/payment_summary.php'),'cash-stack',        'Payment Summary',   _is_active('payment_summary.php'));
        echo "<p class='sidebar-section-label'>Communication</p>";
        echo _sidebar_link(app_href('teacher/messages.php'),       'chat-dots',         'Messages',          _is_active('teacher/messages.php'));
        echo "<p class='sidebar-section-label'>Account</p>";
        echo _sidebar_link(app_href('teacher/profile.php'),        'person-circle',     'Profile',           _is_active('teacher/profile.php'));
    }

    if ($isAdmin) {
        echo _sidebar_link(app_href('admin/dashboard.php'), 'speedometer2',  'Dashboard',   _is_active('admin/dashboard.php'));
        echo _sidebar_link(app_href('admin/index.php'),     'people',        'Students',    _is_active('admin/index.php'));
        echo _sidebar_link(app_href('admin/teachers.php'),  'person-badge',  'Teachers',    _is_active('admin/teachers.php'));
        echo "<p class='sidebar-section-label'>Finance</p>";
        echo _sidebar_link(app_href('admin/payouts.php'),   'wallet2',       'Payouts',     _is_active('admin/payouts.php'));
        echo "<p class='sidebar-section-label'>System</p>";
        echo _sidebar_link(app_href('admin/logs.php'),      'journal-text',  'Audit Logs',  _is_active('admin/logs.php'));
    }

    if (!$user) {
        echo _sidebar_link(app_href(''),           'house',              'Home',   false);
        echo _sidebar_link(app_href('login.php'),  'box-arrow-in-right', 'Login',  _is_active('login.php'));
    }

    echo "</nav>";

    /* Sidebar Footer (user + logout) */
    if ($user) {
        $initials = strtoupper(substr($user['name'] ?? 'U', 0, 1));
        $role     = ucfirst($user['user_type'] ?? 'user');
        echo "<div class='sidebar-footer'>";
        echo "  <div class='sidebar-user'>";
        if (!empty($user['profile_image'])) {
            echo "<div class='sidebar-avatar'><img src='" . htmlspecialchars(app_href($user['profile_image'])) . "' alt='Avatar'></div>";
        } else {
            echo "<div class='sidebar-avatar'>" . htmlspecialchars($initials) . "</div>";
        }
        echo "    <div class='sidebar-user-info'>";
        echo "      <span class='sidebar-user-name'>" . htmlspecialchars($user['name'] ?? 'User') . "</span>";
        echo "      <span class='sidebar-user-role'>" . htmlspecialchars($role) . "</span>";
        echo "    </div>";
        echo "  </div>";
        echo "  <a href='" . htmlspecialchars(app_href('logout.php')) . "' class='sidebar-link sidebar-link-danger' data-label='Sign Out'>";
        echo "    <i class='bi bi-box-arrow-right sidebar-icon'></i>";
        echo "    <span class='sidebar-label'>Sign Out</span>";
        echo "  </a>";
        echo "</div>";
    }

    echo "</aside>";

    /* Early script: apply saved collapsed state before first paint */
    echo "<script>(function(){var s=document.getElementById('appSidebar');if(s&&localStorage.getItem('sidebar_collapsed')==='1'){s.classList.add('sidebar-collapsed');}})();</script>";

    /* ── APP CONTENT ────────────────────────────────────── */
    echo "<div class='app-content' id='appContent'>";

    /* Topbar */
    echo "<header class='app-topbar' role='banner'>";
    echo "  <button class='topbar-mobile-toggle' id='sidebarMobileToggle' aria-label='Open sidebar'>";
    echo "    <i class='bi bi-list'></i>";
    echo "  </button>";
    echo "  <div class='topbar-page-info'>";
    echo "    <p class='topbar-breadcrumb'>Exami.lk</p>";
    echo "    <h1 class='topbar-page-title'>" . htmlspecialchars($title) . "</h1>";
    echo "  </div>";
    echo "  <div class='topbar-actions'>";
    if ($user) {
        echo "    <div class='dropdown'>";
        echo "      <button class='btn btn-light btn-sm d-flex align-items-center gap-2 rounded-pill px-3' type='button' id='userDropdown' data-bs-toggle='dropdown' aria-expanded='false'>";
        if (!empty($user['profile_image'])) {
            echo "<img src='" . htmlspecialchars(app_href($user['profile_image'])) . "' class='rounded-circle' style='width:26px;height:26px;object-fit:cover;border:2px solid #fff;' alt='Profile'>";
        } else {
            $initials = strtoupper(substr($user['name'] ?? 'U', 0, 1));
            echo "        <span class='avatar-circle' style='width:26px;height:26px;font-size:11px;'>" . htmlspecialchars($initials) . "</span>";
        }
        echo "        <span class='d-none d-md-inline' style='font-size:13px;font-weight:600;'>" . htmlspecialchars($user['name'] ?? 'User') . "</span>";
        echo "        <i class='bi bi-chevron-down' style='font-size:10px;opacity:0.6;'></i>";
        echo "      </button>";
        echo "      <ul class='dropdown-menu dropdown-menu-end' aria-labelledby='userDropdown'>";
        echo "        <li><h6 class='dropdown-header'>" . htmlspecialchars($user['name'] ?? 'User') . "</h6></li>";
        if (!empty($user['email'])) {
            echo "        <li><span class='dropdown-header small text-muted' style='font-size:11px;font-weight:400;'>" . htmlspecialchars($user['email']) . "</span></li>";
        }
        echo "        <li><hr class='dropdown-divider'></li>";
        if ($isAdmin) {
            echo "        <li><a class='dropdown-item' href='" . app_href('admin/dashboard.php') . "'><i class='bi bi-speedometer2'></i>Dashboard</a></li>";
        }
        if ($isTeacher) {
            echo "        <li><a class='dropdown-item' href='" . app_href('teacher/profile.php') . "'><i class='bi bi-person'></i>Profile</a></li>";
        }
        if ($isStudent) {
            echo "        <li><a class='dropdown-item' href='" . app_href('student/dashboard.php') . "'><i class='bi bi-house-door'></i>Dashboard</a></li>";
            echo "        <li><a class='dropdown-item' href='" . app_href('student/profile.php') . "'><i class='bi bi-person'></i>Profile</a></li>";
        }
        echo "        <li><hr class='dropdown-divider'></li>";
        echo "        <li><a class='dropdown-item text-danger' href='" . app_href('logout.php') . "'><i class='bi bi-box-arrow-right'></i>Sign Out</a></li>";
        echo "      </ul>";
        echo "    </div>";
    }
    echo "  </div>";
    echo "</header>";

    /* Open Main */
    echo "<main id='main' class='app-main' role='main'>";

    /* SR live regions */
    echo "<div class='sr-live' aria-live='polite' aria-atomic='true'></div>";
    echo "<div class='sr-live-assertive' aria-live='assertive' aria-atomic='true' style='position:absolute;left:-9999px;height:1px;width:1px;overflow:hidden;'></div>";
}

/* ── render_auth_shell_start ───────────────────────────── */
function render_auth_shell_start(string $title, string $subtitle = ''): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

    /* Detect logged-in user for auth page nav */
    $authUser = null;
    if (function_exists('current_user')) {
        $authUser = current_user();
    } elseif (isset($_SESSION['user_id'])) {
        try {
            $stmt = db()->prepare('SELECT id, user_type, name FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $authUser = $stmt->fetch() ?: null;
        } catch (Throwable $e) { /* ignore */ }
    }

    $isAdmin   = $authUser && $authUser['user_type'] === 'admin';
    $isTeacher = $authUser && $authUser['user_type'] === 'teacher';
    $isStudent = $authUser && $authUser['user_type'] === 'student';

    echo "<!DOCTYPE html><html lang='en'><head>";
    echo "<meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
    echo "<title>" . htmlspecialchars($title) . " — Exami.lk</title>";
    echo "<link rel='preconnect' href='https://fonts.googleapis.com'>";
    echo "<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>";
    echo "<link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap' rel='stylesheet'>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet' integrity='sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN' crossorigin='anonymous'>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css' rel='stylesheet'>";
    echo "<link rel='stylesheet' href='" . app_href('assets/style.css') . "'>";
    echo "</head><body class='auth-body'>";

    echo "<div class='auth-shell'>";

    /* ── Left Panel ────────────────────────────────────── */
    echo "<div class='auth-left'>";
    echo "  <div>";
    echo "    <a href='" . htmlspecialchars(function_exists('app_href') ? app_href('') : '/') . "' class='auth-brand-block'>";
    echo "      <img src='" . app_href('assets/logoexami.png') . "' class='auth-brand-logo' alt='Exami.lk'>";
    echo "      <div>";
    echo "        <span class='auth-brand-name'>Exami.lk</span>";
    echo "        <span class='auth-brand-sub'>The Smart Way to Learn</span>";
    echo "      </div>";
    echo "    </a>";
    echo "    <h1 class='auth-headline'>" . htmlspecialchars($title) . "<br><strong>with Exami.lk</strong></h1>";
    if ($subtitle !== '') {
        echo "    <p class='auth-tagline'>" . htmlspecialchars($subtitle) . "</p>";
    }
    echo "    <ul class='auth-feature-list'>";
    echo "      <li><div class='auth-feature-icon'><i class='bi bi-patch-check-fill'></i></div>Practice real exam-style MCQs</li>";
    echo "      <li><div class='auth-feature-icon'><i class='bi bi-graph-up-arrow'></i></div>Track your progress across papers</li>";
    echo "      <li><div class='auth-feature-icon'><i class='bi bi-people-fill'></i></div>Join your class with teacher codes</li>";
    echo "      <li><div class='auth-feature-icon'><i class='bi bi-shield-check'></i></div>Exam integrity monitoring built-in</li>";
    echo "    </ul>";
    echo "  </div>";
    echo "  <div class='auth-left-bottom'>";
    echo "    <div class='auth-stat-row'>";
    echo "      <div class='auth-stat'><span class='auth-stat-number'>100%</span><span class='auth-stat-label'>Sri Lankan curriculum</span></div>";
    echo "      <div class='auth-stat'><span class='auth-stat-number'>Free</span><span class='auth-stat-label'>To get started</span></div>";
    echo "    </div>";
    echo "  </div>";
    echo "</div>"; /* /auth-left */

    /* ── Right Panel ───────────────────────────────────── */
    echo "<div class='auth-right'>";

    /* Topbar on right panel */
    echo "  <div class='auth-topbar'>";
    echo "    <a href='" . htmlspecialchars(function_exists('app_href') ? app_href('') : '/') . "' class='auth-topbar-brand'>";
    echo "      <img src='" . app_href('assets/logoexami.png') . "' alt='Exami.lk'>";
    echo "      <span class='auth-topbar-brand-name'>Exami.lk</span>";
    echo "    </a>";
    echo "    <nav class='auth-topbar-nav'>";
    if ($authUser) {
        $home = $isStudent ? 'student/dashboard.php' : ($isTeacher ? 'teacher/manage_papers.php' : ($isAdmin ? 'admin/dashboard.php' : ''));
        if ($home !== '') {
            echo "      <a class='btn btn-sm btn-outline-primary' href='" . htmlspecialchars(app_href($home)) . "'><i class='bi bi-house-door'></i> Home</a>";
        }
        echo "      <a class='btn btn-sm btn-outline-secondary' href='" . htmlspecialchars(app_href('logout.php')) . "'><i class='bi bi-box-arrow-right'></i> Logout</a>";
    } else {
        echo "      <a class='btn btn-sm btn-outline-secondary' href='" . htmlspecialchars(app_href('login.php')) . "'><i class='bi bi-box-arrow-in-right'></i> Sign In</a>";
        echo "      <a class='btn btn-sm btn-primary' href='" . htmlspecialchars(app_href('register.php')) . "'><i class='bi bi-person-plus'></i> Register</a>";
    }
    echo "    </nav>";
    echo "  </div>";

    /* Form area */
    echo "  <div class='auth-form-area'>";
    echo "    <div class='auth-form-inner'>";
    echo "      <div class='auth-form-heading'>";
    echo "        <h2 class='auth-form-title'>" . htmlspecialchars($title) . "</h2>";
    if ($subtitle !== '') {
        echo "        <p class='auth-form-sub'>" . htmlspecialchars($subtitle) . "</p>";
    }
    echo "      </div>";
}

/* ── render_auth_shell_end ─────────────────────────────── */
function render_auth_shell_end(): void {
    echo "    </div>"; /* /auth-form-inner */
    echo "  </div>";   /* /auth-form-area */

    /* Footer bar */
    echo "  <div class='auth-footer-bar'>";
    echo "    <span>&copy; " . date('Y') . " Exami.lk &mdash; Built by <a href='https://wa.me/94751534972' target='_blank' rel='noreferrer'>Ecodez Digital Solution</a></span>";
    echo "    <div class='d-flex gap-3'>";
    echo "      <a href='mailto:hello@exami.lk'><i class='bi bi-envelope me-1'></i>Support</a>";
    echo "      <a href='https://wa.me/94751534972' target='_blank' rel='noreferrer'><i class='bi bi-whatsapp me-1' style='color:#25D366;'></i>0751534972</a>";
    echo "    </div>";
    echo "  </div>";

    echo "</div>"; /* /auth-right */
    echo "</div>"; /* /auth-shell */

    echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js' integrity='sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL' crossorigin='anonymous'></script>";
    echo "<script>(function(){function focusFirstAlert(){const alerts=document.querySelectorAll('.alert[role]');for(const alert of alerts){if(alert.offsetParent===null) continue;if(!alert.hasAttribute('tabindex')){alert.setAttribute('tabindex','-1');}alert.focus();break;}}document.addEventListener('DOMContentLoaded',focusFirstAlert);})();</script>";
    echo "</body></html>";
}

/* ── render_footer ─────────────────────────────────────── */
function render_footer(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

    echo "</main>"; /* /app-main */

    /* Mini footer */
    echo "<footer class='app-footer' role='contentinfo'>";
    echo "  <span>&copy; " . date('Y') . " Exami.lk. All rights reserved.</span>";
    echo "  <span>Built by <a href='https://wa.me/94751534972' target='_blank' rel='noreferrer'>Ecodez Digital Solution</a> &nbsp;";
    echo "    <a href='https://wa.me/94751534972' target='_blank' rel='noreferrer'><i class='bi bi-whatsapp'></i></a></span>";
    echo "</footer>";

    echo "</div>"; /* /app-content */
    echo "</div>"; /* /app-shell */

    echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js' integrity='sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL' crossorigin='anonymous'></script>";

    /* Sidebar toggle script */
    echo "<script>
(function() {
    'use strict';
    const sidebar      = document.getElementById('appSidebar');
    const overlay      = document.getElementById('sidebarOverlay');
    const desktopToggle = document.getElementById('sidebarToggle');
    const mobileToggle  = document.getElementById('sidebarMobileToggle');
    if (!sidebar) return;

    /* Desktop — collapse / expand */
    if (desktopToggle) {
        desktopToggle.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('sidebar-collapsed') ? '1' : '0');
        });
    }

    /* Mobile — slide in / out */
    function openMobile() {
        sidebar.classList.add('mobile-open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeMobile() {
        sidebar.classList.remove('mobile-open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (mobileToggle) mobileToggle.addEventListener('click', function() {
        sidebar.classList.contains('mobile-open') ? closeMobile() : openMobile();
    });

    if (overlay) overlay.addEventListener('click', closeMobile);

    /* Close on Escape */
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMobile();
    });

    /* Alert focus for SR */
    function focusFirstAlert() {
        const alerts = document.querySelectorAll('.alert[role]');
        for (const alert of alerts) {
            if (alert.offsetParent === null) continue;
            if (!alert.hasAttribute('tabindex')) alert.setAttribute('tabindex', '-1');
            alert.focus();
            break;
        }
    }
    function announceAlerts() {
        const region = document.querySelector('.sr-live');
        if (!region) return;
        document.querySelectorAll('.alert[role]').forEach(a => { region.textContent = a.textContent; });
    }
    document.addEventListener('DOMContentLoaded', function() {
        announceAlerts();
        focusFirstAlert();
    });
})();
</script>";

    /* Theme toggle (kept for compat) */
    echo "<script>(function(){
const tBtn=document.getElementById('toggleTheme');
const cBtn=document.getElementById('toggleContrast');
const root=document.body;
function applyStored(){const m=localStorage.getItem('mode');if(m==='light'){root.classList.add('light');}if(m==='dark'){root.classList.remove('light');}const hc=localStorage.getItem('hc');if(hc==='1'){root.classList.add('hc');}}
applyStored();
tBtn&&tBtn.addEventListener('click',function(){const isLight=root.classList.toggle('light');localStorage.setItem('mode', isLight?'light':'dark');tBtn.setAttribute('aria-pressed', isLight);});
cBtn&&cBtn.addEventListener('click',function(){const isHC=root.classList.toggle('hc');localStorage.setItem('hc', isHC?'1':'0');cBtn.setAttribute('aria-pressed', isHC);});
})();</script>";

    echo "</body></html>";
}

/* ── nav_link (legacy helper, kept for compat) ─────────── */
function nav_link(string $href, string $label): string {
    if (function_exists('app_href')) {
        $normalized = ltrim($href, '/');
        $href = app_href($normalized);
    }
    $current  = $_SERVER['REQUEST_URI'] ?? '';
    $isActive = ($href !== '' && strpos($current, $href) === 0);
    $classes  = 'nav-link d-flex align-items-center gap-1' . ($isActive ? ' active' : '');
    if (strpos($label, '<i') === false) {
        $iconMap = [
            'Home' => 'house', 'Login' => 'box-arrow-in-right', 'Logout' => 'box-arrow-right',
            'My Papers' => 'journal', 'Create Paper' => 'plus-circle', 'Preapproved' => 'people',
            'Teachers' => 'person-badge', 'Audit Logs' => 'journal-text'
        ];
        $key  = trim(strip_tags($label));
        $icon = $iconMap[$key] ?? 'star';
        $label = "<i class='bi bi-{$icon}'></i> " . htmlspecialchars($key);
    }
    return "<li class='nav-item'><a class='{$classes}' href='" . htmlspecialchars($href) . "'>{$label}</a></li>";
}

/* ── render_welcome_banner ─────────────────────────────── */
function render_welcome_banner(?array $user = null): void {
    if ($user === null && isset($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT id, name, user_type FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    if (!$user) return;

    $firstName = htmlspecialchars(explode(' ', $user['name'])[0]);

    if ($user['user_type'] === 'teacher') {
        $icon    = 'easel2';
        $message = "Your papers, students, and metrics at a glance.";
    } elseif ($user['user_type'] === 'admin') {
        $icon    = 'shield-check';
        $message = "Manage users, monitor activity, and oversee the platform.";
    } else {
        $icon    = 'stars';
        $message = "Your classes, teachers, and papers at a glance.";
    }

    echo "<div class='welcome-banner'>";
    echo "  <div class='welcome-banner-icon'><i class='bi bi-{$icon}'></i></div>";
    echo "  <div>";
    echo "    <h1>Welcome back, {$firstName}!</h1>";
    echo "    <p>{$message}</p>";
    echo "  </div>";
    echo "</div>";
}
