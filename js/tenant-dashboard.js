const user = requireAuth("tenant");

function toImageUrl(path) {
  if (!path) return "https://via.placeholder.com/800x500?text=No+Image";
  if (path.startsWith("http")) return path;
  return `${window.location.origin}${APP_BASE}/${path.replace(/^\/+/, "")}`;
}

async function loadMetrics() {
  const box = document.getElementById("metrics");
  try {
    const [c, p] = await Promise.all([
      apiRequest(`/contracts/get_tenant_contracts.php?tenant_id=${user.id}`),
      apiRequest(`/payments/get_tenant_payments.php?tenant_id=${user.id}`),
    ]);
    const contracts = c.contracts || [];
    const payments = p.payments || [];
    const paid = payments
      .filter((x) => x.payment_status === "paid")
      .reduce((s, x) => s + Number(x.total_amount || 0), 0);
    const pending = payments
      .filter((x) => x.payment_status !== "paid")
      .reduce((s, x) => s + Number(x.total_amount || 0), 0);
    const cards = [
      ["Hợp đồng của tôi", contracts.length, "bi-file-text", "metric-primary"],
      ["Đã thanh toán", `${paid.toLocaleString()} VND`, "bi-cash-stack", "metric-success"],
      ["Chưa thanh toán", `${pending.toLocaleString()} VND`, "bi-hourglass-split", "metric-warning"],
    ];
    box.innerHTML = cards
      .map(
        (c) => `<div class="col-md-4"><div class="metric-card ${c[3]} p-3"><div class="d-flex justify-content-between"><div><div class="small">${c[0]}</div><div class="h5 mb-0 mt-1">${c[1]}</div></div><div class="metric-icon"><i class="bi ${c[2]}"></i></div></div></div></div>`
      )
      .join("");
  } catch (e) {
    box.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
  }
}

async function loadRooms() {
  const box = document.getElementById("roomsContainer");
  try {
    const data = await apiRequest("/rooms/get_rooms.php?status=available");
    const rooms = (data.rooms || []).slice(0, 6);
    box.innerHTML =
      rooms
        .map(
          (r) => `<div class="col-md-6 col-xl-4"><div class="section-card overflow-hidden h-100"><img src="${toImageUrl(
            r.images && r.images[0] ? r.images[0] : ""
          )}" class="room-thumb"><div class="p-3"><div class="d-flex justify-content-between"><strong>Phòng ${
            r.room_number || "-"
          }</strong><span class="badge bg-success">${r.status || "-"}</span></div><div class="small text-muted mt-1">${
            r.area || 0
          } m2</div><div class="fw-semibold">${Number(r.price || 0).toLocaleString()} VND</div></div></div></div>`
        )
        .join("") || '<div class="text-muted">Không có dữ liệu phòng.</div>';
  } catch (e) {
    box.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
  }
}

loadMetrics();
loadRooms();
