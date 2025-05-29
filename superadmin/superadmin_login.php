<?php
require_once "../config/db_config.php";
require_once "../vendor/autoload.php"; // Include Composer autoload
require_once "../config.php";

use Firebase\JWT\JWT;

    $secret_key = JWT_WEB_TOKEN;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT * FROM super_admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin["password"])) {

            $issuedAt   = time();
            $expire     = $issuedAt + (60 * 60); // 1 hour

            $payload = [
                'iat' => $issuedAt,
                'exp' => $expire,
                'uid' => $admin["id"],
                'username' => $admin["username"]
            ];

            $jwt = JWT::encode($payload, $secret_key, 'HS256');

            // Set token in a secure cookie
            setcookie("super_admin_token", $jwt, [
                'expires' => $expire,
                'httponly' => true,
                'samesite' => 'Strict',
                'secure' => true
            ]);


             echo $_COOKIE['super_admin_token'] ?? 'No token';

            header("Location: superadmin_dashboard.php");
            exit();


        }
    }

    $error = "Invalid credentials";
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stall Administrator Portal</title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #FF6B6B;
            --dark-color: #2D3436;
            --light-color: #F9F9F9;
            --header-primary: #00674F
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--dark-color);
            height: 100vh;
            display: flex;
            align-items: center;
        }

        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            margin: 0 auto;
        }

        .login-header {
            background: var(--header-primary);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .login-title {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .logo-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .stall-logo {
            width: 410px;
            height: auto;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .login-body {
            padding: 2rem;
        }

        .input-group-icon {
            width: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light-color);
            border-right: none !important;
        }

        .form-control {
            border-left: none;
            padding-left: 0;
        }

        .password-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            opacity: 0.8;
        }

        .btn-login {
            background: var(--header-primary);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-login:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <h2 class="login-title"><i class="fas fa-user-shield"></i> Super Admin Login</h2>
            </div>
            <div class="login-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-icon">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input type="text" class="form-control" name="username" required
                                placeholder="Enter username" value="<?= htmlspecialchars(
                                    $_POST["username"] ?? ""
                                ) ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-icon">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" required
                                placeholder="Enter Password">
                            <span class="input-group-icon password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye-slash" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
            }
        }
    </script>
</body>

</html>
