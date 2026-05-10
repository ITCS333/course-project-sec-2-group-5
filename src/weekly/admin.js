/*
  Weekly Breakdown Admin Page
*/

const API_URL = "./api/index.php";


// --- Global Data Store ---
let weeks = [];


// --- Element Selections ---
const weekForm = document.getElementById("week-form");

const weeksTbody = document.getElementById("weeks-tbody");

const addWeekButton = document.getElementById("add-week");


// --- Functions ---

function createWeekRow(week) {

    const tr = document.createElement("tr");

    tr.innerHTML = `
        <td>${week.title}</td>

        <td>${week.start_date}</td>

        <td>${week.description}</td>

        <td>
            <button
                class="edit-btn"
                data-id="${week.id}"
            >
                Edit
            </button>

            <button
                class="delete-btn"
                data-id="${week.id}"
            >
                Delete
            </button>
        </td>
    `;

    return tr;

}


function renderTable() {

    weeksTbody.innerHTML = "";

    weeks.forEach((week) => {

        const row = createWeekRow(week);

        weeksTbody.appendChild(row);

    });

}


async function handleAddWeek(event) {

    event.preventDefault();

    const title = document
        .getElementById("week-title")
        .value
        .trim();

    const start_date = document
        .getElementById("week-start-date")
        .value;

    const description = document
        .getElementById("week-description")
        .value
        .trim();

    const links = document
        .getElementById("week-links")
        .value
        .split("\n")
        .map(link => link.trim())
        .filter(link => link !== "");

    const editId = addWeekButton.dataset.editId;

    const fields = {
        title,
        start_date,
        description,
        links
    };


    // Edit Mode
    if (editId) {

        await handleUpdateWeek(editId, fields);

        return;
    }


    // Add Mode
    try {

        const response = await fetch(API_URL, {

            method: "POST",

            headers: {
                "Content-Type": "application/json"
            },

            body: JSON.stringify(fields)

        });

        const result = await response.json();

        if (result.success) {

            const newWeek = {
                id: result.id,
                ...fields
            };

            weeks.push(newWeek);

            renderTable();

            weekForm.reset();
        }

    } catch (error) {

        console.error("Error adding week:", error);

    }

}


async function handleUpdateWeek(id, fields) {

    try {

        const response = await fetch(API_URL, {

            method: "PUT",

            headers: {
                "Content-Type": "application/json"
            },

            body: JSON.stringify({
                id,
                ...fields
            })

        });

        const result = await response.json();

        if (result.success) {

            const index = weeks.findIndex(
                week => week.id == id
            );

            if (index !== -1) {

                weeks[index] = {
                    id: Number(id),
                    ...fields
                };
            }

            renderTable();

            weekForm.reset();

            addWeekButton.textContent = "Add Week";

            delete addWeekButton.dataset.editId;
        }

    } catch (error) {

        console.error("Error updating week:", error);

    }

}


async function handleTableClick(event) {

    const target = event.target;


    // Delete
    if (target.classList.contains("delete-btn")) {

        const id = Number(target.dataset.id);

        try {

            const response = await fetch(
                `${API_URL}?id=${id}`,
                {
                    method: "DELETE"
                }
            );

            const result = await response.json();

            if (result.success) {

                weeks = weeks.filter(
                    week => week.id !== id
                );

                renderTable();
            }

        } catch (error) {

            console.error("Error deleting week:", error);

        }

    }


    // Edit
    if (target.classList.contains("edit-btn")) {

        const id = Number(target.dataset.id);

        const week = weeks.find(
            week => week.id === id
        );

        if (!week) {
            return;
        }

        document.getElementById("week-title").value =
            week.title;

        document.getElementById("week-start-date").value =
            week.start_date;

        document.getElementById("week-description").value =
            week.description;

        document.getElementById("week-links").value =
            week.links.join("\n");


        addWeekButton.textContent = "Update Week";

        addWeekButton.dataset.editId = id;
    }

}


async function loadAndInitialize() {

    try {

        const response = await fetch(API_URL);

        const result = await response.json();

        if (result.success) {

            weeks = result.data;

            renderTable();
        }

    } catch (error) {

        console.error("Error loading weeks:", error);

    }


    weekForm.addEventListener(
        "submit",
        handleAddWeek
    );

    weeksTbody.addEventListener(
        "click",
        handleTableClick
    );

}


// --- Initial Page Load ---
loadAndInitialize();
