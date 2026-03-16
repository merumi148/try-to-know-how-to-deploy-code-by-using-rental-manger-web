const APP_BASE = window.location.pathname.split("/frontend")[0];
const API_BASE = `${window.location.origin}${APP_BASE}/api`;

async function apiRequest(path, method = "GET", body = null) {
    const options = {
        method,
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
        },
    };
    if (body) {
        options.body = JSON.stringify(body);
    }

    const response = await fetch(`${API_BASE}${path}`, options);
    const rawText = await response.text();

    let data;
    try {
        data = JSON.parse(rawText);
    } catch (_) {
        const preview = rawText ? rawText.slice(0, 160) : "No response body";
        throw new Error(`API tra ve du lieu khong phai JSON: ${preview}`);
    }

    if (!response.ok || !data.success) {
        throw new Error(data.message || "Yeu cau API that bai");
    }

    return data.data;
}

function saveUser(user) {
    localStorage.setItem("qpt_user", JSON.stringify(user));
}

function getUser() {
    const raw = localStorage.getItem("qpt_user");
    return raw ? JSON.parse(raw) : null;
}

function requireAuth(expectedRole = null) {
    const user = getUser();
    if (!user) {
        window.location.href = `${APP_BASE}/frontend/login.html`;
        return null;
    }
    if (expectedRole && user.role !== expectedRole) {
        window.location.href = `${APP_BASE}/frontend/index.html`;
        return null;
    }
    return user;
}

function logout() {
    localStorage.removeItem("qpt_user");
    window.location.href = `${APP_BASE}/frontend/login.html`;
}
