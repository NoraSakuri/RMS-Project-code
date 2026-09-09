document.getElementById("loginForm").addEventListener("submit", function (e) {
    e.preventDefault();

    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value.trim();
    const message = document.getElementById("message");

    const button = document.querySelector("#loginForm button");

    const formData = new FormData();
    formData.append("username", username);
    formData.append("password", password);

    button.disabled = true;
    button.innerHTML = "Logging in...";

    fetch("../back-end/api/auth/login.php", {
        method: "POST",
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            button.disabled = false;
            button.innerHTML = "Login";
            if (data.success) {
                if (data.role === "ADMIN") {
                    window.location.href = "admin/dashboard.php";
                } else if (data.role === "MANAGER") {
                    window.location.href = "manager/dashboard.php";
                } else if (data.role === "WAITER") {
                    window.location.href = "waiter/dashboard.php";
                } else if (data.role === "CHEF") {
                    window.location.href = "chef/dashboard.php";
                } else if (data.role === "CASHIER") {
                    window.location.href = "cashier/dashboard.php";
                }
            } else {
                message.innerText = data.message;
            }
        })
        .catch(error => {
            console.log(error);
            message.innerText = "Login failed. Check backend path or database.";
        });
});