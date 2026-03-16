requireAuth("tenant");

const cards = document.getElementById("roomCards");
const roomDetailModal = new bootstrap.Modal(document.getElementById("roomDetailModal"));
let roomList = [];

function toImageUrl(path) {
  if (!path) return "https://via.placeholder.com/1200x700?text=No+Image";
  if (path.startsWith("http")) return path;
  return `${window.location.origin}${APP_BASE}/${path.replace(/^\/+/, "")}`;
}

function formatPrice(value) {
  return `${Number(value || 0).toLocaleString()} VND`;
}

function openDetail(index) {
  const room = roomList[index];
  if (!room) return;

  const images = Array.isArray(room.images) && room.images.length > 0 ? room.images : [""];
  const firstImage = toImageUrl(images[0]);

  document.getElementById("roomDetailTitle").textContent = `Chi tiết phòng ${room.room_number || "-"}`;
  document.getElementById("detailImage").src = firstImage;
  document.getElementById("detailRoomNumber").textContent = room.room_number || "-";
  document.getElementById("detailPrice").textContent = formatPrice(room.price);
  document.getElementById("detailArea").textContent = `${room.area || 0} m2`;
  document.getElementById("detailFloor").textContent = room.floor ?? "-";
  document.getElementById("detailStatus").textContent = room.status || "-";
  document.getElementById("detailBuilding").textContent = room.building_name || "-";
  document.getElementById("detailDescription").textContent = room.description || "Chưa có mô tả.";
  document.getElementById("detailRequestBtn").href = `./requests.html?room_id=${room.id}`;

  const thumbs = document.getElementById("detailThumbs");
  thumbs.innerHTML = images
    .map(
      (img) => `
        <div class="col-4 col-md-3">
          <img src="${toImageUrl(
            img
          )}" class="img-fluid rounded border" style="height:72px;object-fit:cover;cursor:pointer;" onclick="document.getElementById('detailImage').src='${toImageUrl(
            img
          )}'">
        </div>
      `
    )
    .join("");

  const featureBox = document.getElementById("detailFeatures");
  const features = Array.isArray(room.features) ? room.features : [];
  if (features.length === 0) {
    featureBox.innerHTML = '<span class="text-muted">Chưa có thông tin tiện ích.</span>';
  } else {
    featureBox.innerHTML = features
      .map((f) => `<span class="badge text-bg-light border me-1 mb-1">${f.name}</span>`)
      .join("");
  }

  roomDetailModal.show();
}

async function loadRooms() {
  try {
    const data = await apiRequest("/rooms/get_rooms.php");
    roomList = data.rooms || [];

    cards.innerHTML =
      roomList
        .map(
          (r, idx) => `
          <div class="col-md-6 col-xl-4">
            <div class="section-card overflow-hidden h-100">
              <img src="${toImageUrl(r.images && r.images[0] ? r.images[0] : "")}" class="room-thumb" alt="Ảnh phòng">
              <div class="p-3">
                <div class="d-flex justify-content-between align-items-center">
                  <strong>Phòng ${r.room_number || "-"}</strong>
                  <span class="badge ${r.status === "available" ? "bg-success" : "bg-secondary"}">${r.status || "-"}</span>
                </div>
                <div class="small text-muted mt-1">Diện tích: ${r.area || 0} m2</div>
                <div class="fw-semibold">${formatPrice(r.price)}</div>
                <div class="mt-2 d-flex gap-2">
                  <button class="btn btn-sm btn-outline-primary" onclick="openDetail(${idx})">
                    <i class="bi bi-eye"></i> Xem chi tiết
                  </button>
                  <a class="btn btn-sm btn-primary" href="./requests.html?room_id=${r.id}">
                    <i class="bi bi-send"></i> Yêu cầu xem
                  </a>
                </div>
              </div>
            </div>
          </div>
        `
        )
        .join("") || '<div class="text-muted">Không có dữ liệu.</div>';
  } catch (e) {
    cards.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
  }
}

window.openDetail = openDetail;
loadRooms();
