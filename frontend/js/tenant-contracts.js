const user = requireAuth("tenant");
const rows = document.getElementById("contractRows");

function statusBadge(status) {
  if (status === "active") return '<span class="badge text-bg-success">active</span>';
  if (status === "expired") return '<span class="badge text-bg-warning">expired</span>';
  return `<span class="badge text-bg-secondary">${status || "-"}</span>`;
}

async function loadContracts() {
  try {
    const data = await apiRequest(`/contracts/get_contracts.php?tenant_id=${user.id}`);
    rows.innerHTML =
      (data.contracts || [])
        .map(
          (c) => `
          <tr>
            <td>${c.id}</td>
            <td>${c.room_number || c.room_id}</td>
            <td>${Number(c.price || 0).toLocaleString()} VND</td>
            <td>${statusBadge(c.status)}</td>
            <td>${c.start_date || ""} - ${c.end_date || ""}</td>
            <td>${Number(c.deposit_amount || 0).toLocaleString()} VND</td>
          </tr>
        `
        )
        .join("") || '<tr><td colspan="6" class="text-muted">Chưa có hợp đồng.</td></tr>';
  } catch (error) {
    rows.innerHTML = `<tr><td colspan="6" class="text-danger">${error.message}</td></tr>`;
  }
}

loadContracts();
