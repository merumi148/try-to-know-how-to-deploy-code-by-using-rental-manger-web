function ensureTenantChatWidget() {
  if (document.getElementById("chatFab")) return;

  const widgetHtml = `
    <button id="chatFab" class="chat-fab btn btn-primary" aria-label="Mở chatbot">
      <i class="bi bi-chat-dots"></i>
    </button>
    <div id="chatPopup" class="chat-popup shadow">
      <div class="chat-popup-header">
        <div class="d-flex align-items-center gap-2">
          <div class="brand-mark">PT</div>
          <strong>Chatbot</strong>
        </div>
        <button id="chatClose" class="btn btn-light btn-sm" aria-label="Đóng">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div id="chatBox" class="chat-box chat-popup-body"></div>
      <form id="chatForm" class="chat-popup-footer">
        <input id="chatInput" class="form-control" placeholder="Ví dụ: phòng dưới 3 triệu" autocomplete="off" required>
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-send"></i>
        </button>
      </form>
    </div>
  `;

  document.body.insertAdjacentHTML("beforeend", widgetHtml);
}

ensureTenantChatWidget();
