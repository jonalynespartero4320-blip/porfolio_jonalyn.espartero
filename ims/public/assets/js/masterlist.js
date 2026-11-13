// masterlist.js

// Function to handle Add Case
function addCase(event) {
    event.preventDefault();

    const caseNo = document.getElementById("case_no").value;
    const caseType = document.getElementById("case_type").value;

    if (!caseNo || !caseType) {
        Swal.fire("Warning!", "Case number and type are required.", "warning");
        return;
    }

    const form = document.createElement("form");
    form.method = "POST";

    form.innerHTML = `
        <input type="hidden" name="action" value="add_case">
        <input type="hidden" name="case_no" value="${caseNo}">
        <input type="hidden" name="case_type" value="${caseType}">
    `;

    document.body.appendChild(form);
    form.submit();
}

function editCase(event) {
    event.preventDefault();

    const id = document.getElementById("edit_case_id").value;
    const caseNo = document.getElementById("edit_case_no").value;
    const caseType = document.getElementById("edit_case_type").value;

    if (!caseNo || !caseType) {
        Swal.fire("Warning!", "Case number and type are required.", "warning");
        return;
    }

    const form = document.createElement("form");
    form.method = "POST";

    form.innerHTML = `
        <input type="hidden" name="action" value="edit_case">
        <input type="hidden" name="case_id" value="${id}">
        <input type="hidden" name="case_no" value="${caseNo}">
        <input type="hidden" name="case_type" value="${caseType}">
    `;

    document.body.appendChild(form);
    form.submit();
}

// Function to handle Delete Case
function deleteCase(id) {
    Swal.fire({
        title: "Are you sure?",
        text: "This case will be deleted!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!"
    }).then((result) => {
        if (result.isConfirmed) {

            const form = document.createElement("form");
            form.method = "POST";

            form.innerHTML = `
                <input type="hidden" name="action" value="delete_case">
                <input type="hidden" name="id" value="${id}">
            `;

            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Function to handle Add Court
function addCourt(event) {
    event.preventDefault();

    const courtName = document.getElementById("court_name").value;

    if (!courtName) {
        Swal.fire("Warning!", "Court name is required.", "warning");
        return;
    }

    const form = document.createElement("form");
    form.method = "POST";

    form.innerHTML = `
        <input type="hidden" name="action" value="add_court">
        <input type="hidden" name="court_name" value="${courtName}">
    `;

    document.body.appendChild(form);
    form.submit();
}

function editCourt(event) {
    event.preventDefault();

    const id = document.getElementById("edit_court_id").value;
    const courtName = document.getElementById("edit_court_name").value;

    if (!courtName) {
        Swal.fire("Warning!", "Court name is required.", "warning");
        return;
    }

    const form = document.createElement("form");
    form.method = "POST";

    form.innerHTML = `
        <input type="hidden" name="action" value="edit_court">
        <input type="hidden" name="court_id" value="${id}">
        <input type="hidden" name="court_name" value="${courtName}">
    `;

    document.body.appendChild(form);
    form.submit();
}

// Function to handle Delete Court
function deleteCourt(id) {
    Swal.fire({
        title: "Are you sure?",
        text: "This court will be deleted!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!"
    }).then((result) => {
        if (result.isConfirmed) {

            const form = document.createElement("form");
            form.method = "POST";

            form.innerHTML = `
                <input type="hidden" name="action" value="delete_court">
                <input type="hidden" name="id" value="${id}">
            `;

            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Function to handle Add Dorm
function addDorm(event) {
    event.preventDefault();

    const dormNo = document.getElementById("dorm_no").value;
    const description = document.getElementById("description").value;

    if (!dormNo || !description) {
        Swal.fire("Warning!", "Dorm number and description are required.", "warning");
        return;
    }

    const form = document.createElement("form");
    form.method = "POST";

    form.innerHTML = `
        <input type="hidden" name="action" value="add_dorm">
        <input type="hidden" name="dorm_no" value="${dormNo}">
        <input type="hidden" name="description" value="${description}">
    `;

    document.body.appendChild(form);
    form.submit();
}

// Function to handle Edit Dorm
function editDorm(event, id) {
    event.preventDefault();

    const dormNo = document.getElementById("edit_dorm_no").value;
    const description = document.getElementById("edit_description").value;

    if (!dormNo || !description) {
        Swal.fire("Warning!", "Dorm number and description are required.", "warning");
        return;
    }

    const form = document.createElement("form");
    form.method = "POST";

    form.innerHTML = `
        <input type="hidden" name="action" value="edit_dorm">
        <input type="hidden" name="dorm_id" value="${id}">
        <input type="hidden" name="dorm_no" value="${dormNo}">
        <input type="hidden" name="description" value="${description}">
    `;

    document.body.appendChild(form);
    form.submit();
}

// Function to handle Delete Dorm
function deleteDorm(id) {
    Swal.fire({
        title: "Are you sure?",
        text: "This dorm will be deleted!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!"
    }).then((result) => {
        if (result.isConfirmed) {

            const form = document.createElement("form");
            form.method = "POST";

            form.innerHTML = `
                <input type="hidden" name="action" value="delete_dorm">
                <input type="hidden" name="id" value="${id}">
            `;

            document.body.appendChild(form);
            form.submit();
        }
    });
}