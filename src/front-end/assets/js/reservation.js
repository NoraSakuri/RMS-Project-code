"use strict";

const reservationModal =
    document.getElementById("reservationModal");

const reservationForm =
    document.getElementById("reservationForm");

const reservationModalTitle =
    document.getElementById("reservationModalTitle");

const reservationIdInput =
    document.getElementById("reservationId");

let editMode = false;

function openReservationModal() {
    editMode = false;

    reservationForm.reset();
    reservationIdInput.value = "";
    reservationModalTitle.textContent = "Add Reservation";

    reservationModal.classList.add("show");
}

function closeReservationModal() {
    reservationModal.classList.remove("show");
}

reservationModal.addEventListener("click", function (event) {
    if (event.target === reservationModal) {
        closeReservationModal();
    }
});

document.querySelectorAll(".edit-btn").forEach(button => {
    button.addEventListener("click", function () {
        const row = this.closest("tr");

        editMode = true;

        reservationIdInput.value = row.dataset.id;
        document.getElementById("customerName").value =
            row.dataset.customer;

        document.getElementById("tableNumber").value =
            row.dataset.tableId;

        document.getElementById("guestCount").value =
            row.dataset.guests;

        document.getElementById("reservationDate").value =
            row.dataset.date;

        document.getElementById("reservationTime").value =
            row.dataset.time;

        document.getElementById("reservationStatus").value =
            row.dataset.status;

        reservationModalTitle.textContent = "Edit Reservation";
        reservationModal.classList.add("show");
    });
});

reservationForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const saveButton =
        reservationForm.querySelector(".save-reservation-btn");

    const formData = new FormData();

    if (editMode) {
        formData.append(
            "reservation_id",
            reservationIdInput.value
        );
    }

    formData.append(
        "customer_name",
        document.getElementById("customerName").value.trim()
    );

    formData.append(
        "table_id",
        document.getElementById("tableNumber").value
    );

    formData.append(
        "number_of_guests",
        document.getElementById("guestCount").value
    );

    formData.append(
        "reservation_status",
        document.getElementById("reservationStatus").value
    );

    formData.append(
        "reservation_date",
        document.getElementById("reservationDate").value
    );

    formData.append(
        "reservation_time",
        document.getElementById("reservationTime").value
    );

    const endpoint = editMode
        ? "../../back-end/api/reservations/update.php"
        : "../../back-end/api/reservations/create.php";

    try {
        saveButton.disabled = true;
        saveButton.textContent =
            editMode ? "Updating..." : "Saving...";

        const response = await fetch(endpoint, {
            method: "POST",
            body: formData
        });

        const text = await response.text();

        let result;

        try {
            result = JSON.parse(text);
        } catch {
            console.error("Invalid response:", text);
            throw new Error("Server returned invalid response.");
        }

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || "Request failed."
            );
        }

        alert(result.message);
        window.location.reload();

    } catch (error) {
        console.error(error);
        alert(error.message);

    } finally {
        saveButton.disabled = false;
        saveButton.textContent = "Save Reservation";
    }
});

/*
|--------------------------------------------------------------------------
| Complete reservation
|--------------------------------------------------------------------------
*/

document.querySelectorAll(".complete-btn").forEach(button => {
    button.addEventListener("click", async function () {
        const row = this.closest("tr");
        const reservationId = row.dataset.id;

        if (!reservationId) {
            alert("Invalid reservation ID.");
            return;
        }

        if (!confirm("Complete this reservation?")) {
            return;
        }

        const formData = new FormData();
        formData.append("reservation_id", reservationId);

        try {
            this.disabled = true;
            this.textContent = "Completing...";

            const response = await fetch(
                "../../back-end/api/reservations/complete.php",
                {
                    method: "POST",
                    body: formData
                }
            );

            const text = await response.text();

            let result;

            try {
                result = JSON.parse(text);
            } catch {
                console.error("Invalid response:", text);
                throw new Error("Server returned invalid response.");
            }

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || "Unable to complete reservation."
                );
            }

            alert(result.message);
            window.location.reload();

        } catch (error) {
            console.error(error);
            alert(error.message);

        } finally {
            this.disabled = false;
            this.textContent = "Complete";
        }
    });
});

document.querySelectorAll(".cancel-btn").forEach(button => {
    button.addEventListener("click", async function () {
        const row = this.closest("tr");
        const reservationId = row.dataset.id;

        if (!confirm("Cancel this reservation?")) {
            return;
        }

        const formData = new FormData();
        formData.append("reservation_id", reservationId);

        try {
            const response = await fetch(
                "../../back-end/api/reservations/cancel.php",
                {
                    method: "POST",
                    body: formData
                }
            );

            const text = await response.text();

            let result;

            try {
                result = JSON.parse(text);
            } catch {
                console.error("Invalid response:", text);
                throw new Error("Server returned invalid response.");
            }

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || "Unable to cancel reservation."
                );
            }

            alert(result.message);
            window.location.reload();

        } catch (error) {
            console.error(error);
            alert(error.message);
        }
    });
});

/*
|--------------------------------------------------------------------------
| Reservation tabs
|--------------------------------------------------------------------------
*/

const reservationTabs =
    document.querySelectorAll('.reservation-tab');

const reservationTabContents =
    document.querySelectorAll('.reservation-tab-content');

reservationTabs.forEach(tab => {
    tab.addEventListener('click', function () {
        reservationTabs.forEach(button => {
            button.classList.remove('active');
        });

        reservationTabContents.forEach(content => {
            content.classList.remove('active');
        });

        this.classList.add('active');

        const target =
            this.dataset.tab === 'history'
                ? document.getElementById('historyTab')
                : document.getElementById('upcomingTab');

        target?.classList.add('active');
    });
});


/*
|--------------------------------------------------------------------------
| Upcoming filter
|--------------------------------------------------------------------------
*/

const searchUpcoming =
    document.getElementById('searchUpcomingReservation');

const upcomingStatusFilter =
    document.getElementById('upcomingStatusFilter');

function filterUpcomingReservations() {
    const searchValue =
        String(searchUpcoming?.value || '')
            .trim()
            .toLowerCase();

    const selectedStatus =
        String(upcomingStatusFilter?.value || 'ALL')
            .toUpperCase();

    document
        .querySelectorAll('#upcomingReservationBody .reservation-row')
        .forEach(row => {
            const customer =
                String(row.dataset.customer || '')
                    .toLowerCase();

            const status =
                String(row.dataset.status || '')
                    .toUpperCase();

            const matchesSearch =
                customer.includes(searchValue);

            const matchesStatus =
                selectedStatus === 'ALL'
                || status === selectedStatus;

            row.style.display =
                matchesSearch && matchesStatus
                    ? ''
                    : 'none';
        });
}

searchUpcoming?.addEventListener(
    'input',
    filterUpcomingReservations
);

upcomingStatusFilter?.addEventListener(
    'change',
    filterUpcomingReservations
);


/*
|--------------------------------------------------------------------------
| History filter
|--------------------------------------------------------------------------
*/

const searchHistory =
    document.getElementById('searchReservationHistory');

const historyStatusFilter =
    document.getElementById('historyStatusFilter');

function filterReservationHistory() {
    const searchValue =
        String(searchHistory?.value || '')
            .trim()
            .toLowerCase();

    const selectedStatus =
        String(historyStatusFilter?.value || 'ALL')
            .toUpperCase();

    document
        .querySelectorAll('#reservationHistoryBody .history-row')
        .forEach(row => {
            const customer =
                String(row.dataset.customer || '')
                    .toLowerCase();

            const status =
                String(row.dataset.status || '')
                    .toUpperCase();

            const matchesSearch =
                customer.includes(searchValue);

            const matchesStatus =
                selectedStatus === 'ALL'
                || status === selectedStatus;

            row.style.display =
                matchesSearch && matchesStatus
                    ? ''
                    : 'none';
        });
}

searchHistory?.addEventListener(
    'input',
    filterReservationHistory
);

historyStatusFilter?.addEventListener(
    'change',
    filterReservationHistory
);