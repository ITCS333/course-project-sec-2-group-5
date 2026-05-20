/*
  Requirement: Make the "Manage Resources" page interactive.
*/

// --- Global Data Store ---
let resources = [];

// --- Element Selections ---
const resourceForm = document.querySelector('#resource-form');
const resourcesTbody = document.querySelector('#resources-tbody');

// --- Functions ---

/**
 * Creates and returns a <tr> element for a single resource.
 */
function createResourceRow(resource) {
  const { id, title, description, link } = resource;

  const tr = document.createElement('tr');

  // Title cell
  const titleTd = document.createElement('td');
  titleTd.textContent = title;

  // Description cell
  const descTd = document.createElement('td');
  descTd.textContent = description;

  // Link cell
  const linkTd = document.createElement('td');
  const anchor = document.createElement('a');
  anchor.href = link;
  anchor.textContent = link;
  anchor.target = '_blank';
  linkTd.appendChild(anchor);

  // Actions cell
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

  tr.appendChild(titleTd);
  tr.appendChild(descTd);
  tr.appendChild(linkTd);
  tr.appendChild(actionsTd);

  return tr;
}

/**
 * Clears and re-renders all rows in the resources table.
 */
function renderTable() {
  resourcesTbody.innerHTML = '';

  resources.forEach(resource => {
    const row = createResourceRow(resource);
    resourcesTbody.appendChild(row);
  });
}

/**
 * Handles the form submit event to add a new resource via POST.
 */
function handleAddResource(event) {
  event.preventDefault();

  const title       = document.querySelector('#resource-title').value.trim();
  const description = document.querySelector('#resource-description').value.trim();
  const link        = document.querySelector('#resource-link').value.trim();

  fetch('./api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title, description, link })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        resources.push({ id: data.id, title, description, link });
        renderTable();
        resourceForm.reset();
      }
    })
    .catch(err => console.error('Error adding resource:', err));
}

/**
 * Handles click events on the table body via event delegation.
 * Routes to delete or edit logic based on the clicked button's class.
 */
function handleTableClick(event) {
  const target = event.target;

  // --- Delete ---
  if (target.classList.contains('delete-btn')) {
    const id = target.dataset.id;

    fetch(`./api/index.php?id=${id}`, {
      method: 'DELETE'
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          resources = resources.filter(r => String(r.id) !== String(id));
          renderTable();
        }
      })
      .catch(err => console.error('Error deleting resource:', err));
  }

  // --- Edit ---
  if (target.classList.contains('edit-btn')) {
    const id       = target.dataset.id;
    const resource = resources.find(r => String(r.id) === String(id));

    if (!resource) return;

    // Populate form fields with existing values
    document.querySelector('#resource-title').value       = resource.title;
    document.querySelector('#resource-description').value = resource.description;
    document.querySelector('#resource-link').value        = resource.link;

    // Switch button to "Update" mode
    const submitBtn = document.querySelector('#add-resource');
    submitBtn.textContent = 'Update Resource';

    // Remove the previous submit listener and attach a one-time update handler
    const handleUpdateResource = (event) => {
      event.preventDefault();

      const title       = document.querySelector('#resource-title').value.trim();
      const description = document.querySelector('#resource-description').value.trim();
      const link        = document.querySelector('#resource-link').value.trim();

      fetch('./api/index.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, title, description, link })
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            // Update the matching entry in the global array
            const index = resources.findIndex(r => String(r.id) === String(id));
            if (index !== -1) {
              resources[index] = { id, title, description, link };
            }

            renderTable();
            resourceForm.reset();

            // Restore form to "Add" mode
            submitBtn.textContent = 'Add Resource';
            resourceForm.removeEventListener('submit', handleUpdateResource);
            resourceForm.addEventListener('submit', handleAddResource);
          }
        })
        .catch(err => console.error('Error updating resource:', err));
    };

    // Swap listeners: remove Add, attach Update
    resourceForm.removeEventListener('submit', handleAddResource);
    resourceForm.addEventListener('submit', handleUpdateResource);
  }
}

/**
 * Fetches all resources from the API, renders the table,
 * and attaches all event listeners.
 */
async function loadAndInitialize() {
  try {
    const res  = await fetch('./api/index.php');
    const data = await res.json();

    if (data.success) {
      resources = data.data;
      renderTable();
    }

    resourceForm.addEventListener('submit', handleAddResource);
    resourcesTbody.addEventListener('click', handleTableClick);

  } catch (err) {
    console.error('Error initialising page:', err);
  }
}

// --- Initial Page Load ---
loadAndInitialize();
