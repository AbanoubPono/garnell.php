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

// التحقق من تسجيل الدخول كأدمن
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// استعلام البحث عن نتائج المستخدم بناءً على كود المستخدم
$user_code_search = "";
$whereClause = "";
$params = [];
$types = "";

// إذا تم إدخال كود المستخدم، يتم تطبيق البحث
if (isset($_GET['user_code']) && !empty($_GET['user_code'])) {
    $user_code_search = trim($_GET['user_code']);
    $whereClause = " WHERE u.code LIKE ?";
    $params[] = "%" . $user_code_search . "%"; // البحث الجزئي
    $types .= "s";
}

// تحضير الاستعلام لجلب بيانات المستخدمين ونتائج الامتحانات
$query = "
    SELECT u.id AS user_id, u.name AS user_name, u.code AS user_code, e.exam_name AS exam_title, 
           COUNT(CASE WHEN q.type != 'text' THEN q.id END) AS total_questions, -- استبعاد الأسئلة النصية من العد
           SUM(CASE WHEN q.type != 'text' AND q.correct_answer = ua.user_answer THEN 1 ELSE 0 END) AS correct_answers,
           GROUP_CONCAT(CASE WHEN q.type = 'text' THEN CONCAT(q.question, ' | ', ua.user_answer) END SEPARATOR '||') AS text_answers -- تجميع الأسئلة النصية مع إجابات المستخدم
    FROM user_answers ua
    JOIN questions q ON ua.question_id = q.id
    JOIN exams e ON ua.exam_id = e.id
    JOIN users u ON ua.user_id = u.id
    $whereClause
    GROUP BY ua.user_id, ua.exam_id
    ORDER BY u.name, e.exam_name
";

$stmt = $conn->prepare($query);

// ربط المعاملات إذا تم إدخال كود المستخدم
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>نتائج الامتحانات - لوحة التحكم</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<a href="logout.php">تسجيل الخروج</a>

<div class="container mt-4">
    <h2 class="text-center">📊 نتائج الامتحانات</h2>

    <!-- نموذج البحث -->
    <form method="GET" class="d-flex justify-content-center mb-4">
        <input type="text" name="user_code" class="form-control w-25" placeholder="🔍 أدخل كود المستخدم" value="<?= htmlspecialchars($user_code_search) ?>">
        <button type="submit" class="btn btn-primary ms-2">🔍 بحث</button>
        <a href="exam_results.php" class="btn btn-secondary ms-2">🔄 عرض الكل</a>
    </form>

    <?php if ($result->num_rows > 0): ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>👤 اسم المستخدم</th>
                    <th>🆔 كود المستخدم</th>
                    <th>📑 اسم الامتحان</th>
                    <th>❓ عدد الأسئلة</th>
                    <th>✅ الإجابات الصحيحة</th>
                    <th>📊 النسبة المئوية</th>
                    <th>✍️ الإجابات النصية</th> <!-- زر عرض الإجابات النصية -->
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): 
                    $score_percentage = ($row['total_questions'] > 0) ? round(($row['correct_answers'] / $row['total_questions']) * 100, 2) : 0;
                    $modal_id = "textAnswersModal_" . $row['user_id']; // تعريف ID لكل مستخدم للنافذة المنبثقة
                ?>
                    <tr>
                        <td><?= htmlspecialchars($row['user_name']) ?></td>
                        <td><?= htmlspecialchars($row['user_code']) ?></td>
                        <td><?= htmlspecialchars($row['exam_title']) ?></td>
                        <td><?= $row['total_questions'] ?></td>
                        <td><?= $row['correct_answers'] ?></td>
                        <td><?= $score_percentage ?>%</td>
                        <td>
                            <?php if (!empty($row['text_answers'])): ?>
                                <!-- زر عرض الإجابات النصية -->
                                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#<?= $modal_id ?>">📄 عرض</button>
                                
                                <!-- النافذة المنبثقة لعرض الإجابات النصية -->
                                <div class="modal fade" id="<?= $modal_id ?>" tabindex="-1" aria-labelledby="modalLabel_<?= $row['user_id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="modalLabel_<?= $row['user_id'] ?>">✍️ إجابات <?= htmlspecialchars($row['user_name']) ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                                            </div>
                                            <div class="modal-body">
                                                <?php 
                                                    $text_answers = explode('||', $row['text_answers']);
                                                    foreach ($text_answers as $answer) {
                                                        list($question_text, $user_answer) = explode('|', $answer);
                                                        echo "<p><strong>📝 السؤال:</strong> " . htmlspecialchars($question_text) . "</p>";
                                                        echo "<p><strong>✅ إجابة المستخدم:</strong> " . htmlspecialchars($user_answer) . "</p>";
                                                        echo "<hr>";
                                                    }
                                                ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                ❌ لا توجد إجابات نصية
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-center">❌ لا يوجد أي نتائج.</p>
    <?php endif; ?>
</div>

<div class="text-center mt-4">
    <a href="admin_dashboard.php" class="btn btn-secondary">🏠 العودة للوحة التحكم</a>
</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
