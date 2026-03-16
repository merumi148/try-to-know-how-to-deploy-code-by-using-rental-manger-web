const user = requireAuth("tenant");
const form = document.getElementById("requestForm");
const rows = document.getElementById("requestRows");
const urlParams = new URLSearchParams(window.location.search);
const roomId = urlParams.get("room_id") || urlParams.get("post_id");
if (roomId) form.elements.room_id.value = roomId;

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(form).entries());
  data.tenant_id = user.id;
  data.customer_name = user.full_name || "";
  data.phone = user.phone || "";
  try {
    await apiRequest("/posts/create_viewing_request.php", "POST", data);
    form.reset();
    loadRequests();
  } catch (error) {
    alert(error.message);
  }
});

async function loadRequests() {
  try {
    const data = await apiRequest(`/posts/get_viewing_requests.php?tenant_id=${user.id}`);
    rows.innerHTML =
      (data.requests || [])
        .map(
          (r) => `
          <tr><td>${r.id}</td><td>${r.room_number || r.room_id}</td><td>${r.status}</td><td>${r.preferred_date || ""}</td></tr>
        `
        )
        .join("") || '<tr><td colspan="4" class="text-muted">Chưa có yêu cầu.</td></tr>';
  } catch (error) {
    rows.innerHTML = `<tr><td colspan="4" class="text-danger">${error.message}</td></tr>`;
  }
}

loadRequests();
