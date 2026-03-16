requireAuth("tenant");

(() => {
  const chatBox = document.getElementById("chatBox");
  const chatForm = document.getElementById("chatForm");
  const chatInput = document.getElementById("chatInput");
  const chatFab = document.getElementById("chatFab");
  const chatPopup = document.getElementById("chatPopup");
  const chatClose = document.getElementById("chatClose");

  if (!chatBox || !chatForm || !chatInput || !chatFab || !chatPopup || !chatClose) {
    return;
  }

  function openChat() {
    chatPopup.classList.add("is-open");
  }

  function closeChat() {
    chatPopup.classList.remove("is-open");
  }

  chatFab.addEventListener("click", openChat);
  chatClose.addEventListener("click", closeChat);

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function appendMessage(text, type) {
    const div = document.createElement("div");
    div.className = "chat-item " + (type === "user" ? "chat-user" : "chat-bot");
    div.innerHTML = escapeHtml(text).replace(/\n/g, "<br>");
    chatBox.appendChild(div);
    chatBox.scrollTop = chatBox.scrollHeight;
  }

  appendMessage("Xin chào! Tôi hỗ trợ tra cứu phòng trống, giá phòng và giá tiện ích từ hệ thống.", "bot");

  chatForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const message = chatInput.value.trim();
    if (!message) return;

    appendMessage(message, "user");
    chatInput.value = "";

    try {
      const response = await fetch(`${API_BASE}/chatbot/chat.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message }),
      });

      const raw = await response.text();
      let data;
      try {
        data = JSON.parse(raw);
      } catch (_) {
        appendMessage("Lỗi: API trả về dữ liệu không hợp lệ.", "bot");
        return;
      }

      appendMessage(data.response || "Không có phản hồi.", "bot");
    } catch (err) {
      appendMessage("Lỗi kết nối chatbot: " + err.message, "bot");
    }
  });
})();
