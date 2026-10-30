<?php
/**
 * Helper d'affichage des icones via Lucide (online CDN).
 * Licence : Lucide ISC - https://lucide.dev
 *
 * icon('dashboard', 'classe-suppl') renvoie :
 *   <i data-lucide="dashboard" class="icon classe-suppl" aria-hidden="true"></i>
 *
 * Le script Lucide est charge une seule fois dans l'en-tete des pages
 * (voir le <script src="https://unpkg.com/lucide@latest"></script> dans le head).
 * Les icones sont rechargees automatiquement par Lucide au chargement du DOM.
 */
function icon(string $name, string $class = ''): string
{
    $name = preg_replace('/[^a-z0-9_-]/i', '', $name);
    if ($name === '') {
        $name = 'help-circle';
    }
    $cls = $class === '' ? 'icon' : 'icon ' . preg_replace('/[^a-z0-9 _-]/i', '', $class);
    return '<i data-lucide="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>';
}