let assignments = [];

const assignmentForm = document.getElementById("assignment-form");
const assignmentsTbody = document.getElementById("assignments-tbody");

function createAssignmentRow(assignment) {
  const row = document.createElement("tr");

  const titleCell = document.createElement("td");
  titleCell.textContent = assignment.title;

  const dueDateCell = document.createElement("td");
  dueDateCell.textContent = assignment.due_date;

  const descriptionCell = document.createElement("td");
  descriptionCell.textContent = assignment.description;

  const actionsCell = document.createElement("td");

  const editButton = document.createElement("button");
  editButton.textContent = "Edit";
  editButton.className = "edit-btn";
  editButton.dataset.id = assignment.id;

  const deleteButton = document.createElement("button");
  deleteButton.textContent = "Delete";
  deleteButton.className = "delete-btn";
  deleteButton.dataset.id = assignment.id;

  actionsCell.appendChild(editButton);
  actionsCell.appendChild(deleteButton);

  row.appendChild(titleCell);
  row.appendChild(dueDateCell);
  row.appendChild(descriptionCell);
  row.appendChild(actionsCell);

  return row;
}

function renderTable() {
  assignmentsTbody.innerHTML = "";

  assignments.forEach(function (assignment) {
    assignmentsTbody.appendChild(createAssignmentRow(assignment));
  });
}

async function handleAddAssignment(event) {
  event.preventDefault();

  const title = document.getElementById("assignment-title").value.trim();
  const due_date = document.getElementById("assignment-due-date").value;
  const description = document.getElementById("assignment-description").value.trim();

  const files = document
    .getElementById("assignment-files")
    .value
    .split("\n")
    .map(function (url) {
      return url.trim();
    })
    .filter(function (url) {
      return url !== "";
    });

  const submitButton = document.getElementById("add-assignment");
  const editId = submitButton.dataset.editId;

  const fields = {
    title: title,
    due_date: due_date,
    description: description,
    files: files
  };

  if (editId) {
    await handleUpdateAssignment(editId, fields);
    return;
  }

  const response = await fetch("./api/index.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify(fields)
  });

  const result = await response.json();

  if (result.success) {
    assignments.push({
      id: result.id,
      title: title,
      due_date: due_date,
      description: description,
      files: files
    });

    renderTable();
    assignmentForm.reset();
  }
}

async function handleUpdateAssignment(id, fields) {
  const response = await fetch("./api/index.php", {
    method: "PUT",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      id: Number(id),
      title: fields.title,
      due_date: fields.due_date,
      description: fields.description,
      files: fields.files
    })
  });

  const result = await response.json();

  if (result.success) {
    assignments = assignments.map(function (assignment) {
      if (Number(assignment.id) === Number(id)) {
        return {
          id: Number(id),
          title: fields.title,
          due_date: fields.due_date,
          description: fields.description,
          files: fields.files
        };
      }

      return assignment;
    });

    renderTable();
    assignmentForm.reset();

    const submitButton = document.getElementById("add-assignment");
    submitButton.textContent = "Add Assignment";
    delete submitButton.dataset.editId;
  }
}

async function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = event.target.dataset.id;

    const response = await fetch("./api/index.php?id=" + id, {
      method: "DELETE"
    });

    const result = await response.json();

    if (result.success) {
      assignments = assignments.filter(function (assignment) {
        return Number(assignment.id) !== Number(id);
      });

      renderTable();
    }
  }

  if (event.target.classList.contains("edit-btn")) {
    const id = event.target.dataset.id;

    const assignment = assignments.find(function (item) {
      return Number(item.id) === Number(id);
    });

    if (!assignment) {
      return;
    }

    document.getElementById("assignment-title").value = assignment.title;
    document.getElementById("assignment-due-date").value = assignment.due_date;
    document.getElementById("assignment-description").value = assignment.description;
    document.getElementById("assignment-files").value = assignment.files.join("\n");

    const submitButton = document.getElementById("add-assignment");
    submitButton.textContent = "Update Assignment";
    submitButton.dataset.editId = assignment.id;
  }
}

async function loadAndInitialize() {
  try {
    const response = await fetch("./api/index.php");
    const result = await response.json();

    assignments = result.success && Array.isArray(result.data)
      ? result.data
      : [];

    renderTable();

    assignmentForm.addEventListener("submit", handleAddAssignment);
    assignmentsTbody.addEventListener("click", handleTableClick);
  } catch (error) {
    console.error("Error:", error);
  }
}

loadAndInitialize();
