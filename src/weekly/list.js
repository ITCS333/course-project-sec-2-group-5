/*
  Weekly Course Breakdown List Page
*/

const API_URL = "./api/index.php";


// --- Element Selections ---
const weekListSection =
    document.getElementById(
        "week-list-section"
    );


// --- Functions ---

function createWeekArticle(week) {

    const article =
        document.createElement("article");


    article.innerHTML = `
        <h2>${week.title}</h2>

        <p>
            Starts on: ${week.start_date}
        </p>

        <p>
            ${week.description}
        </p>

        <a href="details.html?id=${week.id}">
            View Details & Discussion
        </a>
    `;

    return article;

}


async function loadWeeks() {

    try {

        const response =
            await fetch(API_URL);

        const result =
            await response.json();


        weekListSection.innerHTML = "";


        result.data.forEach((week) => {

            const article =
                createWeekArticle(week);

            weekListSection.appendChild(
                article
            );

        });

    } catch (error) {

        console.error(
            "Error loading weeks:",
            error
        );

    }

}


// --- Initial Page Load ---
loadWeeks();
