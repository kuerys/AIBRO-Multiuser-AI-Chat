/* ==================== 動態載入 marked、hljs、DOMPurify ==================== */
(async function loadLibraries() {
  console.log('開始動態載入庫...');
  const cdn = 'https://cdn.jsdelivr.net/npm/';
  const scripts = [
    { test: 'DOMPurify', src: `${cdn}dompurify@3.1.6/dist/purify.min.js` },
    { test: 'marked', src: `${cdn}marked@13.0.0/marked.min.js` },
    { test: 'hljs', src: 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js' }
  ];
  for (const lib of scripts) {
    if (typeof window[lib.test] === 'undefined') {
      await new Promise(resolve => {
        const s = document.createElement('script');
        s.src = lib.src;
        s.onload = () => { console.log(lib.src.split('/').pop(), '載入成功'); resolve(); };
        s.onerror = () => { console.warn(lib.src, '載入失敗'); resolve(); };
        document.head.appendChild(s);
      });
    }
  }
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css';
  link.onload = () => console.log('hljs CSS 載入成功');
  document.head.appendChild(link);

  if (typeof marked !== 'undefined' && typeof hljs !== 'undefined') {
    marked.setOptions({
      highlight: (code, lang) => {
        const language = hljs.getLanguage(lang) ? lang : 'plaintext';
        return hljs.highlight(code, { language }).value;
      },
      langPrefix: 'hljs language-'
    });
  }
  window.librariesLoaded = true;
  console.log('所有庫已準備就緒。');
})();

function waitForLibraries() {
  return new Promise(resolve => {
    if (window.librariesLoaded) resolve();
    else setInterval(() => window.librariesLoaded && (clearInterval(this), resolve()), 50);
  });
}

/* ==================== DOM 元素 ==================== */
const DOM = {
  settingsToggle: document.getElementById('settingsToggle'),
  settingsMenu: document.getElementById('settingsMenu'),
  tempSelect: document.getElementById('tempSelect'),
  userInput: document.getElementById('userInput'),
  voiceButton: document.getElementById('voiceButton'),
  sendButton: document.getElementById('sendButton'),
  clearButton: document.getElementById('clearButton'),
  chatStage: document.getElementById('chatStage'),
  statusLight: document.getElementById('statusLight'),
  typingIndicator: document.getElementById('typingIndicator'),
  progressBar: document.getElementById('progressBar'),
  progressFill: document.querySelector('.progress-fill'),
  nicknameInput: document.getElementById('nicknameInput'),
  userListToggle: document.getElementById('userListToggle'),
  userListPanel: document.getElementById('userListPanel'),
  userCount: document.getElementById('userCount'),
  customConfirm: document.getElementById('customConfirm'),
  confirmYes: document.getElementById('confirmYes'),
  confirmNo: document.getElementById('confirmNo'),
  welcomeMessage: document.getElementById('welcomeMessage'),
  welcomeNickname: document.getElementById('welcomeNickname'),
  nicknameDialog: document.getElementById('nicknameDialog'),
  confirmNicknameYes: document.getElementById('confirmNicknameYes'),
  confirmNicknameNo: document.getElementById('confirmNicknameNo')
};

/* ==================== 狀態管理 ==================== */
const state = {
  contextMemory: [],
  isSending: false,
  ws: null,
  localUserId: localStorage.getItem('localUserId') || crypto.randomUUID(),
  localNickname: localStorage.getItem('localNickname') || `訪客_${Math.floor(Math.random() * 100)}`,
  renderedMessages: new Set(),
  currentRoomId: 'lobby'
};
localStorage.setItem('localUserId', state.localUserId);
localStorage.setItem('localNickname', state.localNickname);
DOM.welcomeNickname.textContent = state.localNickname;

/* ==================== 工具函式 ==================== */
function sanitizeHTML(str) {
  const temp = document.createElement('div');
  temp.textContent = str;
  return temp.innerHTML;
}
function debounce(fn, delay) {
  let timeout;
  return (...args) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => fn(...args), delay);
  };
}
function scrollToBottom() {
  DOM.chatStage.scrollTo({ top: DOM.chatStage.scrollHeight, behavior: 'smooth' });
}
const debouncedScroll = debounce(scrollToBottom, 100);

function getUserColor(id) {
  const colors = ['#FF5733', '#33FF57', '#3357FF', '#F333FF', '#FF33A1', '#FFC300'];
  let hash = 0;
  for (let i = 0; i < id.length; i++) hash = ((hash << 5) - hash + id.charCodeAt(i)) | 0;
  return colors[Math.abs(hash) % colors.length];
}

function createTtsButton(text, msgId) {
  const esc = text.replace(/"/g, '&quot;');
  return `<button class="tts-play-btn" data-text="${esc}" data-message-id="${msgId}" title="播放語音"></button>`;
}

/* ==================== 渲染訊息 ==================== */
function renderMessage(data) {
  if (state.renderedMessages.has(data.message_id)) return;
  state.renderedMessages.add(data.message_id);

  const isUser = data.sender_id === state.localUserId;
  const isAi = data.is_ai;
  const div = document.createElement('div');
  div.className = `message ${isUser ? 'user' : isAi ? 'ai' : 'other'}`;
  div.dataset.messageId = data.message_id;

  const header = document.createElement('div');
  header.className = 'header';
  const nick = document.createElement('span');
  nick.className = 'nickname';
  nick.textContent = data.nickname || '未知';
  nick.style.color = getUserColor(data.sender_id);
  header.appendChild(nick);

  if (isAi && data.response_time) {
    const time = document.createElement('span');
    time.className = 'time';
    time.textContent = ` ⏱ ${data.response_time.toFixed(2)}s`;
    if (data.timings_ms) {
      time.title = `搜尋: ${data.timings_ms.t2_search || 0}ms | AI: ${data.timings_ms.t3_ai || 0}ms`;
    }
    header.appendChild(time);
  }
  div.appendChild(header);

  const content = document.createElement('div');
  content.className = 'content';

  let html = typeof DOMPurify !== 'undefined' ? DOMPurify.sanitize(marked.parse(data.content)) : sanitizeHTML(data.content);
  const ttsMatches = data.content.match(/\[TTS_START\](.*?)\[TTS_END\]/g) || [];
  ttsMatches.forEach(match => {
    const text = match.slice(11, -9).trim();
    const btn = createTtsButton(text, data.message_id);
    html = html.replace(match, `${btn} ${text}`);
  });

  content.innerHTML = html;
  if (typeof hljs !== 'undefined') hljs.highlightAll();

  div.appendChild(content);
  DOM.chatStage.appendChild(div);
  debouncedScroll();

  // TTS 按鈕事件
  content.querySelectorAll('.tts-play-btn').forEach(btn => {
    btn.onclick = () => {
      if (state.ws?.readyState === WebSocket.OPEN) {
        state.ws.send(JSON.stringify({
          type: 'generate_tts',
          room_id: state.currentRoomId,
          message_id: data.message_id,
          text: btn.dataset.text
        }));
        btn.classList.add('loading');
      }
    };
  });
}

/* ==================== WebSocket ==================== */
function connect() {
  waitForLibraries().then(() => {
    const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss:' : 'ws:'}//${location.host}/ws/`);
    state.ws = ws;

    ws.onopen = () => {
      console.log('WebSocket 已連線');
      DOM.statusLight.className = 'status-light online';
      ws.send(JSON.stringify({ type: 'join', room_id: 'lobby', user_id: state.localUserId, nickname: state.localNickname }));
    };

    ws.onmessage = e => {
      try {
        const data = JSON.parse(e.data);
        if (data.type === 'join_status') {
          state.localUserId = data.user_id;
          DOM.welcomeNickname.textContent = data.nickname;
          return;
        }
        if (data.type === 'user_list') {
          const users = Array.isArray(data.users) ? data.users : [];
          DOM.userCount.textContent = users.length;
          DOM.userListPanel.innerHTML = users.map(u => 
            `<div class="user" style="color:${getUserColor(u.sender_id || u.id)}">${sanitizeHTML(u.nickname)}</div>`
          ).join('');
          return;
        }
        if (data.type === 'message') {
          renderMessage(data);
          if (data.is_ai) {
            stopProgressBar();
            stopTypingIndicator();
          }
          return;
        }
        if (data.type === 'tts_ready') {
          document.querySelectorAll(`[data-message-id="${data.message_id}"][data-text="${data.text.replace(/"/g, '&quot;')}"]`)
            .forEach(btn => {
              btn.dataset.audioUrl = data.audio_url;
              btn.classList.remove('loading');
              btn.classList.add('ready');
              btn.onclick = () => new Audio(data.audio_url).play();
            });
          return;
        }
        if (data.type === 'error') {
          renderMessage({ ...data, content: `錯誤: ${data.message}`, is_ai: false, sender_id: 'system', nickname: '系統' });
        }
      } catch (err) {
        console.error('訊息解析錯誤:', err);
      }
    };

    ws.onclose = () => {
      DOM.statusLight.className = 'status-light offline';
      setTimeout(connect, 3000);
    };
  });
}

/* ==================== 發送 ==================== */
function send() {
  if (state.isSending || !state.ws || state.ws.readyState !== WebSocket.OPEN) return;
  const text = DOM.userInput.value.trim();
  if (!text) return;

  state.isSending = true;
  DOM.userInput.value = '';
  DOM.sendButton.disabled = true;

  const msg = {
    type: 'message',
    room_id: 'lobby',
    content: text,
    message_id: crypto.randomUUID(),
    sender_id: state.localUserId,
    nickname: state.localNickname
  };

  state.ws.send(JSON.stringify(msg));
  renderMessage({ ...msg, is_ai: false });

  if (text.match(/^[@＠]AI\s*/iu)) {
    startProgressBar();
    startTypingIndicator();
  }

  state.isSending = false;
  DOM.sendButton.disabled = false;
}

/* ==================== 進度條 ==================== */
function startProgressBar() {
  DOM.progressBar.classList.remove('hidden');
  DOM.progressFill.style.width = '0%';
  DOM.progressFill.style.transition = 'width 12s linear';
  requestAnimationFrame(() => DOM.progressFill.style.width = '90%');
}
function stopProgressBar() {
  DOM.progressFill.style.width = '100%';
  setTimeout(() => {
    DOM.progressBar.classList.add('hidden');
    DOM.progressFill.style.width = '0%';
    DOM.progressFill.style.transition = 'none';
  }, 600);
}
function startTypingIndicator() { DOM.typingIndicator.classList.remove('hidden'); }
function stopTypingIndicator() { DOM.typingIndicator.classList.add('hidden'); }

/* ==================== 事件 ==================== */
DOM.sendButton.onclick = send;
DOM.userInput.onkeydown = e => e.key === 'Enter' && !e.shiftKey && (e.preventDefault(), send());
DOM.voiceButton.onclick = () => {
  const rec = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
  rec.lang = 'zh-TW';
  rec.onresult = e => { DOM.userInput.value = e.results[0][0].transcript; send(); };
  rec.start();
};
DOM.clearButton.onclick = () => confirm('清除所有訊息？') && (DOM.chatStage.innerHTML = '', state.renderedMessages.clear());

/* ==================== 啟動 ==================== */
document.addEventListener('DOMContentLoaded', connect);