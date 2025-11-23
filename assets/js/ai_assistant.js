// assets/js/ai_assistant.js
// Attach after DOM ready. Expects: an element #aiChatBox with #aiChatBody (for messages),
// #aiInput (text input) and quick area #aiQuickReplies. If not present it will create a small floating UI.

(function(){
  // small helper to create chat UI if missing
  function ensureUI() {
    if (document.getElementById('aiAssistantRoot')) return;
    const root = document.createElement('div');
    root.id = 'aiAssistantRoot';
    root.innerHTML = `
      <div id="aiChatBox" style="position:fixed;right:20px;bottom:20px;width:380px;z-index:9999;box-shadow:0 8px 30px rgba(0,0,0,0.2);border-radius:12px;overflow:hidden;background:#fff;font-family:Inter,Arial">
        <div id="aiChatHeader" style="background:linear-gradient(90deg,#667eea,#764ba2);color:#fff;padding:10px;display:flex;align-items:center;justify-content:space-between">
          <div>🤖 Airline Assistant</div>
          <div style="font-size:14px"><button id="aiMinBtn" style="background:transparent;border:none;color:#fff;cursor:pointer">—</button></div>
        </div>
        <div id="aiChatBody" style="height:320px;overflow:auto;padding:12px;background:#f7fbff"></div>
        <div style="padding:8px;border-top:1px solid #eee;background:#fff">
          <div style="display:flex;gap:6px">
            <button id="aiMic" title="Use microphone" style="width:40px;height:40px;border-radius:8px;border:1px solid #ddd;background:#fff">🎤</button>
            <input id="aiInput" type="text" placeholder="Ask about flights, e.g. 'Show my bookings'" style="flex:1;padding:10px;border-radius:8px;border:1px solid #ddd" />
            <button id="aiSend" style="margin-left:6px;padding:8px 12px;border-radius:8px;border:none;background:linear-gradient(90deg,#667eea,#764ba2);color:#fff;">Send</button>
          </div>
          <div id="aiQuickReplies" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap"></div>
        </div>
      </div>
    `;
    document.body.appendChild(root);

    // wire up
    document.getElementById('aiSend').addEventListener('click', sendMessage);
    document.getElementById('aiInput').addEventListener('keypress', function(e){ if(e.key==='Enter'){ sendMessage(); }});
    document.getElementById('aiMinBtn').addEventListener('click', ()=> {
      const box = document.getElementById('aiChatBox');
      if (box.style.height && box.style.height !== '') {
        box.style.height = '';
        document.getElementById('aiChatBody').style.display = 'block';
      } else {
        box.style.height = '40px';
        document.getElementById('aiChatBody').style.display = 'none';
      }
    });

    // mic
    const micBtn = document.getElementById('aiMic');
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
      micBtn.addEventListener('click', startMic);
    } else {
      micBtn.title = 'Microphone not supported in this browser';
      micBtn.style.opacity = 0.5;
    }

    // initial quick replies
    renderQuick(['Show my bookings','Available flights','Fare lookup','Help','Run SQL']);
    appendAssistant("Hi — ask me about flights, bookings, or say 'Book AI101 tomorrow'.");
  }

  // append messages
  function appendUser(text) {
    const body = document.getElementById('aiChatBody');
    const d = document.createElement('div');
    d.style.textAlign = 'right';
    d.innerHTML = `<div style="display:inline-block;background:linear-gradient(90deg,#667eea,#764ba2);color:#fff;padding:8px 12px;border-radius:12px;margin:6px 0;max-width:85%">${escapeHtml(text)}</div>`;
    body.appendChild(d); body.scrollTop = body.scrollHeight;
  }
  function appendAssistant(html, isHTML=true) {
    const body = document.getElementById('aiChatBody');
    const d = document.createElement('div');
    d.style.textAlign = 'left';
    if (isHTML) d.innerHTML = `<div style="display:inline-block;background:linear-gradient(90deg,#e9eefc,#f0f4ff);color:#222;padding:8px 12px;border-radius:12px;margin:6px 0;max-width:95%">${html}</div>`;
    else d.textContent = html;
    body.appendChild(d); body.scrollTop = body.scrollHeight;
  }

  function renderQuick(arr) {
    const container = document.getElementById('aiQuickReplies');
    container.innerHTML = '';
    (arr || []).forEach(s=>{
      const b = document.createElement('button');
      b.className = 'btn btn-sm';
      b.style.cssText = 'background:#f1f5ff;border:1px solid #e6eefc;padding:6px 10px;border-radius:8px;cursor:pointer';
      b.textContent = s;
      b.addEventListener('click', ()=> {
        document.getElementById('aiInput').value = s;
        sendMessage();
      });
      container.appendChild(b);
    });
  }

  // escape
  function escapeHtml(s){ return String(s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[m];}); }

  // voice
  let recognition = null;
  function startMic(){
    if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
      alert('Speech recognition not supported');
      return;
    }
    const ctor = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new ctor();
    recognition.lang = 'en-US';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    recognition.onresult = function(e){
      const text = e.results[0][0].transcript;
      document.getElementById('aiInput').value = text;
      sendMessage();
    };
    recognition.onerror = function(e){ console.error('Speech error', e); alert('Speech error: '+e.error); };
    recognition.start();
  }

  // send message
  let sending = false;
  async function sendMessage(){
    if (sending) return;
    const input = document.getElementById('aiInput');
    const text = input.value.trim();
    if (!text) return;
    appendUser(text);
    input.value = '';
    appendAssistant('<em>Thinking…</em>', true);
    sending = true;
    try {
      const res = await fetch('/ai_assistant_api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        credentials: 'include',
        body: JSON.stringify({prompt: text})
      });
      const data = await res.json();
      // remove last thinking bubble
      const body = document.getElementById('aiChatBody');
      if (body.lastChild) body.removeChild(body.lastChild);
      // if memory_html returned, prepend
      if (data.memory_html) {
        appendAssistant(data.memory_html, true);
      }
      // quick replies
      if (data.quick) renderQuick(data.quick);
      // structured types
      if (data.type === 'html') {
        appendAssistant(data.reply, true);
      } else if (data.type === 'action' && data.action === 'prefill_booking') {
        // show booking card with button to open reservation
        let html = `<div>${data.reply || 'Prepared booking'}</div><div style="margin-top:8px"><button id="openBookingBtn" style="background:linear-gradient(90deg,#1e3c72,#2a5298);color:#fff;padding:8px 10px;border-radius:8px;border:none">Open Booking</button></div>`;
        appendAssistant(html, true);
        // attach click
        setTimeout(()=> {
          const btn = document.getElementById('openBookingBtn');
          if (!btn) return;
          btn.addEventListener('click', ()=> {
            // open url in new tab
            const url = data.data && data.data.url ? data.data.url : null;
            if (url) window.open(url, '_blank');
          });
        }, 300);
      } else {
        // plain text
        appendAssistant(data.reply, true);
      }
    } catch (err) {
      console.error(err);
      const body = document.getElementById('aiChatBody');
      if (body.lastChild) body.removeChild(body.lastChild);
      appendAssistant("⚠️ Error contacting assistant: " + err.message, false);
    } finally {
      sending = false;
    }
  }

  // initialize UI on DOM ready
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ensureUI);
  else ensureUI();

  // expose for debugging
  window.AIRLINE_AI = { sendMessage };
})();
