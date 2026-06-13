<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/layout.php';
require_login();
$user = current_user();
if ($user['user_type'] !== 'student') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
$pdo = db();
$msgTableExists = true;
try {
  $pdo->query('SELECT 1 FROM messages LIMIT 1');
} catch (Throwable $e) {
  $msgTableExists = false;
}

if ($msgTableExists) {
  $contactStmt = $pdo->prepare(
    'SELECT t.id, t.name, t.email,
        SUM(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) AS unread
     FROM teacher_student ts
     JOIN users t ON t.id = ts.teacher_id
     LEFT JOIN messages m ON m.teacher_id = ts.teacher_id AND m.student_id = ts.student_id
     WHERE ts.student_id = ?
     GROUP BY t.id, t.name, t.email
     ORDER BY t.name'
  );
  $contactStmt->execute([$user['id'], $user['id']]);
  $contacts = $contactStmt->fetchAll();
} else {
  $contactStmt = $pdo->prepare(
    'SELECT t.id, t.name, t.email, 0 AS unread
     FROM teacher_student ts
     JOIN users t ON t.id = ts.teacher_id
     WHERE ts.student_id = ?
     GROUP BY t.id, t.name, t.email
     ORDER BY t.name'
  );
  $contactStmt->execute([$user['id']]);
  $contacts = $contactStmt->fetchAll();
}
$activePeerId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
if ($activePeerId === 0 && $contacts) {
    $activePeerId = (int)$contacts[0]['id'];
}
render_header('Messages', [], $user);
?>
<div class="row g-4" style="height:calc(100vh - var(--topbar-h) - 80px); min-height: 500px;">
  <!-- Contacts panel -->
  <div class="col-lg-4 h-100">
    <div class="app-card h-100 d-flex flex-column" style="overflow:hidden;">
      <div class="app-card-header">
        <div>
          <p class="mb-0" style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted);">Conversations</p>
          <h2 class="app-card-title mt-0">Teachers</h2>
        </div>
        <span class="badge-soft badge-soft-primary"><?= count($contacts) ?> linked</span>
      </div>
      <div class="flex-grow-1 overflow-y-auto" style="overflow-y:auto;">
        <?php if (empty($contacts)): ?>
          <div class="p-4 text-center">
            <i class="bi bi-person-x" style="font-size:2rem;color:var(--muted-light);display:block;margin-bottom:10px;"></i>
            <p class="mb-0" style="font-size:13px;color:var(--muted);">No teachers linked yet.</p>
          </div>
        <?php else: ?>
          <div id="chatContacts" role="listbox" style="display:flex;flex-direction:column;">
            <?php foreach ($contacts as $c): ?>
              <button
                type="button"
                class="chat-contact d-flex align-items-center gap-3 px-4 py-3 text-start border-0 bg-transparent w-100"
                data-peer-id="<?= (int)$c['id'] ?>"
                data-peer-name="<?= htmlspecialchars($c['name']) ?>"
                aria-label="Chat with <?= htmlspecialchars($c['name']) ?>"
                <?php if ($activePeerId === (int)$c['id']) echo 'aria-current="true"'; ?>
                style="border-bottom:1px solid var(--border)!important;transition:background var(--transition);cursor:pointer;"
              >
                <div class="sidebar-avatar flex-shrink-0"><?= strtoupper(substr($c['name'], 0, 1)) ?></div>
                <div class="flex-grow-1 min-width-0">
                  <p class="mb-0 fw-semibold" style="font-size:13.5px;color:var(--on-surface);"><?= htmlspecialchars($c['name']) ?></p>
                  <p class="mb-0" style="font-size:11.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($c['email']) ?></p>
                </div>
                <?php if ((int)$c['unread'] > 0): ?>
                  <span class="badge-soft badge-soft-danger flex-shrink-0"><?= (int)$c['unread'] ?></span>
                <?php endif; ?>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Chat panel -->
  <div class="col-lg-8 h-100">
    <div class="app-card h-100 d-flex flex-column" style="overflow:hidden;">
      <!-- Chat header -->
      <div class="app-card-header flex-shrink-0">
        <div>
          <p class="mb-0" style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted);">Active conversation</p>
          <h2 class="app-card-title mt-0" id="peerName">Select a teacher to start</h2>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge-soft badge-soft-gray" id="chatStatus">Idle</span>
          <button class="btn btn-sm btn-outline-secondary" id="refreshBtn"><i class="bi bi-arrow-repeat"></i></button>
        </div>
      </div>

      <?php if (!$msgTableExists): ?>
        <div class="alert alert-warning m-3" role="alert">Messages table is missing. Please run init_schema.php.</div>
      <?php endif; ?>

      <!-- Messages scroll area -->
      <div class="flex-grow-1 chat-scroll" id="chatMessages" role="log" aria-live="polite" aria-busy="false" style="overflow-y:auto;padding:16px;">
        <p class="text-center mb-0 py-4" style="color:var(--muted);">Select a teacher to load messages.</p>
      </div>

      <div id="chatAlert" class="alert alert-danger d-none mx-3 mt-2" role="alert"></div>

      <!-- Attachment preview -->
      <div class="file-upload-area d-none mx-3 mt-2" id="attachmentPreview">
        <div class="alert alert-info d-flex align-items-center justify-content-between mb-0 py-2">
          <span id="attachmentName" style="font-size:13px;">No file selected</span>
          <button type="button" class="btn btn-sm btn-outline-danger" id="clearAttachment"><i class="bi bi-x"></i></button>
        </div>
      </div>

      <!-- Send form -->
      <form id="sendForm" autocomplete="off" class="flex-shrink-0 p-3" style="border-top:1px solid var(--border);">
        <div class="input-group">
          <div class="file-upload-area">
            <input type="file" id="fileInput" accept="image/*,.pdf" <?= empty($contacts) ? 'disabled' : '' ?> />
            <button type="button" class="btn btn-outline-secondary" id="fileBtn" <?= empty($contacts) ? 'disabled' : '' ?> title="Attach file">
              <i class="bi bi-paperclip"></i>
            </button>
          </div>
          <button type="button" class="btn btn-outline-secondary voice-record-btn" id="voiceBtn" <?= empty($contacts) ? 'disabled' : '' ?> title="Voice message">
            <i class="bi bi-mic-fill"></i>
          </button>
          <textarea class="form-control" id="messageInput" rows="1" placeholder="Type your message…" <?= empty($contacts) ? 'disabled' : '' ?> style="resize:none;min-height:40px;"></textarea>
          <button class="btn btn-primary" type="submit" id="sendBtn" <?= empty($contacts) ? 'disabled' : '' ?>>
            <i class="bi bi-send-fill"></i>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
  <div id="sendToast" class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="sendToastBody">Message sent</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<script>
(function(){
  const contacts = <?= json_encode($contacts, JSON_UNESCAPED_SLASHES) ?>;
  let activePeerId = <?= (int)$activePeerId ?>;
  let lastId = 0;
  const apiUrl = '<?= htmlspecialchars(app_href('api/messages.php')) ?>';
  const currentUserId = parseInt('<?= (int)$user['id'] ?>');
  console.log('Current User ID:', currentUserId, 'Type:', typeof currentUserId);
  const chatBox = document.getElementById('chatMessages');
  const peerName = document.getElementById('peerName');
  const chatStatus = document.getElementById('chatStatus');
  const refreshBtn = document.getElementById('refreshBtn');
  const alertBox = document.getElementById('chatAlert');
  const sendForm = document.getElementById('sendForm');
  const messageInput = document.getElementById('messageInput');
  const sendBtn = document.getElementById('sendBtn');
  const toastEl = document.getElementById('sendToast');
  const toastBody = document.getElementById('sendToastBody');
  let toast = null;
  if (toastEl && typeof bootstrap !== 'undefined') {
    toast = new bootstrap.Toast(toastEl, { delay: 2500 });
  }

  const fileInput = document.getElementById('fileInput');
  const fileBtn = document.getElementById('fileBtn');
  const voiceBtn = document.getElementById('voiceBtn');
  const attachmentPreview = document.getElementById('attachmentPreview');
  const attachmentName = document.getElementById('attachmentName');
  const clearAttachment = document.getElementById('clearAttachment');
  let currentAttachment = null;
  let mediaRecorder = null;
  let audioChunks = [];

  fileBtn?.addEventListener('click', () => fileInput?.click());
  
  fileInput?.addEventListener('change', function(e){
    const file = e.target.files[0];
    if (!file) return;
    
    // Check file size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
      showAlert('File too large. Maximum 10MB allowed.');
      fileInput.value = '';
      return;
    }
    
    const reader = new FileReader();
    reader.onload = function(evt){
      let type = 'image';
      if (file.type === 'application/pdf') type = 'pdf';
      else if (file.type.startsWith('audio/')) type = 'audio';
      
      currentAttachment = { type, data: evt.target.result };
      attachmentName.textContent = file.name;
      attachmentPreview.classList.remove('d-none');
    };
    reader.readAsDataURL(file);
  });

  clearAttachment?.addEventListener('click', function(){
    currentAttachment = null;
    fileInput.value = '';
    attachmentPreview.classList.add('d-none');
  });

  voiceBtn?.addEventListener('click', async function(){
    if (!mediaRecorder || mediaRecorder.state === 'inactive') {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];
        
        mediaRecorder.ondataavailable = (e) => audioChunks.push(e.data);
        mediaRecorder.onstop = () => {
          const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
          const reader = new FileReader();
          reader.onload = function(evt){
            currentAttachment = { type: 'audio', data: evt.target.result };
            attachmentName.textContent = 'Voice message (' + Math.round(audioBlob.size/1024) + ' KB)';
            attachmentPreview.classList.remove('d-none');
          };
          reader.readAsDataURL(audioBlob);
          voiceBtn.classList.remove('recording');
          stream.getTracks().forEach(track => track.stop());
        };
        
        mediaRecorder.start();
        voiceBtn.classList.add('recording');
        voiceBtn.innerHTML = '<i class="bi bi-stop-fill"></i>';
      } catch(err) {
        showAlert('Microphone access denied or not available');
      }
    } else {
      mediaRecorder.stop();
      voiceBtn.classList.remove('recording');
      voiceBtn.innerHTML = '<i class="bi bi-mic-fill"></i>';
    }
  });

  function setStatus(text, busy){
    chatStatus.textContent = text;
    chatBox.setAttribute('aria-busy', busy ? 'true' : 'false');
  }

  function showAlert(msg){
    alertBox.textContent = msg;
    alertBox.classList.remove('d-none');
  }

  function clearAlert(){ alertBox.classList.add('d-none'); }

  function humanTime(ts){
    if(!ts) return '';
    const dt = new Date(ts.replace(' ', 'T'));
    return dt.toLocaleString([], { hour: '2-digit', minute: '2-digit', month: 'short', day: 'numeric' });
  }

  function renderMessages(list, replace){
    console.log('renderMessages called with', list.length, 'messages, replace:', replace);
    if (replace) { chatBox.innerHTML = ''; }
    if (!list || list.length === 0) {
      if (replace) {
        chatBox.innerHTML = '<p class="text-muted text-center py-4 mb-0">No messages yet. Say hello!</p>';
      }
      return;
    }
    const appBase = '<?= htmlspecialchars(app_href('')) ?>';
    list.forEach(msg => {
      const msgSenderId = parseInt(msg.sender_id);
      const isSent = msgSenderId === currentUserId;
      console.log('Message ID:', msg.id, 'Sender ID:', msgSenderId, 'Current User:', currentUserId, 'Is Sent:', isSent);
      
      const wrapper = document.createElement('div');
      wrapper.className = 'chat-row ' + (isSent ? 'chat-me' : 'chat-them');
      let attachmentHtml = '';
      if (msg.attachment_path && msg.attachment_type) {
        const url = appBase + '/' + msg.attachment_path;
        if (msg.attachment_type === 'image') {
          attachmentHtml = `<div class="chat-attachment"><a href="${url}" target="_blank"><img src="${url}" alt="Image"></a></div>`;
        } else if (msg.attachment_type === 'pdf') {
          attachmentHtml = `<div class="chat-attachment"><a href="${url}" target="_blank" class="text-decoration-none"><i class="bi bi-file-pdf-fill text-danger"></i> View PDF</a></div>`;
        } else if (msg.attachment_type === 'audio') {
          attachmentHtml = `<div class="chat-attachment"><audio controls src="${url}"></audio></div>`;
        }
      }
      let messageHtml = '';
      if (msg.body && msg.body.trim() !== '') {
        messageHtml = '<div class="chat-body">' + escapeHtml(msg.body) + '</div>';
      }
      wrapper.innerHTML = `
        <div class="chat-bubble">
          ${messageHtml}
          ${attachmentHtml}
          <div class="chat-meta">${isSent ? '📤 You' : '📥 Teacher'} · ${humanTime(msg.created_at)}</div>
        </div>`;
      chatBox.appendChild(wrapper);
      lastId = Math.max(lastId, parseInt(msg.id, 10));
    });
    chatBox.scrollTop = chatBox.scrollHeight;
  }

  function escapeHtml(str){
    return str.replace(/[&<>\"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c] || c;
    });
  }

  function loadMessages(since){
    if(!activePeerId){ return; }
    clearAlert();
    setStatus('Loading...', true);
    const url = apiUrl + '?peer_id=' + activePeerId + (since ? '&since_id=' + since : '');
    console.log('Fetching messages from:', url);
    fetch(url)
      .then(r => {
        console.log('Response status:', r.status);
        return r.ok ? r.json() : r.json().then(j => Promise.reject(j));
      })
      .then(data => {
        console.log('Received data:', data);
        peerName.textContent = data.peer ? data.peer.name : 'Conversation';
        renderMessages(data.messages || [], !since);
        setStatus('Live', false);
      })
      .catch(err => {
        console.error('Error loading messages:', err);
        showAlert(err && err.error ? err.error : 'Failed to load messages');
        setStatus('Error', false);
      });
  }

  function sendMessage(evt){
    evt.preventDefault();
    if (!activePeerId) { showAlert('Select a teacher first.'); return; }
    const body = messageInput.value.trim();
    if (!body && !currentAttachment) { showAlert('Message or attachment required.'); return; }
    sendBtn.disabled = true; setStatus('Sending...', true);
    
    const payload = { 
      peer_id: activePeerId, 
      body: body || ''
    };
    if (currentAttachment) {
      payload.attachment = currentAttachment;
    }
    
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(r => r.ok ? r.json() : r.json().then(j => Promise.reject(j)))
    .then(data => {
      if (data.message) { renderMessages([data.message], false); }
      messageInput.value = '';
      currentAttachment = null;
      fileInput.value = '';
      attachmentPreview.classList.add('d-none');
      clearAlert();
      if (toast && data.notification) { toastBody.textContent = data.notification; toast.show(); }
      setStatus('Live', false);
    })
    .catch(err => {
      showAlert(err && err.message ? err.message : 'Failed to send message');
      setStatus('Error', false);
    })
    .finally(() => { sendBtn.disabled = false; });
  }

  function selectPeer(id){
    activePeerId = id;
    lastId = 0;
    document.querySelectorAll('.chat-contact').forEach(btn => {
      if (parseInt(btn.dataset.peerId, 10) === id) {
        btn.classList.add('active');
        btn.setAttribute('aria-current', 'true');
        const badge = btn.querySelector('.badge');
        if (badge) { badge.remove(); }
      } else {
        btn.classList.remove('active');
        btn.removeAttribute('aria-current');
      }
    });
    loadMessages();
  }

  document.getElementById('chatContacts')?.addEventListener('click', function(e){
    const btn = e.target.closest('.chat-contact');
    if (!btn) return;
    const id = parseInt(btn.dataset.peerId, 10);
    selectPeer(id);
  });

  refreshBtn.addEventListener('click', function(){ loadMessages(lastId); });
  sendForm.addEventListener('submit', sendMessage);

  if (activePeerId) { selectPeer(activePeerId); }

  setInterval(function(){ if (activePeerId && lastId) { loadMessages(lastId); } }, 5000);
})();
</script>
<?php render_footer(); ?>
