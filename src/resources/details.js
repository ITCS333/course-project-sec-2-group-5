/*
  Requirement: Populate the resource detail page and discussion forum.

  Instructions:
  1. Link this file to `details.html` using:
     <script src="details.js" defer></script>

  2. In `details.html`, add the following IDs:
     - To the <h1>:                           id="resource-title"
     - To the description <p>:                id="resource-description"
     - To the "Access Resource Material" <a>: id="resource-link"
     - To the <div> for comments:             id="comment-list"
     - To the comment <form>:                 id="comment-form"
     - To the <textarea>:                     id="new-comment"

  3. Implement the TODOs below.
*/

// --- Global Data Store ---
// These will hold the data related to this specific resource.
let currentResourceId = null;
let currentComments = [];

// --- Element Selections ---
// TODO: Select all the elements you added IDs for in step 2.
const resourceTitle       = document.querySelector('#resource-title');
const resourceDescription = document.querySelector('#resource-description');
const resourceLink        = document.querySelector('#resource-link');
const commentList         = document.querySelector('#comment-list');
const commentForm         = document.querySelector('#comment-form');
const newCommentTextarea  = document.querySelector('#new-comment');

// --- Functions ---

/**
 * TODO: Implement the getResourceIdFromURL function.
 * It should:
 * 1. Get the query string from `window.location.search`.
 * 2. Use the `URLSearchParams` object to get the value of the 'id' parameter.
 * 3. Return the id value (as a string).
 */
function getResourceIdFromURL() {
  // 1 & 2. Access search parameters
  const params = new URLSearchParams(window.location.search);
  // 3. Return the 'id' value as a string (returns null if not present)
  return params.get('id');
}

/**
 * TODO: Implement the renderResourceDetails function.
 * It takes one resource object { id, title, description, link }.
 * It should:
 * 1. Set the `textContent` of the title element (id="resource-title")
 * to the resource's title.
 * 2. Set the `textContent` of the description element (id="resource-description")
 * to the resource's description.
 * 3. Set the `href` attribute of the link element (id="resource-link")
 * to the resource's link.
 */
function renderResourceDetails(resource) {
  if (!resource) return;
  
  // 1. Set title text
  if (resourceTitle) resourceTitle.textContent = resource.title;
  
  // 2. Set description text
  if (resourceDescription) resourceDescription.textContent = resource.description;
  
  // 3. Set link destination URL
  if (resourceLink) {
    resourceLink.href = resource.link;
    // Optional: match textContent to href or custom message if requested
    resourceLink.textContent = "Access Resource Material";
  }
}

/**
 * TODO: Implement the createCommentArticle function.
 * It takes one comment object { id, resource_id, author, text, created_at }.
 * It should return an <article> element matching the structure in `details.html`:
 * - A <p> containing the comment's text.
 * - A <footer> containing the comment's author
 * (e.g., "Posted by: Ali Hassan").
 */
function createCommentArticle(comment) {
  const { author, text } = comment;

  // Create wrapping article container
  const article = document.createElement('article');

  // Create element for comment text
  const textPara = document.createElement('p');
  textPara.textContent = text;

  // Create footer element for meta details
  const footerMeta = document.createElement('footer');
  footerMeta.textContent = `Posted by: ${author}`;

  // Assemble elements
  article.appendChild(textPara);
  article.appendChild(footerMeta);

  return article;
}

/**
 * TODO: Implement the renderComments function.
 * It should:
 * 1. Clear the comment list container (id="comment-list").
 * 2. Loop through the global `currentComments` array.
 * 3. For each comment, call `createCommentArticle()` and
 * append the returned <article> to the comment list container.
 */
function renderComments() {
  if (!commentList) return;

  // 1. Clear container content
  commentList.innerHTML = '';

  // 2 & 3. Iterate and append comment nodes
  currentComments.forEach(comment => {
    const commentNode = createCommentArticle(comment);
    commentList.appendChild(commentNode);
  });
}

/**
 * TODO: Implement the handleAddComment function.
 * This is the event handler for the comment form's 'submit' event.
 */
function handleAddComment(event) {
  // 1. Prevent form's default submission behavior
  event.preventDefault();

  if (!newCommentTextarea) return;

  // 2. Extract values from input
  const commentText = newCommentTextarea.value.trim();

  // 3. Validation safeguard clause
  if (!commentText) return;

  // 4. Use fetch() to POST the new comment to the API
  fetch('./api/index.php?action=comment', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      resource_id: currentResourceId,
      author: 'Student',
      text: commentText
    })
  })
    .then(res => res.json())
    .then(data => {
      // 5. On success, push the response comment payload to data arrays
      if (data.success && data.data) {
        currentComments.push(data.data);
      } else if (data.success) {
        // Fallback option if data returns the raw entity parameters directly
        currentComments.push({
          id: data.id,
          resource_id: currentResourceId,
          author: 'Student',
          text: commentText
        });
      }

      // 6. Refresh view list elements
      renderComments();

      // 7. Clear original container inputs
      newCommentTextarea.value = '';
    })
    .catch(err => console.error('Error submitting comment data:', err));
}

/**
 * TODO: Implement the initializePage function.
 * This function must be 'async'.
 */
async function initializePage() {
  // 1. Fetch query keys
  currentResourceId = getResourceIdFromURL();

  // 2. ID validation error check mapping
  if (!currentResourceId) {
    if (resourceTitle) resourceTitle.textContent = "Resource not found.";
    return;
  }

  try {
    // 3. Concurrently await both resource profile properties and relative sub-lists
    const [resourceRes, commentsRes] = await Promise.all([
      fetch(`./api/index.php?id=${currentResourceId}`),
      fetch(`./api/index.php?resource_id=${currentResourceId}&action=comments`)
    ]);

    const resourceData = await resourceRes.json();
    const commentsData = await commentsRes.json();

    // 4. Store retrieved logs to global arrays safely
    if (commentsData.success && Array.isArray(commentsData.data)) {
      currentComments = commentsData.data;
    } else {
      currentComments = [];
    }

    // 5 & 6. Conditional state logic for resource availability mapping
    if (resourceData.success && resourceData.data) {
      // Call visual processors
      renderResourceDetails(resourceData.data);
      renderComments();

      // Attach interaction tracking listeners
      if (commentForm) {
        commentForm.addEventListener('submit', handleAddComment);
      }
    } else {
      if (resourceTitle) resourceTitle.textContent = "Resource not found.";
    }

  } catch (err) {
    console.error('Failed to initialize Details Module pipeline context:', err);
    if (resourceTitle) resourceTitle.textContent = "Resource not found.";
  }
}

// --- Initial Page Load ---
initializePage();
