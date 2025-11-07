/* =============== for (v4.7 - 精準效能監控) ============== */

/* ==================== 動態載入 marked、hljs、DOMPurify ==================== */
(async function loadLibraries() {
  console.log('開始動態載入庫...');

  if (typeof DOMPurify === 'undefined') {
    await new Promise((resolve) => {
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/dompurify@3.1.6/dist/purify.min.js';
      script.onload = () => { console.log('DOMPurify 載入成功'); resolve(); };
      script.onerror = () => { console.warn('DOMPurify 載入失敗'); resolve(); };
      document.head.appendChild(script);
    });
  }

  if (typeof marked === 'undefined') {
    await new Promise((resolve) => {
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/marked@13.0.0/marked.min.js';
      script.onload = () => { console.log('marked 載入成功'); resolve(); };
      script.onerror = () => {
        console.warn('marked 載入失敗，使用備援解析');
        window.marked = { parse: (text) => text.replace(/</g, "&lt;").replace(/>/g, "&gt;") };
        resolve();
      };
      document.head.appendChild(script);
    });
  }

  if (typeof hljs === 'undefined') {
    await new Promise((resolve) => {
      const script = document.createElement('script');
      script.src = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js';
      script.onload = () => { console.log('hljs 載入成功'); resolve(); };
      script.onerror = () => {
        console.warn('hljs 載入失敗');
        window.hljs = { highlightElement: () => {}, getLanguage: () => false };
        resolve();
      };
      document.head.appendChild(script);
    });
  }

  if (!document.querySelector('link[href*="highlight.js"]')) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css';
    link.onload = () => console.log('hljs CSS 載入成功');
    document.head.appendChild(link);
  }

  if (typeof marked !== 'undefined' && typeof hljs !== 'undefined') {
    try {
      marked.setOptions({
        highlight: (code, lang) => {
          const language = hljs.getLanguage(lang) ? lang : 'plaintext';
          return hljs.highlight(code, { language }).value;
        },
        langPrefix: 'hljs language-'
      });
    } catch (e) {
      console.warn('Marked 配置錯誤:', e);
    }
  }

  window.librariesLoaded = true;
  console.log('所有庫已準備就緒。');
})();

function waitForLibraries() {
  return new Promise(resolve => {
    if (window.librariesLoaded) {
      resolve();
    } else {
      const check = setInterval(() => {
        if (window.librariesLoaded) {
          clearInterval(check);
          resolve();
        }
      }, 50);
    }
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
  contextMemory: JSON.parse(localStorage.getItem('aiContext') || '[]'),
  isSending: false,
  ws: null,
  localUserId: localStorage.getItem('localUserId') || crypto.randomUUID(),
  localNickname: localStorage.getItem('localNickname') || `訪客_${Math.floor(Math.random() * 100)}`,
  renderedMessages: new Set(),
  tempWarningShown: false,
  currentRoomId: 'single_room'
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
const debouncedSendMessage = debounce(sendMessage, 300);

function getUserColor(userId) {
  const colors = ['#FF5733', '#33FF57', '#3357FF', '#F333FF', '#FF33A1', '#FFC300'];
  let hash = 0;
  for (let i = 0; i < userId.length; i++) {
    hash = ((hash << 5) - hash + userId.charCodeAt(i)) | 0;
  }
  return colors[Math.abs(hash) % colors.length];
}

function createTtsButtonHTML(text, messageId) {
  const escapedText = text.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  return `<button class="tts-play-btn" data-text="${escapedText}" data-message-id="${messageId}" aria-label="播放語音：${escapedText.substring(0, 50)}..."></button>`;
}

function escapeRegExp(str) {
  try {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  } catch (e) {
    console.warn('RegExp escape 錯誤:', str, e);
    return '';
  }
}

/* ==================== 安全 renderMessage (完全修正版) ==================== */
function renderMessage(
  content,
  isUser = false,
  extraClass = '',
  senderNickname = '',
  senderId = '',
  messageId = '',
  responseTime = null,
  isAi = false,
  timings = null // [新增] 接收詳細計時物件
) {
  if (messageId && state.renderedMessages.has(messageId)) return;

  const div = document.createElement('div');
  const baseClass = isUser ? 'user-line' : (senderId === 'AI_BOT' ? 'assistant-line' : 'other-user-line');
  div.className = `${baseClass} ${extraClass}`.trim();
  div.dataset.messageId = messageId || crypto.randomUUID();

  const header = document.createElement('div');
  header.className = 'message-header';

  if (senderNickname) {
    const label = document.createElement('span');
    label.className = 'sender-nickname';
    label.textContent = senderNickname;
    label.style.color = getUserColor(senderId);
    header.appendChild(label);
  }

  // ==================== 修正並增強的回應時間顯示邏輯 START ====================
  if (senderId === 'AI_BOT' && responseTime != null && !isNaN(responseTime) && responseTime > 0) {
    let seconds = Math.max(0, responseTime);
    let readable;

    // [修正] 移除不正確的 seconds > 86400 判斷
    if (seconds >= 60) {
        const minutes = Math.floor(seconds / 60);
        const secs = Math.round(seconds % 60);
        readable = `${minutes} 分 ${secs} 秒`;
    } else {
        readable = `${seconds.toFixed(2)} 秒`;
    }

    if (readable) {
        const timeSpan = document.createElement('span');
        timeSpan.className = 'response-time';
        timeSpan.textContent = `⏱ ${readable}`;

        // [增強] 如果後端有傳來詳細計時，則增加 tooltip 提示
        if (timings) {
          const details = `搜尋耗時: ${timings.t2_search || 0} ms\n` +
                        `AI 生成: ${timings.t3_ai || 'N/A'} ms\n` +
                        `後端總耗時: ${timings.t4_backend || 'N/A'} ms`;
          timeSpan.title = details;
        }
        header.appendChild(timeSpan);
    }
  }
  // ==================== 修正並增強的回應時間顯示邏輯 END ====================


  if (!isUser && !extraClass.includes('system-line')) {
      div.appendChild(header);
  } else if(isUser) {
      header.style.justifyContent = 'flex-end';
      div.appendChild(header);
  }
  
  const msgContentDiv = document.createElement('div');
  msgContentDiv.className = 'message-content';

  let processedContent = content;
  const ttsSegments = [];
  let ttsIndex = 0;
  let placeholderIndex = 0;

  const ttsRegex = /TTS_ai_[a-f0-9-]+\_(\d+)：\s*(.*?)(?=\n\nTTS_ai_[a-f0-9-]+\_\d+：|$)/gs;
  processedContent = processedContent.replace(ttsRegex, (match, indexStr, segmentText) => {
    const ttsText = segmentText.trim();
    const placeholder = `@@TTS_SEG_${messageId}_${ttsIndex++}@@`;
    ttsSegments.push({ placeholder, text: ttsText });
    return placeholder;
  });

  processedContent = processedContent.replace(/\[TTS_START\](.*?)\[TTS_END\]/gs, (match, text) => {
    const placeholder = `@@TTS_STD_${messageId}_${placeholderIndex++}@@`;
    ttsSegments.push({ placeholder, text: text.trim() });
    return placeholder;
  });

  let safeHTML = typeof DOMPurify !== 'undefined'
    ? DOMPurify.sanitize(marked.parse(processedContent))
    : sanitizeHTML(processedContent);
    
  let hasTts = false;
  if ((senderId === 'AI_BOT' || isAi) && ttsSegments.length > 0) {
    ttsSegments.forEach((seg) => {
      const buttonHTML = createTtsButtonHTML(seg.text, messageId);
      const fullReplacement = `${buttonHTML} ${seg.text}`;
      
      const placeholderInHtml = `<p>${seg.placeholder}</p>`;
      if (safeHTML.includes(placeholderInHtml)) {
          safeHTML = safeHTML.replace(placeholderInHtml, fullReplacement);
          hasTts = true;
      } else if (safeHTML.includes(seg.placeholder)) {
          safeHTML = safeHTML.replace(new RegExp(escapeRegExp(seg.placeholder), 'g'), fullReplacement);
          hasTts = true;
      }
    });
  }

  msgContentDiv.innerHTML = safeHTML;

  if (hasTts) {
    msgContentDiv.querySelectorAll('.tts-play-btn').forEach(btn => {
      btn.onclick = () => requestTts(btn);
      btn.title = `播放語音：${btn.dataset.text.substring(0, 50)}...`;
    });
  }

  if (typeof hljs !== 'undefined') {
    msgContentDiv.querySelectorAll('pre code').forEach(block => {
      try { hljs.highlightElement(block); } catch (e) { console.warn('Highlight.js error:', e); }
    });
  }

  div.appendChild(msgContentDiv);
  div.style.opacity = '0';
  requestAnimationFrame(() => { div.style.opacity = '1'; });

  DOM.chatStage.appendChild(div);
  debouncedScroll();

  if (messageId) state.renderedMessages.add(messageId);

  if (state.renderedMessages.size > 150) {
    const oldMessages = Array.from(state.renderedMessages).slice(0, 50);
    oldMessages.forEach(id => {
      state.renderedMessages.delete(id);
      const oldEl = document.querySelector(`[data-message-id="${id}"]`);
      if (oldEl) oldEl.remove();
    });
  }
}

/* ==================== TTS 播放（點擊才播放） ==================== */
function requestTts(button) {
  const audioUrl = button.dataset.audioUrl;
  const text = button.dataset.text;
  const messageId = button.dataset.messageId;

  if (audioUrl) {
    const audio = new Audio(audioUrl);
    audio.play().catch(e => {
      console.error("播放失敗:", e);
      button.innerHTML = '❌';
      setTimeout(() => { button.innerHTML = ''; }, 2000);
    });
    button.classList.add('playing');
    audio.onended = () => button.classList.remove('playing');
    return;
  }

  if (!text) return;

  if (state.ws && state.ws.readyState === WebSocket.OPEN) {
    button.disabled = true;
    button.classList.add('generating');

    const timeout = setTimeout(() => {
      if(button.classList.contains('generating')){
        button.disabled = false;
        button.classList.remove('generating');
        console.warn('TTS 生成超時');
      }
    }, 20000);

    state.ws.send(JSON.stringify({
      type: 'generate_tts',
      room_id: state.currentRoomId,
      message_id: messageId,
      text: text
    }));
  }
}

/* ==================== 進度條與打字動畫 ==================== */
function startProgressBar() {
  DOM.progressBar.classList.remove('hidden');
  DOM.progressFill.style.transition = 'width 15s cubic-bezier(0.22, 1, 0.36, 1)';
  DOM.progressFill.style.width = '95%';
}

function stopProgressBar() {
  DOM.progressFill.style.transition = 'width 0.5s ease-out';
  DOM.progressFill.style.width = '100%';
  setTimeout(() => {
    DOM.progressBar.classList.add('hidden');
    DOM.progressFill.style.width = '0%';
    DOM.progressFill.style.transition = 'none';
  }, 500);
}

function startTypingIndicator() { DOM.typingIndicator.classList.remove('hidden'); }
function stopTypingIndicator() { DOM.typingIndicator.classList.add('hidden'); }

/* ==================== WebSocket 連線 ==================== */
function connectWebSocket() {
  waitForLibraries().then(() => {
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${protocol}//${window.location.host}/ws/`;
    state.ws = new WebSocket(wsUrl);

    state.ws.onopen = () => {
      console.log(`WebSocket connected to ${wsUrl}`);
      DOM.statusLight.className = 'status-light online';
      state.ws.send(JSON.stringify({ type: 'join', room_id: state.currentRoomId, user_id: state.localUserId, nickname: state.localNickname }));
      state.ws.send(JSON.stringify({ type: 'load_history', room_id: state.currentRoomId }));
    };

    state.ws.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        switch (data.type) {
          case 'message':
            // [修正] 將 timings_ms 傳給 renderMessage
            renderMessage(
                data.content, 
                data.sender_id === state.localUserId, 
                '', 
                data.nickname, 
                data.sender_id, 
                data.message_id, 
                data.response_time, 
                data.is_ai,
                data.timings_ms || null
            );
            if (data.is_ai) {
              stopProgressBar();
              stopTypingIndicator();
            }
            break;

          case 'tts_ready':
            if (data.message_id) {
              const escapedText = data.text.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
              const buttons = document.querySelectorAll(`[data-message-id="${data.message_id}"][data-text="${escapedText}"]`);
              buttons.forEach(button => {
                if (button.classList.contains('generating')) {
                  button.dataset.audioUrl = data.audio_url || '';
                  button.disabled = false;
                  button.classList.remove('generating');
                  if (!data.audio_url) {
                      button.innerHTML = '❌'; 
                      setTimeout(() => { button.innerHTML = ''; }, 2000);
                  }
                }
              });
            }
            break;

          case 'ai_context':
            state.contextMemory = data.context || [];
            localStorage.setItem('aiContext', JSON.stringify(state.contextMemory));
            break;

          case 'load_history':
            DOM.chatStage.innerHTML = '';
            state.renderedMessages.clear();
            data.lines.forEach(line => {
              try {
                const msg = JSON.parse(line);
                if (msg.type === 'message') {
                  renderMessage(msg.content, msg.sender_id === state.localUserId, 'history-line', msg.nickname, msg.sender_id, msg.message_id, msg.response_time, msg.is_ai || false, msg.timings_ms || null);
                }
              } catch (e) {
                console.warn('歷史訊息解析錯誤:', e, '原始訊息:', line);
              }
            });
            scrollToBottom();
            break;

          case 'user_list':
            DOM.userCount.textContent = data.users.length;
            DOM.userListPanel.innerHTML = data.users.map(u => `<div class="user-item" style="color:${getUserColor(u.id)}">${sanitizeHTML(u.nickname)}</div>`).join('');
            break;

          case 'user_joined': case 'user_left':
            renderMessage(`${sanitizeHTML(data.nickname)} ${data.type === 'user_joined' ? '加入' : '離開'}了聊天室`, false, 'system-line');
            break;

          case 'join_status':
            if (data.reconnect) renderMessage('重新連線成功', false, 'system-line success');
            break;

          case 'error':
            renderMessage(`錯誤: ${data.message}`, false, 'system-line error');
            stopProgressBar();
            stopTypingIndicator();
            break;
        }
      } catch (e) {
        console.error('WebSocket 訊息解析錯誤:', e);
      }
    };

    state.ws.onclose = () => {
      console.log('WebSocket 斷線，5秒後重連...');
      DOM.statusLight.className = 'status-light offline';
      setTimeout(connectWebSocket, 5000);
    };

    state.ws.onerror = (e) => {
      console.error('WebSocket 錯誤:', e);
    };
  });
}

/* ==================== 發送訊息 ==================== */
function sendMessage() {
  if (state.isSending || !state.ws || state.ws.readyState !== WebSocket.OPEN) return;
  const text = DOM.userInput.value.trim();
  if (!text) return;

  state.isSending = true;
  DOM.userInput.value = '';
  DOM.sendButton.disabled = true;

  const messageId = crypto.randomUUID();
  const temperature = parseFloat(DOM.tempSelect.value) || 0.6;
  const isAiCommand = text.match(/^[@＠]AI\s*/iu);
  const clientSentAt = performance.now();

  renderMessage(text, true, '', state.localNickname, state.localUserId, messageId, null, false);

  if (isAiCommand) {
    state.contextMemory.push({ role: 'user', content: text });
    localStorage.setItem('aiContext', JSON.stringify(state.contextMemory));
  }

  state.ws.send(JSON.stringify({
    type: 'message',
    room_id: state.currentRoomId,
    content: text,
    sender_id: state.localUserId,
    nickname: state.localNickname,
    message_id: messageId,
    temperature: temperature,
    context: isAiCommand ? state.contextMemory : null,
    _client_sent_at: clientSentAt,
    is_ai: !!isAiCommand
  }));

  if (isAiCommand) {
    startProgressBar();
    startTypingIndicator();
  }

  state.isSending = false;
  DOM.sendButton.disabled = false;
  DOM.userInput.focus();
}

/* ==================== 語音輸入 ==================== */
function startVoiceInput() {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SpeechRecognition) return renderMessage('瀏覽器不支援語音輸入', false, 'system-line error');

  const recognition = new SpeechRecognition();
  recognition.lang = 'zh-TW';
  recognition.interimResults = false;

  recognition.onstart = () => { DOM.voiceButton.classList.add('recording'); };
  recognition.onend = () => { DOM.voiceButton.classList.remove('recording'); };
  recognition.onerror = (e) => { renderMessage(`語音辨識錯誤: ${e.error}`, false, 'system-line error'); };

  recognition.onresult = (e) => {
    DOM.userInput.value = e.results[0][0].transcript;
    sendMessage();
  };

  recognition.start();
}

/* ==================== 清除聊天 ==================== */
function showCustomConfirm(message, callback) {
  DOM.customConfirm.querySelector('p').textContent = message;
  DOM.customConfirm.classList.remove('hidden');
  DOM.confirmYes.onclick = () => { DOM.customConfirm.classList.add('hidden'); callback(true); };
  DOM.confirmNo.onclick = () => { DOM.customConfirm.classList.add('hidden'); callback(false); };
}

function clearChat() {
  showCustomConfirm('確定要清除所有聊天紀錄嗎？此操作不可復原。', (confirmed) => {
    if (confirmed) {
      DOM.chatStage.innerHTML = '';
      state.contextMemory = [];
      state.renderedMessages.clear();
      localStorage.removeItem('aiContext');
      renderMessage('聊天紀錄已清除', false, 'system-line success');
    }
  });
}

/* ==================== 拖曳設定按鈕 ==================== */
function makeDraggable(element) {
  let isDragging = false, currentX, currentY, initialX, initialY;

  const startDrag = (e) => {
    isDragging = true;
    const rect = element.getBoundingClientRect();
    const clientX = e.clientX || e.touches[0].clientX;
    const clientY = e.clientY || e.touches[0].clientY;
    initialX = clientX - rect.left;
    initialY = clientY - rect.top;
    
    element.style.position = 'fixed'; 
    element.style.cursor = 'grabbing';
    element.style.zIndex = 1002;
  };

  const drag = (e) => {
    if (!isDragging) return;
    e.preventDefault();
    const clientX = e.clientX || e.touches[0].clientX;
    const clientY = e.clientY || e.touches[0].clientY;
    
    currentX = clientX - initialX;
    currentY = clientY - initialY;
    
    element.style.left = `${currentX}px`;
    element.style.top = `${currentY}px`;
    element.style.right = 'auto'; 
    element.style.bottom = 'auto'; 
  };

  const endDrag = () => { isDragging = false; element.style.cursor = 'grab'; };

  element.addEventListener('mousedown', startDrag);
  document.addEventListener('mousemove', drag);
  document.addEventListener('mouseup', endDrag);
  element.addEventListener('touchstart', startDrag, { passive: false });
  document.addEventListener('touchmove', drag, { passive: false });
  document.addEventListener('touchend', endDrag);
}

/* ==================== 事件綁定 ==================== */
function setupEventListeners() {
  DOM.settingsToggle.addEventListener('click', () => DOM.settingsMenu.classList.toggle('hidden'));
  DOM.voiceButton.addEventListener('click', startVoiceInput);
  DOM.sendButton.addEventListener('click', debouncedSendMessage);
  DOM.clearButton.addEventListener('click', clearChat);

  DOM.userInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      debouncedSendMessage();
    }
  });

  DOM.welcomeMessage?.addEventListener('click', () => {
    DOM.nicknameDialog.classList.remove('hidden');
    DOM.nicknameInput.value = state.localNickname;
    DOM.nicknameInput.focus();
  });

  const updateNickname = () => {
    const MAX_NICKNAME_LENGTH = 15; 
    const rawNewNick = DOM.nicknameInput.value.trim();
    const newNick = rawNewNick.substring(0, MAX_NICKNAME_LENGTH) || `訪客_${Math.floor(Math.random() * 100)}`;
    
    state.localNickname = newNick;
    localStorage.setItem('localNickname', newNick);
    DOM.welcomeNickname.textContent = newNick;
    if (state.ws?.readyState === WebSocket.OPEN) {
      state.ws.send(JSON.stringify({ type: 'join', room_id: state.currentRoomId, user_id: state.localUserId, nickname: newNick }));
    }
    DOM.nicknameDialog.classList.add('hidden');
  };

  DOM.confirmNicknameYes?.addEventListener('click', updateNickname);
  DOM.nicknameInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') updateNickname();
  });
  DOM.confirmNicknameNo?.addEventListener('click', () => DOM.nicknameDialog.classList.add('hidden'));

  DOM.userListToggle.addEventListener('click', () => {
    DOM.userListPanel.classList.toggle('hidden');
    DOM.userListToggle.textContent = DOM.userListPanel.classList.contains('hidden') ? '▼' : '▲';
  });
}

/* ==================== 初始化 ==================== */
document.addEventListener('DOMContentLoaded', () => {
  makeDraggable(DOM.settingsToggle);
  setupEventListeners();
  connectWebSocket();
});