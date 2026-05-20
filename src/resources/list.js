/*
  Requirement: Populate the "Course Resources" list page.
*/

// --- Element Selections ---
const resourceListSection = document.querySelector('#resource-list-section');

// --- Functions ---

/**
 * Builds and returns a single resource <article> element.
 */
function createResourceArticle(resource) {
  const { id, title, description } = resource;

  const article = document.createElement('article');

  const heading = document.createElement('h3');
  heading.textContent = title;

  const p = document.createElement('p');
  p.textContent = description;

  const a = document.createElement('a');
  a.href        = `details.html?id=${id}`;
  a.textContent = 'View Resource & Discussion';

  article.appendChild(heading);
  article.appendChild(p);
  article.appendChild(a);

  return article;
}

/**
 * Fetches all resources from the API and renders them into the list section.
 */
async function loadResources() {
  try {
    const res  = await fetch('./api/index.php');
    const data = await res.json();

    resourceListSection.innerHTML = '';

    if (data.success && data.data.length > 0) {
      data.data.forEach(resource => {
        const article = createResourceArticle(resource);
        resourceListSection.appendChild(article);
      });
    } else {
      const empty = document.createElement('p');
      empty.textContent = 'No resources are available yet.';
      resourceListSection.appendChild(empty);
    }

  } catch (err) {
    console.error('Error loading resources:', err);

    const errorMsg = document.createElement('p');
    errorMsg.textContent = 'Failed to load resources. Please try again later.';
    resourceListSection.appendChild(errorMsg);
  }
}

// --- Initial Page Load ---
loadResources();
