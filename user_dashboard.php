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

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: login.php");
    exit();
}


// منع تخزين الصفحة في المتصفح بعد تسجيل الخروج
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$user_name = $_SESSION['name'];
$user_job = $_SESSION['job']; // الوظيفة الخاصة بالمستخدم

// جلب الملفات المتاحة لوظيفة المستخدم
$sql = "SELECT * FROM files WHERE allowed_job = '$user_job' ORDER BY category";
$result = $conn->query($sql);

$categories = []; // مصفوفة لتخزين الملفات حسب التصنيف

if ($result) {
    while ($file = $result->fetch_assoc()) {
        $category = $file['category'] ?? 'other'; // تجنب الأخطاء في حالة عدم وجود تصنيف
        $categories[$category][] = $file;
    }
} else {
    echo "❌ خطأ في جلب الملفات: " . $conn->error;
}

// جلب الامتحانات المسموح بها للمستخدم
$exams = [];
$sql = "SELECT id, exam_name FROM exams WHERE allowed_roles = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_job);
$stmt->execute();
$result = $stmt->get_result();

while ($exam = $result->fetch_assoc()) {
    $exams[] = $exam;
}
$stmt->close();

?>



<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>صفحة المستخدم</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="./Garnell.web/style.css">
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 20px; }
        .file-container { width: 80%; margin: auto; display: flex; flex-wrap: wrap; justify-content: center; gap: 20px; }
        .file-box { border: 1px solid #ddd; padding: 10px; width: 45%; text-align: center; }
        iframe, video { width: 100%; height: 250px; }
    </style>
</head>
<body>

    <h2>مرحبًا، <?= $user_name ?> 👋</h2>
    <p>وظيفتك: <strong><?= ucfirst($user_job) ?></strong></p>
    

    <h3>: الملفات المتاحة لك📂 </h3>
<div class="btn-group mb-3 ">
    <button class="btn btn-primary" onclick="showCategory('pdf')">📄 PDF</button>
    <button class="btn btn-success" onclick="showCategory('video')">🎥 الفيديوهات</button>
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#examModal">📑 الامتحان</button>
</div>

<div class="file-container" id="filesContainer">
    <?php foreach ($categories as $category => $files): ?>
        <?php foreach ($files as $file): ?>
            <?php 
                $file_ext = pathinfo($file['file_path'], PATHINFO_EXTENSION);
                $category_type = in_array($file_ext, ['mp4', 'webm', 'ogg']) ? 'video' : ($file_ext == 'pdf'  ? 'pdf' : 'other');
            ?>
            <div class="file-box" data-category="<?= $category_type ?>">
                <h4><?= $file['file_name'] ?></h4>
                <?php if ($file_ext == 'pdf'): ?>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="<?= $file['file_path'] ?>" target="_blank" class="btn btn-primary">👁️ عرض</a>
                        <a href="<?= $file['file_path'] ?>" download class="btn btn-success">⬇️ تحميل</a>
                    </div>
                <?php elseif (in_array($file_ext, ['mp4', 'webm', 'ogg'])): ?>
                    <div class="d-flex justify-content-center gap-2">
                        <a href="<?= $file['file_path'] ?>" target="_blank" class="btn btn-primary">👁️ عرض</a>
                        <a href="<?= $file['file_path'] ?>" download class="btn btn-success">⬇️ تحميل</a>
                    </div>
                <?php else: ?>
                    <p>❌ ملف غير مدعوم.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
<!-- نافذة منبثقة لعرض الامتحانات -->
<div class="modal fade" id="examModal" tabindex="-1" aria-labelledby="examModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="examModalLabel">📑 الامتحانات المتاحة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($exams)): ?>
                    <ul class="list-group">
                        <?php foreach ($exams as $exam): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($exam['exam_name']) ?>
                                <a href="exam_success.php?exam_id=<?= $exam['id'] ?>" class="btn btn-primary btn-sm">📝 بدء الامتحان</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-center">❌ لا يوجد امتحانات متاحة لك.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- سكريبت التحكم -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    let files = document.querySelectorAll('.file-box');
    files.forEach(file => file.style.display = 'none'); // إخفاء جميع الملفات عند تحميل الصفحة
});

function showCategory(category) {
    let files = document.querySelectorAll('.file-box');

    if (category === 'exam') {
        var examModal = new bootstrap.Modal(document.getElementById('examModal'));
        examModal.show();
        return;
    }

    files.forEach(file => {
        file.style.display = (file.getAttribute('data-category') === category) ? 'block' : 'none';
    });
}
</script>


<a href="logout.php">تسجيل الخروج</a>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let files = document.querySelectorAll('.file-box');
    files.forEach(file => file.style.display = 'none'); // إخفاء جميع الملفات عند تحميل الصفحة
});

function showCategory(category) {
    let files = document.querySelectorAll('.file-box');

    if (category === 'exam') {
        window.location.href = "./exam_success.php";
        return; // إيقاف تنفيذ باقي الكود بعد التوجيه
    }

    files.forEach(file => {
        file.style.display = (file.getAttribute('data-category') === category) ? 'block' : 'none';
    });
}
</script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>    
</body>
</html>

<?php $conn->close(); ?>
