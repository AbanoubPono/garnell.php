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

if (!isset($_GET['exam_id']) || empty($_GET['exam_id'])) {
    echo "<p class='text-danger text-center'>⚠️ يجب اختيار امتحان للمتابعة.</p>";
    exit();
}

$exam_id = intval($_GET['exam_id']);

$check_query = $conn->prepare("SELECT * FROM user_answers WHERE user_id = ? AND exam_id = ?");
$check_query->bind_param("ii", $user_id, $exam_id);
$check_query->execute();
$result = $check_query->get_result();

if ($result->num_rows > 0) {
    echo "<p class='text-danger text-center'>❌ لقد أكملت هذا الامتحان من قبل، لا يمكنك إعادة الدخول.</p>";
    exit();
}

$query = $conn->prepare("SELECT * FROM exams WHERE id = ?");
$query->bind_param("i", $exam_id);
$query->execute();
$result = $query->get_result();
$exam = $result->fetch_assoc();

if (!$exam) {
    echo "<p class='text-danger text-center'>⚠️ الامتحان غير موجود.</p>";
    exit();
}

$exam_title = $exam['title'];
$exam_duration = $exam['duration'];

$start_time_query = $conn->prepare("SELECT start_time FROM user_exam_sessions WHERE user_id = ? AND exam_id = ?");
$start_time_query->bind_param("ii", $user_id, $exam_id);
$start_time_query->execute();
$start_time_result = $start_time_query->get_result();

if ($start_time_result->num_rows > 0) {
    $row = $start_time_result->fetch_assoc();
    $start_time = strtotime($row['start_time']);
} else {
    $current_time = date("Y-m-d H:i:s");
    $insert_start_time = $conn->prepare("INSERT INTO user_exam_sessions (user_id, exam_id, start_time) VALUES (?, ?, ?)");
    $insert_start_time->bind_param("iis", $user_id, $exam_id, $current_time);
    $insert_start_time->execute();
    $start_time = strtotime($current_time);
}

$current_time = time();
$elapsed_time = $current_time - $start_time;
$remaining_time = max(($exam_duration * 60) - $elapsed_time, 0);

// ✅ **التحقق من انتهاء الوقت وعرض رسالة**
if ($remaining_time <= 0) {
    echo "<p class='text-danger text-center'>⏳ انتهى الوقت! لا يمكنك الدخول إلى الامتحان.</p>";
    exit();
}

$query = $conn->prepare("
    SELECT q.* FROM questions q
    JOIN exam_questions eq ON q.id = eq.question_id
    WHERE eq.exam_id = ?
");
$query->bind_param("i", $exam_id);
$query->execute();
$questions = $query->get_result();
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أداء الامتحان</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script>
        let timeLeft = <?php echo $remaining_time; ?>;

        function startTimer() {
            let timerElement = document.getElementById("timer");
            let interval = setInterval(function () {
                let minutes = Math.floor(timeLeft / 60);
                let seconds = timeLeft % 60;
                timerElement.innerHTML = minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
                
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    alert("⏳ انتهى الوقت! سيتم إغلاق الامتحان.");
                    document.getElementById("examForm").submit();
                }
                timeLeft--;
            }, 1000);
        }

        window.onload = startTimer;
    </script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }

        .container {
            max-width: 800px;
            margin-top: 20px;
        }

        .question {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }

        .form-check {
            margin-top: 10px;
        }

        .timer {
            font-size: 1.5rem;
            font-weight: bold;
            color: red;
        }

        .btn-primary {
            width: 100%;
            padding: 12px;
        }

        @media (max-width: 768px) {
            .btn-primary {
                font-size: 1.2rem;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center"><?php echo htmlspecialchars($exam_title); ?></h2>
        <p class="text-center timer">الوقت المتبقي: <span id="timer"></span></p>
        <form id="examForm" method="post" action="submit_exam.php">
            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

            <?php while ($question = $questions->fetch_assoc()) { ?>
                <div class="question">
                    <p><strong><?php echo htmlspecialchars($question['question']); ?></strong></p>

                    <?php if ($question['type'] == 'multiple_choice') { 
                        $query_choices = $conn->prepare("SELECT * FROM choices WHERE question_id = ?");
                        $query_choices->bind_param("i", $question['id']);
                        $query_choices->execute();
                        $choices = $query_choices->get_result();
                    ?>
                        <?php while ($choice = $choices->fetch_assoc()) { ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="answer[<?php echo $question['id']; ?>]" value="<?php echo htmlspecialchars($choice['choice_text']); ?>" required>
                                <label class="form-check-label">
                                    <?php echo htmlspecialchars($choice['choice_text']); ?>
                                </label>
                            </div>
                        <?php } ?>

                    <?php } elseif ($question['type'] == 'true_false') { ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answer[<?php echo $question['id']; ?>]" value="true" required>
                            <label class="form-check-label">صح</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answer[<?php echo $question['id']; ?>]" value="false" required>
                            <label class="form-check-label">خطأ</label>
                        </div>

                    <?php } else { ?>
                        <input type="text" class="form-control" name="answer[<?php echo $question['id']; ?>]" required>
                    <?php } ?>
                </div>
            <?php } ?>

            <button type="submit" class="btn btn-primary">إرسال الإجابات</button>
        </form>
    </div>
</body>
</html>
