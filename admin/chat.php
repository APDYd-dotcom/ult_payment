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
<script>
let lastId = 0;
async function load() {
  if (!<?= $with ?>) return;
  const r = await fetch('/payment/get_messages.php?with=<?= $with ?>&since=' + lastId);
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
  fetch('/payment/send_message.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'csrf_token=<?= $_SESSION['csrf_token'] ?>&receiver_id=<?= $with ?>&message=' + encodeURIComponent(msg)});
  document.getElementById('msg').value='';
  setTimeout(load, 500);
}
</script>
</head><body>
<div class="container"><aside class="sidebar"><?php include 'sidebar.php'; ?></aside><main class="main-content">
<h1 class="page-title">Chat</h1>
<form method="GET" class="form-section">
<select name="with"><?php foreach ($students as $s): ?><option value="<?= (int)$s['userId'] ?>" <?= $with===$s['userId']?'selected':'' ?>><?= htmlspecialchars($s['fullname']) ?></option><?php endforeach; ?></select>
<button class="btn-primary" type="submit">Ouvrir</button>
</form>
<div id="box" style="border:1px solid #ccc;padding:10px;min-height:200px;"></div>
<input id="msg" style="width:80%"><button onclick="send()">Envoyer</button>
</main></div></body></html>
PHP