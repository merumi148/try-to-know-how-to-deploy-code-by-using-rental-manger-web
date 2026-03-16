async function handleLogin(event) {
    event.preventDefault();
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;
    const alertBox = document.getElementById("alertBox");

    try {
        const result = await apiRequest("/auth/login.php", "POST", { email, password });
        saveUser(result.user);
        if (result.user.role === "landlord") {
            window.location.href = `${APP_BASE}/frontend/landlord/dashboard.html`;
            return;
        }
        window.location.href = `${APP_BASE}/frontend/tenant/dashboard.html`;
    } catch (error) {
        alertBox.className = "alert alert-danger";
        alertBox.textContent = error.message;
        alertBox.classList.remove("d-none");
    }
}

async function handleRegister(event) {
    event.preventDefault();
    const full_name = document.getElementById("full_name").value.trim();
    const phone = document.getElementById("phone").value.trim();
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;
    const role = document.getElementById("role").value;
    const alertBox = document.getElementById("alertBox");

    try {
        await apiRequest("/auth/register.php", "POST", { full_name, phone, email, password, role });
        alertBox.className = "alert alert-success";
        alertBox.textContent = "Dang ky thanh cong. Dang chuyen den trang dang nhap...";
        alertBox.classList.remove("d-none");
        setTimeout(() => {
            window.location.href = `${APP_BASE}/frontend/login.html`;
        }, 1200);
    } catch (error) {
        alertBox.className = "alert alert-danger";
        alertBox.textContent = error.message;
        alertBox.classList.remove("d-none");
    }
}
