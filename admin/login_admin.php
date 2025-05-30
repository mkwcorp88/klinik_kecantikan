<?php
require_once '../config.php'; // Path ke config.php dari dalam folder admin

$errors = [];

// Jika sudah login sebagai admin, redirect ke dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password']; // Password plain text untuk perbandingan

    if (empty($username)) {
        $errors[] = "Username wajib diisi.";
    }
    if (empty($password)) {
        $errors[] = "Password wajib diisi.";
    }

    if (empty($errors)) {
        if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD_PLAIN) {
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
</head>
<body>
    <div class="main-content container py-5">
        <div class="card shadow-lg">
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

    <footer class="bg-dark text-white text-center py-3 mt-auto">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> <?php echo NAMA_KLINIK; ?>. Admin Area.</p>
        </div>
    </footer>
    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>