<?php
declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('pcre.jit', '0');

define('REQUIRED_ROLE', 'student');
require __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../functions.php';

$currentUserId = (int) ($_SESSION['userId'] ?? 0);

// --- Contacts : UNIQUEMENT les administrateurs pour un étudiant ---
try {
    $stmt = $bdd->prepare("
        SELECT userId, fullname, email, role 
        FROM user 
        WHERE role = 'admin' 
        ORDER BY fullname ASC
    ");
    $stmt->execute();
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur contacts : ' . $e->getMessage());
}

$selectedContactId = isset($_GET['contact']) ? (int) $_GET['contact'] : ($contacts[0]['userId'] ?? 0);
$selectedContact = null;
foreach ($contacts as $c) {
    if ((int) $c['userId'] === $selectedContactId) { $selectedContact = $c; break; }
}

// --- Messages ---
$messages = [];
if ($selectedContact) {
    try {
        $stmt = $bdd->prepare("
            SELECT id, sender_id, message, created_at 
            FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC
        ");
        $stmt->execute([$currentUserId, $selectedContactId, $selectedContactId, $currentUserId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $update = $bdd->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $update->execute([$selectedContactId, $currentUserId]);
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Chat - ULT Payment</title>
    <link rel="stylesheet" href="/payment/assets/css/app.css?v=20261030">
    <link rel="stylesheet" href="./styles.css?v=20261030">
    <?php loadTheme($bdd); ?>
    <link rel="stylesheet" href="/payment/assets/css/responsive.css?v=20261030">
    <link rel="stylesheet" href="/payment/assets/ui.css">
    <style>
        .main-content { height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        .chat-layout { display: grid; grid-template-columns: 320px 1fr; flex: 1; min-height: 0; margin-bottom: 20px; background: #fff; border-radius: var(--radius); border: 1px solid var(--gray-200); box-shadow: var(--shadow); overflow: hidden; }
        .chat-contacts { border-right: 1px solid var(--gray-200); display: flex; flex-direction: column; background: var(--gray-50); min-height: 0; }
        .chat-contacts-header { padding: 18px 20px; border-bottom: 1px solid var(--gray-200); background: #fff; flex-shrink: 0; }
        .chat-contacts-header h2 { font-size: 1rem; color: var(--primary); margin: 0; }
        .chat-contacts-header p { font-size: 0.8rem; color: var(--gray-600); margin-top: 4px; }
        .chat-contacts-list { flex: 1; overflow-y: auto; padding: 8px; min-height: 0; }
        .chat-contact { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 10px; text-decoration: none; color: var(--gray-900); transition: background 0.2s; }
        .chat-contact:hover { background: #fff; }
        .chat-contact.active { background: var(--primary-light); color: #fff; }
        .chat-contact.active .chat-contact-role { color: rgba(255,255,255,0.8); }
        .chat-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--gray-200); color: var(--gray-600); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0; }
        .chat-contact.active .chat-avatar { background: rgba(255,255,255,0.2); color: #fff; }
        .chat-contact-info { min-width: 0; }
        .chat-contact-name { font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .chat-contact-role { font-size: 0.75rem; color: var(--gray-600); text-transform: capitalize; }
        
        .chat-conversation { display: flex; flex-direction: column; min-height: 0; background: #fff; }
        .chat-header { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--gray-200); background: #fff; flex-shrink: 0; }
        .chat-header-info h3 { font-size: 1rem; color: var(--primary); margin: 0; }
        .chat-header-info p { font-size: 0.8rem; color: var(--gray-600); margin: 0; }
        
        .chat-messages { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; background: var(--gray-50); min-height: 0; }
        
        .message-row { display: flex; gap: 10px; max-width: 75%; position: relative; }
        .message-row.mine { align-self: flex-end; flex-direction: row-reverse; }
        
        .message-bubble { padding: 10px 14px; border-radius: 14px; font-size: 0.9rem; line-height: 1.45; word-wrap: break-word; position: relative; padding-right: 30px; }
        .message-row:not(.mine) .message-bubble { background: #fff; color: var(--gray-900); border: 1px solid var(--gray-200); border-bottom-left-radius: 4px; }
        .message-row.mine .message-bubble { background: var(--primary-light); color: #fff; border-bottom-right-radius: 4px; }
        .message-meta { font-size: 0.7rem; margin-top: 6px; opacity: 0.7; }

        .msg-options-btn { position: absolute; right: 2px; top: 6px; background: none; border: none; color: rgba(255,255,255,0.7); cursor: pointer; padding: 4px; display: none; align-items: center; justify-content: center; }
        .message-row.mine:hover .msg-options-btn { display: flex; }
        .msg-options-btn:hover { color: #fff; }
        
        .msg-dropdown { position: absolute; top: 25px; right: 0; background: #fff; border: 1px solid var(--gray-200); border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none; flex-direction: column; min-width: 120px; z-index: 100; overflow: hidden; }
        .msg-dropdown.show { display: flex; }
        .msg-dropdown button { background: none; border: none; padding: 10px 14px; text-align: left; font-size: 0.85rem; color: var(--gray-900); cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .msg-dropdown button:hover { background: var(--gray-50); }
        .msg-dropdown .del-btn { color: var(--danger); }

        .chat-input { display: flex; gap: 10px; padding: 14px 20px; border-top: 1px solid var(--gray-200); background: #fff; flex-shrink: 0; }
        .chat-input textarea { flex: 1; resize: none; border: 1px solid var(--gray-200); border-radius: 10px; padding: 10px 14px; font-size: 0.9rem; font-family: inherit; min-height: 44px; max-height: 120px; outline: none; background: var(--gray-50); }
        .chat-input textarea:focus { border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); background: #fff; }
        .chat-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: var(--gray-600); padding: 40px; text-align: center; }
        
        @media (max-width: 900px) { .chat-layout { grid-template-columns: 1fr; } .chat-contacts { max-height: 200px; border-right: none; border-bottom: 1px solid var(--gray-200); } .chat-conversation { height: 500px; } }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main id="main-content" class="main-content">
        <section class="page active" style="display: flex; flex-direction: column; height: 100%;">
            <h1 class="page-title" style="flex-shrink: 0;">Chat</h1>
            <div class="chat-layout">
                <!-- Contacts (Admins uniquement) -->
                <div class="chat-contacts">
                    <div class="chat-contacts-header">
                        <h2>Administrateurs</h2>
                        <p><?= count($contacts) ?> contact(s)</p>
                    </div>
                    <div class="chat-contacts-list">
                        <?php if ($contacts): ?>
                            <?php foreach ($contacts as $contact): ?>
                                <?php $initial = strtoupper(substr($contact['fullname'], 0, 1)); $isActive = ((int) $contact['userId'] === $selectedContactId); ?>
                                <a href="chat.php?contact=<?= (int) $contact['userId'] ?>" class="chat-contact <?= $isActive ? 'active' : '' ?>">
                                    <div class="chat-avatar"><?= $initial ?></div>
                                    <div class="chat-contact-info">
                                        <div class="chat-contact-name"><?= htmlspecialchars($contact['fullname']) ?></div>
                                        <div class="chat-contact-role"><?= htmlspecialchars($contact['role']) ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="padding: 20px; color: var(--gray-600); font-size: 0.9rem;">Aucun administrateur disponible.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Conversation -->
                <div class="chat-conversation">
                    <?php if ($selectedContact): ?>
                        <div class="chat-header">
                            <div class="chat-avatar"><?= strtoupper(substr($selectedContact['fullname'], 0, 1)) ?></div>
                            <div class="chat-header-info">
                                <h3><?= htmlspecialchars($selectedContact['fullname']) ?></h3>
                                <p><?= htmlspecialchars($selectedContact['role']) ?> — <?= htmlspecialchars($selectedContact['email']) ?></p>
                            </div>
                        </div>
                        <div class="chat-messages" id="chat-messages">
                            <?php foreach ($messages as $msg): ?>
                                <?php $isMine = ($msg['sender_id'] == $currentUserId); ?>
                                <div class="message-row <?= $isMine ? 'mine' : '' ?>" data-message-id="<?= $msg['id'] ?>">
                                    <div class="message-bubble">
                                        <div class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                        <div class="message-meta"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                                        
                                        <?php if ($isMine): ?>
                                            <button class="msg-options-btn" onclick="toggleDropdown(this)">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                                            </button>
                                            <div class="msg-dropdown">
                                                <button class="edit-btn" data-id="<?= $msg['id'] ?>">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                    Modifier
                                                </button>
                                                <button class="delete-btn del-btn" data-id="<?= $msg['id'] ?>">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    Supprimer
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form class="chat-input" id="chat-form" data-receiver-id="<?= (int) $selectedContact['userId'] ?>">
                            <textarea id="chat-message-input" placeholder="Écrivez votre message à l'administrateur..." rows="1" required></textarea>
                            <button type="submit" class="btn btn-primary">Envoyer</button>
                        </form>
                    <?php else: ?>
                        <div class="chat-empty">
                            <p>Aucun administrateur disponible pour le moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
function toggleDropdown(btn) {
    document.querySelectorAll('.msg-dropdown').forEach(d => {
        if (d !== btn.nextElementSibling) d.classList.remove('show');
    });
    btn.nextElementSibling.classList.toggle('show');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.msg-options-btn')) {
        document.querySelectorAll('.msg-dropdown').forEach(d => d.classList.remove('show'));
    }
});

document.addEventListener('DOMContentLoaded', function () {
    const chatForm = document.getElementById('chat-form');
    const messagesContainer = document.getElementById('chat-messages');
    const messageInput = document.getElementById('chat-message-input');
    if (!chatForm) return;
    const receiverId = chatForm.dataset.receiverId;

    if (messagesContainer) messagesContainer.scrollTop = messagesContainer.scrollHeight;

    chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const message = messageInput.value.trim();
        if (message === '') return;
        const submitBtn = chatForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '...';

        const formData = new FormData();
        formData.append('receiver_id', receiverId);
        formData.append('message', message);
        formData.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');

        fetch('../send_message.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur : ' + (data.error || 'Inconnu'));
            }
        })
        .catch(err => alert('Erreur réseau.'))
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            document.querySelectorAll('.msg-dropdown').forEach(d => d.classList.remove('show'));
            
            const msgId = this.dataset.id;
            const bubble = this.closest('.message-bubble');
            const textDiv = bubble.querySelector('.message-text');
            const metaDiv = bubble.querySelector('.message-meta');
            const btnOptions = bubble.querySelector('.msg-options-btn');
            const textarea = document.createElement('textarea');
            
            textarea.value = textDiv.innerText.trim();
            textarea.style.width = '100%';
            textarea.style.minHeight = '60px';
            textarea.style.border = '1px solid #ccc';
            textarea.style.borderRadius = '6px';
            textarea.style.padding = '6px';
            textarea.style.fontSize = '0.9rem';

            const saveBtn = document.createElement('button');
            saveBtn.textContent = 'Enregistrer';
            saveBtn.className = 'btn btn-primary';
            saveBtn.style.marginTop = '6px';
            saveBtn.style.padding = '4px 10px';
            saveBtn.style.fontSize = '0.8rem';

            const cancelBtn = document.createElement('button');
            cancelBtn.textContent = 'Annuler';
            cancelBtn.className = 'btn btn-secondary';
            cancelBtn.style.marginLeft = '6px';
            cancelBtn.style.padding = '4px 10px';
            cancelBtn.style.fontSize = '0.8rem';

            const editContainer = document.createElement('div');
            editContainer.appendChild(textarea);
            editContainer.appendChild(saveBtn);
            editContainer.appendChild(cancelBtn);

            textDiv.style.display = 'none';
            metaDiv.style.display = 'none';
            btnOptions.style.display = 'none';
            bubble.appendChild(editContainer);

            cancelBtn.addEventListener('click', function () {
                textDiv.style.display = '';
                metaDiv.style.display = '';
                btnOptions.style.display = '';
                editContainer.remove();
            });

            saveBtn.addEventListener('click', function () {
                const newMessage = textarea.value.trim();
                if (newMessage === '') return;

                const formData = new FormData();
                formData.append('message_id', msgId);
                formData.append('message', newMessage);
                formData.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');

                fetch('../edit_message.php', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            textDiv.innerHTML = newMessage.replace(/\n/g, '<br>');
                            textDiv.style.display = '';
                            metaDiv.style.display = '';
                            btnOptions.style.display = '';
                            editContainer.remove();
                        } else {
                            alert('Erreur : ' + data.error);
                        }
                    } catch (e) {
                        alert('Erreur serveur (non-JSON) : ' + text);
                        console.error(text);
                    }
                })
                .catch(err => alert('Erreur réseau : ' + err.message));
            });
        });
    });

    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            document.querySelectorAll('.msg-dropdown').forEach(d => d.classList.remove('show'));
            
            if (!confirm('Supprimer ce message ?')) return;
            const msgId = this.dataset.id;
            const row = this.closest('.message-row');

            const formData = new FormData();
            formData.append('message_id', msgId);
            formData.append('csrf_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');

            fetch('../delete_message.php', { method: 'POST', body: formData })
            .then(r => r.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        row.remove();
                    } else {
                        alert('Erreur : ' + data.error);
                    }
                } catch (e) {
                    alert('Erreur serveur (non-JSON) : ' + text);
                    console.error(text);
                }
            })
            .catch(err => alert('Erreur réseau : ' + err.message));
        });
    });

    messageInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    });
});
</script>
<script src="/payment/assets/js/responsive.js?v=20261030"></script>
</body>
</html>
