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

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['exam_id'])) {
    $exam_id = $_POST['exam_id'];

    // التحقق مما إذا كان المستخدم قد قدم الامتحان بالفعل
    $check_query = $conn->prepare("SELECT * FROM user_answers WHERE user_id = ? AND exam_id = ?");
    $check_query->bind_param("ii", $user_id, $exam_id);
    $check_query->execute();
    $result = $check_query->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('لقد قمت بإرسال هذا الامتحان من قبل!'); window.location.href = 'user_dashboard.php';</script>";
        exit();
    }

    // إدراج الإجابات في قاعدة البيانات
    $stmt = $conn->prepare("INSERT INTO user_answers (user_id, exam_id, question_id, user_answer) VALUES (?, ?, ?, ?)");

    foreach ($_POST['answer'] as $question_id => $user_answer) {
        $stmt->bind_param("iiis", $user_id, $exam_id, $question_id, $user_answer);
        $stmt->execute();
    }

    // إغلاق الاتصال
    $stmt->close();
    $conn->close();

    // عرض رسالة نجاح ثم إعادة التوجيه إلى صفحة `user_dashboard.php`
    echo "<script>
            alert('✅ تم إرسال إجاباتك بنجاح!');
            window.location.href = 'user_dashboard.php';
          </script>";
    exit();
} else {
    header("Location: user_dashboard.php");
    exit();
}
?>
