<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$with = (int)($_GET['with'] ?? 0);
$students = $bdd->query("SELECT userId, fullname FROM user WHERE role='student' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><title>Chat</title><link rel="stylesheet" href="./styles.css?v=20261030">
<style>
.chat-layout { display:flex; gap:16px; height:70vh; }
.chat-sidebar { width:260px; background:#fff; border-radius:12px; padding:12px; overflow:auto; }
.chat-main { flex:1; display:flex; flex-direction:column; background:#fff; border-radius:12px; padding:16px; }
.chat-box { flex:1; overflow:auto; padding:8px; background:#f8fafc; border-radius:8px; }
.msg { max-width:70%; margin:8px 0; padding:10px 14px; border-radius:14px; line-height:1.4; }
.msg.me { background:#1e3a8a; color:#fff; margin-left:auto; border-bottom-right-radius:4px; }
.msg.them { background:#e2e8f0; color:#1e293b; border-bottom-left-radius:4px; }
.chat-input { display:flex; gap:8px; margin-top:12px; }
.chat-input input { flex:1; padding:10px; border:1px solid #cbd5e1; border-radius:8px; }
.chat-input button { padding:10px 18px; background:#1e3a8a; color:#fff; border:none; border-radius:8px; cursor:pointer; }
</style>
<script>
const EDIT_WINDOW = 120;
const me = <?= (int)$_SESSION['userId'] ?>;
let lastId = 0;
let loading = false;
async function load() {
  if (loading) return;
  loading = true;
  try {
    if (!<?= $with ?>) return;
    const r = await fetch('/payment/get_messages.php?with=<?= $with ?>&since=' + lastId);
    const msgs = await r.json();
    const box = document.getElementById('box');
    msgs.forEach(m => {
      const div = document.createElement('div');
      div.className = 'msg ' + (m.sender_id == me ? 'me' : 'them');
      div.textContent = m.message;
      if (m.sender_id == me) {
        const actions = document.createElement('div');
        actions.style.fontSize='0.75rem';
        actions.style.marginTop='4px';
        const remaining = EDIT_WINDOW - (m.age_seconds || 0);
        if (remaining > 0) {
          const editBtn = document.createElement('button');
          editBtn.textContent='Modifier';
          editBtn.onclick = async () => {
            const nm = prompt('Nouveau message', m.message);
            if (nm && nm.trim()) {
              const res = await fetch('/payment/edit_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=<?= $_SESSION['csrf_token'] ?>&id=' + m.id + '&message=' + encodeURIComponent(nm)});
              if (!res.ok) {
                const data = await res.json().catch(()=>({}));
                alert(data.error || 'Modification impossible');
                if (res.status === 403) editBtn.remove();
              } else {
                if (div.firstChild) div.firstChild.nodeValue = nm;
              }
            }
          };
          actions.appendChild(editBtn);
          setTimeout(() => editBtn.remove(), remaining * 1000);
        }
        const delBtn = document.createElement('button');
        delBtn.textContent='Supprimer';
        delBtn.onclick = async () => {
          if (confirm('Supprimer ce message ?')) {
            await fetch('/payment/delete_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=<?= $_SESSION['csrf_token'] ?>&id=' + m.id});
            box.innerHTML=''; lastId=0; load();
          }
        };
        actions.appendChild(delBtn);
        div.appendChild(actions);
      }
      box.appendChild(div);
      lastId = m.id;
    });
    box.scrollTop = box.scrollHeight;
  } finally {
    loading = false;
  }
}
setInterval(load, 3000);
async function send() {
  const input = document.getElementById('msg');
  const msg = input.value.trim();
  if (!msg) return;
  const btn = document.getElementById('sendBtn');
  btn.disabled = true;
  try {
    const res = await fetch('/payment/send_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=<?= $_SESSION['csrf_token'] ?>&receiver_id=<?= $with ?>&message=' + encodeURIComponent(msg)});
    if (!res.ok) { alert('Envoi impossible'); return; }
    input.value='';
    await load();
  } finally {
    btn.disabled = false;
  }
}
document.addEventListener('DOMContentLoaded', load);
</script>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
</head><body>
<div class="container">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <section class="page active">
            <h1 class="page-title">Messagerie</h1>
<div class="chat-layout">
  <div class="chat-sidebar">
    <h3>Étudiants</h3>
    <form method="GET">
      <select name="with" style="width:100%;padding:8px;border-radius:8px;">
        <?php foreach ($students as $s): ?>
          <option value="<?= (int)$s['userId'] ?>" <?= $with===$s['userId']?'selected':'' ?>><?= htmlspecialchars($s['fullname']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn-primary" type="submit" style="margin-top:8px;width:100%;">Ouvrir</button>
    </form>
  </div>
  <div class="chat-main">
    <div class="chat-box" id="box"></div>
    <div class="chat-input">
      <input id="msg" placeholder="Écrire un message..." onkeydown="if(event.key==='Enter')send()">
      <button id="sendBtn" onclick="send()">Envoyer</button>
    </div>
  </div>
</div>
</main></div>    <script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body></html>
