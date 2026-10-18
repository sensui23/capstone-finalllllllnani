<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Barangay 76-A Chatbot</title>
  <style>
    :root {
      --bg: #edf4ff;
      --panel: rgba(255,255,255,0.82);
      --border: rgba(30,64,175,0.18);
      --primary: #1d4ed8;
      --primary-deep: #1e3a8a;
      --text: #0f172a;
      --muted: rgba(30,64,175,0.72);
      --user: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);
      --bot: rgba(255,255,255,0.96);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      width: 100%;
      height: 100%;
      background: transparent;
      font-family: Arial, Helvetica, sans-serif;
      color: var(--text);
    }

    body {
      display: flex;
      align-items: stretch;
      justify-content: center;
      background: linear-gradient(180deg, rgba(237,244,255,0.7), rgba(224,233,255,0.92));
    }

    @keyframes floatOrb {
      0%, 100% { transform: translateY(0) scale(1); }
      50% { transform: translateY(-16px) scale(1.04); }
    }

    @keyframes pulse-ring {
      0% { transform: scale(1); opacity: 0.7; }
      70% { transform: scale(1.5); opacity: 0; }
      100% { transform: scale(1.8); opacity: 0; }
    }

    @keyframes typingBounce {
      0%, 80%, 100% { transform: translateY(0); opacity: 0.45; }
      40% { transform: translateY(-6px); opacity: 1; }
    }

    @keyframes msgIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    #chat-wrap {
      position: relative;
      width: 100%;
      height: 100vh;
      display: flex;
      flex-direction: column;
      background: var(--bg);
      overflow: hidden;
    }

    .orb {
      position: absolute;
      border-radius: 50%;
      pointer-events: none;
      z-index: 0;
    }

    #scanline {
      position: absolute;
      inset: 0;
      background: repeating-linear-gradient(
        180deg,
        transparent,
        transparent 2px,
        rgba(15,23,42,0.025) 2px,
        rgba(15,23,42,0.025) 4px
      );
      pointer-events: none;
      z-index: 1;
    }

    #chat-header {
      position: relative;
      z-index: 2;
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 18px 18px 14px;
      background: rgba(255,255,255,0.78);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
      flex-shrink: 0;
    }

    #logo-wrap {
      position: relative;
      width: 52px;
      height: 52px;
      flex-shrink: 0;
    }

    #logo-icon {
      width: 52px;
      height: 52px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      background: linear-gradient(135deg, var(--primary-deep), var(--primary));
      box-shadow: 0 8px 24px rgba(29,78,216,0.28);
    }

    #online-dot {
      position: absolute;
      right: 1px;
      bottom: 1px;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: #4ade80;
      box-shadow: 0 0 0 2px rgba(255,255,255,0.7);
    }

    #online-ring {
      position: absolute;
      inset: -2px;
      border-radius: 50%;
      border: 2px solid rgba(74,222,128,0.9);
      animation: pulse-ring 1.8s ease-out infinite;
    }

    #header-copy {
      flex: 1;
      min-width: 0;
    }

    #header-title {
      font-size: clamp(1rem, 2vw, 1.2rem);
      font-weight: 700;
      line-height: 1.2;
    }

    #header-sub {
      margin-top: 4px;
      font-size: 0.68rem;
      color: var(--muted);
      letter-spacing: 0.12rem;
      text-transform: uppercase;
    }

    #ai-badge {
      flex-shrink: 0;
      font-size: 0.67rem;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(29,78,216,0.2);
      background: rgba(29,78,216,0.08);
      color: var(--primary);
      letter-spacing: 0.08rem;
      text-transform: uppercase;
      font-weight: 700;
    }

    #chat-area {
      position: relative;
      z-index: 2;
      flex: 1;
      overflow-y: auto;
      padding: 18px 16px 12px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      scroll-behavior: smooth;
    }

    .msg-row {
      display: flex;
      align-items: flex-end;
      gap: 10px;
      animation: msgIn 0.22s ease both;
    }

    .msg-row.user { flex-direction: row-reverse; }

    .avatar {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 15px;
      user-select: none;
    }

    .avatar.bot {
      background: linear-gradient(135deg, var(--primary-deep), var(--primary));
      color: #fff;
      box-shadow: 0 5px 18px rgba(29,78,216,0.34);
    }

    .avatar.user {
      background: rgba(29,78,216,0.12);
      border: 1px solid rgba(29,78,216,0.2);
      color: var(--primary-deep);
    }

    .bubble {
      max-width: min(74%, 420px);
      padding: 11px 14px;
      font-size: 0.92rem;
      line-height: 1.6;
      word-break: break-word;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
    }

    .bubble.bot {
      background: var(--bot);
      color: var(--text);
      border: 1px solid rgba(30,64,175,0.08);
      border-radius: 18px 18px 18px 6px;
      box-shadow: 0 8px 18px rgba(15,23,42,0.04);
    }

    .bubble.user {
      background: var(--user);
      color: #fff;
      border-radius: 18px 18px 6px 18px;
      box-shadow: 0 12px 24px rgba(29,78,216,0.22);
    }

    .typing-dots {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      min-width: 32px;
      padding: 2px 2px 0;
    }

    .typing-dots span {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: rgba(29,78,216,0.75);
      animation: typingBounce 1.2s infinite ease-in-out;
    }

    .typing-dots span:nth-child(2) { animation-delay: 0.15s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.3s; }

    #suggestions {
      width: 100%;
      margin-top: 4px;
    }

    #suggestions-label {
      font-size: 0.68rem;
      text-transform: uppercase;
      letter-spacing: 0.12rem;
      color: rgba(30,64,175,0.62);
      font-weight: 700;
      margin-bottom: 10px;
      padding-left: 4px;
    }

    #suggestions-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .suggest-chip {
      appearance: none;
      border: 1px solid rgba(29,78,216,0.18);
      background: rgba(29,78,216,0.06);
      color: var(--primary-deep);
      border-radius: 999px;
      padding: 8px 12px;
      font-size: 0.78rem;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .suggest-chip:hover {
      background: rgba(29,78,216,0.12);
      border-color: rgba(29,78,216,0.34);
      transform: translateY(-1px);
    }

    #bottom-anchor {
      width: 100%;
      height: 1px;
      flex-shrink: 0;
    }

    #input-area {
      position: relative;
      z-index: 2;
      background: rgba(255,255,255,0.82);
      border-top: 1px solid var(--border);
      padding: 14px 14px 18px;
      flex-shrink: 0;
      backdrop-filter: blur(14px);
    }

    #input-row {
      display: flex;
      gap: 10px;
      align-items: flex-end;
      background: rgba(255,255,255,0.9);
      border: 1px solid rgba(29,78,216,0.18);
      border-radius: 18px;
      padding: 8px 10px 8px 14px;
      box-shadow: 0 6px 18px rgba(29,78,216,0.12);
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    #input-row:focus-within {
      border-color: rgba(29,78,216,0.38);
      box-shadow: 0 8px 22px rgba(29,78,216,0.14);
    }

    #msg-input {
      flex: 1;
      width: 100%;
      min-height: 38px;
      max-height: 120px;
      resize: none;
      border: none;
      outline: none;
      background: transparent;
      color: var(--text);
      font-size: 0.93rem;
      line-height: 1.5;
      padding: 8px 0;
    }

    #msg-input::placeholder {
      color: rgba(30,64,175,0.42);
    }

    .send-btn {
      width: 42px;
      height: 42px;
      border: none;
      border-radius: 12px;
      background: rgba(29,78,216,0.08);
      color: rgba(29,78,216,0.38);
      font-size: 1.2rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: all 0.2s ease;
    }

    .send-btn:not(:disabled):hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 18px rgba(29,78,216,0.18);
    }

    .send-btn:disabled {
      cursor: not-allowed;
      opacity: 0.7;
    }

    .spinner {
      width: 17px;
      height: 17px;
      border-radius: 50%;
      border: 2px solid rgba(29,78,216,0.2);
      border-top-color: rgba(29,78,216,0.9);
      animation: spin 0.8s linear infinite;
    }

    #footer-label {
      margin-top: 10px;
      text-align: center;
      font-size: 0.68rem;
      color: rgba(30,64,175,0.4);
      line-height: 1.5;
    }

    @media (max-width: 640px) {
      #chat-header { padding: 16px 14px 12px; }
      #chat-area { padding: 16px 12px 8px; }
      .bubble { max-width: 82%; }
      #ai-badge { display: none; }
      #input-area { padding: 12px 12px 16px; }
      #footer-label { font-size: 0.62rem; }
    }

    @media (max-width: 420px) {
      #chat-header { gap: 10px; }
      #logo-wrap { width: 46px; height: 46px; }
      #logo-icon { width: 46px; height: 46px; }
      .suggest-chip { padding: 7px 10px; font-size: 0.72rem; }
      .bubble { padding: 10px 12px; font-size: 0.88rem; }
      #input-row { padding-right: 8px; }
      .send-btn { width: 38px; height: 38px; }
    }
  </style>
</head>
<body>
  <div id="chat-wrap">
    <div class="orb" style="top:-100px; right:-60px; width:280px; height:280px; background:radial-gradient(circle, rgba(29,78,216,0.12), transparent 70%); animation: floatOrb 9s ease-in-out infinite;"></div>
    <div class="orb" style="bottom:-60px; left:-40px; width:240px; height:240px; background:radial-gradient(circle, rgba(30,64,175,0.10), transparent 70%); animation: floatOrb 11s ease-in-out infinite reverse;"></div>
    <div id="scanline"></div>

    <div id="chat-header">
      <div id="logo-wrap">
        <div id="logo-icon">🏛️</div>
        <div id="online-dot"><div id="online-ring"></div></div>
      </div>
      <div id="header-copy">
        <div id="header-title">Barangay 76-A System</div>
        <div id="header-sub">FAQ · Web Assistant</div>
      </div>
      <div id="ai-badge">Online</div>
    </div>

    <div id="chat-area">
      <div id="suggestions">
        <div id="suggestions-label">Try asking</div>
        <div id="suggestions-chips"></div>
      </div>
      <div id="bottom-anchor"></div>
    </div>

    <div id="input-area">
      <div id="input-row">
        <textarea id="msg-input" rows="1" placeholder="Ask about the portal, services, or barangay info..."></textarea>
        <button id="send-btn" class="send-btn" type="button" aria-label="Send message" disabled>↑</button>
      </div>
      <div id="footer-label">Barangay 76-A • Civic Services • Local Information Portal</div>
    </div>
  </div>

  <script>
    const SUGGESTIONS = [
      { icon: '📋', text: 'What services does this website offer?' },
      { icon: '👤', text: 'How do I create a resident account?' },
      { icon: '💬', text: 'How can I report a community issue?' },
      { icon: '📄', text: 'How do I request a certificate or document?' },
      { icon: '🏛️', text: 'What is this project about?' },
      { icon: '👥', text: 'Who are the developers?' },
      { icon: '⚙️', text: 'What technology stack is used?' },
      { icon: '📞', text: 'What are the contact details for the barangay?' }
    ];

    let loading = false;
    const chatArea = document.getElementById('chat-area');
    const bottomAnchor = document.getElementById('bottom-anchor');
    const msgInput = document.getElementById('msg-input');
    const sendBtn = document.getElementById('send-btn');
    const suggestions = document.getElementById('suggestions');
    const sugChips = document.getElementById('suggestions-chips');

    function scrollToBottom() {
      bottomAnchor.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }

    function escapeHtml(text) {
      return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\n/g, '<br>');
    }

    function addMessage(role, content, typing = false) {
      const row = document.createElement('div');
      row.className = `msg-row ${role}`;

      const avatar = document.createElement('div');
      avatar.className = `avatar ${role}`;
      avatar.textContent = role === 'user' ? '👤' : '🏛️';

      const bubble = document.createElement('div');
      bubble.className = `bubble ${role}`;

      if (typing) {
        bubble.innerHTML = '<div class="typing-dots"><span></span><span></span><span></span></div>';
        row.dataset.typing = 'true';
      } else {
        bubble.innerHTML = escapeHtml(content);
      }

      if (role === 'user') {
        row.appendChild(bubble);
        row.appendChild(avatar);
      } else {
        row.appendChild(avatar);
        row.appendChild(bubble);
      }

      chatArea.insertBefore(row, suggestions);
      scrollToBottom();
      return row;
    }

    function removeTyping() {
      const typingRow = chatArea.querySelector('[data-typing="true"]');
      if (typingRow) {
        typingRow.remove();
      }
    }

    function resizeInput() {
      msgInput.style.height = 'auto';
      const maxHeight = 120;
      const nextHeight = Math.min(msgInput.scrollHeight, maxHeight);
      msgInput.style.height = nextHeight + 'px';
    }

    function updateSendBtn() {
      const hasText = msgInput.value.trim().length > 0;

      if (loading) {
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<div class="spinner"></div>';
        sendBtn.style.background = 'rgba(29,78,216,0.08)';
        sendBtn.style.color = 'rgba(29,78,216,0.38)';
        return;
      }

      sendBtn.disabled = !hasText;
      sendBtn.innerHTML = '↑';

      if (hasText) {
        sendBtn.style.background = 'linear-gradient(135deg, #1e3a8a, #1d4ed8)';
        sendBtn.style.color = '#fff';
      } else {
        sendBtn.style.background = 'rgba(29,78,216,0.08)';
        sendBtn.style.color = 'rgba(29,78,216,0.38)';
      }
    }

    async function sendMessage(textOverride) {
      const message = (textOverride || msgInput.value).trim();
      if (!message || loading) return;

      msgInput.value = '';
      resizeInput();
      suggestions.style.display = 'none';

      loading = true;
      msgInput.disabled = true;
      updateSendBtn();

      addMessage('user', message);
      addMessage('assistant', '', true);

      try {
        const response = await fetch('get_chat_response.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message })
        });

        const data = await response.json();
        const reply = data.reply || 'Sorry, I could not answer that right now.';

        removeTyping();
        addMessage('assistant', reply);
      } catch (error) {
        console.error('Chatbot fetch error:', error);
        removeTyping();
        addMessage('assistant', '⚠️ The local chatbot is unavailable. Please refresh the page and try again.');
      }

      loading = false;
      msgInput.disabled = false;
      updateSendBtn();
      msgInput.focus();
    }

    msgInput.addEventListener('input', () => {
      resizeInput();
      updateSendBtn();
    });

    msgInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
      }
    });

    sendBtn.addEventListener('click', () => sendMessage());

    SUGGESTIONS.forEach((item) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'suggest-chip';
      btn.innerHTML = `<span>${item.icon}</span> ${item.text}`;
      btn.addEventListener('click', () => sendMessage(item.text));
      sugChips.appendChild(btn);
    });

    addMessage('assistant', 'Maayong Adlaw! 👋 I’m your local Barangay 76-A assistant. Ask me about the site, services, resident access, certificates, or the project itself.');
    resizeInput();
    updateSendBtn();
    scrollToBottom();
  </script>
</body>
</html>