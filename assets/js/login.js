document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("loginForm");
    const btn = document.getElementById("loginBtn");

    if (!form || !btn) return;

    form.addEventListener("submit", function () {
        if (btn.disabled) return;

        setTimeout(function () {
            btn.innerHTML = "Logging in...";
            btn.disabled = true;
        }, 0);
    });

});