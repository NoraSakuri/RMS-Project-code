const registerForm = document.getElementById("registerForm");

registerForm.addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(registerForm);

    fetch("../../back-end/api/users/register.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.text())
    .then(text => {
        console.log(text);
        const data = JSON.parse(text);

        if (data.success) {
            Swal.fire({
                icon: "success",
                title: "Registered Successfully",
                text: data.message,
                confirmButtonColor: "#ff6b35"
            }).then(() => {
                window.location.href = "../index.php";
            });
        } else {
            Swal.fire({
                icon: "error",
                title: "Register Failed",
                text: data.message,
                confirmButtonColor: "#ff6b35"
            });
        }
    })
    .catch(error => {
        console.log(error);
        Swal.fire({
            icon: "error",
            title: "Server Error",
            text: "Register API error.",
            confirmButtonColor: "#ff6b35"
        });
    });
});