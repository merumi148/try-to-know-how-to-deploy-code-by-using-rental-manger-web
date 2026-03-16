requireAuth("landlord");

const rows = document.getElementById("requestRows");

function statusBadge(status) {
  if (status === "confirmed") return '<span class="badge text-bg-success">confirmed</span>';
  if (status === "cancelled") return '<span class="badge text-bg-danger">cancelled</span>';
  if (status === "pending") return '<span class="badge text-bg-warning">pending</span>';
  return `<span class="badge text-bg-secondary">${status || "-"}</span>`;
}

async function updateRequestStatus(id, status) {
  try {
    console.log("[viewing_request] update", { id, status });
    await apiRequest("/posts/update_viewing_request.php", "PUT", { id, status });
    loadRequests();
  } catch (err) {
    console.error("[viewing_request] update error", err);
    alert(err.message);
  }
}

async function loadRequests() {
  try {
    const data = await apiRequest("/posts/get_viewing_requests.php");
    console.log("[viewing_request] fetched", data);
    const requests = data.requests || [];
    rows.innerHTML =
      requests
        .map((r) => {
          const status = r.status || "pending";
          return `
            <tr>
              <td>${r.id}</td>
              <td>${r.room_number || r.room_id}</td>
              <td>${r.customer_name || "-"}</td>
              <td>${r.phone || "-"}</td>
              <td>${r.preferred_date || ""}</td>
              <td>${statusBadge(status)}</td>
              <td>
                <div class="btn-group btn-group-sm" role="group">
                  <button class="btn btn-success" onclick="updateRequestStatus(${r.id}, 'confirmed')" ${status === "confirmed" ? "disabled" : ""}>Chấp nhận</button>
                  <button class="btn btn-outline-danger" onclick="updateRequestStatus(${r.id}, 'cancelled')" ${status === "cancelled" ? "disabled" : ""}>Từ chối</button>
                </div>
              </td>
            </tr>
          `;
        })
        .join("") || '<tr><td colspan="7" class="text-muted">Chưa có yêu cầu.</td></tr>';
  } catch (err) {
    rows.innerHTML = `<tr><td colspan="7" class="text-danger">${err.message}</td></tr>`;
  }
}

window.updateRequestStatus = updateRequestStatus;
loadRequests();
