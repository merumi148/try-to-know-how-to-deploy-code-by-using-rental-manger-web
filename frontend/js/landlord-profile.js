const currentUser = requireAuth("landlord");
const alertBox = document.getElementById("alertBox");
const profileForm = document.getElementById("profileForm");
const passwordForm = document.getElementById("passwordForm");
const passwordAlert = document.getElementById("passwordAlert");

function showAlert(type, text) {
  alertBox.className = `alert alert-${type}`;
  alertBox.textContent = text;
  alertBox.classList.remove("d-none");
}

function showPasswordAlert(type, text) {
  passwordAlert.className = `alert alert-${type}`;
  passwordAlert.textContent = text;
  passwordAlert.classList.remove("d-none");
}

async function loadProfile() {
  try {
    const data = await apiRequest(`/auth/get_profile.php?user_id=${currentUser.id}`);
    const user = data.user;
    document.getElementById("full_name").value = user.full_name || "";
    document.getElementById("phone").value = user.phone || "";
    document.getElementById("email").value = user.email || "";
    document.getElementById("role").value = user.role || "";
    document.getElementById("created_at").value = user.created_at || "";
  } catch (error) {
    showAlert("danger", error.message);
  }
}

profileForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  try {
    const payload = {
      user_id: currentUser.id,
      full_name: document.getElementById("full_name").value.trim(),
      phone: document.getElementById("phone").value.trim(),
    };
    const data = await apiRequest("/auth/update_profile.php", "POST", payload);
    saveUser(data.user);
    showAlert("success", "Cập nhật thông tin thành công.");
  } catch (error) {
    showAlert("danger", error.message);
  }
});

passwordForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  try {
    const payload = {
      current_password: document.getElementById("current_password").value,
      new_password: document.getElementById("new_password").value,
      confirm_password: document.getElementById("confirm_password").value,
    };
    await apiRequest("/auth/change_password.php", "POST", payload);
    passwordForm.reset();
    showPasswordAlert("success", "Đổi mật khẩu thành công.");
  } catch (error) {
    showPasswordAlert("danger", error.message);
  }
});

loadProfile();
