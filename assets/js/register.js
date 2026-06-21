document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("registerForm");
    const btn = document.getElementById("regBtn");

    if (!form || !btn) return;

    form.addEventListener("submit", function (e) {

        const name = form.name.value.trim();
        const email = form.email.value.trim();
        const password = form.password.value;

        const strongPassword =
            password.length >= 8 &&
            /[A-Z]/.test(password) &&
            /[a-z]/.test(password) &&
            /[0-9]/.test(password);

        if (!name || !email || !password) {
            e.preventDefault();
            alert("All fields are required!");
            return;
        }

        if (!strongPassword) {
            e.preventDefault();
            alert("Password must be 8+ characters and include uppercase, lowercase, and a number.");
            return;
        }

        // Defer disabling to the next tick so the browser has already
        // captured the button's name/value pair for form submission.
        // Disabling synchronously in the submit handler can cause some
        // browsers to drop the button from the submitted form data.
        setTimeout(function () {
            btn.innerHTML = "Registering...";
            btn.disabled = true;
        }, 0);
    });

});