<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'student');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$adminId = (int)$bdd->query("SELECT userId FROM user WHERE role='admin' ORDER BY userId LIMIT 1")->fetchColumn();
?>
<!DOCTYPE html>
<html><head><title>Chat</title><link rel="stylesheet" href="../admin/styles.css?v=1.1">
<style>
.chat-main { display:flex; flex-direction:column; background:#fff; border-radius:12px; padding:16px; height:70vh; box-shadow:0 2px 8px rgba(0,0,0,.08); }
.chat-box { flex:1; overflow:auto; padding:12px; background:#f8fafc; border-radius:8px; }
.msg { max-width:75%; margin:8px 0; padding:10px 14px; border-radius:14px; line-height:1.4; word-wrap:break-word; }
.msg.me { background:#1e3a8a; color:#fff; margin-left:auto; border-bottom-right-radius:4px; }
.msg.them { background:#e2e8f0; color:#1e293b; border-bottom-left-radius:4px; }
.chat-input { display:flex; gap:8px; margin-top:12px; }
.chat-input input { flex:1; padding:10px; border:1px solid #cbd5e1; border-radius:8px; }
.chat-input button { padding:10px 18px; background:#1e3a8a; color:#fff; border:none; border-radius:8px; cursor:pointer; }
</style>
<script>
const me = <?= (int)$_SESSION['userId'] ?>;
const adminId = <?= $adminId ?>;
let lastId = 0;
async function load() {
  const r = await fetch('/payment/get_messages.php?with=' + adminId + '&since=' + lastId);
  const msgs = await r.json();
  const box = document.getElementById('box');
  msgs.forEach(m => {
    const div = document.createElement('div');
    div.className = 'msg ' + (m.sender_id == me ? 'me' : 'them');
    div.textContent = m.message;
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
  const box = document.getElementById('box');
  const div = document.createElement('div');
  div.className = 'msg me';
  div.textContent = msg;
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
  fetch('/payment/send_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=<?= $_SESSION['csrf_token'] ?>&receiver_id=' + adminId + '&message=' + encodeURIComponent(msg)});
  input.value='';
  setTimeout(load, 500);
}
document.addEventListener('DOMContentLoaded', load);
</script>
</head><body>
<div class="container"><aside class="sidebar"><?php include 'sidebar.php'; ?></aside><main class="main-content">
<h1 class="page-title">Chat avec l'administration</h1>
<div class="chat-main">
  <div class="chat-box" id="box"></div>
  <div class="chat-input">
    <input id="msg" placeholder="Écrire un message..." onkeydown="if(event.key==='Enter')send()">
    <button onclick="send()">Envoyer</button>
  </div>
</div>
</main></div></body></html>
PHP