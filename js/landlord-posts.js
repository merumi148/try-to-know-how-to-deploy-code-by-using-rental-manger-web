const user = requireAuth("landlord");
const form = document.getElementById("postForm");
const rows = document.getElementById("postRows");
const editForm = document.getElementById("postEditForm");
const editModal = new bootstrap.Modal(document.getElementById("postEditModal"));
const postImageModal = new bootstrap.Modal(document.getElementById("postImagePickerModal"));
let postList = [];

function toImageUrl(path) {
  if (!path) return "https://via.placeholder.com/120x80?text=No+Image";
  if (path.startsWith("http")) return path;
  return `${window.location.origin}${APP_BASE}/${path.replace(/^\/+/, "")}`;
}

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  const d = Object.fromEntries(new FormData(form).entries());
  d.landlord_id = user.id;
  d.image_url = (d.image_url || "").trim();
  if (!d.image_url) delete d.image_url;
  try {
    await apiRequest("/posts/create_post.php", "POST", d);
    form.reset();
    loadPosts();
  } catch (err) {
    alert(err.message);
  }
});

function openPostImagePicker() {
  const input = document.getElementById("postImageFile");
  input.value = "";
  document.getElementById("postImageFileName").textContent = "";
  postImageModal.show();
}

document.getElementById("postImageFile").addEventListener("change", (e) => {
  const file = e.target.files && e.target.files[0];
  document.getElementById("postImageFileName").textContent = file ? file.name : "";
});

document.getElementById("postImageApply").addEventListener("click", () => {
  const file = document.getElementById("postImageFile").files[0];
  const target = document.getElementById("post_image_url");
  if (target) {
    target.value = file ? `uploads/posts/${file.name}` : "";
  }
  postImageModal.hide();
});

function statusBadge(status) {
  if (status === "published") return '<span class="badge text-bg-success">published</span>';
  if (status === "draft") return '<span class="badge text-bg-secondary">draft</span>';
  return `<span class="badge text-bg-light border">${status || "-"}</span>`;
}

function setPostStatus(status) {
  const input = document.getElementById("edit_status");
  if (input) input.value = status;
}

function openEdit(index) {
  const p = postList[index];
  if (!p) return;
  document.getElementById("edit_post_id").value = p.id || "";
  document.getElementById("edit_room_id").value = p.room_id || "";
  document.getElementById("edit_title").value = p.title || "";
  document.getElementById("edit_status").value = p.status || "draft";
  document.getElementById("edit_content").value = p.content || "";
  editModal.show();
}

editForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const payload = {
    id: Number(document.getElementById("edit_post_id").value),
    room_id: Number(document.getElementById("edit_room_id").value),
    title: document.getElementById("edit_title").value.trim(),
    status: document.getElementById("edit_status").value,
    content: document.getElementById("edit_content").value.trim(),
  };
  try {
    await apiRequest("/posts/update_post.php", "PUT", payload);
    editModal.hide();
    loadPosts();
  } catch (err) {
    alert(err.message);
  }
});

async function deletePost(id) {
  if (!confirm("Xóa bài đăng này?")) return;
  try {
    await apiRequest("/posts/delete_post.php", "DELETE", { id });
    loadPosts();
  } catch (err) {
    alert(err.message);
  }
}

async function quickStatus(id, status) {
  try {
    await apiRequest("/posts/update_post.php", "PUT", { id, status });
    loadPosts();
  } catch (err) {
    alert(err.message);
  }
}

async function loadPosts() {
  try {
    const data = await apiRequest(`/posts/get_posts.php?landlord_id=${user.id}`);
    postList = data.posts || [];
    rows.innerHTML =
      postList
        .map((p, idx) => {
          const contentPreview = (p.content || "").slice(0, 80);
          const firstImage = Array.isArray(p.images) && p.images.length ? p.images[0] : "";
          return `
            <tr>
              <td><img src="${toImageUrl(firstImage)}" alt="room" style="width:80px;height:56px;object-fit:cover;border-radius:8px;"></td>
              <td>${p.id}</td>
              <td>${p.room_number || p.room_id}</td>
              <td>${p.title || ""}</td>
              <td>${contentPreview}${p.content && p.content.length > 80 ? "..." : ""}</td>
              <td>${statusBadge(p.status)}</td>
              <td>
                <div class="d-flex flex-wrap gap-1">
                  <button class="btn btn-sm btn-outline-primary" onclick="openEdit(${idx})"><i class="bi bi-pencil-square"></i> Sửa</button>
                  <button class="btn btn-sm btn-outline-danger" onclick="deletePost(${p.id})"><i class="bi bi-trash"></i> Xóa</button>
                  <button class="btn btn-sm btn-outline-secondary" onclick="quickStatus(${p.id}, 'draft')">Draft</button>
                  <button class="btn btn-sm btn-outline-success" onclick="quickStatus(${p.id}, 'published')">Published</button>
                </div>
              </td>
            </tr>
          `;
        })
        .join("") || '<tr><td colspan="7" class="text-muted">Chưa có bài đăng.</td></tr>';
  } catch (err) {
    rows.innerHTML = `<tr><td colspan="7" class="text-danger">${err.message}</td></tr>`;
  }
}

window.openEdit = openEdit;
window.deletePost = deletePost;
window.quickStatus = quickStatus;
window.setPostStatus = setPostStatus;
window.openPostImagePicker = openPostImagePicker;
loadPosts();
