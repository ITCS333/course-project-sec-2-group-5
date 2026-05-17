/*
  Weekly Details Page
*/

const API_URL = "./api/index.php";


// --- Global Data Store ---
let currentWeekId = null;

let currentComments = [];


// --- Element Selections ---
const weekTitle = document.getElementById("week-title");

const weekStartDate = document.getElementById("week-start-date");

const weekDescription = document.getElementById("week-description");

const weekLinksList = document.getElementById("week-links-list");

const commentList = document.getElementById("comment-list");

const commentForm = document.getElementById("comment-form");

const newCommentInput = document.getElementById("new-comment");


// --- Functions ---

function getWeekIdFromURL() {

    const params = new URLSearchParams(
        window.location.search
    );

    return params.get("id");

}


function renderWeekDetails(week) {

    weekTitle.textContent = week.title;

    weekStartDate.textContent =
        "Starts on: " + week.start_date;

    weekDescription.textContent =
        week.description;


    weekLinksList.innerHTML = "";


    week.links.forEach((url) => {

        const li = document.createElement("li");

        const a = document.createElement("a");

        a.href = url;

        a.textContent = url;

        a.target = "_blank";

        li.appendChild(a);

        weekLinksList.appendChild(li);

    });

}


function createCommentArticle(comment) {

    const article = document.createElement("article");

    article.innerHTML = `
        <p>${comment.text}</p>

        <footer>
            Posted by: ${comment.author}
        </footer>
    `;

    return article;

}


function renderComments() {

    commentList.innerHTML = "";


    currentComments.forEach((comment) => {

        const article =
            createCommentArticle(comment);

        commentList.appendChild(article);

    });

}


async function handleAddComment(event) {

    event.preventDefault();

    const commentText =
        newCommentInput.value.trim();


    if (!commentText) {
        return;
    }


    try {

        const response = await fetch(
            `${API_URL}?action=comment`,
            {

                method: "POST",

                headers: {
                    "Content-Type": "application/json"
                },

                body: JSON.stringify({

                    week_id: Number(currentWeekId),

                    author: "Student",

                    text: commentText

                })

            }
        );


        const result = await response.json();


        if (result.success) {

            currentComments.push(result.data);

            renderComments();

            newCommentInput.value = "";
        }

    } catch (error) {

        console.error(
            "Error adding comment:",
            error
        );

    }

}


async function initializePage() {

    currentWeekId = getWeekIdFromURL();


    if (!currentWeekId) {

        weekTitle.textContent =
            "Week not found.";

        return;
    }


    try {

        const [weekResponse, commentsResponse] =
            await Promise.all([

                fetch(
                    `${API_URL}?id=${currentWeekId}`
                ),

                fetch(
                    `${API_URL}?action=comments&week_id=${currentWeekId}`
                )

            ]);


        const weekResult =
            await weekResponse.json();

        const commentsResult =
            await commentsResponse.json();


        currentComments =
            commentsResult.data || [];


        if (weekResult.success) {

            const week = weekResult.data;

            renderWeekDetails(week);

            renderComments();

            commentForm.addEventListener(
                "submit",
                handleAddComment
            );

        } else {

            weekTitle.textContent =
                "Week not found.";
        }

    } catch (error) {

        console.error(
            "Error loading page:",
            error
        );

    }

}


// --- Initial Page Load ---
initializePage();
