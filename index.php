<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "my_database";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("فشل الاتصال بقاعدة البيانات: " . $conn->connect_error);
}

if (isset($_POST['login'])) {
    $code = $_POST['code'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE code='$code' AND password='$password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['job'] = $user['job'];

        if ($user['role'] == 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: user_dashboard.php");
        }
        exit();
    } else {
        $error = "بيانات الدخول غير صحيحة!";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            font-size: 24px;
            margin-bottom: 20px;
        }
        .form-label {
            font-size: 14px;
        }
        .btn {
            font-size: 16px;
            padding: 12px;
        }
        .alert {
            margin-bottom: 20px;
        }
        .login-container img {
            width: 80px;
            height: auto;
            margin-bottom: 20px;
        }

        @media (max-width: 576px) {
            .login-container {
                padding: 20px;
                max-width: 90%;
            }
            h2 {
                font-size: 20px;
            }
            .btn {
                font-size: 14px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="" alt="Logo" class="d-block mx-auto">
        <h2 class="text-center mb-4">Garnell</h2>

        <h2 class="text-center mb-4">تسجيل الدخول</h2>
        
        <?php if (isset($error)) { ?>
            <div class="alert alert-danger text-center"><?= $error ?></div>
        <?php } ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">الكود:</label>
                <input type="text" name="code" class="form-control" placeholder="أدخل الكود" required>
            </div>
            <div class="mb-3">
                <label class="form-label">كلمة المرور:</label>
                <input type="password" name="password" class="form-control" placeholder="كلمة المرور" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100">تسجيل الدخول</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
