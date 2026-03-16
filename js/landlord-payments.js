requireAuth("landlord");
const rows = document.getElementById("paymentRows");
const notice = document.getElementById("paymentNotice");

function statusBadge(status) {
  if (status === "paid") return '<span class="badge text-bg-success">paid</span>';
  if (status === "pending") return '<span class="badge text-bg-warning">pending</span>';
  return '<span class="badge text-bg-secondary">unpaid</span>';
}

async function updatePaymentStatus(id, status) {
  try {
    await apiRequest("/payments/update_payment.php", "PUT", { id, payment_status: status });
    load();
  } catch (err) {
    alert(err.message);
  }
}

async function load() {
  try {
    const data = await apiRequest("/payments/get_payments.php");
    const payments = data.payments || [];
    const pendingCount = payments.filter((p) => p.payment_status === "pending").length;
    if (pendingCount > 0) {
      notice.textContent = `Có ${pendingCount} yêu cầu xác nhận thanh toán mới.`;
      notice.classList.remove("d-none");
    } else {
      notice.classList.add("d-none");
    }

    rows.innerHTML =
      payments
        .map((p) => {
          const status = p.payment_status || "unpaid";
          const disablePaid = status === "paid" ? "disabled" : "";
          const disableUnpaid = status === "unpaid" ? "disabled" : "";
          return `
            <tr>
              <td>${p.id}</td>
              <td>${p.room_number || p.room_id}</td>
              <td>${p.month_year || ""}</td>
              <td>${Number(p.total_amount || 0).toLocaleString()} VND</td>
              <td>${statusBadge(status)}</td>
              <td>
                <div class="btn-group btn-group-sm" role="group">
                  <button class="btn btn-success" ${disablePaid} onclick="updatePaymentStatus(${p.id}, 'paid')">Paid</button>
                  <button class="btn btn-outline-secondary" ${disableUnpaid} onclick="updatePaymentStatus(${p.id}, 'unpaid')">Unpaid</button>
                </div>
              </td>
            </tr>
          `;
        })
        .join("") || '<tr><td colspan="6" class="text-muted">Chưa có thanh toán.</td></tr>';
  } catch (err) {
    rows.innerHTML = `<tr><td colspan="6" class="text-danger">${err.message}</td></tr>`;
  }
}

window.updatePaymentStatus = updatePaymentStatus;
load();
