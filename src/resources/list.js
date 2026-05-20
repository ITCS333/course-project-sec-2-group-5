/*
  Requirement: Populate the "Course Resources" list page.
  Instructions:
  1. Link this file to list.html using:
     <script src="list.js" defer></script>
  2. In list.html, add id="resource-list-section" to the
     <section> element that will contain the resource articles.
  3. Implement the TODOs below.
*/

// --- Element Selections ---
// TODO: Select the section for the resource list ('#resource-list-section').
const resourceListSection = document.querySelector('#resource-list-section');

// --- Functions ---

/**
 * TODO: Implement the createResourceArticle function.
 * It takes one resource object { id, title, description, link }.
 * It should return an <article> element matching the structure in list.html.
 * The "View Resource & Discussion" link's href MUST be set to
 * details.html?id=${id} so the detail page knows which resource to load.
 */
function createResourceArticle(resource) {
  const { id, title, description } = resource;

  // 1. Create the main wrapping <article> element
  const article = document.createElement('article');

  // 2. Create the resource title heading element
  const titleHeading = document.createElement('h3');
  titleHeading.textContent = title;

  // 3. Create the paragraph element for the description
  const descriptionPara = document.createElement('p');
  descriptionPara.textContent = description;

  // 4. Create the detail/discussion link pointing to details.html?id=${id}
  const detailLink = document.createElement('a');
  detailLink.href = `details.html?id=${id}`;
  detailLink.textContent = 'View Resource & Discussion';
  
  // Custom styling hook: Display block or line break if needed for clean layout
  detailLink.style.display = 'inline-block';
  detailLink.style.marginTop = '10px';

  // 5. Assemble all components inside the article node
  article.appendChild(titleHeading);
  article.appendChild(descriptionPara);
  article.appendChild(detailLink);

  return article;
}

/**
 * TODO: Implement the loadResources function.
 * This function must be 'async'.
 * It should:
 * 1. Use fetch() to GET data from the API endpoint:
 * './api/index.php'
 * 2. Parse the JSON response. The API returns { success: true, data: [...] }.
 * 3. Clear any existing content from the list section.
 * 4. Loop through the resources array in data. For each resource:
 * - Call createResourceArticle() with the resource object.
 * - Append the returned <article> element to the list section.
 */
async function loadResources() {
  // Guard clause to ensure target element exists in the current page DOM
  if (!resourceListSection) return;

  try {
    // 1. Fetch data from the resource API endpoint
    const response = await fetch('./api/index.php');

    // 2. Parse the incoming JSON stream payload
    const result = await response.json();

    // 3. Clear out loading placeholders or pre-existing fallback layout frames
    resourceListSection.innerHTML = '';

    // 4. Inspect data status and map resources to structural elements
    if (result.success && Array.isArray(result.data)) {
      
      // Handle scenario when zero resources have been published yet
      if (result.data.length === 0) {
        const fallbackMessage = document.createElement('p');
        fallbackMessage.textContent = 'No course resources are currently available.';
        resourceListSection.appendChild(fallbackMessage);
        return;
      }

      // Loop through arrays and append configured article modules
      result.data.forEach(resource => {
        const resourceCard = createResourceArticle(resource);
        resourceListSection.appendChild(resourceCard);
      });
      
    } else {
      console.error('API responded with unexpected layout structures:', result);
      resourceListSection.innerHTML = '<p>Error parsing database resources.</p>';
    }

  } catch (error) {
    console.error('Error fetching resource material payload records:', error);
    if (resourceListSection) {
      resourceListSection.innerHTML = '<p>Unable to retrieve course files at this moment.</p>';
    }
  }
}

// --- Initial Page Load ---
// Call the function to populate the page.
loadResources();
