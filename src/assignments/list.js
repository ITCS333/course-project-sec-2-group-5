const assignmentListSection = document.getElementById("assignment-list-section");

function createAssignmentArticle(assignment) {
  const article = document.createElement("article");

  const title = document.createElement("h2");
  title.textContent = assignment.title;

  const dueDate = document.createElement("p");
  dueDate.textContent = "Due: " + assignment.due_date;

  const description = document.createElement("p");
  description.textContent = assignment.description;

  const link = document.createElement("a");
  link.href = "details.html?id=" + assignment.id;
  link.textContent = "View Details & Discussion";

  article.appendChild(title);
  article.appendChild(dueDate);
  article.appendChild(description);
  article.appendChild(link);

  return article;
}

async function loadAssignments() {
  try {
    const response = await fetch("./api/index.php");
    const result = await response.json();

    assignmentListSection.innerHTML = "";

    if (result.success && Array.isArray(result.data)) {
      result.data.forEach(function (assignment) {
        const article = createAssignmentArticle(assignment);
        assignmentListSection.appendChild(article);
      });
    } else {
      assignmentListSection.innerHTML = "<p>No assignments found.</p>";
    }
  } catch (error) {
    assignmentListSection.innerHTML = "<p>Error loading assignments.</p>";
    console.error("Error:", error);
  }
}

loadAssignments();
