<?php
require_once ROOT_PATH.'/modules/layout/header.php';
require_once ROOT_PATH . '/config/database.php';

// 🔐 RBAC PAGE PROTECTION
require_permission('pdl.view');

$PHOTO_PATH = ROOT_PATH . '/public/demo-uploads/pdl/';
$PHOTO_URL  = BASE_URL . '/demo-uploads/pdl/';
$DEFAULT_PHOTO = BASE_URL . '/assets/img/default-avatar.png';

// ================= FUNCTION TO LOAD DATA =================
function getPDL($pdo, $gender) {
    $stmt = $pdo->prepare("
        SELECT p.*, COUNT(pav.id) AS visitor_count
        FROM pdl_info p
        LEFT JOIN pdl_authorized_visitors pav ON p.id = pav.pdl_id
        WHERE LOWER(TRIM(p.pdl_sex)) IN ($gender)
        GROUP BY p.id
        ORDER BY p.id DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$male   = getPDL($pdo, "'m','male'");
$female = getPDL($pdo, "'f','female'");
?>

<div class="container-fluid py-3">

    <!-- HEADER -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h5 class="fw-bold mb-1">
                    <i class="bi bi-person-badge"></i> Resident Records
                </h5>
                <small class="text-muted">Manage Person Deprived of Liberty records</small>
            </div>

            <?php if (can('pdl.create')): ?>
            <a href="<?= BASE_URL ?>/pdl_add_edit" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle"></i> Add New
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABS -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#maleTab">Male</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#femaleTab">Female</button>
        </li>
    </ul>

    <div class="tab-content">

        <?php
        function renderTable($data, $PHOTO_PATH, $PHOTO_URL, $DEFAULT_PHOTO) {
        ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle w-100 dataTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Dorm</th>
                        <th>Age</th>
                        <th>Case</th>
                        <th>Court</th>
                        <th>Visitors</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>

                <?php $i=1; foreach($data as $row): 

                    $photoFile = $row['pdl_photo'];
                    $photo = (!empty($photoFile) && file_exists($PHOTO_PATH.$photoFile))
                        ? $PHOTO_URL.$photoFile
                        : $DEFAULT_PHOTO;
                ?>

                    <tr>
                        <td><?= $i++; ?></td>

                        <td>
                            <img src="<?= $photo ?>" 
                                 class="rounded-circle shadow-sm"
                                 style="width:45px;height:45px;object-fit:cover;">
                        </td>

                        <td class="fw-semibold"><?= e($row['pdl_id']) ?></td>

                        <td>
                            <?= e($row['pdl_fname'].' '.$row['pdl_mname'].' '.$row['pdl_lname']) ?>
                        </td>

                        <td><?= e($row['dorm_no']) ?></td>
                        <td><?= e($row['pdl_age']) ?></td>
                        <td><?= e($row['case_no']) ?></td>
                        <td><?= e($row['court']) ?></td>

                        <td>
                            <span class="badge bg-primary rounded-pill">
                                <?= $row['visitor_count'] ?>
                            </span>
                        </td>

                        <td class="text-center">

                            <?php if (can('pdl.view')): ?>
                            <button class="btn btn-info btn-sm viewVisitorsBtn" data-id="<?= $row['id'] ?>">
                                <i class="bi bi-people"></i>
                            </button>
                            <?php endif; ?>

                            <?php if (can('pdl.edit')): ?>
                            <a href="<?= BASE_URL ?>/pdl_add_edit?id=<?= $row['id'] ?>" 
                               class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>

                            <?php if (can('pdl.delete')): ?>
                            <button class="btn btn-danger btn-sm deleteBtn" data-id="<?= $row['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>

                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>
        </div>
        <?php } ?>

        <!-- MALE -->
        <div class="tab-pane fade show active" id="maleTab">
            <?php renderTable($male, $PHOTO_PATH, $PHOTO_URL, $DEFAULT_PHOTO); ?>
        </div>

        <!-- FEMALE -->
        <div class="tab-pane fade" id="femaleTab">
            <?php renderTable($female, $PHOTO_PATH, $PHOTO_URL, $DEFAULT_PHOTO); ?>
        </div>

    </div>

</div>

<script>
$(document).ready(function () {
    $('.dataTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0,'asc']]
    });
});
</script>

<?php require_once ROOT_PATH.'/modules/layout/footer.php'; ?>