// --- Global Data Store ---
let resources = [];

// --- Element Selections ---
const resourceForm = document.querySelector('#resource-form');
const resourcesTbody = document.querySelector('#resources-tbody');

// --- Functions ---

function createResourceRow(resource) {
  const { id, title, description, link } = resource;

  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>${title}</td>
    <td>${description}</td>
    <td><a href="${link}" target="_blank">${link}</a></td>
    <td>
      <button class="edit-btn"   data-id="${id}">Edit</button>
      <button class="delete-btn" data-id="${id}">Delete</button>
    </td>
  `;
  return tr;
}

function renderTable() {
  resourcesTbody.innerHTML = '';
  resources.forEach(resource => {
    resourcesTbody.appendChild(createResourceRow(resource));
  });
}

async function handleAddResource(event) {
  event.preventDefault();

  const title       = document.querySelector('#resource-title').value;
  const description = document.querySelector('#resource-description').value;
  const link        = document.querySelector('#resource-link').value;

  const response = await fetch('./api/index.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ title, description, link }),
  });

  const result = await response.json();

  if (result.success) {
    resources.push({ id: result.id, title, description, link });
    renderTable();
    resourceForm.reset();
  }
}

function handleTableClick(event) {
  const target = event.target;
  const id     = target.dataset.id;

  if (target.classList.contains('delete-btn')) {
    fetch(`./api/index.php?id=${id}`, { method: 'DELETE' })
      .then(res => res.json())
      .then(result => {
        if (result.success) {
          resources = resources.filter(r => r.id != id);
          renderTable();
        }
      });
  }

  if (target.classList.contains('edit-btn')) {
    const resource = resources.find(r => r.id == id);

    document.querySelector('#resource-title').value       = resource.title;
    document.querySelector('#resource-description').value = resource.description;
    document.querySelector('#resource-link').value        = resource.link;

    const submitBtn  = document.querySelector('#add-resource');
    submitBtn.textContent = 'Update Resource';

    // Replace the submit listener with an update handler
    const handleUpdate = async (event) => {
      event.preventDefault();

      const title       = document.querySelector('#resource-title').value;
      const description = document.querySelector('#resource-description').value;
      const link        = document.querySelector('#resource-link').value;

      const response = await fetch('./api/index.php', {
        method:  'PUT',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id, title, description, link }),
      });

      const result = await response.json();

      if (result.success) {
        const index = resources.findIndex(r => r.id == id);
        resources[index] = { id, title, description, link };

        renderTable();
        resourceForm.reset();

        submitBtn.textContent = 'Add Resource';
        resourceForm.removeEventListener('submit', handleUpdate);
        resourceForm.addEventListener('submit', handleAddResource);
      }
    };

    resourceForm.removeEventListener('submit', handleAddResource);
    resourceForm.addEventListener('submit', handleUpdate);
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result   = await response.json();

  resources = result.data;
  renderTable();

  resourceForm.addEventListener('submit', handleAddResource);
  resourcesTbody.addEventListener('click', handleTableClick);
}

// --- Initial Page Load ---
loadAndInitialize();
