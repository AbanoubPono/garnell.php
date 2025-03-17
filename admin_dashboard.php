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

$jobs = ['manager', 'captain', 'waiter', 'pass boy' , 'kitchen'];


// إلبحث
if (isset($_POST['search_code'])) {
    $search_code = $_POST['search_code'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE code = ?");
    $stmt->bind_param("s", $search_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($user = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$user['id']}</td>
                <td>{$user['name']}</td>
                <td>{$user['code']}</td>
                <td>{$user['role']}</td>
                <td>" . ucfirst($user['job']) . "</td>
                <td>
                    <button class='btn btn-warning btn-sm' onclick=\"editUser({$user['id']}, '{$user['name']}', '{$user['code']}', '{$user['password']}', '{$user['role']}', '{$user['job']}')\">✏ تعديل</button>
                    <a class='btn btn-danger btn-sm' href='?delete_user={$user['id']}' onclick=\"return confirm('هل أنت متأكد من الحذف؟')\">❌ حذف</a>
                </td>
            </tr>";
    }
    exit;
}



// إضافة أو تعديل مستخدم
if (isset($_POST['add_user']) || isset($_POST['edit_user'])) {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $password = $_POST['password']; 
    $role = $_POST['role'];
    $job = $_POST['job'];

    if (isset($_POST['edit_user']) && $id) {
        if (!empty($_POST['password'])) {
            $password = $_POST['password'];
            $stmt = $conn->prepare("UPDATE users SET name=?, code=?, password=?, role=?, job=? WHERE id=?");
            $stmt->bind_param("sssssi", $name, $code, $password, $role, $job, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, code=?, role=?, job=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $code, $role, $job, $id);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, code, password, role, job) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $code, $password, $role, $job);
    }
    
    $stmt->execute();

    // إعادة التوجيه لمنع الإرسال المزدوج عند التحديث
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// حذف مستخدم
if (isset($_GET['delete_user'])) {
    $id = $_GET['delete_user'];

    // تنفيذ عملية الحذف
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // إعادة التوجيه لتحديث الصفحة بعد الحذف
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


// إضافة ملف جديد أو تعديله
if (isset($_POST['add_file']) || isset($_POST['edit_file'])) {
    $file_name = trim($_POST['file_name']);
    $job = $_POST['job'];
    $file_id = $_POST['file_id'] ?? null;

    if (isset($_POST['edit_file']) && $file_id) {
        $stmt = $conn->prepare("UPDATE files SET file_name=?, allowed_job=? WHERE id=?");
        $stmt->bind_param("ssi", $file_name, $job, $file_id);
    } else {
        $file = $_FILES['file'];
        $allowed_extensions = ['pdf', 'jpg', 'png', 'mp4']; // أنواع الملفات المسموح بها
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
            die("❌ نوع الملف غير مسموح به!");
        }

        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_path = $upload_dir . uniqid() . "." . $file_extension;

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $stmt = $conn->prepare("INSERT INTO files (file_name, file_path, allowed_job) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $file_name, $file_path, $job);
        }
    }
    $stmt->execute();

    // إعادة التوجيه لمنع الإرسال المزدوج عند التحديث
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// حذف ملف
if (isset($_GET['delete_file'])) {
    $id = $_GET['delete_file'];

    // استعلام لجلب مسار الملف
    $stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();

    if ($file) {
        $file_path = $file['file_path'];
        
        // حذف الملف الفعلي من السيرفر
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // حذف السجل من قاعدة البيانات
        $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }

    // إعادة التوجيه لمنع إعادة إرسال الطلب عند التحديث
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}



// إضافة أو تعديل سؤال
if (isset($_POST['save_question'])) {
    $question = $_POST['question'];
    $type = $_POST['type'];
    $correct_answer = $_POST['correct_answer'];
    $question_id = isset($_POST['question_id']) ? $_POST['question_id'] : null;

    if ($question_id) {
        $stmt = $conn->prepare("UPDATE questions SET question=?, type=?, correct_answer=? WHERE id=?");
        $stmt->bind_param("sssi", $question, $type, $correct_answer, $question_id);
        $stmt->execute();
        $stmt->close();

        if ($type == "multiple_choice") {
            $stmt = $conn->prepare("DELETE FROM choices WHERE question_id=?");
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $stmt->close();

            foreach ($_POST['choices'] as $index => $choice) {
                $is_correct = ($index == $_POST['correct_choice']) ? 1 : 0;
                $stmt = $conn->prepare("INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $question_id, $choice, $is_correct);
                $stmt->execute();
                $stmt->close();
            }
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO questions (question, type, correct_answer) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $question, $type, $correct_answer);
        $stmt->execute();
        $question_id = $stmt->insert_id;
        $stmt->close();

        if ($type == "multiple_choice") {
            foreach ($_POST['choices'] as $index => $choice) {
                $is_correct = ($index == $_POST['correct_choice']) ? 1 : 0;
                $stmt = $conn->prepare("INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $question_id, $choice, $is_correct);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // منع تكرار الإرسال عند إعادة تحميل الصفحة
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}
// حذف سؤال
if (isset($_POST['delete_question'])) {
    $id = $_POST['question_id'];

    // حذف الخيارات المرتبطة بالسؤال من جدول choices
    $stmt = $conn->prepare("DELETE FROM choices WHERE question_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // حذف السؤال نفسه من جدول questions
    $stmt = $conn->prepare("DELETE FROM questions WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    // منع تكرار الإرسال عند إعادة تحميل الصفحة
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

$questions = $conn->query("SELECT * FROM questions");


// جلب الوظائف المتاحة من users
$roles_result = $conn->query("SELECT DISTINCT job FROM users");

// جلب جميع الأسئلة المتاحة
$questions_result = $conn->query("SELECT * FROM questions");

// جلب جميع الامتحانات
$exams_result = $conn->query("SELECT * FROM exams");

// إضافة أو تعديل امتحان
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $exam_id = $_POST['exam_id'] ?? null;
    $exam_name = $_POST['exam_name'] ?? null;
    $duration = $_POST['duration'] ?? null;
    $allowed_roles = $_POST['allowed_roles'] ?? [];
    $selected_questions = $_POST['selected_questions'] ?? [];

    if ($exam_name && $duration && !empty($allowed_roles) && !empty($selected_questions)) {
        $allowed_roles_str = implode(",", $allowed_roles);

        if ($exam_id) {
            // تعديل الامتحان
            $stmt = $conn->prepare("UPDATE exams SET exam_name=?, duration=?, allowed_roles=? WHERE id=?");
            $stmt->bind_param("sisi", $exam_name, $duration, $allowed_roles_str, $exam_id);
            $stmt->execute();
            $stmt->close();

            // حذف الأسئلة القديمة وإضافة الجديدة
            $conn->query("DELETE FROM exam_questions WHERE exam_id=$exam_id");

            // 🔴 حذف إجابات المستخدمين السابقين لهذا الامتحان
            $delete_answers = $conn->prepare("DELETE FROM user_answers WHERE exam_id = ?");
            $delete_answers->bind_param("i", $exam_id);
            $delete_answers->execute();
            $delete_answers->close();

            // 🔴 حذف جلسات الامتحان السابقة للمستخدمين
            $delete_sessions = $conn->prepare("DELETE FROM user_exam_sessions WHERE exam_id = ?");
            $delete_sessions->bind_param("i", $exam_id);
            $delete_sessions->execute();
            $delete_sessions->close();

        } else {
            // إضافة امتحان جديد
            $stmt = $conn->prepare("INSERT INTO exams (exam_name, duration, allowed_roles) VALUES (?, ?, ?)");
            $stmt->bind_param("sis", $exam_name, $duration, $allowed_roles_str);
            $stmt->execute();
            $exam_id = $stmt->insert_id;
            $stmt->close();
        }

        // إدخال الأسئلة الجديدة
        foreach ($selected_questions as $question_id) {
            $stmt = $conn->prepare("INSERT INTO exam_questions (exam_id, question_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $exam_id, $question_id);
            $stmt->execute();
            $stmt->close();
        }

        echo "<script>alert('تم تعديل الامتحان وإعادة إرساله للمستخدمين الذين أكملوه.'); window.location.href = 'admin_dashboard.php';</script>";
        exit();
    } else {
        echo "<div class='alert alert-danger'>يرجى ملء جميع الحقول المطلوبة</div>";
    }
}

// حذف الامتحان
if (isset($_GET['delete_exam'])) {
    $exam_id = $_GET['delete_exam'];
    
    // تنفيذ عملية الحذف
    $conn->query("DELETE FROM exams WHERE id = $exam_id");
    $conn->query("DELETE FROM exam_questions WHERE exam_id = $exam_id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// استرجاع بيانات المستخدمين
$users = $conn->query("SELECT * FROM users");
// استرجاع بيانات الملفات
$files = $conn->query("SELECT * FROM files");

?>


<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إدارة المستخدمين والملفات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
</head>
<body class="bg-light">
<a href="logout.php">تسجيل الخروج</a>

<div class="container py-5">
    <h2 class="text-center mb-4">لوحة التحكم المركزيه</h2>

    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">إدارة المستخدمين</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="files-tab" data-bs-toggle="tab" data-bs-target="#files" type="button" role="tab">إدارة الملفات</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="exam-tab" data-bs-toggle="tab" data-bs-target="#exam" type="button" role="tab">إدارة الاساله</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="manage_exam-tab" data-bs-toggle="tab" data-bs-target="#manage_exam" type="button" role="tab">إدارة الامتحانات</button>
        </li>
        <li class="nav-item" role="presentation">
        <a class="nav-link" id="results-tab" href="exam_results.php" role="tab">📊 نتائج الامتحانات</a>
        </li>
    </ul>

                <div class="tab-content mt-3" id="adminTabsContent">
                    <!-- إدارة المستخدمين -->
                    <div class="tab-pane fade show active" id="users" role="tabpanel">
                    <div class="card p-4 shadow-sm">
                    <form class="row g-3">
                        <div class="col-md-6">
                        <input type="text" id="search_code" name="search_code" class="form-control" placeholder="ابحث برقم الكود">
                        </div>
                        <div class="col-md-6">
                        <button type="button" class="btn btn-primary w-100" onclick="searchUser()">🔍 بحث</button>
                        </div>
                    </form>
                </div>
                        <div class="card p-4 shadow-sm">
                            <h4 class="text-center">إدارة المستخدمين</h4>
                            <form method="POST" class="row g-3">
                                <input type="hidden" id="id" name="id">
                                <div class="col-md-6">
                                    <input type="text" id="name" name="name" class="form-control" placeholder="الاسم" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" id="code" name="code" class="form-control" placeholder="الكود" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="password" id="password" name="password" class="form-control" placeholder="كلمة المرور" required>
                                </div>
                                <div class="col-md-3">
                                    <select id="role" name="role" class="form-select">
                                        <option value="user">مستخدم عادي</option>
                                        <option value="admin">مدير</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select id="user_job" name="job" class="form-select" required>
                                        <option value="" disabled selected>اختر الوظيفة</option>
                                        <?php foreach ($jobs as $job): ?>
                                            <option value="<?= $job ?>"><?= ucfirst($job) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <button type="submit" id="submitBtn" name="add_user" class="btn btn-success w-100">إضافة</button>
                                </div>
                            </form>
                            <table class="table table-striped table-bordered mt-4">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>الاسم</th>
                                        <th>الكود</th>
                                        <th>الدور</th>
                                        <th>الوظيفة</th>
                                        <th>تحكم</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td><?= $user['name'] ?></td>
                                        <td><?= $user['code'] ?></td>
                                        <td><?= $user['role'] ?></td>
                                        <td><?= ucfirst($user['job']) ?></td>
                                        <td>
                                            <button class='btn btn-warning btn-sm' onclick="editUser(<?= $user['id'] ?>, '<?= $user['name'] ?>', '<?= $user['code'] ?>', '<?= $user['password'] ?>', '<?= $user['role'] ?>', '<?= $user['job'] ?>')">✏ تعديل</button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteUser(<?= $user['id'] ?>)">❌ حذف</button>
                                            </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- إدارة الملفات -->
                    <div class="tab-pane fade" id="files" role="tabpanel">
                        <div class="card p-4 shadow-sm">
                            <h4 class="text-center">إدارة الملفات</h4>
                            <form method="POST" enctype="multipart/form-data" class="row g-3">
                                <input type="hidden" id="file_id" name="file_id">
                                <div class="col-md-6">
                                    <input type="text" id="file_name" name="file_name" class="form-control" placeholder="اسم الملف" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="file" name="file" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <select id="file_job" name="job" class="form-select">
                                        <?php foreach ($jobs as $job): ?>
                                        <option value="<?= $job ?>"><?= ucfirst($job) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <button type="submit" id="file_submit" name="add_file" class="btn btn-success w-100">إضافة</button>
                                </div>
                            </form>
                            <table class="table table-striped table-bordered mt-4">
                                <thead class="table-dark">
                                    <tr>
                                        <th>التسلسل</th>
                                        <th>اسم الملف</th>
                                        <th>المسموح له</th>
                                        <th>الرابط</th>
                                        <th>التحكم</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($file = $files->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $file['id'] ?></td>
                                        <td><?= $file['file_name'] ?></td>
                                        <td><?= ucfirst($file['allowed_job']) ?></td>
                                        <td><a class="btn btn-primary btn-sm" href="<?= $file['file_path'] ?>" target="_blank">📂 عرض</a></td>
                                        <td>
                                            <button class='btn btn-warning btn-sm' onclick="editFile(<?= $file['id'] ?>, '<?= $file['file_name'] ?>', '<?= $file['allowed_job'] ?>')">✏ تعديل</button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteFile(<?= $file['id'] ?>)">❌ حذف</button>
                                            </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                            <!-- إدارة الاساله -->
                    <div class="tab-pane fade" id="exam" role="tabpanel">
                    <h2 class="text-center">إدارة الأسئلة</h2>
                <div class="card p-4 shadow-sm">
                    <h4>إضافة / تعديل سؤال</h4>
                    <form method="POST">
                        <input type="hidden" name="question_id" id="question_id">
                        <div class="mb-3">
                            <label class="form-label">السؤال:</label>
                            <input type="text" class="form-control" name="question" id="question" required placeholder="أدخل السؤال">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">نوع السؤال:</label>
                            <select class="form-control" name="type" id="type" onchange="toggleChoices()" required>
                                <option value="multiple_choice">اختيار من متعدد</option>
                                <option value="true_false">صح أو خطأ</option>
                                <option value="text">نصي</option>
                            </select>
                        </div>
                        <div id="choices_div" class="mb-3" style="display:none;">
                            <label class="form-label">أدخل الخيارات وحدد الإجابة الصحيحة:</label>
                            <div class="input-group mb-2">
                                <input type="radio" name="correct_choice" value="0" required>
                                <input type="text" class="form-control" name="choices[]" placeholder="الخيار 1">
                            </div>
                            <div class="input-group mb-2">
                                <input type="radio" name="correct_choice" value="1">
                                <input type="text" class="form-control" name="choices[]" placeholder="الخيار 2">
                            </div>
                            <div class="input-group mb-2">
                                <input type="radio" name="correct_choice" value="2">
                                <input type="text" class="form-control" name="choices[]" placeholder="الخيار 3">
                            </div>
                            <div class="input-group mb-2">
                                <input type="radio" name="correct_choice" value="3">
                                <input type="text" class="form-control" name="choices[]" placeholder="الخيار 4">
                            </div>
                        </div>
                        <div id="true_false_div" class="mb-3" style="display:none;">
                            <label class="form-label">الإجابة الصحيحة:</label>
                            <select class="form-control" name="correct_answer" id="correct_answer">
                                <option value="true">صح</option>
                                <option value="false">خطأ</option>
                            </select>
                        </div>
                        <button type="submit" name="save_question" class="btn btn-primary">حفظ</button>
                    </form>
                </div>
                <h2 class="mt-5">الأسئلة المضافة</h2>
                <ul class="list-group mt-3">
                    <?php while ($row = $questions->fetch_assoc()) { ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= $row['question'] ?> <span class="badge bg-secondary"><?= $row['type'] ?></span></span>
                            <div>
                                <button onclick="editQuestion(<?= $row['id'] ?>, '<?= addslashes($row['question']) ?>', '<?= $row['type'] ?>', '<?= $row['correct_answer'] ?>')" class="btn btn-warning btn-sm">تعديل</button>
                                <button onclick="deleteQuestion(<?= $row['id'] ?>)" class="btn btn-danger btn-sm">حذف</button>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function editQuestion(id, question, type, correct_answer) {
        document.getElementById("question_id").value = id;
        document.getElementById("question").value = question;
        document.getElementById("type").value = type;
        
        // تحديث الخيارات بناءً على نوع السؤال
        toggleChoices();

        if (type === "true_false") {
            document.getElementById("correct_answer").value = correct_answer;
        }
    }

    function deleteQuestion(questionId) {
        Swal.fire({
            title: "هل أنت متأكد؟",
            text: "لن تتمكن من استعادة هذا السؤال بعد الحذف!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "نعم، احذفه!",
            cancelButtonText: "إلغاء"
        }).then((result) => {
            if (result.isConfirmed) {
                // إنشاء نموذج لحذف السؤال وإرساله تلقائيًا
                var form = document.createElement("form");
                form.method = "POST";
                form.action = window.location.href;

                var inputDelete = document.createElement("input");
                inputDelete.type = "hidden";
                inputDelete.name = "delete_question";
                inputDelete.value = "1";

                var inputId = document.createElement("input");
                inputId.type = "hidden";
                inputId.name = "question_id";
                inputId.value = questionId;

                form.appendChild(inputDelete);
                form.appendChild(inputId);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>


                <script>
                    function toggleChoices() {
                        var type = document.getElementById("type").value;
                        document.getElementById("choices_div").style.display = (type == "multiple_choice") ? "block" : "none";
                        document.getElementById("true_false_div").style.display = (type == "true_false") ? "block" : "none";
                    }

                    function editQuestion(id, question, type, correct_answer) {
                        document.getElementById("question_id").value = id;
                        document.getElementById("question").value = question;
                        document.getElementById("type").value = type;
                        document.getElementById("correct_answer").value = correct_answer;
                        toggleChoices();
                    }
                    document.querySelector("form").addEventListener("submit", function(event) {
                var type = document.getElementById("type").value;
                if (type === "multiple_choice") {
                    var checked = document.querySelector('input[name="correct_choice"]:checked');
                    if (!checked) {
                        alert("يرجى تحديد الإجابة الصحيحة!");
                        event.preventDefault(); // منع إرسال النموذج
                    }
                }
            });

                </script>

                <script>
                    function toggleChoices() {
                var type = document.getElementById("type").value;
                var choicesDiv = document.getElementById("choices_div");
                var trueFalseDiv = document.getElementById("true_false_div");

                if (type === "multiple_choice") {
                    choicesDiv.style.display = "block";
                    trueFalseDiv.style.display = "none";
                    document.querySelectorAll('input[name="correct_choice"]').forEach(input => input.required = true);
                } else {
                    choicesDiv.style.display = "none";
                    trueFalseDiv.style.display = (type === "true_false") ? "block" : "none";
                    document.querySelectorAll('input[name="correct_choice"]').forEach(input => input.required = false);
                }
            }
                </script>

            </div>
                            <!-- إدارة الامتحانات -->
                <div class="tab-pane fade" id="manage_exam" role="tabpanel">

                
                <h2 class="text-center">إدارة وإنشاء الامتحانات</h2>

                <!-- نموذج إنشاء / تعديل امتحان -->
                <div class="card p-4 shadow-sm mb-4">
                    <h4 id="form-title">إنشاء امتحان جديد</h4>
                    <form method="POST">
                        <input type="hidden" name="exam_id" id="exam_id">
                        <div class="mb-3">
                            <label class="form-label">عنوان الامتحان:</label>
                            <input type="text" class="form-control" name="exam_name" id="exam_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">مدة الامتحان (بالدقائق):</label>
                            <input type="number" class="form-control" name="duration" id="duration" required min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">الأدوار المسموح لها:</label>
                            <select class="form-control" name="allowed_roles[]" id="allowed_roles" multiple required>
                                <?php while ($role = $roles_result->fetch_assoc()) { ?>
                                    <option value="<?= $role['job'] ?>"><?= $role['job'] ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">اختر الأسئلة:</label>
                            <br>
                            <br>
                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 border p-3" style="max-height: 300px; overflow-y: auto;">
                                <?php while ($question = $questions_result->fetch_assoc()) { ?>
                                    <div class="col">
                                        <div class="card shadow-sm border-0">
                                            <div class="card-body">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="selected_questions[]" value="<?= $question['id'] ?>">
                                                    <label class="form-check-label fw-bold">
                                                        <?= htmlspecialchars($question['question']) ?>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                        <button type="submit" name="save_exam" class="btn btn-primary">حفظ الامتحان</button>
                    </form>
                </div>

                <!-- عرض الامتحانات -->
                <div class="card p-4 shadow-sm">
                    <h4>قائمة الامتحانات</h4>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>العنوان</th>
                                <th>المدة (دقائق)</th>
                                <th>الأدوار المسموح لها</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($exam = $exams_result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?= $exam['exam_name'] ?></td>
                                    <td><?= $exam['duration'] ?></td>
                                    <td><?= $exam['allowed_roles'] ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick="editExam(<?= $exam['id'] ?>, '<?= $exam['exam_name'] ?>', <?= $exam['duration'] ?>, '<?= $exam['allowed_roles'] ?>')">تعديل</button>
                                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?= $exam['id'] ?>)">حذف</button>
                                        </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

            <script>
            function editExam(id, name, duration, roles) {
                document.getElementById('exam_id').value = id;
                document.getElementById('exam_name').value = name;
                document.getElementById('duration').value = duration;
                document.getElementById('form-title').innerText = 'تعديل الامتحان';
            }
            </script>
            <script>
                function confirmDelete(examId) {
                    Swal.fire({
                        title: "هل أنت متأكد؟",
                        text: "لن تتمكن من التراجع عن هذا الحذف!",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#d33",
                        cancelButtonColor: "#3085d6",
                        confirmButtonText: "نعم، احذف!",
                        cancelButtonText: "إلغاء"
                    }).then((result) => {
        if (result.isConfirmed) {
            fetch(window.location.href + "?delete_exam=" + examId, {
                method: "GET"
            }).then(response => {
                if (response.ok) {
                    Swal.fire("تم الحذف!", "تم حذف الامتحان بنجاح.", "success")
                        .then(() => location.reload());
                } else {
                    Swal.fire("خطأ!", "حدث خطأ أثناء الحذف.", "error");
                }
            }).catch(error => {
                console.error("Error:", error);
                Swal.fire("خطأ!", "لم يتمكن النظام من حذف المستخدم.", "error");
            });
        }
    });
}
                    </script>
                    </div>
                    </div>
                </div>
            </div>

<script>
    function searchUser() {
    let searchCode = document.getElementById("search_code").value.trim();
    if (searchCode === "") {
        alert("يرجى إدخال كود للبحث!");
        return;
    }

    let formData = new FormData();
    formData.append("search_code", searchCode);

    fetch(window.location.href, {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        document.querySelector("tbody").innerHTML = data;
    })
    .catch(error => console.error("Error:", error));
}



</script>
    <script>
        function editUser(id, name, code, password, role, job) {
            document.getElementById('id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('code').value = code;
            document.getElementById('password').value = password ;
            document.getElementById('role').value = role;
            document.getElementById('user_job').value = job;
            document.getElementById('submitBtn').name = 'edit_user';
            document.getElementById('submitBtn').textContent = 'تعديل';
        }
    </script>
    <script>
        function deleteUser(userId) {
    Swal.fire({
        title: "هل أنت متأكد؟",
        text: "لن تتمكن من استعادة هذا المستخدم بعد الحذف!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "نعم، احذفه!",
        cancelButtonText: "إلغاء"
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(window.location.href + "?delete_user=" + userId, {
                method: "GET"
            }).then(response => {
                if (response.ok) {
                    Swal.fire("تم الحذف!", "تم حذف المستخدم بنجاح.", "success")
                        .then(() => location.reload());
                } else {
                    Swal.fire("خطأ!", "حدث خطأ أثناء الحذف.", "error");
                }
            }).catch(error => {
                console.error("Error:", error);
                Swal.fire("خطأ!", "لم يتمكن النظام من حذف المستخدم.", "error");
            });
        }
    });
}

    </script>
    <script>
        function editFile(id, name, job) {
            document.getElementById('file_id').value = id;
            document.getElementById('file_name').value = name;
            document.getElementById('file_job').value = job;
            document.getElementById('file_submit').name = 'edit_file';
            document.getElementById('file_submit').textContent = 'تعديل';
        }
    </script>

    <script>
        function deleteFile(fileId) {
    Swal.fire({
        title: "هل أنت متأكد؟",
        text: "لن تتمكن من استعادة هذا الملف بعد الحذف!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "نعم، احذفه!",
        cancelButtonText: "إلغاء"
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(window.location.href + "?delete_file=" + fileId, {
                method: "GET"
            }).then(response => {
                if (response.ok) {
                    Swal.fire("تم الحذف!", "تم حذف الملف بنجاح.", "success")
                        .then(() => location.reload());
                } else {
                    Swal.fire("خطأ!", "حدث خطأ أثناء الحذف.", "error");
                }
            }).catch(error => {
                console.error("Error:", error);
                Swal.fire("خطأ!", "لم يتمكن النظام من حذف الملف.", "error");
            });
        }
    });
}

    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
