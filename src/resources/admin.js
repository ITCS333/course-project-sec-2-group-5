/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. Link this file to `admin.html` using:
     <script src="admin.js" defer></script>
  
  2. In `admin.html`, add id="resources-tbody" to the <tbody> element
     inside your resources-table. This id is required by this script.
  
  3. Implement the TODOs below.
*/

// --- Global Data Store ---
// This will hold the resources loaded from the API.
let resources = [];
let currentEditId = null; // Tracks which resource is currently being edited (null means Add mode)

// --- Element Selections ---
// TODO: Select the resource form ('#resource-form').
const resourceForm = document.querySelector('#resource-form');

// TODO: Select the resources table body ('#resources-tbody').
const resourcesTbody = document.querySelector('#resources-tbody');

// Reference to the submit button to dynamically toggle text
const submitBtn = document.querySelector('#add-resource');

// --- Functions ---

/**
 * TODO: Implement the createResourceRow function.
 * It takes one resource object { id, title, description, link }.
 * It should return a <tr> element with the following <td>s:
 * 1. A <td> for the title.
 * 2. A <td> for the description.
 * 3. A <td> for the link.
 * 4. A <td> containing two buttons:
 * - An "Edit" button with class="edit-btn" and data-id="${id}".
 * - A "Delete" button with class="delete-btn" and data-id="${id}".
 */
function createResourceRow(resource) {
  const { id, title, description, link } = resource;

  const tr = document.createElement('tr');

  // 1. Title cell
  const titleTd = document.createElement('td');
  titleTd.textContent = title;

  // 2. Description cell
  const descTd = document.createElement('td');
  descTd.textContent = description;

  // 3. Link cell
  const linkTd = document.createElement('td');
  const anchor = document.createElement('a');
  anchor.href = link;
  anchor.textContent = link;
  anchor.target = '_blank';
  linkTd.appendChild(anchor);

  // 4. Actions cell with Edit and Delete buttons
  const actionsTd = document.createElement('td');

  const editBtn = document.createElement('button');
  editBtn.textContent = 'Edit';
  editBtn.className = 'edit-btn';
  editBtn.dataset.id = id;

  const deleteBtn = document.createElement('button');
  deleteBtn.textContent = 'Delete';
  deleteBtn.className = 'delete-btn';
  deleteBtn.dataset.id = id;

  actionsTd.appendChild(editBtn);
  actionsTd.appendChild(deleteBtn);

  // Append all table data cells to the table row
  tr.appendChild(titleTd);
  tr.appendChild(descTd);
  tr.appendChild(linkTd);
  tr.appendChild(actionsTd);

  return tr;
}

/**
 * TODO: Implement the renderTable function.
 * It should:
 * 1. Clear the resources table body ('#resources-tbody').
 * 2. Loop through the global `resources` array.
 * 3. For each resource, call `createResourceRow()` and
 * append the returned <tr> to the table body.
 */
function renderTable() {
  // 1. Clear the table body
  resourcesTbody.innerHTML = '';

  // 2 & 3. Loop through and append rows
  resources.forEach(resource => {
    const row = createResourceRow(resource);
    resourcesTbody.appendChild(row);
  });
}

/**
 * Helper function to handle cleaning up the UI state back to "Add" mode.
 */
function resetFormState() {
  currentEditId = null;
  resourceForm.reset();
  if (submitBtn) {
    submitBtn.textContent = 'Add Resource';
  }
  renderTable();
}

/**
 * TODO: Implement the handleAddResource function.
 * This is the event handler for the form's 'submit' event.
 * It handles both creating a new resource (POST) and updating an existing resource (PUT).
 */
function handleAddResource(event) {
  // 1. Prevent default submission
  event.preventDefault();

  // 2. Get trimmed values from inputs
  const title       = document.querySelector('#resource-title').value.trim();
  const description = document.querySelector('#resource-description').value.trim();
  const link        = document.querySelector('#resource-link').value.trim();

  if (currentEditId !== null) {
    // --- 5. On form submit (PUT execution path) ---
    fetch('./api/index.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: currentEditId, title, description, link })
    })
      .then(res => res.json())
      .then(data => {
        // 6. On success, update matching item in the global array
        if (data.success) {
          const index = resources.findIndex(r => String(r.id) === String(currentEditId));
          if (index !== -1) {
            resources[index] = { id: currentEditId, title, description, link };
          }
          // 7. Refresh list, clear tracking state, reset submit button text and clear inputs
          resetFormState();
        }
      })
      .catch(err => console.error('Error updating resource:', err));

  } else {
    // --- 3. Use fetch() to POST the new resource to the API ---
    fetch('./api/index.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, description, link })
    })
      .then(res => res.json())
      .then(data => {
        // 4. Add the new resource object to global store
        if (data.success) {
          resources.push({ id: data.id, title, description, link });
          // 5 & 6. Refresh the list and reset the form
          resetFormState();
        }
      })
      .catch(err => console.error('Error adding resource:', err));
  }
}

/**
 * TODO: Implement the handleTableClick function.
 * This handles click events on the table body using event delegation.
 */
function handleTableClick(event) {
  const target = event.target;
  const id = target.dataset.id;

  // --- If the clicked element has class "delete-btn" ---
  if (target.classList.contains('delete-btn')) {
    // 1 & 2. Execute DELETE via API
    fetch(`./api/index.php?id=${id}`, {
      method: 'DELETE'
    })
      .then(res => res.json())
      .then(data => {
        // 3 & 4. Remove resource from global array and refresh table list
        if (data.success) {
          resources = resources.filter(r => String(r.id) !== String(id));
          
          // If the resource being deleted was currently being edited, reset the form view
          if (String(currentEditId) === String(id)) {
            resetFormState();
          } else {
            renderTable();
          }
        }
      })
      .catch(err => console.error('Error deleting resource:', err));
  }

  // --- If the clicked element has class "edit-btn" ---
  if (target.classList.contains('edit-btn')) {
    // 1 & 2. Find the matching item
    const resource = resources.find(r => String(r.id) === String(id));
    if (!resource) return;

    // Save context globally to signify we are editing this database item
    currentEditId = id;

    // 3. Populate form fields with existing values
    document.querySelector('#resource-title').value       = resource.title;
    document.querySelector('#resource-description').value = resource.description;
    document.querySelector('#resource-link').value        = resource.link;

    // 4. Change submit button styling text to indicate edit mode
    if (submitBtn) {
      submitBtn.textContent = 'Update Resource';
    }
  }
}

/**
 * TODO: Implement the loadAndInitialize function.
 * This function must be 'async'.
 */
async function loadAndInitialize() {
  try {
    // 1. Use fetch() to GET all resources from the API
    const res = await fetch('./api/index.php');
    const data = await res.json();

    // 2. Store the elements globally
    if (data.success && data.data) {
      resources = data.data;
    }

    // 3. Populate the table view initially
    renderTable();

    // 4. Persistent single-listener attachment for the form submit lifecycle
    if (resourceForm) {
      resourceForm.addEventListener('submit', handleAddResource);
      
      // Secondary helper: If the admin resets the form manually, restore button labels
      resourceForm.addEventListener('reset', () => {
        currentEditId = null;
        if (submitBtn) submitBtn.textContent = 'Add Resource';
      });
    }

    // 5. Add event listener to the table body for event delegation
    if (resourcesTbody) {
      resourcesTbody.addEventListener('click', handleTableClick);
    }

  } catch (err) {
    console.error('Error initializing page:', err);
  }
}

// --- Initial Page Load ---
// Call the main async function to start the application.
loadAndInitialize();
