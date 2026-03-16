requireAuth("landlord");
const rows = document.getElementById("contractRows");
const form = document.getElementById("contractForm");
const editForm = document.getElementById("contractEditForm");
const editModal = new bootstrap.Modal(document.getElementById("contractEditModal"));
let contractList = [];

function statusBadge(status) {
  if (status === "active") return '<span class="badge text-bg-success">active</span>';
  if (status === "expired") return '<span class="badge text-bg-warning">expired</span>';
  return `<span class="badge text-bg-secondary">${status || "-"}</span>`;
}

function setContractStatus(status) {
  const input = document.getElementById("edit_status");
  if (input) input.value = status;
}

async function load() {
  try {
    const data = await apiRequest(`/contracts/get_contracts.php`);
    contractList = data.contracts || [];
    rows.innerHTML =
      contractList
        .map(
          (c, idx) => `
          <tr>
            <td>${c.id}</td>
            <td>${c.room_number || c.room_id}</td>
            <td>${c.tenant_name || c.tenant_id}</td>
            <td>${c.start_date || ""} - ${c.end_date || ""}</td>
            <td>${Number(c.deposit_amount || 0).toLocaleString()} VND</td>
            <td>${statusBadge(c.status)}</td>
            <td>
              <div class="d-flex flex-wrap gap-1">
                <button class="btn btn-sm btn-outline-primary" onclick="openEdit(${idx})"><i class="bi bi-pencil-square"></i> Sửa</button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteContract(${c.id})"><i class="bi bi-trash"></i> Xóa</button>
                <button class="btn btn-sm btn-success" onclick="quickStatus(${c.id}, 'active')">Active</button>
                <button class="btn btn-sm btn-warning" onclick="quickStatus(${c.id}, 'expired')">Expired</button>
              </div>
            </td>
          </tr>
        `
        )
        .join("") || '<tr><td colspan="7" class="text-muted">Chưa có hợp đồng.</td></tr>';
  } catch (err) {
    rows.innerHTML = `<tr><td colspan="7" class="text-danger">${err.message}</td></tr>`;
  }
}

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(form).entries());
  try {
    await apiRequest("/contracts/create_contract.php", "POST", payload);
    form.reset();
    load();
  } catch (err) {
    alert(err.message);
  }
});

function openEdit(index) {
  const c = contractList[index];
  if (!c) return;
  document.getElementById("edit_contract_id").value = c.id || "";
  document.getElementById("edit_tenant_id").value = c.tenant_id || "";
  document.getElementById("edit_room_id").value = c.room_id || "";
  document.getElementById("edit_status").value = c.status || "active";
  document.getElementById("edit_start_date").value = c.start_date || "";
  document.getElementById("edit_end_date").value = c.end_date || "";
  document.getElementById("edit_deposit_amount").value = c.deposit_amount || 0;
  editModal.show();
}

editForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const payload = {
    id: Number(document.getElementById("edit_contract_id").value),
    tenant_id: Number(document.getElementById("edit_tenant_id").value),
    room_id: Number(document.getElementById("edit_room_id").value),
    status: document.getElementById("edit_status").value.trim(),
    start_date: document.getElementById("edit_start_date").value,
    end_date: document.getElementById("edit_end_date").value,
    deposit_amount: Number(document.getElementById("edit_deposit_amount").value),
  };
  try {
    await apiRequest("/contracts/update_contract.php", "PUT", payload);
    editModal.hide();
    load();
  } catch (err) {
    alert(err.message);
  }
});

async function deleteContract(id) {
  if (!confirm("Xóa hợp đồng này?")) return;
  try {
    await apiRequest("/contracts/delete_contract.php", "DELETE", { id });
    load();
  } catch (err) {
    alert(err.message);
  }
}

async function quickStatus(id, status) {
  try {
    await apiRequest("/contracts/update_contract.php", "PUT", { id, status });
    load();
  } catch (err) {
    alert(err.message);
  }
}

window.openEdit = openEdit;
window.deleteContract = deleteContract;
window.quickStatus = quickStatus;
window.setContractStatus = setContractStatus;
load();
