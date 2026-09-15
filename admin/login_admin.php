<?php
require_once '../config.php'; // Path ke config.php dari dalam folder admin

$errors = [];
$csrfToken = drw_csrf_token();

// Jika sudah login sebagai admin, redirect ke dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

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

    if (empty($errors)) {
        $passwordIsValid = ADMIN_PASSWORD_HASH !== '' && password_verify($password, ADMIN_PASSWORD_HASH);
        $legacyLocalPasswordIsValid = APP_ENV === 'local'
            && ADMIN_PASSWORD_PLAIN !== ''
            && hash_equals(ADMIN_PASSWORD_PLAIN, $password);

        if ($username === ADMIN_USERNAME && ($passwordIsValid || $legacyLocalPasswordIsValid)) {
            unset(
                $_SESSION['csrf_token'],
                $_SESSION['user_id'],
                $_SESSION['username'],
                $_SESSION['display_name'],
                $_SESSION['user_auth_provider'],
                $_SESSION['post_login_redirect']
            );
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header("Location: index.php"); // Arahkan ke dashboard admin
            exit();
        } else {
            $errors[] = "Username atau password admin salah.";
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin - <?php echo NAMA_KLINIK; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .card {
            width: 100%;
            max-width: 400px;
        }
        .main-content {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
    <link href="../css/admin-theme.css" rel="stylesheet">
</head>
<body class="admin-login-body">
    <div class="main-content container py-5">
        <div class="card shadow-lg admin-login-card">
            <div class="card-header bg-dark text-white text-center">
                <h4 class="text-white">Login Administrator</h4>
                <small><?php echo NAMA_KLINIK; ?></small>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-0"><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'belum_login_admin'): ?>
                    <div class="alert alert-warning">Anda harus login sebagai admin untuk mengakses halaman tersebut.</div>
                <?php endif; ?>

                <form action="login_admin.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username Admin</label>
                        <input type="text" class="form-control" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password Admin</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>
                 <p class="mt-3 text-center"><a href="../index.php">Kembali ke Halaman Utama</a></p>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-3 mt-auto admin-login-footer">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> <?php echo NAMA_KLINIK; ?>. Admin Area.</p>
        </div>
    </footer>
    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
