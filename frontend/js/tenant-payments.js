const user = requireAuth("tenant");
const rows = document.getElementById("paymentRows");

function statusBadge(status) {
  if (status === "paid") return '<span class="badge text-bg-success">paid</span>';
  if (status === "pending") return '<span class="badge text-bg-warning">pending</span>';
  return '<span class="badge text-bg-secondary">unpaid</span>';
}

async function submitPayment(id) {
  try {
    await apiRequest("/payments/tenant_submit_payment.php", "POST", { id });
    loadPayments();
  } catch (error) {
    alert(error.message);
  }
}

async function loadPayments() {
  try {
    const data = await apiRequest(`/payments/get_tenant_payments.php?tenant_id=${user.id}`);
    const payments = data.payments || [];
    rows.innerHTML =
      payments
        .map((p) => {
          const status = p.payment_status || "unpaid";
          let actionHtml = "";
          if (status === "unpaid") {
            actionHtml = `<button class="btn btn-sm btn-primary" onclick="submitPayment(${p.id})">Gửi đã thanh toán</button>`;
          } else if (status === "pending") {
            actionHtml = `<span class="badge text-bg-warning">Đã gửi</span>`;
          } else {
            actionHtml = `<span class="badge text-bg-success">Đã thanh toán</span>`;
          }
          return `
            <tr>
              <td>${p.id}</td>
              <td>${p.room_number || p.room_id}</td>
              <td>${p.month_year || ""}</td>
              <td>${Number(p.total_amount || 0).toLocaleString()} VND</td>
              <td>${statusBadge(status)}</td>
              <td>${actionHtml}</td>
            </tr>
          `;
        })
        .join("") || '<tr><td colspan="6" class="text-muted">Chưa có thanh toán.</td></tr>';
  } catch (error) {
    rows.innerHTML = `<tr><td colspan="6" class="text-danger">${error.message}</td></tr>`;
  }
}

window.submitPayment = submitPayment;
loadPayments();
