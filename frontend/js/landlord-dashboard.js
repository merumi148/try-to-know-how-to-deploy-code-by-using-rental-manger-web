requireAuth("landlord");

function toImageUrl(path) {
  if (!path) return "https://via.placeholder.com/800x500?text=No+Image";
  if (path.startsWith("http://") || path.startsWith("https://")) return path;
  return `${window.location.origin}${APP_BASE}/${path.replace(/^\/+/, "")}`;
}

async function loadStats() {
  const metrics = document.getElementById("metrics");
  try {
    const data = await apiRequest("/dashboard/get_stats.php");
    const cards = [
      ["Tổng số phòng", data.total_rooms || 0, "bi-building", "metric-primary"],
      ["Phòng còn trống", data.available_rooms || 0, "bi-door-open", "metric-success"],
      ["Yêu cầu xem phòng", data.pending_viewing_requests || 0, "bi-eye", "metric-warning"],
      ["Tổng hợp đồng", data.total_contracts || 0, "bi-clipboard-check", "metric-primary"],
      ["Hợp đồng đang hiệu lực", data.active_contracts || 0, "bi-file-text", "metric-primary"],
      ["Thanh toán chờ xử lý", data.pending_payments || 0, "bi-hourglass-split", "metric-warning"],
      ["Doanh thu tháng này", `${Number(data.monthly_revenue || 0).toLocaleString()} VND`, "bi-graph-up", "metric-success"],
      ["Tổng doanh thu", `${Number(data.total_revenue || 0).toLocaleString()} VND`, "bi-cash-stack", "metric-danger"],
    ];
    metrics.innerHTML = cards
      .map(
        (c) => `
          <div class="col-md-6 col-xl-3">
            <div class="metric-card ${c[3]} p-3">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="small">${c[0]}</div>
                  <div class="h5 mb-0 mt-1">${c[1]}</div>
                </div>
                <div class="metric-icon"><i class="bi ${c[2]}"></i></div>
              </div>
            </div>
          </div>`
      )
      .join("");
  } catch (e) {
    metrics.innerHTML = `<div class="col-12"><div class="alert alert-danger">${e.message}</div></div>`;
  }
}

async function loadRooms() {
  const container = document.getElementById("roomsContainer");
  try {
    const data = await apiRequest("/rooms/get_rooms.php");
    const rooms = (data.rooms || []).slice(0, 6);
    container.innerHTML =
      rooms
        .map((room) => {
          const image = toImageUrl(room.images && room.images[0] ? room.images[0] : "");
          const statusCls = room.status === "available" ? "bg-success" : "bg-secondary";
          return `<div class="col-md-6 col-xl-4">
            <div class="section-card overflow-hidden h-100">
              <img src="${image}" class="room-thumb" alt="room">
              <div class="p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong>Phòng ${room.room_number || "-"}</strong>
                  <span class="badge ${statusCls} room-status">${room.status || "-"}</span>
                </div>
                <div class="text-muted small">${room.building_name || "-"}</div>
                <div class="mt-2 fw-semibold">${Number(room.price || 0).toLocaleString()} VND</div>
              </div>
            </div>
          </div>`;
        })
        .join("") || '<div class="col-12 text-muted">Chưa có phòng.</div>';
  } catch (e) {
    container.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
  }
}

loadStats();
loadRooms();
