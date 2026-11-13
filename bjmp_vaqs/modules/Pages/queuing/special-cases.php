<?php
$page_title = 'Special Cases';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/modules/layout/header.php';

$canQueueCreate = can('queue.create');
$queueDormDisplayExpr = "COALESCE((SELECT COALESCE(NULLIF(TRIM(dm.description), ''), NULLIF(TRIM(dm.dorm_no), '')) FROM dorm_masterlist dm WHERE TRIM(CAST(dm.id AS CHAR)) = TRIM(p.dorm_no) OR TRIM(dm.dorm_no) = TRIM(p.dorm_no) LIMIT 1), TRIM(p.dorm_no))";
$queuePdlNameExpr = "TRIM(CONCAT(COALESCE(p.pdl_fname, ''), ' ', COALESCE(p.pdl_mname, ''), ' ', COALESCE(p.pdl_lname, ''), ' ', COALESCE(p.pdl_suffix, '')))";
$specialCasePdlOptions = $pdo->query("
    SELECT
        p.id,
        p.pdl_id,
        $queuePdlNameExpr AS pdl_name,
        $queueDormDisplayExpr AS dorm_name
    FROM pdl_info p
    ORDER BY p.pdl_lname ASC, p.pdl_fname ASC
")->fetchAll(PDO::FETCH_ASSOC);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS queue_special_cases (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        firstname VARCHAR(150) NOT NULL,
        middlename VARCHAR(150) DEFAULT NULL,
        lastname VARCHAR(150) NOT NULL,
        age INT(11) DEFAULT NULL,
        gender ENUM('Male','Female','Other') NOT NULL,
        pdl_id INT(11) NOT NULL,
        pdl_name VARCHAR(255) NOT NULL,
        dorm_no VARCHAR(100) DEFAULT NULL,
        address TEXT NOT NULL,
        id_type VARCHAR(100) DEFAULT NULL,
        id_number VARCHAR(150) DEFAULT NULL,
        reason_for_visit TEXT NOT NULL,
        approved_by VARCHAR(150) NOT NULL,
        approved_date DATE NOT NULL,
        status ENUM('approved','queued','inside','completed','rejected') NOT NULL DEFAULT 'approved',
        created_by INT(11) DEFAULT NULL,
        queue_request_id INT(11) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_pdl_id (pdl_id),
        INDEX idx_approved_date (approved_date),
        INDEX idx_queue_request_id (queue_request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$rows = $pdo->query("
    SELECT
        qsc.*,
        qr.request_time,
        qr.accepted_time,
        qd.time_in,
        qd.time_out,
        qd.hours_stayed
    FROM queue_special_cases qsc
    LEFT JOIN queuing_requests qr ON qr.id = qsc.queue_request_id
    LEFT JOIN queue_display qd ON qd.special_case_id = qsc.id
    ORDER BY qsc.created_at DESC, qd.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'total' => count($rows),
    'queued' => 0,
    'inside' => 0,
    'completed' => 0,
];

foreach ($rows as $row) {
    $status = strtolower(trim((string) ($row['status'] ?? '')));
    if (isset($stats[$status])) {
        $stats[$status]++;
    }
}

function special_case_full_name(array $row): string
{
    return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
        $row['firstname'] ?? '',
        $row['middlename'] ?? '',
        $row['lastname'] ?? '',
    ]))));
}

function special_case_datetime(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M j, Y g:i A', $timestamp) : $value;
}

function special_case_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M j, Y', $timestamp) : $value;
}

function special_case_duration($hoursStayed): string
{
    if ($hoursStayed === null || $hoursStayed === '') {
        return '-';
    }

    $minutes = (int) round(((float) $hoursStayed) * 60);
    if ($minutes <= 0) {
        return '0 mins';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;
    $parts = [];

    if ($hours > 0) {
        $parts[] = $hours . ' hr' . ($hours === 1 ? '' : 's');
    }

    if ($remainingMinutes > 0) {
        $parts[] = $remainingMinutes . ' min' . ($remainingMinutes === 1 ? '' : 's');
    }

    return implode(' ', $parts);
}
?>

<style>
  .special-shell { display: grid; gap: 1.25rem; 
  /* background: linear-gradient(180deg, rgba(255,255,255,0.86) 0%, rgba(248,250,252,0.96) 100%);  */
  border-radius: 28px; padding: 24px; }
  .special-hero, .special-stat-card, .special-table-card { border: 0 !important; border-radius: 24px; box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08) !important; }
  .special-hero {
    background: linear-gradient(135deg, #062150, #447A61) !important;
    color: #fff !important;
    overflow: hidden;
    position: relative;
    box-shadow: 0 24px 54px rgba(8, 40, 68, 0.18) !important;
  }
  .special-hero::after { content: ""; position: absolute; right: -90px; bottom: -90px; width: 240px; height: 240px; border-radius: 50%; background: rgba(255,255,255,0.08); }
  .special-hero .card-body { position: relative; z-index: 1; padding: 1.35rem 1.5rem !important; }
  .special-hero .text-uppercase,
  .special-hero h2,
  .special-hero p,
  .special-hero .btn {
    color: #fff !important;
    background: transparent !important;
  }
  .special-hero h2 { font-size: clamp(1.45rem, 2vw, 2rem); margin-bottom: .45rem !important; }
  .special-hero .btn.btn-light {
    background: rgba(255,255,255,0.96) !important;
    color: #062150 !important;
    border-color: rgba(255,255,255,0.96) !important;
  }
  .special-hero .btn.btn-light:hover {
    background: #fff !important;
    color: #062150 !important;
  }
  .special-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
  .special-stat-card .card-body { padding: .95rem 1rem !important; }
  .special-stat-icon { width: 54px; height: 54px; border-radius: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; }
  .special-table-card > .card-header {
    background: linear-gradient(135deg, rgba(180,215,190,0.18), rgba(107,114,128,0.16)) !important;
    border: 0 !important;
    padding: 1rem 1.35rem !important;
  }
  .special-table-card > .card-body { padding: 1rem 1.35rem 1.35rem !important; }
  .special-table-card .table-responsive {
    border-radius: 18px;
    background: rgba(255,255,255,0.94);
    padding: .5rem .75rem .2rem;
  }
  .special-table-card table.dataTable { margin-top: .4rem !important; margin-bottom: .4rem !important; }
  .special-table-card .dataTables_filter input { min-width: 280px; }
  .special-table-card .dataTables_empty {
    padding: 2.5rem 1rem !important;
    color: #64748b !important;
    font-size: 1rem;
  }
  #specialCaseModal .modal-header {
    background: linear-gradient(135deg, rgba(107,114,128,0.16), rgba(229,231,235,0.72)) !important;
    border-bottom-color: rgba(107,114,128,0.18) !important;
  }
  #specialCaseModal .alert-warning {
    background: rgba(107,114,128,0.10) !important;
    color: #374151 !important;
    border: 1px solid rgba(107,114,128,0.14) !important;
  }
  #specialCaseModal .text-warning,
  .special-shell .text-warning {
    color: #6b7280 !important;
  }
  .special-status { border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
  @media (max-width: 1199px) { .special-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 767px) {
    .special-stat-grid { grid-template-columns: 1fr; }
    .special-hero .card-body { padding: 1.1rem 1rem !important; }
    .special-table-card .dataTables_filter input { min-width: 0; width: 100% !important; }
  }
</style>

<div class="container-fluid py-3">
    <div class="special-shell">
        <div class="card special-hero">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold opacity-75 mb-2">Super Admin Oversight</div>
                    <h2 class="fw-bold mb-2">Special Cases</h2>
                    <p class="mb-0 opacity-75">Review every special-case visitor entry, the approving details, linked Resident, and current queue lifecycle from one protected page.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($canQueueCreate): ?>
                        <button type="button" class="btn btn-primary btn-sm" id="btnOpenSpecialCaseModal">
                            <i class="bi bi-star-fill me-1"></i> Add Special Case
                        </button>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/queue" class="btn btn-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back to Queue
                    </a>
                </div>
            </div>
        </div>

        <div class="special-stat-grid">
            <div class="card special-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Total Records</div><div class="display-6 fw-bold mb-0"><?= $stats['total'] ?></div></div><div class="special-stat-icon bg-primary-subtle text-primary"><i class="bi bi-archive"></i></div></div></div></div>
            <div class="card special-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Queued</div><div class="display-6 fw-bold mb-0"><?= $stats['queued'] ?></div></div><div class="special-stat-icon bg-warning-subtle text-secondary"><i class="bi bi-hourglass-split"></i></div></div></div></div>
            <div class="card special-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Inside</div><div class="display-6 fw-bold mb-0"><?= $stats['inside'] ?></div></div><div class="special-stat-icon bg-success-subtle text-success"><i class="bi bi-door-open"></i></div></div></div></div>
            <div class="card special-stat-card"><div class="card-body"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted small">Completed</div><div class="display-6 fw-bold mb-0"><?= $stats['completed'] ?></div></div><div class="special-stat-icon bg-secondary-subtle text-secondary"><i class="bi bi-check2-circle"></i></div></div></div></div>
        </div>

        <div class="card special-table-card">
            <div class="card-header pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold mb-0">Special Case Register</h6>
                    <small class="text-muted">Super admin visibility for all special-case visitor transactions.</small>
                </div>
                <span class="badge bg-dark rounded-pill"><?= count($rows) ?> record(s)</span>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="table-responsive">
                    <table id="specialCasesTable" class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Visitor</th>
                                <th>Resident</th>
                                <th>Dorm</th>
                                <th>Reason</th>
                                <th>Approved By</th>
                                <th>Approved Date</th>
                                <th>Status</th>
                                <th>Requested At</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Hours Stayed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $index => $row): ?>
                                <?php
                                $status = strtolower(trim((string) ($row['status'] ?? 'approved')));
                                $statusClass = match ($status) {
                                    'queued' => 'bg-secondary',
                                    'inside' => 'bg-success',
                                    'completed' => 'bg-secondary',
                                    'rejected' => 'bg-danger',
                                    default => 'bg-primary',
                                };
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= e(special_case_full_name($row)) ?></div>
                                        <div class="small text-muted"><?= e(($row['gender'] ?? '-') . ' | Age: ' . ($row['age'] ?? '-')) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= e($row['pdl_name'] ?? '-') ?></div>
                                        <div class="small text-muted">Resident ID: <?= e((string) ($row['pdl_id'] ?? '-')) ?></div>
                                    </td>
                                    <td><?= e($row['dorm_no'] ?? '-') ?></td>
                                    <td><?= e($row['reason_for_visit'] ?? '-') ?></td>
                                    <td><?= e($row['approved_by'] ?? '-') ?></td>
                                    <td><?= e(special_case_date($row['approved_date'] ?? null)) ?></td>
                                    <td><span class="badge special-status <?= $statusClass ?>"><?= e($status) ?></span></td>
                                    <td><?= e(special_case_datetime($row['request_time'] ?? $row['created_at'] ?? null)) ?></td>
                                    <td><?= e(special_case_datetime($row['time_in'] ?? $row['accepted_time'] ?? null)) ?></td>
                                    <td><?= e(special_case_datetime($row['time_out'] ?? null)) ?></td>
                                    <td><?= e(special_case_duration($row['hours_stayed'] ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canQueueCreate): ?>
<div class="modal fade" id="specialCaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="frmSpecialCase" class="modal-content">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title">
                    <i class="bi bi-star-fill me-2 text-warning"></i>
                    Special Case Visitor Approval
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning border-0 rounded-4">
                    Use this only for visitors approved as special cases, including visitors without a valid ID card.
                    Once submitted, the visitor will be marked as a special case and placed in the queue for review.
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="firstname" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Middle Name</label>
                        <input type="text" class="form-control" name="middlename">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="lastname" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Age <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="age" min="1" max="120" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                        <select class="form-select" name="gender" required>
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Resident Name <span class="text-danger">*</span></label>
                        <select class="form-select select2" name="pdl_id" id="special_pdl_id" data-placeholder="Search Resident" required>
                            <option value="">Select Resident</option>
                            <?php foreach ($specialCasePdlOptions as $pdl): ?>
                                <?php
                                    $pdlName = trim((string) ($pdl['pdl_name'] ?? ''));
                                    $pdlCode = trim((string) ($pdl['pdl_id'] ?? ''));
                                    $pdlDorm = trim((string) ($pdl['dorm_name'] ?? ''));
                                    $label = ($pdlName !== '' ? $pdlName : 'Unnamed Resident') . ($pdlCode !== '' ? ' | ' . $pdlCode : '') . ($pdlDorm !== '' ? ' | ' . $pdlDorm : '');
                                ?>
                                <option value="<?= (int) ($pdl['id'] ?? 0) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="address" rows="2" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ID Type <span class="text-muted fw-normal">(optional)</span></label>
                        <select class="form-select" name="id_type">
                            <option value="">No ID Provided</option>
                            <option value="Driver's License">Driver's License</option>
                            <option value="National ID">National ID</option>
                            <option value="Passport">Passport</option>
                            <option value="Student ID">Student ID</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ID Number <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control" name="id_number" placeholder="Leave blank if no ID">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reason for Visit <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="reason_for_visit" rows="2" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Approved By <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="approved_by" value="<?= e(current_user()['username'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Approved Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="approved_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2-circle me-1"></i> Approve Special Case
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
$(function () {
    <?php if ($canQueueCreate): ?>
    $('select.select2').select2({
        width: '100%',
        dropdownParent: $('#specialCaseModal'),
        placeholder: function () {
            return $(this).data('placeholder') || 'Select an option';
        },
        allowClear: true
    });

    $('#btnOpenSpecialCaseModal').on('click', function () {
        const modal = new bootstrap.Modal(document.getElementById('specialCaseModal'));
        modal.show();
    });

    $('#frmSpecialCase').on('submit', function (e) {
        e.preventDefault();

        const formData = $(this).serializeArray();
        formData.push({ name: 'action', value: 'addSpecialCase' });

        $.post(<?= json_encode(BASE_URL . '/queue-action') ?>, formData, function (res) {
            Swal.fire(
                res.ok ? 'Special Case Queued' : 'Error',
                res.msg || res.message || '',
                res.ok ? 'success' : 'error'
            );

            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('specialCaseModal')).hide();
                $('#frmSpecialCase')[0].reset();
                $('#special_pdl_id').val('').trigger('change');
                window.location.reload();
            }
        }, 'json').fail(function () {
            Swal.fire('Error', 'Failed to add special-case visitor.', 'error');
        });
    });
    <?php endif; ?>

    $('#specialCasesTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[8, 'desc']],
        language: {
            search: '',
            searchPlaceholder: 'Search special cases...',
            emptyTable: 'No special-case records found.'
        }
    });
});
</script>

<?php require_once ROOT_PATH . '/modules/layout/footer.php'; ?>
