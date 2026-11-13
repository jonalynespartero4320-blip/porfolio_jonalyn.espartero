<?php
include ROOT_PATH . '/modules/layout/header.php'; 
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/helpers/functions.php'; // Include the helper for notifications

// Handle form submissions for adding new entries
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Case Masterlist Insert
    if (isset($_POST['case_no'], $_POST['case_type'])) {
        $case_no = trim($_POST['case_no']);
        $case_type = trim($_POST['case_type']);
        
        if (!empty($case_no) && !empty($case_type)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO case_masterlist (case_no, case_type) VALUES (?, ?)");
                $stmt->execute([$case_no, $case_type]);
                show_notification('success', 'Case added successfully.');
            } catch (PDOException $e) {
                show_notification('error', 'Error adding case: ' . $e->getMessage());
            }
        } else {
            show_notification('warning', 'Case number and type are required.');
        }
    }

    // Court Masterlist Insert
    if (isset($_POST['court_name'])) {
        $court_name = trim($_POST['court_name']);
        
        if (!empty($court_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO court_masterlist (court_name) VALUES (?)");
                $stmt->execute([$court_name]);
                show_notification('success', 'Court added successfully.');
            } catch (PDOException $e) {
                show_notification('error', 'Error adding court: ' . $e->getMessage());
            }
        } else {
            show_notification('warning', 'Court name is required.');
        }
    }

    // Dorm Masterlist Insert
    if (isset($_POST['dorm_no'], $_POST['description'])) {
        $dorm_no = trim($_POST['dorm_no']);
        $description = trim($_POST['description']);
        
        if (!empty($dorm_no) && !empty($description)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO dorm_masterlist (dorm_no, description) VALUES (?, ?)");
                $stmt->execute([$dorm_no, $description]);
                show_notification('success', 'Dorm added successfully.');
            } catch (PDOException $e) {
                show_notification('error', 'Error adding dorm: ' . $e->getMessage());
            }
        } else {
            show_notification('warning', 'Dorm number and description are required.');
        }
    }

    // Reload the page after saving with a success message
    header("Location: " . BASE_URL . "/masterlist"); // Make sure the URL is correct without ".php"
    exit;
}

// Fetch all data from the masterlists
try {
    $cases = $pdo->query("SELECT * FROM case_masterlist")->fetchAll(PDO::FETCH_ASSOC);
    $courts = $pdo->query("SELECT * FROM court_masterlist")->fetchAll(PDO::FETCH_ASSOC);
    $dorms = $pdo->query("SELECT * FROM dorm_masterlist")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    show_notification('error', 'Error fetching data: ' . $e->getMessage());
}

// Include the masterlist view
?>

<div class="container">
    <h2 class="mb-4">Masterlist Management</h2>

    <!-- Case Masterlist Form -->
    <form method="POST" action="">
        <h3>Add Case</h3>
        <div class="mb-3">
            <label for="case_no" class="form-label">Case No</label>
            <input type="text" class="form-control" id="case_no" name="case_no" required>
        </div>
        <div class="mb-3">
            <label for="case_type" class="form-label">Case Type</label>
            <input type="text" class="form-control" id="case_type" name="case_type" required>
        </div>
        <button type="submit" class="btn btn-primary">Add Case</button>
    </form>

    <!-- Court Masterlist Form -->
    <form method="POST" action="">
        <h3>Add Court</h3>
        <div class="mb-3">
            <label for="court_name" class="form-label">Court Name</label>
            <input type="text" class="form-control" id="court_name" name="court_name" required>
        </div>
        <button type="submit" class="btn btn-primary">Add Court</button>
    </form>

    <!-- Dorm Masterlist Form -->
    <form method="POST" action="">
        <h3>Add Dorm</h3>
        <div class="mb-3">
            <label for="dorm_no" class="form-label">Dorm No</label>
            <input type="text" class="form-control" id="dorm_no" name="dorm_no" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <input type="text" class="form-control" id="description" name="description" required>
        </div>
        <button type="submit" class="btn btn-primary">Add Dorm</button>
    </form>

    <hr>

    <!-- Display All Data -->
    <h3>All Data</h3>

    <h4>Cases</h4>
    <ul>
        <?php foreach ($cases as $case): ?>
            <li><?= e($case['case_no']) ?> - <?= e($case['case_type']) ?></li>
        <?php endforeach; ?>
    </ul>

    <h4>Courts</h4>
    <ul>
        <?php foreach ($courts as $court): ?>
            <li><?= e($court['court_name']) ?></li>
        <?php endforeach; ?>
    </ul>

    <h4>Dorms</h4>
    <ul>
        <?php foreach ($dorms as $dorm): ?>
            <li><?= e($dorm['dorm_no']) ?> - <?= e($dorm['description']) ?></li>
        <?php endforeach; ?>
    </ul>

</div>

<?php include ROOT_PATH . '/modules/layout/footer.php'; ?>