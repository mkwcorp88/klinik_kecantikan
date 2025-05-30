<?php
require_once 'config.php';
$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_lengkap = $conn->real_escape_string(trim($_POST['nama_lengkap']));
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $conn->real_escape_string(trim($_POST['email']));
    $no_telepon = $conn->real_escape_string(trim($_POST['no_telepon']));
    $alamat = $conn->real_escape_string(trim($_POST['alamat']));

    // Validasi dasar
    if (empty($nama_lengkap)) $errors[] = "Nama lengkap wajib diisi.";
    if (empty($username)) $errors[] = "Username wajib diisi.";
    if (empty($password)) $errors[] = "Password wajib diisi.";
    if ($password !== $confirm_password) $errors[] = "Konfirmasi password tidak cocok.";
    if (empty($no_telepon)) $errors[] = "Nomor telepon wajib diisi.";
    if (empty($alamat)) $errors[] = "Alamat wajib diisi.";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format email tidak valid.";

    // Cek username sudah ada atau belum
    if (empty($errors)) {
        $stmt_check = $conn->prepare("SELECT id_user FROM user WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $errors[] = "Username sudah terdaftar. Silakan gunakan username lain.";
        }
        $stmt_check->close();
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $email_val = !empty($email) ? $email : NULL;

        $stmt = $conn->prepare("INSERT INTO user (nama_lengkap, username, password, email, no_telepon, alamat) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $nama_lengkap, $username, $hashed_password, $email_val, $no_telepon, $alamat);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Pendaftaran berhasil! Silakan login.";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Pendaftaran gagal: " . $stmt->error;
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Member - <?php echo NAMA_KLINIK; ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php"><?php echo NAMA_KLINIK; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="layanan_tampil.php">Layanan</a></li>
                    <li class="nav-item"><a class="nav-link" href="testimoni_tampil.php">Testimoni</a></li>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="daftar.php">Daftar</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Form Pendaftaran Member</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors as $error): ?>
                                    <p class="mb-0"><?php echo $error; ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success">
                                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                            </div>
                        <?php endif; ?>

                        <form action="daftar.php" method="post">
                            <div class="mb-3">
                                <label for="nama_lengkap" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required value="<?php echo isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email (Opsional)</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="no_telepon" class="form-label">Nomor Telepon <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="no_telepon" name="no_telepon" required value="<?php echo isset($_POST['no_telepon']) ? htmlspecialchars($_POST['no_telepon']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="alamat" class="form-label">Alamat <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="alamat" name="alamat" rows="3" required><?php echo isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : ''; ?></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Daftar</button>
                            </div>
                        </form>
                        <p class="mt-3 text-center">Sudah punya akun? <a href="login.php">Login di sini</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white text-center py-4 mt-auto">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> <?php echo NAMA_KLINIK; ?>. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>