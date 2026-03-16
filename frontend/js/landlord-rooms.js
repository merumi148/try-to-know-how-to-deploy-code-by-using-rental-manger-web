requireAuth("landlord");
const form = document.getElementById("roomForm");
const cards = document.getElementById("roomCards");
const editForm = document.getElementById("editForm");
const detailModal = new bootstrap.Modal(document.getElementById("detailModal"));
const editModal = new bootstrap.Modal(document.getElementById("editModal"));
const imagePickerModal = new bootstrap.Modal(document.getElementById("imagePickerModal"));
let roomList = [];
let imagePickerTarget = "";
let imagePickerPrefix = "uploads/rooms/";
let imagePickerMultiple = true;

function openImagePicker(targetId, prefix, multiple) {
  imagePickerTarget = targetId || "";
  imagePickerPrefix = prefix || "";
  imagePickerMultiple = multiple !== false;
  const input = document.getElementById("imageFiles");
  input.value = "";
  input.multiple = imagePickerMultiple;
  document.getElementById("imageFileList").innerHTML = "";
  imagePickerModal.show();
}

document.getElementById("imageFiles").addEventListener("change", (e) => {
  const files = Array.from(e.target.files || []);
  const list = document.getElementById("imageFileList");
  list.innerHTML = files.length ? files.map((f) => `<li>${f.name}</li>`).join("") : "";
});

document.getElementById("imagePickerApply").addEventListener("click", () => {
  const files = Array.from(document.getElementById("imageFiles").files || []);
  const paths = files.map((f) => `${imagePickerPrefix}${f.name}`);
  const target = document.getElementById(imagePickerTarget);
  if (target) {
    target.value = imagePickerMultiple ? paths.join(", ") : paths[0] || "";
  }
  imagePickerModal.hide();
});

function toImageUrl(path) {
  if (!path) return "https://via.placeholder.com/800x500?text=No+Image";
  if (path.startsWith("http")) return path;
  return `${window.location.origin}${APP_BASE}/${path.replace(/^\/+/, "")}`;
}
function formatPrice(price) {
  return `${Number(price || 0).toLocaleString()} VND`;
}

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  const d = Object.fromEntries(new FormData(form).entries());
  const images = (document.getElementById("create_images").value || "")
    .split(",")
    .map((v) => v.trim())
    .filter(Boolean);
  d.images = images;
  d.feature_ids = [];
  try {
    await apiRequest("/rooms/create_room.php", "POST", d);
    form.reset();
    loadRooms();
  } catch (err) {
    alert(err.message);
  }
});

async function deleteRoom(id) {
  if (!confirm("Xóa phòng này?")) return;
  try {
    await apiRequest("/rooms/delete_room.php", "DELETE", { id });
    loadRooms();
  } catch (err) {
    alert(err.message);
  }
}

function openDetail(index) {
  const r = roomList[index];
  if (!r) return;
  const images = Array.isArray(r.images) && r.images.length ? r.images : [""];
  document.getElementById("detailTitle").textContent = `Chi tiết phòng ${r.room_number || "-"}`;
  document.getElementById("detailMainImage").src = toImageUrl(images[0]);
  document.getElementById("detailRoomNumber").textContent = r.room_number || "-";
  document.getElementById("detailPrice").textContent = formatPrice(r.price);
  document.getElementById("detailArea").textContent = `${r.area || 0} m2`;
  document.getElementById("detailFloor").textContent = r.floor ?? "-";
  document.getElementById("detailStatus").textContent = r.status || "-";
  document.getElementById("detailBuilding").textContent = r.building_name || "-";
  document.getElementById("detailDescription").textContent = r.description || "Chưa có mô tả";

  const thumbs = document.getElementById("detailThumbs");
  thumbs.innerHTML = images
    .map(
      (img) =>
        `<div class="col-4 col-md-3"><img src="${toImageUrl(
          img
        )}" class="img-fluid rounded border" style="height:72px;object-fit:cover;cursor:pointer;" onclick="document.getElementById('detailMainImage').src='${toImageUrl(
          img
        )}'"></div>`
    )
    .join("");
  detailModal.show();
}

function openEdit(index) {
  const r = roomList[index];
  if (!r) return;
  document.getElementById("edit_id").value = r.id || "";
  document.getElementById("edit_building_id").value = r.building_id ?? "";
  document.getElementById("edit_room_number").value = r.room_number || "";
  document.getElementById("edit_floor").value = r.floor ?? "";
  document.getElementById("edit_area").value = r.area ?? "";
  document.getElementById("edit_price").value = r.price ?? "";
  document.getElementById("edit_status").value = r.status || "available";
  document.getElementById("edit_description").value = r.description || "";
  document.getElementById("edit_images").value = (r.images || []).join(", ");
  editModal.show();
}

editForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const images = (document.getElementById("edit_images").value || "")
    .split(",")
    .map((v) => v.trim())
    .filter(Boolean);

  const payload = {
    id: Number(document.getElementById("edit_id").value),
    building_id: Number(document.getElementById("edit_building_id").value),
    room_number: document.getElementById("edit_room_number").value.trim(),
    floor: Number(document.getElementById("edit_floor").value),
    area: Number(document.getElementById("edit_area").value),
    price: Number(document.getElementById("edit_price").value),
    status: document.getElementById("edit_status").value.trim(),
    description: document.getElementById("edit_description").value.trim(),
    images,
  };

  try {
    await apiRequest("/rooms/update_room.php", "PUT", payload);
    editModal.hide();
    loadRooms();
  } catch (err) {
    alert(err.message);
  }
});

async function loadRooms() {
  try {
    const data = await apiRequest("/rooms/get_rooms.php");
    roomList = data.rooms || [];
    cards.innerHTML =
      roomList
        .map((r, idx) => {
          const img = toImageUrl(r.images && r.images[0] ? r.images[0] : "");
          const badge = r.status === "available" ? "bg-success" : "bg-secondary";
          return `<div class="col-md-6 col-xl-4"><div class="section-card overflow-hidden h-100"><img src="${img}" class="room-thumb" alt="room"><div class="p-3"><div class="d-flex justify-content-between"><strong>Phòng ${r.room_number || "-"}</strong><span class="badge ${badge}">${r.status || "-"}</span></div><div class="small text-muted">${r.area || 0} m2</div><div class="fw-semibold mt-1">${formatPrice(r.price)}</div><div class="mt-2 d-flex gap-2"><button class="btn btn-sm btn-outline-primary" onclick="openDetail(${idx})"><i class="bi bi-eye"></i> Xem chi tiết</button><button class="btn btn-sm btn-outline-warning" onclick="openEdit(${idx})"><i class="bi bi-pencil-square"></i> Chỉnh sửa</button><button class="btn btn-sm btn-outline-danger" onclick="deleteRoom(${r.id})"><i class="bi bi-trash"></i> Xóa</button></div></div></div></div>`;
        })
        .join("") || '<div class="col-12 text-muted">Không có dữ liệu phòng.</div>';
  } catch (err) {
    cards.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
  }
}

window.deleteRoom = deleteRoom;
window.openDetail = openDetail;
window.openEdit = openEdit;
window.openImagePicker = openImagePicker;
loadRooms();
