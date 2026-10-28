<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'admin');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$with = (int)($_GET['with'] ?? 0);
$students = $bdd->query("SELECT userId, fullname FROM user WHERE role='student' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><title>Chat</title><link rel="stylesheet" href="./styles.css?v=1.1">
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
let lastId = 0;
const me = <?= (int)$_SESSION['userId'] ?>;
async function load() {
  if (!<?= $with ?>) return;
  const r = await fetch('/payment/get_messages.php?with=<?= $with ?>&since=' + lastId);
  const msgs = await r.json();
  const box = document.getElementById('box');
  msgs.forEach(m => {
    const div = document.createElement('div');
    div.className = 'msg ' + (m.sender_id == me ? 'me' : 'them');
    div.textContent = m.message;
    const age = (Date.now() - new Date(m.created_at).getTime()) / 1000;
    if (m.sender_id == me) {
      const actions = document.createElement('div');
      actions.style.fontSize='0.75rem';
      actions.style.marginTop='4px';
      if (age < 120) {
      const editBtn = document.createElement('button');
      editBtn.textContent='Modifier';
      editBtn.onclick = async () => {
        const nm = prompt('Nouveau message', m.message);
        if (nm && nm.trim()) {
          await fetch('/payment/edit_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=<?= $_SESSION['csrf_token'] ?>&id=' + m.id + '&message=' + encodeURIComponent(nm)});
          box.innerHTML=''; lastId=0; load();
        }
      };
      }
      const delBtn = document.createElement('button');
      delBtn.textContent='Supprimer';
      delBtn.onclick = async () => {
        if (confirm('Supprimer ce message ?')) {
          await fetch('/payment/delete_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=<?= $_SESSION['csrf_token'] ?>&id=' + m.id});
          box.innerHTML=''; lastId=0; load();
        }
      };
      if (age < 120) actions.appendChild(editBtn);
      actions.appendChild(delBtn);
      div.appendChild(actions);
    }
    box.appendChild(div);
    lastId = m.id;
  });
  box.scrollTop = box.scrollHeight;
}
setInterval(load, 3000);
function send() {
  const input = document.getElementById('msg');
  const msg = input.value.trim();
  if (!msg) return;
  fetch('/payment/send_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=<?= $_SESSION['csrf_token'] ?>&receiver_id=<?= $with ?>&message=' + encodeURIComponent(msg)});
  input.value='';
  setTimeout(load, 300);
}
document.addEventListener('DOMContentLoaded', load);
</script>
</head><body>
<div class="container"><aside class="sidebar"><?php include 'sidebar.php'; ?></aside><main class="main-content">
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
      <button onclick="send()">Envoyer</button>
    </div>
  </div>
</div>
</main></div></body></html>
PHP