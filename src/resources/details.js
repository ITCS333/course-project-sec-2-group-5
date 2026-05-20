/*
  Requirement: Populate the resource detail page and discussion forum.
*/

// --- Global Data Store ---
let currentResourceId = null;
let currentComments   = [];

// --- Element Selections ---
const resourceTitleEl       = document.querySelector('#resource-title');
const resourceDescriptionEl = document.querySelector('#resource-description');
const resourceLinkEl        = document.querySelector('#resource-link');
const commentListEl         = document.querySelector('#comment-list');
const commentFormEl         = document.querySelector('#comment-form');
const newCommentEl          = document.querySelector('#new-comment');

// --- Functions ---

/**
 * Reads the 'id' query parameter from the current page URL.
 * e.g. details.html?id=3  →  returns "3"
 */
function getResourceIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

/**
 * Populates the page header and body with the resource's details.
 */
function renderResourceDetails(resource) {
  const { title, description, link } = resource;

  resourceTitleEl.textContent       = title;
  resourceDescriptionEl.textContent = description;
  resourceLinkEl.href               = link;
}

/**
 * Builds and returns a single comment <article> element.
 */
function createCommentArticle(comment) {
  const { text, author } = comment;

  const article = document.createElement('article');

  const p = document.createElement('p');
  p.textContent = text;

  const footer = document.createElement('footer');
  footer.textContent = `Posted by: ${author}`;

  article.appendChild(p);
  article.appendChild(footer);

  return article;
}

/**
 * Clears and re-renders every comment in the comment list container.
 */
function renderComments() {
  commentListEl.innerHTML = '';

  currentComments.forEach(comment => {
    const article = createCommentArticle(comment);
    commentListEl.appendChild(article);
  });
}

/**
 * Handles the comment form submit: POSTs the new comment and refreshes the list.
 */
function handleAddComment(event) {
  event.preventDefault();

  const commentText = newCommentEl.value.trim();
  if (!commentText) return;

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
      if (data.success) {
        currentComments.push(data.data);
        renderComments();
        newCommentEl.value = '';
      }
    })
    .catch(err => console.error('Error posting comment:', err));
}

/**
 * Entry point: reads the URL id, fetches resource + comments in parallel,
 * then renders everything and wires up the comment form.
 */
async function initializePage() {
  currentResourceId = getResourceIdFromURL();

  if (!currentResourceId) {
    resourceTitleEl.textContent = 'Resource not found.';
    return;
  }

  try {
    const [resourceRes, commentsRes] = await Promise.all([
      fetch(`./api/index.php?id=${currentResourceId}`),
      fetch(`./api/index.php?resource_id=${currentResourceId}&action=comments`)
    ]);

    const resourceData = await resourceRes.json();
    const commentsData = await commentsRes.json();

    currentComments = commentsData.success ? commentsData.data : [];

    if (resourceData.success) {
      renderResourceDetails(resourceData.data);
      renderComments();
      commentFormEl.addEventListener('submit', handleAddComment);
    } else {
      resourceTitleEl.textContent = 'Resource not found.';
    }

  } catch (err) {
    console.error('Error initialising page:', err);
    resourceTitleEl.textContent = 'Failed to load resource.';
  }
}

// --- Initial Page Load ---
initializePage();
