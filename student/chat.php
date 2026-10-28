<?php
declare(strict_types=1);
define('REQUIRED_ROLE', 'student');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$adminId = (int)$bdd->query("SELECT userId FROM user WHERE role='admin' ORDER BY userId LIMIT 1")->fetchColumn();
?>
<!DOCTYPE html>
<html><head><title>Chat</title><link rel="stylesheet" href="../admin/styles.css?v=1.1">
<script>
let lastId = 0;
async function load() {
  const r = await fetch('/payment/get_messages.php?with=<?= $adminId ?>&since=' + lastId);
  const msgs = await r.json();
  const box = document.getElementById('box');
  msgs.forEach(m => {
    const p = document.createElement('p');
    p.textContent = m.message;
    box.appendChild(p);
    lastId = m.id;
  });
}
setInterval(load, 5000);
function send() {
  const msg = document.getElementById('msg').value;
  fetch('/payment/send_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=<?= $_SESSION['csrf_token'] ?>&receiver_id=<?= $adminId ?>&message=' + encodeURIComponent(msg)});
  document.getElementById('msg').value='';
  setTimeout(load, 500);
}
</script>
</head><body>
<div class="container"><main class="main-content">
<h1 class="page-title">Chat avec l'administration</h1>
<div id="box" style="border:1px solid #ccc;padding:10px;min-height:200px;"></div>
<input id="msg" style="width:80%"><button onclick="send()">Envoyer</button>
</main></div></body></html>
PHP