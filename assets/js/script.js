// ================================
// SMART ATTENDANCE SYSTEM
// script.js
// ================================

// Show / Hide Password

const togglePassword = document.getElementById("togglePassword");
const password = document.getElementById("password");

if (togglePassword && password) {

    togglePassword.addEventListener("click", function () {

        const type = password.type === "password" ? "text" : "password";

        password.type = type;

        this.innerHTML =
            password.type === "password"
            ? '<i class="bi bi-eye-fill"></i>'
            : '<i class="bi bi-eye-slash-fill"></i>';

    });

}

// Login Loading Animation

const loginForm = document.getElementById("loginForm");

if (loginForm) {

    loginForm.addEventListener("submit", function () {

        const spinner = document.getElementById("loadingSpinner");
        const buttonText = document.getElementById("buttonText");
        const button = document.getElementById("loginButton");

        spinner.classList.remove("d-none");

        buttonText.innerHTML = "Signing In...";

        button.disabled = true;

    });

}