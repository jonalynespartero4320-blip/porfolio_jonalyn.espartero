<!-- modals.php -->
<style>
  #caseModal button[type="submit"]::before,
  #editCaseModal button[type="submit"]::before,
  #deleteCaseModal button[type="submit"]::before,
  #courtModal button[type="submit"]::before,
  #editCourtModal button[type="submit"]::before,
  #deleteCourtModal button[type="submit"]::before,
  #dormModal button[type="submit"]::before,
  #editDormModal button[type="submit"]::before,
  #deleteDormModal button[type="submit"]::before,
  #relationshipModal button[type="submit"]::before,
  #editRelationshipModal button[type="submit"]::before,
  #requirementModal button[type="submit"]::before,
  #editRequirementModal button[type="submit"]::before,
  #validIdTypeModal button[type="submit"]::before,
  #editValidIdTypeModal button[type="submit"]::before {
    font-family: "bootstrap-icons";
    display: inline-block;
    margin-right: .35rem;
    vertical-align: -.05em;
  }

  #caseModal .btn-primary[type="submit"]::before,
  #courtModal .btn-primary[type="submit"]::before,
  #dormModal .btn-primary[type="submit"]::before,
  #relationshipModal .btn-primary[type="submit"]::before,
  #requirementModal .btn-primary[type="submit"]::before,
  #validIdTypeModal .btn-primary[type="submit"]::before {
    content: "\f4fe";
  }

  #editCaseModal .btn-success[type="submit"]::before,
  #editCourtModal .btn-success[type="submit"]::before,
  #editDormModal .btn-success[type="submit"]::before,
  #editRelationshipModal .btn-success[type="submit"]::before,
  #editRequirementModal .btn-success[type="submit"]::before,
  #editValidIdTypeModal .btn-success[type="submit"]::before {
    content: "\f4ca";
  }

  #deleteCaseModal .btn-danger[type="submit"]::before,
  #deleteCourtModal .btn-danger[type="submit"]::before,
  #deleteDormModal .btn-danger[type="submit"]::before {
    content: "\f5de";
  }
</style>

<!-- ================= CASE MODALS ================= -->

<?php if (!empty($canCaseCreate)): ?>
<!-- Add Case -->
<div class="modal fade" id="caseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Add New Case</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="add_case">
          <input type="hidden" name="active_tab" value="case">

          <div class="mb-3">
            <label>Case No</label>
            <input type="text" class="form-control" name="case_no" required>
          </div>

          <div class="mb-3">
            <label>Case Type</label>
            <input type="text" class="form-control" name="case_type" required>
          </div>

          <button type="submit" class="btn btn-primary">Save Case</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($canCaseEdit)): ?>
<!-- Edit Case -->
<div class="modal fade" id="editCaseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Edit Case</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="edit_case">
          <input type="hidden" name="active_tab" value="case">
          <input type="hidden" id="edit_case_id" name="case_id">

          <div class="mb-3">
            <label>Case No</label>
            <input type="text" class="form-control" id="edit_case_no" name="case_no" required>
          </div>

          <div class="mb-3">
            <label>Case Type</label>
            <input type="text" class="form-control" id="edit_case_type" name="case_type" required>
          </div>

          <button type="submit" class="btn btn-success">Update</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delete Case -->
<div class="modal fade" id="deleteCaseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Delete Case</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete this case?</p>
      </div>
      <div class="modal-footer">
        <form method="POST">
          <input type="hidden" name="action" value="delete_case">
          <input type="hidden" name="active_tab" value="case">
          <input type="hidden" id="delete_case_id" name="id">

          <button type="submit" class="btn btn-danger">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ================= COURT MODALS ================= -->

<?php if (!empty($canCourtCreate)): ?>
<!-- Add Court -->
<div class="modal fade" id="courtModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Add Court</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="add_court">
          <input type="hidden" name="active_tab" value="court">

          <div class="mb-3">
            <label>Court Name</label>
            <input type="text" class="form-control" name="court_name" required>
          </div>

          <button type="submit" class="btn btn-primary">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($canCourtEdit)): ?>
<!-- Edit Court -->
<div class="modal fade" id="editCourtModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Edit Court</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="edit_court">
          <input type="hidden" name="active_tab" value="court">
          <input type="hidden" id="edit_court_id" name="court_id">

          <div class="mb-3">
            <label>Court Name</label>
            <input type="text" class="form-control" id="edit_court_name" name="court_name" required>
          </div>

          <button type="submit" class="btn btn-success">Update</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delete Court -->
<div class="modal fade" id="deleteCourtModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Delete Court</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-footer">
        <form method="POST">
          <input type="hidden" name="action" value="delete_court">
          <input type="hidden" name="active_tab" value="court">
          <input type="hidden" id="delete_court_id" name="id">

          <button type="submit" class="btn btn-danger">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ================= DORM MODALS ================= -->

<?php if (!empty($canDormCreate)): ?>
<!-- Add Dorm -->
<div class="modal fade" id="dormModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Add Dorm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="add_dorm">
          <input type="hidden" name="active_tab" value="dorm">

          <div class="mb-3">
            <label>Dorm No</label>
            <input type="text" class="form-control" name="dorm_no" required>
          </div>

          <div class="mb-3">
            <label>Description</label>
            <input type="text" class="form-control" name="description" required>
          </div>

          <button type="submit" class="btn btn-primary">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($canDormEdit)): ?>
<!-- Edit Dorm -->
<div class="modal fade" id="editDormModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Edit Dorm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="edit_dorm">
          <input type="hidden" name="active_tab" value="dorm">
          <input type="hidden" id="edit_dorm_id" name="dorm_id">

          <div class="mb-3">
            <label>Dorm No</label>
            <input type="text" class="form-control" id="edit_dorm_no" name="dorm_no" required>
          </div>

          <div class="mb-3">
            <label>Description</label>
            <input type="text" class="form-control" id="edit_description" name="description" required>
          </div>

          <button type="submit" class="btn btn-success">Update</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delete Dorm -->
<div class="modal fade" id="deleteDormModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Delete Dorm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-footer">
        <form method="POST">
          <input type="hidden" name="action" value="delete_dorm">
          <input type="hidden" name="active_tab" value="dorm">
          <input type="hidden" id="delete_dorm_id" name="id">

          <button type="submit" class="btn btn-danger">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<!-- ================= RELATIONSHIP MODALS ================= -->

<?php if (!empty($canRelationshipCreate)): ?>
<div class="modal fade" id="relationshipModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Add Relationship</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="add_relationship">
          <input type="hidden" name="active_tab" value="relationship">

          <div class="mb-3">
            <label>Relationship Name</label>
            <input type="text" class="form-control" name="relationship_name" required>
          </div>

          <div class="mb-3">
            <label>Description</label>
            <input type="text" class="form-control" name="description">
          </div>

          <button type="submit" class="btn btn-primary">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($canRelationshipEdit)): ?>
<div class="modal fade" id="editRelationshipModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Edit Relationship</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="edit_relationship">
          <input type="hidden" name="active_tab" value="relationship">
          <input type="hidden" id="edit_relationship_id" name="relationship_id">

          <div class="mb-3">
            <label>Relationship Name</label>
            <input type="text" class="form-control" id="edit_relationship_name" name="relationship_name" required>
          </div>

          <div class="mb-3">
            <label>Description</label>
            <input type="text" class="form-control" id="edit_relationship_description" name="description">
          </div>

          <button type="submit" class="btn btn-success">Update</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ================= REQUIREMENT MODALS ================= -->

<?php if (!empty($canRequirementCreate)): ?>
<div class="modal fade" id="requirementModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Add Requirement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="add_requirement">
          <input type="hidden" name="active_tab" value="requirement">

          <div class="mb-3">
            <label>Requirement Name</label>
            <input type="text" class="form-control" name="requirement_name" required>
          </div>

          <div class="mb-3">
            <label>Description</label>
            <input type="text" class="form-control" name="description">
          </div>

          <div class="mb-3">
            <label>Status</label>
            <select class="form-select select2" name="is_active" data-placeholder="Select Status">
              <option value="1" selected>Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>

          <button type="submit" class="btn btn-primary">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($canRequirementEdit)): ?>
<div class="modal fade" id="editRequirementModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Edit Requirement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="edit_requirement">
          <input type="hidden" name="active_tab" value="requirement">
          <input type="hidden" id="edit_requirement_id" name="requirement_id">

          <div class="mb-3">
            <label>Requirement Name</label>
            <input type="text" class="form-control" id="edit_requirement_name" name="requirement_name" required>
          </div>

          <div class="mb-3">
            <label>Description</label>
            <input type="text" class="form-control" id="edit_requirement_description" name="description">
          </div>

          <div class="mb-3">
            <label>Status</label>
            <select class="form-select select2" id="edit_requirement_active" name="is_active" data-placeholder="Select Status">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>

          <button type="submit" class="btn btn-success">Update</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ================= ACCEPTED ID MODALS ================= -->

<?php if (!empty($canValidIdTypeCreate)): ?>
<div class="modal fade" id="validIdTypeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Add Accepted ID</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="add_valid_id_type">
          <input type="hidden" name="active_tab" value="valid-id-type">

          <div class="mb-3">
            <label>Accepted ID Name</label>
            <input type="text" class="form-control" name="id_type_name" required>
          </div>

          <div class="mb-3">
            <label>Description</label>
            <input type="text" class="form-control" name="description">
          </div>

          <div class="mb-3">
            <label>Status</label>
            <select class="form-select select2" name="is_active" data-placeholder="Select Status">
              <option value="1" selected>Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>

          <button type="submit" class="btn btn-primary">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($canValidIdTypeEdit)): ?>
<div class="modal fade" id="editValidIdTypeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Edit Accepted ID</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="edit_valid_id_type">
          <input type="hidden" name="active_tab" value="valid-id-type">
          <input type="hidden" id="edit_id_type_id" name="id_type_id">

          <div class="mb-3">
            <label>Accepted ID Name</label>
            <input type="text" class="form-control" id="edit_id_type_name" name="id_type_name" required>
          </div>

          <div class="mb-3">
            <label>Description</label>
            <input type="text" class="form-control" id="edit_id_type_description" name="description">
          </div>

          <div class="mb-3">
            <label>Status</label>
            <select class="form-select select2" id="edit_id_type_active" name="is_active" data-placeholder="Select Status">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>

          <button type="submit" class="btn btn-success">Update</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
