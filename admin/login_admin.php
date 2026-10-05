<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin_auth.php';

$errors = [];
$csrfToken = drw_csrf_token();

// Pastikan sesi lama masih memiliki izin sebelum masuk ke dashboard.
if (drw_admin_session_valid($conn)) {
    header('Location: ' . (drw_admin_is_super() ? 'aido_dashboard.php' : 'index.php'));
    exit();
}
drw_admin_clear_session();
$flash = drw_consume_flash();

$branches = drw_admin_fetch_branches($conn);
$branchesById = [];
foreach ($branches as $branch) {
    $branchesById[(int) $branch['id_cabang']] = $branch;
}

$selectedCabang = isset($_POST['id_cabang']) ? trim((string) $_POST['id_cabang']) : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!drw_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesi formulir telah berakhir. Silakan muat ulang halaman dan coba lagi.';
    }
    if (empty($username)) {
        $errors[] = "Username wajib diisi.";
    }
    if (empty($password)) {
        $errors[] = "Password wajib diisi.";
    }

    $chosenCabangId = null;
    $chosenCabangNama = 'Semua Klinik';
    if ($selectedCabang !== '') {
        if (!ctype_digit($selectedCabang) || !isset($branchesById[(int) $selectedCabang])) {
            $errors[] = 'Pilihan klinik tidak valid.';
        } else {
            $chosenCabangId = (int) $selectedCabang;
            $chosenCabangNama = (string) $branchesById[$chosenCabangId]['nama_cabang'];
        }
    }

    if (empty($errors)) {
        $loginOk = false;
        $sessionAdminId = null;
        $sessionIsSuper = false;
        $sessionCabangId = $chosenCabangId;
        $sessionCabangNama = $chosenCabangNama;

        // 1) Coba akun admin per klinik dari database.
        $dbAdmin = drw_admin_login_db_account($conn, $username);
        if (is_array($dbAdmin)
            && ($dbAdmin['status_admin'] ?? '') === 'aktif'
            && is_string($dbAdmin['password_hash'])
            && $dbAdmin['password_hash'] !== ''
            && password_verify($password, $dbAdmin['password_hash'])) {
            $adminCabangId = $dbAdmin['id_cabang'] !== null ? (int) $dbAdmin['id_cabang'] : null;
            if ($adminCabangId === null) {
                if (drw_admin_email_is_owner($dbAdmin['google_email'])) {
                    // Hanya akun pemilik boleh memperoleh hak superadmin.
                    $loginOk = true;
                    $sessionAdminId = (int) $dbAdmin['id_admin'];
                    $sessionIsSuper = true;
                } else {
                    $errors[] = 'Akun admin tidak diizinkan.';
                }
            } else {
                if ($chosenCabangId === null || $chosenCabangId !== $adminCabangId) {
                    $allowedNama = $branchesById[$adminCabangId]['nama_cabang'] ?? 'klinik Anda';
                    $errors[] = 'Akun ini hanya untuk ' . $allowedNama . '. Pilih klinik tersebut saat login.';
                } else {
                    $loginOk = true;
                    $sessionAdminId = (int) $dbAdmin['id_admin'];
                    $sessionIsSuper = false;
                    $sessionCabangId = $adminCabangId;
                    $sessionCabangNama = (string) ($branchesById[$adminCabangId]['nama_cabang'] ?? $chosenCabangNama);
                }
            }
        } elseif ($dbAdmin === null) {
            // 2) Fallback akun superadmin lama dari env/config (masa transisi).
            $passwordIsValid = ADMIN_PASSWORD_HASH !== '' && password_verify($password, ADMIN_PASSWORD_HASH);
            $legacyLocalPasswordIsValid = APP_ENV === 'local'
                && ADMIN_PASSWORD_PLAIN !== ''
                && hash_equals(ADMIN_PASSWORD_PLAIN, $password);

            if ($username === ADMIN_USERNAME && drw_admin_email_is_owner($username) && ($passwordIsValid || $legacyLocalPasswordIsValid)) {
                $loginOk = true;
                $sessionAdminId = null;
                $sessionIsSuper = true;
            } else {
                $errors[] = "Username, password, atau pilihan klinik salah.";
            }
        } else {
            $errors[] = "Username, password, atau pilihan klinik salah.";
        }

        if ($loginOk && empty($errors)) {
            drw_admin_set_session($sessionAdminId, $username, $sessionCabangId, $sessionCabangNama, $sessionIsSuper);
            header('Location: ' . ($sessionIsSuper ? 'aido_dashboard.php' : 'index.php'));
            exit();
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk Admin | <?php echo htmlspecialchars(NAMA_KLINIK, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link href="../css/home.css" rel="stylesheet">
    <link href="../css/site.css?v=11" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        .admin-auth-page {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-family: "DM Sans", sans-serif;
        }

        .admin-auth-header {
            background: #fff;
            border-bottom: 1px solid var(--drw-line);
        }

        .admin-auth-header .container {
            align-items: center;
            display: flex;
            justify-content: space-between;
            min-height: 5rem;
        }

        .admin-auth-header a:last-child {
            color: var(--drw-sage-deep);
            font-size: 0.88rem;
            font-weight: 700;
        }

        .admin-auth-footer {
            background: #fff;
            border-top: 1px solid var(--drw-line);
            color: var(--drw-muted);
            font-size: 0.85rem;
            padding: 1.25rem 0;
            text-align: center;
        }

        .admin-auth-page .drw-auth-form .form-select {
            border: 1px solid #c9dfef;
            border-radius: 0.7rem;
            min-height: 3rem;
            padding: 0.7rem 0.85rem;
        }

        .admin-auth-page .drw-auth-form .form-select:focus {
            border-color: #4eaae0;
            box-shadow: 0 0 0 0.22rem rgba(51, 143, 206, 0.15);
        }

        .admin-auth-page .drw-auth-form .form-text {
            color: var(--drw-muted);
            margin-top: 0.4rem;
        }

        @media (max-width: 575.98px) {
            .admin-auth-header .container {
                min-height: 4.5rem;
            }

            .admin-auth-header a:last-child {
                font-size: 0.78rem;
            }
        }
    </style>
</head>
<body class="home-page auth-page admin-auth-page">
    <header class="admin-auth-header">
        <div class="container">
            <a href="../index.php" aria-label="Beranda <?php echo htmlspecialchars(NAMA_KLINIK, ENT_QUOTES, 'UTF-8'); ?>">
                <img class="drw-logo-image" src="../images/klinik-pratama-drw-estetika-logo.png" alt="<?php echo htmlspecialchars(NAMA_KLINIK, ENT_QUOTES, 'UTF-8'); ?>" width="1500" height="415">
            </a>
            <a href="../index.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembali ke website</a>
        </div>
    </header>

    <main class="drw-auth-page-main">
        <section class="drw-auth-shell">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-xl-10">
                        <div class="drw-auth-card">
                            <aside class="drw-auth-aside">
                                <span class="drw-hero-label">AREA ADMIN</span>
                                <h1>Kelola klinik dengan <em>satu akses.</em></h1>
                                <p>Masuk untuk memantau aktivitas klinik dan data sesuai kewenangan akun Anda.</p>
                                <ul class="drw-auth-benefits">
                                    <li><i class="fa-solid fa-chart-line" aria-hidden="true"></i> Pantau ringkasan klinik</li>
                                    <li><i class="fa-regular fa-calendar-check" aria-hidden="true"></i> Kelola booking dan transaksi</li>
                                    <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Akses cabang sesuai akun</li>
                                </ul>
                            </aside>

                            <section class="drw-auth-form-panel" aria-labelledby="admin-login-title">
                                <span class="drw-hero-label">MASUK ADMIN</span>
                                <h2 id="admin-login-title">Selamat datang kembali.</h2>
                                <p class="drw-auth-intro">Gunakan akun Google admin yang sudah terdaftar untuk melanjutkan.</p>

                                <?php if ($flash !== null): ?>
                                    <div class="drw-auth-alert is-<?php echo htmlspecialchars(in_array($flash['type'], ['success', 'warning', 'info', 'danger'], true) ? $flash['type'] : 'info', ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                                        <span><?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($errors !== []): ?>
                                    <div class="drw-auth-alert is-danger" role="alert">
                                        <div>
                                            <?php foreach ($errors as $error): ?>
                                                <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (($_GET['pesan'] ?? '') === 'belum_login_admin'): ?>
                                    <div class="drw-auth-alert is-warning" role="alert">Anda harus masuk sebagai admin untuk mengakses halaman tersebut.</div>
                                <?php endif; ?>

                                <?php if (GOOGLE_OAUTH_CONFIGURED): ?>
                                    <a class="drw-google-button" href="../auth/google_start.php?mode=admin"><i class="fa-brands fa-google" aria-hidden="true"></i> Lanjutkan dengan Google</a>
                                    <details class="drw-legacy-login"<?php echo $errors !== [] ? ' open' : ''; ?>>
                                        <summary>Login dengan akun admin lama</summary>
                                        <p>Gunakan ini jika akun admin Anda masih menggunakan username dan password.</p>
                                <?php else: ?>
                                    <div class="drw-auth-alert is-info" role="status"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Login Google sedang disiapkan. Akun admin lama tetap dapat digunakan di bawah ini.</span></div>
                                <?php endif; ?>

                                <form class="drw-auth-form" action="login_admin.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div>
                                        <label for="id_cabang" class="form-label">Pilih Klinik</label>
                                        <select class="form-select" id="id_cabang" name="id_cabang">
                                            <option value="" <?php echo $selectedCabang === '' ? 'selected' : ''; ?>>Semua Klinik (superadmin)</option>
                                            <?php foreach ($branches as $branch): ?>
                                                <option value="<?php echo (int) $branch['id_cabang']; ?>" <?php echo $selectedCabang === (string) $branch['id_cabang'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars((string) $branch['nama_cabang'], ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Admin cabang wajib memilih kliniknya masing-masing.</div>
                                    </div>
                                    <div>
                                        <label for="username" class="form-label">Username Admin</label>
                                        <input type="text" class="form-control" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars((string) $_POST['username'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                    </div>
                                    <div>
                                        <label for="password" class="form-label">Password Admin</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                    <button type="submit" class="btn drw-auth-submit w-100">Masuk</button>
                                </form>

                                <?php if (GOOGLE_OAUTH_CONFIGURED): ?>
                                    </details>
                                <?php endif; ?>

                                <p class="drw-auth-admin"><a href="../login.php">Masuk sebagai Pasien <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></p>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="admin-auth-footer">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(NAMA_KLINIK, ENT_QUOTES, 'UTF-8'); ?>. Area Admin.</p>
        </div>
    </footer>
    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
