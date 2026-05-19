/*
  Requirement: Make the "Discussion Board" page interactive.

  Instructions:
  1. This file is already linked to `board.html` via:
         <script src="board.js" defer></script>

  2. In `board.html`:
     - The new-topic form has id="new-topic-form".
     - The topic list container has id="topic-list-container".

  3. Implement the TODOs below.

  API base URL: ./api/index.php
  All requests and responses use JSON.
  Successful list response shape: { success: true, data: [ ...topic objects ] }
  Each topic object shape (from the topics table):
    {
      id:         number,   // integer primary key from the topics table
      subject:    string,
      message:    string,
      author:     string,
      created_at: string    // "YYYY-MM-DD HH:MM:SS" — matches the SQL column name
    }
*/

// --- Global Data Store ---
// Holds the topics currently displayed in the list.
let topics = [];

// --- Element Selections ---
// Select the new-topic form by id 'new-topic-form'.
const newTopicForm = document.getElementById('new-topic-form');

// Select the topic list container by id 'topic-list-container'.
const topicListContainer = document.getElementById('topic-list-container');

// Select the submit button to handle dynamic UI state updates during edit mode
const submitButton = document.getElementById('create-topic');


// --- Functions ---

/**
 * Creates and returns an HTML <article> element matching the discussion board schema.
 *
 * Parameters:
 * topic — one topic object with shape: { id, subject, message, author, created_at }
 *
 * Returns: HTMLArticleElement
 */
function createTopicArticle(topic) {
  const article = document.createElement('article');

  // Clean string helper to protect against template injection vulnerabilities
  const escapeHtml = (str) => {
    if (!str) return '';
    return str.toString()
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  article.innerHTML = `
    <h3><a href="topic.html?id=${topic.id}">${escapeHtml(topic.subject)}</a></h3>
    <footer>Posted by: ${escapeHtml(topic.author)} on ${escapeHtml(topic.created_at)}</footer>
    <div>
      <button class="edit-btn" data-id="${topic.id}">Edit</button>
      <button class="delete-btn" data-id="${topic.id}">Delete</button>
    </div>
  `;

  return article;
}

/**
 * Clears out the current view and populates the container with items from the global store.
 */
function renderTopics() {
  // 1. Clear the topicListContainer
  topicListContainer.innerHTML = "";

  // 2. Loop through the global topics array
  topics.forEach(topic => {
    // 3. Create component and append to structural container hook
    const topicArticle = createTopicArticle(topic);
    topicListContainer.appendChild(topicArticle);
  });
}

/**
 * Event handler for the new-topic form's 'submit' event.
 * Handles both new records (POST) and existing record changes (PUT).
 */
async function handleCreateTopic(event) {
  // 1. Intercept native browser handling
  event.preventDefault();

  // 2. Read structural interface element values
  const subjectInput = document.getElementById('topic-subject');
  const messageInput = document.getElementById('topic-message');

  const subject = subjectInput.value.trim();
  const message = messageInput.value.trim();

  if (!subject || !message) {
    alert("Please fill out all required form fields.");
    return;
  }

  // Intercept workflow routing logic: Check if we are updating an existing entry
  const editId = submitButton.getAttribute('data-edit-id');
  if (editId) {
    await handleUpdateTopic(parseInt(editId, 10), { subject, message });

    // Reset form submission view states back to creation mode
    submitButton.textContent = "Create Topic";
    submitButton.removeAttribute('data-edit-id');
    newTopicForm.reset();
    return;
  }

  // 3. Send a POST payload to store a brand new topic
  try {
    const response = await fetch('./api/index.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        subject,
        message,
        author: "Student" // Author identity context parameter hardcoded for this assignment module
      })
    });

    const result = await response.json();

    // 4. On API verification success:
    if (result.success === true) {
      // Build internal record tracking object using the database entity primary key returned
      const newTopic = {
        id: result.id,
        subject: subject,
        message: message,
        author: "Student",
        created_at: new Date().toISOString().replace('T', ' ').substring(0, 19) // Approximate a fallback client-side valid standard SQL timestamp
      };

      // Push onto tracking cache and update screen views
      topics.push(newTopic);
      renderTopics();
      newTopicForm.reset();
    } else {
      alert(result.message || "An unexpected issue occurred while storing the new topic.");
    }
  } catch (error) {
    console.error("Network communication exception during topic persistence execution:", error);
    alert("Unable to reach servers. Please test connection parameters.");
  }
}

/**
 * Sends structural modifications (PUT) to synchronize state edits back to the storage layer.
 */
async function handleUpdateTopic(id, fields) {
  try {
    // 1. Dispatch update array back to API endpoint context routing layers
    const response = await fetch('./api/index.php', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: id,
        subject: fields.subject,
        message: fields.message
      })
    });

    const result = await response.json();

    // 2. Sync internal data properties matching the successfully targeted entity ID
    if (result.success === true) {
      const targetIndex = topics.findIndex(item => item.id === id);
      if (targetIndex !== -1) {
        topics[targetIndex].subject = fields.subject;
        topics[targetIndex].message = fields.message;
      }

      // Update data visualization panels
      renderTopics();
    } else {
      alert(result.message || "Failed to commit record updates.");
    }
  } catch (error) {
    console.error("Network communication failure during context modification transaction:", error);
  }
}

/**
 * Event-delegated collection event tracking handler hooked onto parent discussion element wrappers.
 */
async function handleTopicListClick(event) {
  const targetElement = event.target;

  // 1. Handle targeted record deletion commands
  if (targetElement.classList.contains('delete-btn')) {
    // a. Parse out resource index identity details
    const topicId = parseInt(targetElement.dataset.id, 10);

    if (!confirm("Are you verify choosing to remove this topic profile permanently?")) {
      return;
    }

    // b. Send DELETE request payload parameter options
    try {
      const response = await fetch(`./api/index.php?id=${topicId}`, {
        method: 'DELETE'
      });
      const result = await response.json();

      // c. On successful backend clearing verification:
      if (result.success === true) {
        topics = topics.filter(item => item.id !== topicId);
        renderTopics();
      } else {
        alert(result.message || "Removal request processing denied on server.");
      }
    } catch (error) {
      console.error("Communication system fault encountered while erasing discussion entity:", error);
    }
    return;
  }

  // 2. Handle component modification workflow state modifications
  if (targetElement.classList.contains('edit-btn')) {
    // a. Parse selection entity data attributes
    const topicId = parseInt(targetElement.dataset.id, 10);

    // b. Retrieve original state from client-side runtime arrays
    const targetedTopic = topics.find(item => item.id === topicId);

    if (targetedTopic) {
      // c. Load input form value states with contextual parameters
      document.getElementById('topic-subject').value = targetedTopic.subject;
      document.getElementById('topic-message').value = targetedTopic.message;

      // d. Reconfigure form tracking contexts to target an update action workflow path
      submitButton.textContent = "Update Topic";
      submitButton.setAttribute('data-edit-id', topicId);

      // UX Polish: Scroll to form container view block smoothly
      newTopicForm.scrollIntoView({ behavior: 'smooth' });
    }
  }
}

/**
 * Resolves application boot, loading initial storage states, and mapping interactive event observers.
 */
async function loadAndInitialize() {
  try {
    // 1. Fetch collection states from endpoint router targets
    const response = await fetch('./api/index.php');
    const result = await response.json();

    // 2. Synchronize responses onto internal global state caches
    if (result.success === true && Array.isArray(result.data)) {
      topics = result.data;

      // 3. Render content entries inside UI viewport view panels
      renderTopics();
    }
  } catch (error) {
    console.error("Critical board dependency loading routine exceptions caught:", error);
  }

  // 4. Attach event listeners
  newTopicForm.addEventListener('submit', handleCreateTopic);

  // 5. Attach event delegation handlers onto global topic collections listing hooks
  topicListContainer.addEventListener('click', handleTopicListClick);
}

// --- Initial Page Load Initialization Execution Trigger ---
loadAndInitialize();