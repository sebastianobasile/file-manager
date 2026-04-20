<?php
/**
 * FILE MANAGER v6.5 - Sebastiano Basile – superscuola.com – sostegno.t.me
 */
session_start();

// ╔══════════════════════════════════════════════════════════════╗
// ║                      CONFIGURAZIONE                         ║
// ╠══════════════════════════════════════════════════════════════╣
// ║  Modifica solo questo blocco per personalizzare il tool     ║
// ╚══════════════════════════════════════════════════════════════╝

// ── 🔐 ACCESSO ──────────────────────────────────────────────────
$LOGIN_REQUIRED = true;       // true = richiede password | false = accesso libero
$PASSWORD       = "Cambiami!";   // Password di accesso (testo in chiaro)

// ── 📤 UPLOAD ────────────────────────────────────────────────────
// ⚠️  Se LOGIN_REQUIRED = false, imposta UPLOAD_ENABLED = false (sito pubblico!)
// ⚠️  Per caricare nella cartella dove si trova index.php, usa: ''
$UPLOAD_ENABLED     = true;
$UPLOAD_FOLDER      = '';  // '' = cartella radice | 'upload' = sottocartella
$UPLOAD_MAX_SIZE_MB = 20;
$UPLOAD_ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'doc', 'xls', 'zip', 'json', 'af', 'php'];

// ── 🪟 APERTURA FILE ─────────────────────────────────────────────
$OPEN_IN_NEW_TAB = true;

// ── 📁 FILE E CARTELLE NASCOSTI ──────────────────────────────────
$EXCLUDED_FILES   = ['index.php', '.htaccess', 'test.php', '.expires.json', '.passwords.json'];
$EXCLUDED_FOLDERS = ['Ammucciata', 'Test'];

// ── 📄 ESTENSIONI ────────────────────────────────────────────────
$ALLOWED_EXTENSIONS   = ['php', 'pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'doc', 'xls', 'html', 'mid', 'zip', 'json', 'af'];
$INLINE_EXTENSIONS    = ['pdf', 'html', 'jpg', 'jpeg', 'png', 'mid', 'php'];
$DOWNLOAD_EXTENSIONS  = ['docx', 'xlsx', 'doc', 'xls', 'zip', 'json'];

// ── 🏷️ FILE RISERVATI (mostrano il lucchetto 🔒) ─────────────────
$RESERVED_PREFIXES = ['RISERVATO_', '🔐', 'PRIV_'];
$NEW_TAB_SUFFIX    = '_NUOVO';

// ── 🖨️ CARTELLA PUBBLICA (accesso senza login) ────────────────────
// Crea manualmente questa cartella sul server (es. via FTP/cPanel)
// Chiunque abbia il link può vedere e aprire i file, senza password
$PUBLIC_FOLDER              = 'stampa'; // Nome cartella pubblica (usa minuscolo: Linux è case-sensitive)
$PUBLIC_FOLDER_EXPIRE_HOURS = 24;       // File eliminati dopo X ore (0 = mai)

// ── 🎨 TESTI INTERFACCIA LOGIN ───────────────────────────────────
$LOGIN_TITLE       = '<i class="fas fa-lock" style="margin-right:5px;"></i>Accesso Riservato';
$LOGIN_DESCRIPTION = '<b>Inserisci la password ricevuta via email o</b><br>
richiedila gratuitamente a <i>Sebastiano <b>Basile</b></i><br>
tramite il <a href="https://forms.gle/RK9vr5eUMu722sYX9" target="_blank" rel="noopener noreferrer">modulo contatti</a>.';

// ── 🎨 TESTI INTERFACCIA PRINCIPALE ─────────────────────────────
$MAIN_TITLE = '<i class="fas fa-folder-open lock-color" style="margin-right:5px;"></i> FILE MANAGER – SEBASTIANO BASILE';
$MAIN_NOTE  = ' <i class="fas fa-lock" style="color:#ffc107;" title="Accesso riservato"></i> = Accesso riservato &nbsp; <i class="fas fa-external-link-alt" title="Apre in nuova scheda"></i> = Nuova scheda.';
$CREDITS    = 'INFO e crediti: <a href="https://superscuola.com" target="_blank" rel="noopener noreferrer">Sebastiano Basile</a> | <a href="https://github.com/sebastianobasile/file-manager" target="_blank" rel="noopener noreferrer">GitHub</a> ©️';

// ╚══════════════════════════════════════════════════════════════╝
//                    FINE CONFIGURAZIONE
// ╔══════════════════════════════════════════════════════════════╝


// ================================================================
// UTILITY: gestione percorsi cartelle
// ================================================================

/**
 * Ritorna il prefisso di cartella con trailing slash, oppure '' per la root.
 * Es: folderPrefix('upload') → 'upload/'
 *     folderPrefix('')       → ''
 */
function folderPrefix($folder) {
    return ($folder !== '' && $folder !== '.') ? rtrim($folder, '/') . '/' : '';
}

/**
 * Sanifica un parametro cartella proveniente da GET/POST.
 */
function safeFolderParam($raw) {
    return str_replace(['..', '\\'], '', trim($raw ?? '', '/'));
}

// ── Percorso del file JSON con le scadenze per-file ──────────────
function expiresFile($folder) { return $folder . '/.expires.json'; }

/**
 * Legge il registro scadenze: ['filename' => unix_timestamp_expiry, ...]
 * timestamp = 0 → mai scade
 */
function readExpires($folder) {
    $f = expiresFile($folder);
    if (!file_exists($f)) return [];
    $data = json_decode(file_get_contents($f), true);
    return is_array($data) ? $data : [];
}

/**
 * Salva il registro scadenze.
 */
function writeExpires($folder, $data) {
    file_put_contents(expiresFile($folder), json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Imposta la scadenza di un singolo file (0 = mai).
 */
function setFileExpiry($folder, $filename, $expire_ts) {
    $data = readExpires($folder);
    if ($expire_ts === 0) {
        unset($data[$filename]);
    } else {
        $data[$filename] = (int)$expire_ts;
    }
    // Rimuovi voci di file non più esistenti
    foreach (array_keys($data) as $k) {
        if (!file_exists($folder . '/' . $k)) unset($data[$k]);
    }
    writeExpires($folder, $data);
}

/**
 * Elimina i file scaduti nella cartella pubblica.
 * Usa il JSON se disponibile, altrimenti il fallback globale.
 */
function cleanPublicFolder($folder, $default_hours) {
    if (!is_dir($folder)) return;
    $now     = time();
    $expires = readExpires($folder);
    foreach (scandir($folder) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $folder . '/' . $f;
        // Ricorri nelle sottocartelle
        if (is_dir($path)) {
            cleanPublicFolder($path, $default_hours);
            continue;
        }
        if (!is_file($path)) continue;
        if (isset($expires[$f])) {
            if ($expires[$f] > 0 && $now >= $expires[$f]) {
                @unlink($path);
                unset($expires[$f]);
            }
        }
    }
    writeExpires($folder, $expires);
}

/**
 * Formatta il tempo rimanente in modo leggibile.
 */
function formatRemaining($expire_ts) {
    if ($expire_ts === 0) return '∞ nessuna scadenza';
    $rem = $expire_ts - time();
    if ($rem <= 0) return 'scaduto';
    if ($rem < 3600)  return 'scade tra ' . round($rem/60) . ' min';
    if ($rem < 86400) return 'scade tra ' . round($rem/3600) . ' h';
    return 'scade tra ' . round($rem/86400) . ' gg';
}

// ── Gestione password per-file ────────────────────────────────────

function passwordsFile($folder) { return $folder . '/.passwords.json'; }

function readPasswords($folder) {
    $f = passwordsFile($folder);
    if (!file_exists($f)) return [];
    $data = json_decode(file_get_contents($f), true);
    return is_array($data) ? $data : [];
}

function writePasswords($folder, $data) {
    file_put_contents(passwordsFile($folder), json_encode($data, JSON_PRETTY_PRINT));
}

/** Imposta (o rimuove) la password per un file. Salva hash bcrypt. */
function setFilePassword($folder, $filename, $plaintext) {
    $data = readPasswords($folder);
    // Rimuovi voci di file non più esistenti
    foreach (array_keys($data) as $k) {
        if (!file_exists($folder . '/' . $k)) unset($data[$k]);
    }
    if ($plaintext === '' || $plaintext === null) {
        unset($data[$filename]);
    } else {
        $data[$filename] = password_hash($plaintext, PASSWORD_BCRYPT);
    }
    writePasswords($folder, $data);
}

/** Verifica se il file è protetto da password. */
function fileHasPassword($folder, $filename) {
    $data = readPasswords($folder);
    return isset($data[$filename]);
}

/** Verifica la password inserita. */
function verifyFilePassword($folder, $filename, $plaintext) {
    $data = readPasswords($folder);
    if (!isset($data[$filename])) return true; // nessuna password = accesso libero
    return password_verify($plaintext, $data[$filename]);
}



// ================================================================
// 1. AUTENTICAZIONE
// ================================================================

$id_folder   = md5(__DIR__);
$cookie_name = "auth_" . $id_folder;
$errore_login = '';

if ($LOGIN_REQUIRED && isset($_POST['password'])) {
    if ($_POST['password'] === $PASSWORD) {
        $session_token = bin2hex(random_bytes(16));
        $_SESSION[$cookie_name] = $session_token;
        setcookie($cookie_name, $session_token, time() + 3600, "/", "", false, true);
        session_regenerate_id(true);
        $redirect_url = 'index.php' . (isset($_GET['folder']) ? '?folder=' . urlencode($_GET['folder']) : '');
        header('Location: ' . $redirect_url);
        exit;
    } else {
        $errore_login = 'Password errata. Riprova.';
    }
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    setcookie($cookie_name, "", time() - 3600, "/");
    header('Location: index.php');
    exit;
}

$session_token = $_SESSION[$cookie_name] ?? null;
$cookie_token  = $_COOKIE[$cookie_name] ?? null;

$is_authenticated = (!$LOGIN_REQUIRED)
    || ($session_token !== null && $session_token !== true && $cookie_token === $session_token);

// ── Auto-pulizia cartella pubblica ─────────────────────────────────
// Risolvi il nome reale della cartella pubblica (Linux è case-sensitive)
if (!is_dir($PUBLIC_FOLDER) && is_dir('.')) {
    foreach (scandir('.') as $_item) {
        if (is_dir($_item) && strtolower($_item) === strtolower($PUBLIC_FOLDER)) {
            $PUBLIC_FOLDER = $_item; // usa il nome esatto trovato sul disco
            break;
        }
    }
}
cleanPublicFolder($PUBLIC_FOLDER, $PUBLIC_FOLDER_EXPIRE_HOURS);

// ================================================================
// 1a. ROTTA PUBBLICA (?$PUBLIC_FOLDER) - nessun login richiesto
// ================================================================

// ================================================================
// 1k. VERIFICA PASSWORD FILE PUBBLICO (prima di ?stampa)
// ================================================================

if (isset($_POST['pub_password_check'])) {
    $pw_file  = basename(str_replace(['..', '\\', '/'], '', $_POST['pub_filename'] ?? ''));
    $pw_plain = $_POST['pub_password'] ?? '';
    $pw_sub   = str_replace(['..','\\','/'], '', trim($_POST['pub_sub'] ?? ''));
    $pw_folder= $PUBLIC_FOLDER . ($pw_sub !== '' ? '/' . $pw_sub : '');
    $path     = $pw_folder . '/' . $pw_file;

    if ($pw_file && file_exists($path) && verifyFilePassword($pw_folder, $pw_file, $pw_plain)) {
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // ── PHP e HTML: include interno (evita il blocco .htaccess sui file diretti) ──
        if (in_array($ext, ['php', 'html'])) {
            include $path;
            exit;
        }

        $mime_map = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'doc'  => 'application/msword', 'xls'  => 'application/vnd.ms-excel',
            'html' => 'text/html; charset=UTF-8', 'mid' => 'audio/midi',
            'zip'  => 'application/zip', 'json' => 'application/json',
        ];
        $mime        = $mime_map[$ext] ?? 'application/octet-stream';
        $disposition = in_array($ext, ['pdf','html','jpg','jpeg','png','mid']) ? 'inline' : 'attachment';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . $pw_file . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=0, no-store');
        readfile($path);
        exit;
    } else {
        $redir = 'index.php?' . $PUBLIC_FOLDER . ($pw_sub !== '' ? '&sub=' . urlencode($pw_sub) : '') . '&pw_error=' . urlencode($pw_file);
        header('Location: ' . $redir);
        exit;
    }
}

if (isset($_GET[$PUBLIC_FOLDER])) {
    // ── Sottocartella corrente (sanificata, solo un livello sotto $PUBLIC_FOLDER) ──
    $pub_sub = '';
    if (isset($_GET['sub'])) {
        $raw_sub = str_replace(['..','\\','/'], '', trim($_GET['sub']));
        if ($raw_sub !== '' && is_dir($PUBLIC_FOLDER . '/' . $raw_sub)) {
            $pub_sub = $raw_sub;
        }
    }
    $pub_dir       = $PUBLIC_FOLDER . ($pub_sub !== '' ? '/' . $pub_sub : '');
    $pub_files     = [];
    $pub_subfolders= [];
    $pub_expires   = is_dir($pub_dir) ? readExpires($pub_dir)   : [];
    $pub_passwords = is_dir($pub_dir) ? readPasswords($pub_dir) : [];
    $pw_error_file = isset($_GET['pw_error'])  ? basename(str_replace(['..','\\','/'],'',$_GET['pw_error']))  : '';
    $pw_prompt_file= isset($_GET['pw_prompt']) ? basename(str_replace(['..','\\','/'],'',$_GET['pw_prompt'])) : '';

    if (is_dir($pub_dir)) {
        foreach (scandir($pub_dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $full = $pub_dir . '/' . $item;
            // Cartelle (solo se siamo nella root di stampa, un livello)
            if (is_dir($full) && $pub_sub === '') {
                $pub_subfolders[] = $item;
                continue;
            }
            if (!is_file($full)) continue;
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if ($item === '.expires.json' || $item === '.passwords.json') continue;
            if (in_array($ext, $ALLOWED_EXTENSIONS)) {
                $exp_ts = isset($pub_expires[$item]) ? (int)$pub_expires[$item] : 0;
                if ($exp_ts > 0 && time() >= $exp_ts) continue;
                $pub_files[] = ['name'=>$item, 'ext'=>$ext, 'mtime'=>filemtime($full),
                                'expire'=>$exp_ts, 'locked'=>isset($pub_passwords[$item])];
            }
        }
    }
    sort($pub_subfolders);
    usort($pub_files, fn($a,$b) => $b['mtime'] - $a['mtime']);

    // URL base per link e form nella pagina pubblica
    $_scheme  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $_baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    $pub_base_url = $_scheme . '://' . $_SERVER['HTTP_HOST'] . $_baseDir;
    ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📁 <?= $pub_sub ? htmlspecialchars($pub_sub) . ' – ' : '' ?><?= htmlspecialchars(strtoupper($PUBLIC_FOLDER)) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:linear-gradient(135deg,#6a11cb 0%,#2575fc 100%); min-height:100vh; display:flex; justify-content:center; align-items:center; padding:10px; }
        .container { background:rgba(255,255,255,0.97); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); padding:24px 18px; max-width:600px; width:100%; text-align:center; }
        h1 { color:#2c3e50; font-size:20px; margin-bottom:4px; }
        .subtitle { font-size:13px; color:#6c757d; margin-bottom:12px; }
        .breadcrumb { text-align:left; font-size:13px; background:#f8f9fa; border-radius:6px; padding:8px 12px; margin-bottom:14px; }
        .breadcrumb a { color:#6a11cb; font-weight:600; text-decoration:none; }
        .breadcrumb a:hover { text-decoration:underline; }
        .breadcrumb span { color:#6c757d; margin:0 4px; }
        .info-box { background:#fff8e1; border-left:4px solid #ffc107; padding:10px 14px; margin-bottom:16px; border-radius:6px; text-align:left; font-size:13px; color:#555; }
        ul { list-style:none; padding:0; }
        li { margin-bottom:8px; }
        a.pub-link { display:block; padding:13px 16px; background:#f8f9fa; border:1px solid #ddd; border-radius:8px; text-decoration:none; color:#2c3e50; font-weight:600; font-size:15px; text-align:left; transition:all .25s; cursor:pointer; }
        a.pub-link:hover, button.pub-link:hover { background:#6a11cb; color:#fff; border-color:#6a11cb; transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,.1); }
        button.pub-link { width:100%; border:1px solid #ddd; border-radius:8px; background:#f8f9fa; padding:13px 16px; font-weight:600; font-size:15px; text-align:left; transition:all .25s; cursor:pointer; color:#2c3e50; }
        button.pub-link.locked { background:#fff8e1; border-color:#ffc107; }
        button.pub-link.locked:hover { background:#6a11cb; border-color:#6a11cb; color:#fff; }
        a.pub-link.folder { background:#e7f3ff; border-color:#2575fc; color:#2575fc; }
        a.pub-link.folder:hover { background:#6a11cb; border-color:#6a11cb; color:#fff; }
        .meta { font-size:11px; color:#999; display:block; margin-top:2px; font-weight:400; }
        .empty { color:#999; font-style:italic; padding:20px 0; }
        .lock-icon { color:#ffc107; margin-right:6px; }
        button.pub-link:hover .lock-icon, button.pub-link:hover .icon { color:#fff !important; }
        a.pub-link:hover .icon { color:#fff !important; }
        .icon { margin-right:8px; }
        .icon.pdf { color:#cc0000; } .icon.img { color:#3b5998; } .icon.doc { color:#28a745; } .icon.xls { color:#1d6f42; } .icon.folder { color:#2575fc; }
        footer { margin-top:18px; font-size:12px; color:#aaa; }
        /* Password modal */
        .pw-modal-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.55); z-index:9999; justify-content:center; align-items:center; }
        .pw-modal-overlay.active { display:flex; }
        .pw-modal-box { background:#fff; border-radius:14px; padding:28px 22px; max-width:360px; width:92%; box-shadow:0 12px 40px rgba(0,0,0,.3); text-align:center; }
        .pw-modal-box h3 { margin:0 0 6px; font-size:18px; color:#2c3e50; }
        .pw-modal-box p  { font-size:13px; color:#6c757d; margin:0 0 16px; }
        .pw-input-wrap { position:relative; margin-bottom:14px; }
        .pw-input-wrap input { width:100%; padding:11px 42px 11px 14px; border:2px solid #6a11cb; border-radius:8px; font-size:16px; box-sizing:border-box; outline:none; }
        .pw-input-wrap input:focus { border-color:#2575fc; }
        .pw-eye { position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:#6c757d; font-size:15px; }
        .pw-btn { width:100%; padding:11px; background:#6a11cb; color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; transition:background .3s; }
        .pw-btn:hover { background:#2575fc; }
        .pw-error { background:#f8d7da; color:#721c24; border-radius:6px; padding:8px 12px; font-size:13px; margin-bottom:12px; }
        .pw-cancel { margin-top:10px; background:none; border:none; color:#6c757d; font-size:13px; cursor:pointer; text-decoration:underline; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-folder-open" style="color:#6a11cb;margin-right:6px;"></i>
        <?= $pub_sub ? htmlspecialchars($pub_sub) : htmlspecialchars(strtoupper($PUBLIC_FOLDER)) ?>
    </h1>
    <p class="subtitle">Accesso pubblico · i file protetti richiedono una password 🔐</p>

    <?php if ($pub_sub !== ''): ?>
    <div class="breadcrumb">
        <a href="index.php?<?= $PUBLIC_FOLDER ?>"><i class="fas fa-folder-open"></i> <?= htmlspecialchars(strtoupper($PUBLIC_FOLDER)) ?></a>
        <span>/</span>
        <strong><?= htmlspecialchars($pub_sub) ?></strong>
    </div>
    <?php endif; ?>

    <?php if (empty($pub_files) && empty($pub_subfolders)): ?>
        <p class="empty"><i class="fas fa-inbox"></i><br>Nessun file disponibile al momento.</p>
    <?php else: ?>
    <ul>
        <?php foreach ($pub_subfolders as $sf):
            $sf_label = htmlspecialchars(ucfirst(str_replace(['_','-'],' ',$sf)));
            $sf_url   = 'index.php?' . $PUBLIC_FOLDER . '&sub=' . urlencode($sf);
            // Conta file validi nella sottocartella
            $sf_dir  = $PUBLIC_FOLDER . '/' . $sf;
            $sf_exp  = readExpires($sf_dir);
            $sf_count = 0;
            foreach (scandir($sf_dir) as $sfi) {
                if ($sfi === '.' || $sfi === '..') continue;
                $sfp = $sf_dir . '/' . $sfi;
                if (!is_file($sfp)) continue;
                $sfext = strtolower(pathinfo($sfi, PATHINFO_EXTENSION));
                if ($sfi === '.expires.json' || $sfi === '.passwords.json') continue;
                if (!in_array($sfext, $ALLOWED_EXTENSIONS)) continue;
                $sf_exp_ts = isset($sf_exp[$sfi]) ? (int)$sf_exp[$sfi] : 0;
                if ($sf_exp_ts > 0 && time() >= $sf_exp_ts) continue;
                $sf_count++;
            }
        ?>
        <li>
            <a href="<?= htmlspecialchars($sf_url) ?>" class="pub-link folder">
                <span class="icon folder"><i class="fas fa-folder-open"></i></span><?= $sf_label ?>
                <span class="meta"><?= $sf_count ?> file disponibil<?= $sf_count === 1 ? 'e' : 'i' ?></span>
            </a>
        </li>
        <?php endforeach; ?>

        <?php foreach ($pub_files as $pf):
            $fname  = $pf['name'];
            $ext    = $pf['ext'];
            $locked = !empty($pf['locked']);
            $link   = 'index.php?file=' . urlencode($pub_dir . '/' . $fname);
            $disp   = htmlspecialchars(pathinfo($fname, PATHINFO_FILENAME));
            $age    = round((time() - $pf['mtime']) / 60);
            $age_label = $age < 60 ? $age . ' min fa' : round($age/60) . ' ore fa';
            $rem_label = formatRemaining($pf['expire']);
            $rem_color = ($pf['expire'] > 0 && ($pf['expire'] - time()) < 3600) ? '#dc3545' : '#999';
            $icon = 'fa-file'; $iclass = '';
            if ($ext === 'pdf')                          { $icon='fa-file-pdf';   $iclass='pdf'; }
            elseif (in_array($ext,['jpg','png','jpeg'])) { $icon='fa-image';      $iclass='img'; }
            elseif (in_array($ext,['docx','doc']))       { $icon='fa-file-word';  $iclass='doc'; }
            elseif (in_array($ext,['xlsx','xls']))       { $icon='fa-file-excel'; $iclass='xls'; }
            $pw_err = ($pw_error_file === $fname);
        ?>
        <li>
            <?php if ($locked): ?>
            <button type="button" class="pub-link locked" onclick="openPwModal(<?= htmlspecialchars(json_encode($fname), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($disp), ENT_QUOTES) ?>)">
                <i class="fas fa-lock lock-icon"></i><span class="icon <?= $iclass ?>"><i class="fas <?= $icon ?>"></i></span><?= $disp ?>
                <span class="meta" style="color:<?= $rem_color ?>">🔐 Protetto da password · <i class="fas fa-clock"></i> <?= $rem_label ?></span>
            </button>
            <?php else: ?>
            <a href="<?= htmlspecialchars($link) ?>" class="pub-link" target="_blank" rel="noopener">
                <span class="icon <?= $iclass ?>"><i class="fas <?= $icon ?>"></i></span><?= $disp ?>
                <span class="meta" style="color:<?= $rem_color ?>"><?= $age_label ?> · <i class="fas fa-clock"></i> <?= $rem_label ?></span>
            </a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <footer><?= $CREDITS ?></footer>
</div>

<!-- ── Modale password file ──────────────────────────────────── -->
<div class="pw-modal-overlay" id="pwModal">
    <div class="pw-modal-box">
        <h3><i class="fas fa-lock" style="color:#6a11cb;margin-right:6px;"></i> File protetto</h3>
        <p id="pwModalDesc">Inserisci la password per accedere a questo file.</p>
        <form method="post" action="index.php?<?= $PUBLIC_FOLDER ?><?= $pub_sub ? '&sub=' . urlencode($pub_sub) : '' ?>" id="pwForm">
            <input type="hidden" name="pub_password_check" value="1">
            <input type="hidden" name="pub_sub" value="<?= htmlspecialchars($pub_sub) ?>">
            <input type="hidden" name="pub_filename" id="pwFilename">
            <div class="pw-input-wrap">
                <input type="password" name="pub_password" id="pwInput" placeholder="Password" autocomplete="off" required>
                <span class="pw-eye" onclick="togglePw()"><i id="pwEye" class="fas fa-eye"></i></span>
            </div>
            <button type="submit" class="pw-btn"><i class="fas fa-unlock-alt"></i> Accedi al file</button>
        </form>
        <button class="pw-cancel" onclick="closePwModal()">Annulla</button>
    </div>
</div>

<script>
function openPwModal(filename, dispname) {
    document.getElementById('pwFilename').value = filename;
    document.getElementById('pwModalDesc').textContent = '🔐 ' + dispname;
    document.getElementById('pwInput').value = '';
    document.getElementById('pwModal').classList.add('active');
    setTimeout(() => document.getElementById('pwInput').focus(), 80);
}
function closePwModal() {
    document.getElementById('pwModal').classList.remove('active');
}
function togglePw() {
    const i = document.getElementById('pwInput');
    const e = document.getElementById('pwEye');
    i.type = i.type === 'password' ? 'text' : 'password';
    e.classList.toggle('fa-eye'); e.classList.toggle('fa-eye-slash');
}
document.getElementById('pwModal').addEventListener('click', function(e) {
    if (e.target === this) closePwModal();
});
document.getElementById('pwInput').addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePwModal();
});
<?php if ($pw_error_file || $pw_prompt_file): ?>
window.addEventListener('DOMContentLoaded', () => {
    const target = <?= json_encode($pw_error_file ?: $pw_prompt_file) ?>;
    const disp   = target.replace(/\.[^.]+$/, '');
    openPwModal(target, disp);
    <?php if ($pw_error_file): ?>
    const errDiv = document.createElement('div');
    errDiv.className = 'pw-error';
    errDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Password errata. Riprova.';
    const form = document.getElementById('pwForm');
    form.insertBefore(errDiv, form.firstChild);
    <?php endif; ?>
});
<?php endif; ?>
</script>
</body>
</html>
<?php exit; }




// ================================================================
// 1b. GESTIONE UPLOAD
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_FILES['upload_file'])) {

    // Usa la cartella corrente (passata via POST) se valida, altrimenti $UPLOAD_FOLDER
    $upload_target = isset($_POST['upload_folder'])
        ? safeFolderParam($_POST['upload_folder'])
        : $UPLOAD_FOLDER;
    // Sicurezza: la cartella deve esistere già (non creiamo cartelle arbitrarie)
    if ($upload_target !== '' && !is_dir($upload_target)) {
        $upload_target = $UPLOAD_FOLDER;
    }
    $upload_prefix = folderPrefix($upload_target);

    // Crea la sottocartella se non esiste (solo per $UPLOAD_FOLDER di default)
    if ($upload_prefix !== '' && !is_dir(rtrim($upload_target, '/'))) {
        mkdir(rtrim($upload_target, '/'), 0755, true);
    }

    $file     = $_FILES['upload_file'];
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $max_size = $UPLOAD_MAX_SIZE_MB * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_msg = ['error', '❌ Errore durante il caricamento.'];
    } elseif (!in_array($ext, $UPLOAD_ALLOWED_EXT)) {
        $upload_msg = ['error', '❌ Estensione non consentita: .' . htmlspecialchars($ext)];
    } elseif ($file['size'] > $max_size) {
        $upload_msg = ['error', '❌ File troppo grande (max ' . $UPLOAD_MAX_SIZE_MB . ' MB).'];
    } else {
        // Nome sicuro: conserva solo caratteri alfanumerici, punti, trattini, underscore
        $safe_name = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($file['name']));

        // Evita sovrascrittura di file di sistema
        if (in_array($safe_name, $EXCLUDED_FILES)) {
            $safe_name = time() . '_' . $safe_name;
        }

        $dest = $upload_prefix . $safe_name;

        // Evita sovrascrittura
        if (file_exists($dest)) {
            $safe_name = time() . '_' . $safe_name;
            $dest      = $upload_prefix . $safe_name;
        }

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $upload_msg = ['success', '✅ File caricato: <strong>' . htmlspecialchars($safe_name) . '</strong>'];
            // Scadenza + password (cartella pubblica o sottocartella)
            $upload_is_pub = ($upload_target === $PUBLIC_FOLDER || strpos($upload_target, $PUBLIC_FOLDER . '/') === 0);
            if ($upload_is_pub) {
                $exp_dt = trim($_POST['expire_datetime'] ?? '');
                if ($exp_dt !== '') {
                    $ts = strtotime($exp_dt);
                    if ($ts === false || $ts <= time()) $ts = 0;
                } else {
                    $ts = 0;
                }
                setFileExpiry($upload_target, $safe_name, $ts);
                $upload_msg[1] .= $ts > 0
                    ? ' · scade: <strong>' . date('d/m/Y H:i', $ts) . '</strong>'
                    : ' · <strong>nessuna scadenza</strong>';
                $pw_upload = $_POST['file_password'] ?? '';
                if ($pw_upload !== '') {
                    setFilePassword($upload_target, $safe_name, $pw_upload);
                    $upload_msg[1] .= ' · <strong>🔐 protetto da password</strong>';
                }
            }
        } else {
            $upload_msg = ['error', '❌ Impossibile salvare il file. Controlla i permessi della cartella.'];
        }
    }

    $_SESSION['upload_msg'] = $upload_msg;
    header('Location: index.php' . (isset($_GET['folder']) ? '?folder=' . urlencode($_GET['folder']) : ''));
    exit;
}

// Recupera messaggio da sessione (dopo redirect)
$upload_msg = null;
if (isset($_SESSION['upload_msg'])) {
    $upload_msg = $_SESSION['upload_msg'];
    unset($_SESSION['upload_msg']);
}


// ================================================================
// 1c. GESTIONE ELIMINA FILE
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $del_folder = safeFolderParam($_POST['folder'] ?? $UPLOAD_FOLDER);
    $del_file   = basename(str_replace(['..', '\\', '/'], '', $_POST['filename'] ?? ''));
    $del_path   = folderPrefix($del_folder) . $del_file;

    if ($del_file && file_exists($del_path) && is_file($del_path)) {
        if (unlink($del_path)) {
            $_SESSION['upload_msg'] = ['success', '🗑️ File eliminato: <strong>' . htmlspecialchars($del_file) . '</strong>'];
        } else {
            $_SESSION['upload_msg'] = ['error', '❌ Impossibile eliminare il file.'];
        }
    } else {
        $_SESSION['upload_msg'] = ['error', '❌ File non trovato.'];
    }
    header('Location: index.php' . ($del_folder !== '' ? '?folder=' . urlencode($del_folder) : ''));
    exit;
}


// ================================================================
// 1d. GESTIONE RINOMINA FILE
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_POST['action']) && $_POST['action'] === 'rename') {
    $ren_folder = safeFolderParam($_POST['folder'] ?? $UPLOAD_FOLDER);
    $old_file   = basename(str_replace(['..', '\\', '/'], '', $_POST['filename'] ?? ''));
    $new_name   = isset($_POST['new_filename']) ? trim($_POST['new_filename']) : '';

    $old_path = folderPrefix($ren_folder) . $old_file;
    $old_ext  = strtolower(pathinfo($old_file, PATHINFO_EXTENSION));

    // Assicura che il nuovo nome abbia la stessa estensione
    $new_ext = strtolower(pathinfo($new_name, PATHINFO_EXTENSION));
    if ($new_ext !== $old_ext) {
        $new_name = pathinfo($new_name, PATHINFO_FILENAME) . '.' . $old_ext;
    }

    $safe_new = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $new_name);
    $new_path = folderPrefix($ren_folder) . $safe_new;

    if ($old_file && $safe_new && file_exists($old_path) && is_file($old_path)) {
        if (file_exists($new_path)) {
            $_SESSION['upload_msg'] = ['error', '❌ Esiste già un file con questo nome.'];
        } elseif (rename($old_path, $new_path)) {
            $_SESSION['upload_msg'] = ['success', '✏️ File rinominato in: <strong>' . htmlspecialchars($safe_new) . '</strong>'];
        } else {
            $_SESSION['upload_msg'] = ['error', '❌ Impossibile rinominare il file.'];
        }
    } else {
        $_SESSION['upload_msg'] = ['error', '❌ File non trovato o nome non valido.'];
    }
    header('Location: index.php' . ($ren_folder !== '' ? '?folder=' . urlencode($ren_folder) : ''));
    exit;
}


// ================================================================
// 1e. GESTIONE CREA CARTELLA
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_POST['action']) && $_POST['action'] === 'mkdir') {
    $parent_folder = safeFolderParam($_POST['folder'] ?? '');
    $folder_name   = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($_POST['folder_name'] ?? ''));
    $new_dir       = ($parent_folder !== '' ? $parent_folder . '/' : '') . $folder_name;

    if ($folder_name === '') {
        $_SESSION['upload_msg'] = ['error', '❌ Nome cartella non valido.'];
    } elseif (in_array($folder_name, $EXCLUDED_FOLDERS)) {
        $_SESSION['upload_msg'] = ['error', '❌ Nome cartella riservato.'];
    } elseif (is_dir($new_dir)) {
        $_SESSION['upload_msg'] = ['error', '❌ Cartella già esistente.'];
    } elseif (mkdir($new_dir, 0755, true)) {
        $_SESSION['upload_msg'] = ['success', '📁 Cartella creata: <strong>' . htmlspecialchars($folder_name) . '</strong>'];
    } else {
        $_SESSION['upload_msg'] = ['error', '❌ Impossibile creare la cartella. Controlla i permessi.'];
    }
    header('Location: index.php' . ($parent_folder !== '' ? '?folder=' . urlencode($parent_folder) : ''));
    exit;
}


// ================================================================
// 1f. GESTIONE RINOMINA CARTELLA
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_POST['action']) && $_POST['action'] === 'rename_folder') {
    $parent   = safeFolderParam($_POST['folder'] ?? '');
    $old_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($_POST['folder_name']     ?? ''));
    $new_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($_POST['new_folder_name'] ?? ''));
    $prefix   = $parent !== '' ? $parent . '/' : '';
    $old_path = $prefix . $old_name;
    $new_path = $prefix . $new_name;

    if (!$old_name || !$new_name) {
        $_SESSION['upload_msg'] = ['error', '❌ Nome cartella non valido.'];
    } elseif (!is_dir($old_path)) {
        $_SESSION['upload_msg'] = ['error', '❌ Cartella non trovata.'];
    } elseif (is_dir($new_path)) {
        $_SESSION['upload_msg'] = ['error', '❌ Esiste già una cartella con questo nome.'];
    } elseif (rename($old_path, $new_path)) {
        $_SESSION['upload_msg'] = ['success', '✏️ Cartella rinominata in: <strong>' . htmlspecialchars($new_name) . '</strong>'];
    } else {
        $_SESSION['upload_msg'] = ['error', '❌ Impossibile rinominare la cartella.'];
    }
    header('Location: index.php' . ($parent !== '' ? '?folder=' . urlencode($parent) : ''));
    exit;
}


// ================================================================
// 1g. GESTIONE ELIMINA CARTELLA
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_POST['action']) && $_POST['action'] === 'delete_folder') {
    $parent   = safeFolderParam($_POST['folder'] ?? '');
    $dir_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($_POST['folder_name'] ?? ''));
    $prefix   = $parent !== '' ? $parent . '/' : '';
    $dir_path = $prefix . $dir_name;

    if (!$dir_name || !is_dir($dir_path)) {
        $_SESSION['upload_msg'] = ['error', '❌ Cartella non trovata.'];
    } elseif (count(scandir($dir_path)) > 2) {
        $_SESSION['upload_msg'] = ['error', '❌ La cartella non è vuota. Svuotala prima di eliminarla.'];
    } elseif (rmdir($dir_path)) {
        $_SESSION['upload_msg'] = ['success', '🗑️ Cartella eliminata: <strong>' . htmlspecialchars($dir_name) . '</strong>'];
    } else {
        $_SESSION['upload_msg'] = ['error', '❌ Impossibile eliminare la cartella.'];
    }
    header('Location: index.php' . ($parent !== '' ? '?folder=' . urlencode($parent) : ''));
    exit;
}


// ================================================================
// 1h. GESTIONE SPOSTA FILE (modale)
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_POST['action']) && $_POST['action'] === 'move') {
    $from_folder = safeFolderParam($_POST['folder']    ?? '');
    $to_folder   = safeFolderParam($_POST['to_folder'] ?? '');
    $filename    = basename(str_replace(['..', '\\', '/'], '', $_POST['filename'] ?? ''));

    $from_path   = folderPrefix($from_folder) . $filename;
    $to_path     = folderPrefix($to_folder)   . $filename;
    $to_dir_real = $to_folder !== '' ? $to_folder : '.';

    if (!$filename || !file_exists($from_path) || !is_file($from_path)) {
        $_SESSION['upload_msg'] = ['error', '❌ File sorgente non trovato.'];
    } elseif (!is_dir($to_dir_real)) {
        $_SESSION['upload_msg'] = ['error', '❌ Cartella di destinazione non valida.'];
    } elseif (file_exists($to_path)) {
        $_SESSION['upload_msg'] = ['error', '❌ Esiste già un file con questo nome nella destinazione.'];
    } elseif (rename($from_path, $to_path)) {
        $msg = '📦 File spostato in <strong>' . htmlspecialchars($to_folder ?: 'cartella principale') . '</strong>: <strong>' . htmlspecialchars($filename) . '</strong>';
        // Se il file arriva in stampa o una sua sottocartella: touch + scadenza default
        $to_is_pub = ($to_folder === $PUBLIC_FOLDER || strpos($to_folder, $PUBLIC_FOLDER . '/') === 0);
        if ($to_is_pub) {
            touch($to_path);
            $default_ts = time() + $PUBLIC_FOLDER_EXPIRE_HOURS * 3600;
            setFileExpiry($to_folder, $filename, $default_ts > time() ? $default_ts : 0);
            $msg .= ' · <strong>⏱️ scadenza: ' . date('d/m/Y H:i', $default_ts) . '</strong> (modificabile con ⏱️)';
        }
        // Se il file ESCE da stampa o una sua sottocartella: rimuovi scadenza e password
        $from_is_pub = ($from_folder === $PUBLIC_FOLDER || strpos($from_folder, $PUBLIC_FOLDER . '/') === 0);
        if ($from_is_pub) {
            setFileExpiry($from_folder, $filename, 0);
            setFilePassword($from_folder, $filename, '');
        }
        $_SESSION['upload_msg'] = ['success', $msg];
    } else {
        $_SESSION['upload_msg'] = ['error', '❌ Impossibile spostare il file.'];
    }
    header('Location: index.php' . ($from_folder !== '' ? '?folder=' . urlencode($from_folder) : ''));
    exit;
}


// ================================================================
// 1h2. GESTIONE COPIA FILE
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_POST['action']) && $_POST['action'] === 'copy_file') {
    $from_folder = safeFolderParam($_POST['folder']    ?? '');
    $to_folder   = safeFolderParam($_POST['to_folder'] ?? '');
    $filename    = basename(str_replace(['..', '\\', '/'], '', $_POST['filename'] ?? ''));

    $from_path   = folderPrefix($from_folder) . $filename;
    $to_path     = folderPrefix($to_folder)   . $filename;
    $to_dir_real = $to_folder !== '' ? $to_folder : '.';

    // Evita sovrascrittura: aggiunge timestamp se il file esiste già
    if (file_exists($to_path)) {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        $new_name = $base . '_copia_' . date('Ymd_His') . ($ext ? '.' . $ext : '');
        $to_path  = folderPrefix($to_folder) . $new_name;
    } else {
        $new_name = $filename;
    }

    if (!$filename || !file_exists($from_path) || !is_file($from_path)) {
        $_SESSION['upload_msg'] = ['error', '❌ File sorgente non trovato.'];
    } elseif (!is_dir($to_dir_real)) {
        $_SESSION['upload_msg'] = ['error', '❌ Cartella di destinazione non valida.'];
    } elseif (copy($from_path, $to_path)) {
        $msg = '📋 Copia di <strong>' . htmlspecialchars($filename) . '</strong> creata in <strong>' . htmlspecialchars($to_folder ?: 'cartella principale') . '</strong>';
        if ($new_name !== $filename) {
            $msg .= ' come <strong>' . htmlspecialchars($new_name) . '</strong>';
        }
        // Se la copia va in stampa o una sua sottocartella: touch + scadenza default
        $copy_to_is_pub = ($to_folder === $PUBLIC_FOLDER || strpos($to_folder, $PUBLIC_FOLDER . '/') === 0);
        if ($copy_to_is_pub) {
            touch($to_path);
            $default_ts = time() + $PUBLIC_FOLDER_EXPIRE_HOURS * 3600;
            setFileExpiry($to_folder, $new_name, $default_ts);
            $msg .= ' · <strong>⏱️ scade: ' . date('d/m/Y H:i', $default_ts) . '</strong> (modificabile con ⏱️)';
        }
        $_SESSION['upload_msg'] = ['success', $msg];
    } else {
        $_SESSION['upload_msg'] = ['error', '❌ Impossibile copiare il file.'];
    }
    header('Location: index.php' . ($from_folder !== '' ? '?folder=' . urlencode($from_folder) : ''));
    exit;
}


// ================================================================
// 1i. GESTIONE SCADENZA PER-FILE (cartella pubblica)
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_POST['action']) && $_POST['action'] === 'set_expiry') {
    $exp_folder   = safeFolderParam($_POST['folder'] ?? '');
    $exp_filename = basename(str_replace(['..', '\\', '/'], '', $_POST['filename'] ?? ''));
    $exp_dt       = trim($_POST['expire_datetime'] ?? '');

    if ($exp_filename && ($exp_folder === $PUBLIC_FOLDER || strpos($exp_folder, $PUBLIC_FOLDER . '/') === 0)) {
        if ($exp_dt !== '') {
            $ts = strtotime($exp_dt);
            if ($ts === false || $ts <= time()) $ts = 0;
        } else {
            $ts = 0;
        }
        setFileExpiry($exp_folder, $exp_filename, $ts);   // ← usa $exp_folder
        $label = $ts > 0 ? date('d/m/Y H:i', $ts) : '∞ nessuna scadenza';
        $_SESSION['upload_msg'] = ['success', '⏱️ Scadenza aggiornata per <strong>' . htmlspecialchars($exp_filename) . '</strong>: <strong>' . $label . '</strong>'];
    } else {
        $_SESSION['upload_msg'] = ['error', '❌ Impossibile aggiornare la scadenza.'];
    }
    header('Location: index.php?folder=' . urlencode($exp_folder));
    exit;
}


// ================================================================
// 1j. GESTIONE PASSWORD PER-FILE (cartella pubblica)
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_POST['action']) && $_POST['action'] === 'set_password') {
    $pw_folder   = safeFolderParam($_POST['folder'] ?? '');
    $pw_filename = basename(str_replace(['..', '\\', '/'], '', $_POST['filename'] ?? ''));
    $pw_plain    = $_POST['file_password'] ?? '';

    if ($pw_filename && ($pw_folder === $PUBLIC_FOLDER || strpos($pw_folder, $PUBLIC_FOLDER . '/') === 0)) {
        setFilePassword($pw_folder, $pw_filename, $pw_plain);   // ← usa $pw_folder
        if ($pw_plain === '') {
            $_SESSION['upload_msg'] = ['success', '🔓 Password rimossa da <strong>' . htmlspecialchars($pw_filename) . '</strong>'];
        } else {
            $_SESSION['upload_msg'] = ['success', '🔐 Password impostata per <strong>' . htmlspecialchars($pw_filename) . '</strong>'];
        }
    } else {
        $_SESSION['upload_msg'] = ['error', '❌ Impossibile aggiornare la password.'];
    }
    header('Location: index.php?folder=' . urlencode($pw_folder));
    exit;
}


// ================================================================
if (isset($_GET['file'])) {

    if ($LOGIN_REQUIRED && !$is_authenticated) {
        // Consenti l'accesso ai file nella cartella pubblica senza login
        $requested_check = ltrim(str_replace(['..', '\\'], '', $_GET['file']), '/');
        $is_public_file  = ($PUBLIC_FOLDER !== ''
            && strpos($requested_check, $PUBLIC_FOLDER . '/') === 0);
        if (!$is_public_file) {
            header('Location: index.php');
            exit;
        }
        // Determina cartella e nome file (supporta sottocartella)
        $rel_path    = substr($requested_check, strlen($PUBLIC_FOLDER) + 1); // es. "Sub/file.pdf" o "file.pdf"
        $check_fname = basename($rel_path);
        $check_sub   = (strpos($rel_path, '/') !== false) ? dirname($rel_path) : '';
        $check_dir   = $PUBLIC_FOLDER . ($check_sub !== '' ? '/' . $check_sub : '');
        if (fileHasPassword($check_dir, $check_fname)) {
            $redir = 'index.php?' . $PUBLIC_FOLDER . ($check_sub !== '' ? '&sub=' . urlencode($check_sub) : '') . '&pw_prompt=' . urlencode($check_fname);
            header('Location: ' . $redir);
            exit;
        }
    }

    $requested = ltrim(str_replace(['..', '\\'], '', $_GET['file']), '/');
    $file_path  = './' . $requested;

    $basename = basename($file_path);
    if (in_array($basename, array_merge($EXCLUDED_FILES, ['index.php', '.htaccess']))) {
        http_response_code(403);
        exit('<p style="font-family:sans-serif;padding:20px;">🚫 Accesso non consentito.</p>');
    }

    if (!file_exists($file_path) || !is_file($file_path)) {
        http_response_code(404);
        exit('<p style="font-family:sans-serif;padding:20px;">⚠️ File non trovato.</p>');
    }

    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    if (!in_array($ext, $ALLOWED_EXTENSIONS)) {
        http_response_code(403);
        exit('<p style="font-family:sans-serif;padding:20px;">🚫 Tipo di file non consentito.</p>');
    }

    if ($ext === 'php') {
        include $file_path;
        exit;
    }

    $mime_map = [
        'pdf'  => 'application/pdf',
        'html' => 'text/html; charset=UTF-8',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'doc'  => 'application/msword',
        'mid'  => 'audio/midi',
        'xls'  => 'application/vnd.ms-excel',
    ];
    $mime = $mime_map[$ext] ?? 'application/octet-stream';

    $disposition = in_array($ext, $INLINE_EXTENSIONS) ? 'inline' : 'attachment';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $disposition . '; filename="' . basename($file_path) . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=0, no-store');
    readfile($file_path);
    exit;
}


// ================================================================
// 3. FUNZIONI UTILITY
// ================================================================

/**
 * Ritorna tutte le cartelle dell'intero albero (ricorsivo), escluse quelle in $excluded.
 * Ogni elemento: ['label' => 'percorso/leggibile', 'path' => 'percorso/relativo']
 */
function getAllFoldersRecursive($base_path, $base_label, $excluded, $max_depth = 6, $depth = 0) {
    $results = [];
    if ($depth > $max_depth || !is_dir($base_path)) return $results;
    $items = @scandir($base_path);
    if (!$items) return $results;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $base_path . '/' . $item;
        if (!is_dir($full)) continue;
        if (in_array($item, $excluded)) continue;
        $rel_path = $base_label !== '' ? $base_label . '/' . $item : $item;
        $results[] = ['label' => $rel_path, 'path' => $rel_path];
        $sub = getAllFoldersRecursive($full, $rel_path, $excluded, $max_depth, $depth + 1);
        foreach ($sub as $s) $results[] = $s;
    }
    return $results;
}

function isReservedFile($filename, $prefixes) {
    foreach ($prefixes as $prefix) {
        if (strpos($filename, $prefix) === 0) return true;
    }
    return false;
}

function getDisplayName($filename, $prefixes) {
    $name = pathinfo($filename, PATHINFO_FILENAME);
    foreach ($prefixes as $prefix) {
        if (strpos($name, $prefix) === 0) {
            $name = substr($name, strlen($prefix));
            break;
        }
    }
    return ucfirst(str_replace(['_', '-'], ' ', $name));
}

function checkNewTabSuffix(&$name, $suffix) {
    if (!$suffix) return false;
    if (substr(strtoupper($name), -strlen($suffix)) === strtoupper($suffix)) {
        $name = substr($name, 0, -strlen($suffix));
        return true;
    }
    return false;
}


// ================================================================
// 4. SCHERMATA DI LOGIN
// ================================================================

if ($LOGIN_REQUIRED && !$is_authenticated) { ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accesso Risorse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:linear-gradient(135deg,#6a11cb 0%,#2575fc 100%); min-height:100vh; display:flex; justify-content:center; align-items:center; padding:10px; }
        .container { background:rgba(255,255,255,0.95); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); padding:30px 20px; max-width:400px; width:100%; text-align:center; }
        h1 { color:#2c3e50; font-size:24px; margin-bottom:20px; }
        .password-container { position:relative; }
        .password-container input { padding-right:40px !important; }
        .toggle-password { position:absolute; top:50%; right:12px; transform:translateY(-50%); cursor:pointer; color:#6c757d; }
        .input-group { margin-bottom:20px; }
        .input-group input { width:100%; padding:12px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; font-size:16px; }
        .input-group button { width:100%; padding:12px; background:#6a11cb; color:white; border:none; border-radius:6px; font-size:16px; cursor:pointer; transition:background .3s; }
        .input-group button:hover { background:#2575fc; }
        .error-message { color:#dc3545; margin-bottom:15px; font-weight:600; background:#fadbd8; padding:10px; border-radius:6px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= $LOGIN_TITLE ?></h1>
        <p style="color:#6c757d;margin-bottom:25px;font-size:14px;"><?= $LOGIN_DESCRIPTION ?></p>
        <?php if ($errore_login) echo '<p class="error-message"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($errore_login) . '</p>'; ?>
        <form method="POST">
            <div class="input-group">
                <div class="password-container">
                    <input type="password" name="password" id="passwordField" placeholder="Inserisci la password" required autofocus>
                    <span class="toggle-password" onclick="togglePsw()"><i id="eyeIcon" class="fas fa-eye"></i></span>
                </div>
            </div>
            <div class="input-group"><button type="submit"><i class="fas fa-sign-in-alt"></i> Accedi</button></div>
        </form>
    </div>
    <script>
    function togglePsw() {
        const f = document.getElementById('passwordField'), e = document.getElementById('eyeIcon');
        f.type = f.type === 'password' ? 'text' : 'password';
        e.classList.toggle('fa-eye'); e.classList.toggle('fa-eye-slash');
    }
    </script>
</body>
</html>
<?php exit; }


// ================================================================
// 5. INTERFACCIA PRINCIPALE
// ================================================================
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risorse (Sebastiano Basile)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; background:linear-gradient(135deg,#6a11cb 0%,#2575fc 100%); min-height:100vh; display:flex; justify-content:center; align-items:center; padding:10px; }
        .container { background:rgba(255,255,255,0.95); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.2); padding:20px 15px; max-width:700px; width:100%; text-align:center; }
        .title-group { color:#2c3e50; margin-bottom:15px; }
        .title-group h1 { font-size:20px; margin-bottom:3px; }
        .breadcrumb { text-align:left; margin-bottom:12px; padding:8px 12px; background:#f8f9fa; border-radius:6px; font-size:14px; word-wrap:break-word; }
        .breadcrumb a { color:#6a11cb; text-decoration:none; font-weight:600; }
        .breadcrumb a:hover { text-decoration:underline; }
        .breadcrumb span { color:#6c757d; margin:0 4px; }
        .breadcrumb .current { color:#2c3e50; font-weight:600; }
        ul { list-style:none; padding:0; }
        li { margin-bottom:8px; }
        a.resource-link { display:block; padding:12px 15px; background:#f8f9fa; border:1px solid #ddd; border-radius:8px; text-decoration:none; color:#2c3e50; font-weight:600; font-size:15px; transition:all .3s ease; text-align:left; }
        a.resource-link:hover { background:#6a11cb; color:white; border-color:#6a11cb; transform:translateY(-2px); box-shadow:0 4px 8px rgba(0,0,0,0.1); }
        a.folder { background:#e7f3ff; border-color:#2575fc; }
        a.reserved { background:#fff3cd; border-color:#ffc107; }
        .file-icon { margin-right:8px; font-size:16px; }
        .file-icon.folder { color:#2575fc; font-size:18px; }
        .file-icon.pdf { color:#cc0000; }
        .file-icon.jpg { color:#3b5998; }
        .file-icon.docx { color:#28a745; }
        .file-icon.xlsx { color:#1d6f42; }
        .file-icon.lock { color:#ffc107; }
        a.resource-link:hover .file-icon { color:white; }
        .info-box { background:#e7f3ff; border-left:4px solid #2575fc; padding:12px; margin-bottom:15px; border-radius:6px; text-align:left; font-size:13px; color:#2c3e50; }
        .logout-link { display:inline-block; padding:8px 15px; background:#dc3545; color:white; border-radius:6px; text-decoration:none; font-weight:500; font-size:14px; }
        .logout-link:hover { background:#c82333; }

        /* ── Upload ── */
        .upload-box { background:#f0fff4; border:2px dashed #28a745; border-radius:10px; padding:15px; margin-bottom:15px; text-align:left; }
        .upload-box h3 { color:#28a745; margin:0 0 10px; font-size:15px; }
        .upload-box input[type=file] { display:block; margin-bottom:10px; font-size:14px; width:100%; }
        .upload-box button { background:#28a745; color:white; border:none; padding:8px 18px; border-radius:6px; cursor:pointer; font-size:14px; font-weight:600; transition:background .3s; }
        .upload-box button:hover { background:#1e7e34; }
        .upload-msg-ok  { background:#d4edda; color:#155724; border:1px solid #c3e6cb; padding:10px 14px; border-radius:6px; margin-bottom:12px; font-size:14px; text-align:left; }
        .upload-msg-err { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:10px 14px; border-radius:6px; margin-bottom:12px; font-size:14px; text-align:left; }

        /* ── Toolbar admin ── */
        .admin-toolbar { display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap; align-items:center; }
        .btn-new-folder { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; background:#6a11cb; color:white; border:none; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; transition:background .3s; }
        .btn-new-folder:hover { background:#2575fc; }
        .toolbar-hint { font-size:12px; color:#6c757d; }

        /* ── Azioni file (rinomina / elimina) ── */
        .file-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
        .file-row a.resource-link { flex:1; margin-bottom:0; }
        .btn-action { display:inline-flex; align-items:center; justify-content:center; padding:8px 10px; border:none; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; transition:all .2s; flex-shrink:0; }
        .btn-rename { background:#fff3cd; color:#856404; border:1px solid #ffc107; }
        .btn-rename:hover { background:#ffc107; color:#fff; }
        .btn-delete { background:#f8d7da; color:#721c24; border:1px solid #dc3545; }
        .btn-delete:hover { background:#dc3545; color:#fff; }
        .btn-move { background:#e8f4fd; color:#0d6efd; border:1px solid #0d6efd; }
        .btn-move:hover { background:#0d6efd; color:#fff; }
        .btn-copy { background:#e8f8f0; color:#0a6640; border:1px solid #28a745; }
        .btn-copy:hover { background:#28a745; color:#fff; }
        .btn-public { background:#e8fff0; color:#1a7a3c; border:1px solid #28a745; }
        .btn-public:hover { background:#28a745; color:#fff; }
        .btn-expiry { background:#fff3cd; color:#7a5900; border:1px solid #ffc107; font-size:11px; padding:5px 8px; min-width:unset; }
        .btn-expiry:hover { background:#ffc107; color:#fff; }
        .btn-password { background:#fde8f5; color:#7a1a5c; border:1px solid #c060a0; font-size:11px; padding:5px 8px; min-width:unset; }
        .btn-password:hover { background:#c060a0; color:#fff; }
        .quick-btn { padding:5px 10px; border:1px solid #ffc107; border-radius:6px; background:#fff8e1; color:#7a5900; font-size:12px; font-weight:600; cursor:pointer; transition:all .2s; }
        .quick-btn:hover { background:#ffc107; color:#fff; }
        .quick-never { border-color:#6c757d; background:#f8f9fa; color:#6c757d; }
        .quick-never:hover { background:#6c757d; color:#fff; }
        .expiry-badge { display:inline-block; font-size:11px; padding:2px 7px; border-radius:10px; font-weight:600; margin-left:4px; }
        .expiry-badge.soon { background:#f8d7da; color:#721c24; }
        .expiry-badge.ok   { background:#d4edda; color:#155724; }
        .expiry-badge.never{ background:#e9ecef; color:#495057; }
        .expire-select { font-size:13px; padding:5px 8px; border-radius:6px; border:1px solid #ffc107; background:#fff8e1; color:#7a5900; margin-top:8px; }
        .expire-label { font-size:13px; color:#7a5900; font-weight:600; display:block; margin-top:8px; margin-bottom:3px; }
        .pub-folder-banner { background:#fff8e1; border:1px dashed #ffc107; border-radius:8px; padding:8px 12px; margin-bottom:10px; font-size:13px; color:#7a5900; text-align:left; }
        .pub-folder-banner a { color:#6a11cb; font-weight:600; text-decoration:none; }
        .pub-folder-banner a:hover { text-decoration:underline; }
        a.folder-public { background:#fff8e1; border-color:#ffc107; }

        /* ── Modale Sposta ── */
        .move-folder-list { list-style:none; padding:0; margin:0 0 14px; max-height:260px; overflow-y:auto; }
        .move-folder-list li { margin-bottom:6px; }
        .move-folder-list button { width:100%; text-align:left; padding:10px 14px; border:1px solid #dee2e6; border-radius:8px; background:#f8f9fa; cursor:pointer; font-size:14px; color:#2c3e50; display:flex; align-items:center; gap:8px; transition:all .15s; }
        .move-folder-list button:hover { background:#6a11cb; color:#fff; border-color:#6a11cb; }
        .move-folder-list .no-folders { color:#6c757d; font-size:13px; padding:8px 0; }

        /* ── Modali ── */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; justify-content:center; align-items:center; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:#fff; border-radius:12px; padding:24px 20px; max-width:380px; width:90%; box-shadow:0 10px 30px rgba(0,0,0,.3); }
        .modal-box h3 { margin:0 0 14px; font-size:16px; color:#2c3e50; }
        .modal-box input { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; font-size:15px; box-sizing:border-box; margin-bottom:14px; }
        .modal-actions { display:flex; gap:8px; justify-content:flex-end; }
        .modal-actions button { padding:8px 16px; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:14px; }
        .btn-cancel  { background:#e9ecef; color:#495057; }
        .btn-confirm { background:#6a11cb; color:#fff; }
        .btn-confirm:hover { background:#2575fc; }
    </style>
</head>
<body>
<div class="container">
    <div class="title-group"><h1><?= $MAIN_TITLE ?></h1></div>
    <div class="info-box">
        <strong>💡 Nota:</strong> <?= $MAIN_NOTE ?>
    </div>

    <?php if ($UPLOAD_ENABLED && $is_authenticated): ?>

        <?php if ($upload_msg): ?>
            <div class="<?= $upload_msg[0] === 'success' ? 'upload-msg-ok' : 'upload-msg-err' ?>">
                <?= $upload_msg[1] ?>
            </div>
        <?php endif; ?>

        <div class="upload-box">
            <?php
                // Leggiamo la cartella corrente da $_GET
                $upload_dest = isset($_GET['folder']) ? safeFolderParam($_GET['folder']) : '';
                // Se la cartella non esiste ricadiamo su UPLOAD_FOLDER
                if ($upload_dest !== '' && !is_dir($upload_dest)) $upload_dest = '';
                // Label da mostrare: cartella corrente, oppure "principale" se siamo in root
                $upload_dest_label = $upload_dest !== '' ? htmlspecialchars($upload_dest) : 'principale';
                // Cartella reale dove salvare: cartella corrente se definita, altrimenti UPLOAD_FOLDER
                $upload_dest_real  = $upload_dest !== '' ? $upload_dest : $UPLOAD_FOLDER;
            ?>
            <h3><i class="fas fa-upload"></i> Carica un file nella cartella <em><?= $upload_dest_label ?></em></h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="upload_folder" value="<?= htmlspecialchars($upload_dest_real) ?>">
                <input type="file" name="upload_file" required>
                <?php if ($upload_dest_real === $PUBLIC_FOLDER): ?>
                <label class="expire-label"><i class="fas fa-clock"></i> Scadenza file (giorno e ora esatta):</label>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <input type="datetime-local" name="expire_datetime" id="uploadExpireDt"
                           style="font-size:13px;padding:6px 10px;border:1px solid #ffc107;border-radius:6px;background:#fff8e1;color:#7a5900;flex:1;"
                           min="<?= date('Y-m-d\TH:i') ?>">
                    <button type="button" onclick="clearUploadExpiry()" title="Nessuna scadenza"
                            style="font-size:12px;padding:6px 10px;border:1px solid #ccc;border-radius:6px;background:#f8f9fa;color:#6c757d;cursor:pointer;white-space:nowrap;">
                        <i class="fas fa-infinity"></i> Mai
                    </button>
                </div>
                <small style="color:#888;display:block;margin-top:4px;">Lascia vuoto per nessuna scadenza automatica.</small>
                <label class="expire-label" style="margin-top:10px;"><i class="fas fa-lock"></i> Password file (opzionale):</label>
                <div style="display:flex;align-items:center;gap:8px;">
                    <div style="position:relative;flex:1;">
                        <input type="password" name="file_password" id="uploadPwInput"
                               placeholder="Lascia vuoto = accesso libero"
                               style="width:100%;padding:6px 36px 6px 10px;border:1px solid #dc3545;border-radius:6px;font-size:13px;background:#fff5f5;color:#2c3e50;box-sizing:border-box;">
                        <span onclick="toggleUploadPw()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;color:#6c757d;">
                            <i id="uploadPwEye" class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                <small style="color:#888;display:block;margin-top:4px;">Se impostata, l'utente dovrà inserirla per scaricare il file.</small>
                <?php endif; ?>
                <button type="submit" style="margin-top:10px;display:block;"><i class="fas fa-cloud-upload-alt"></i> Carica</button>
            </form>
            <small style="color:#6c757d;display:block;margin-top:8px;">Estensioni ammesse: <?= implode(', ', $UPLOAD_ALLOWED_EXT) ?> &nbsp;·&nbsp; Max <?= $UPLOAD_MAX_SIZE_MB ?> MB</small>
        </div>

    <?php endif; ?>

    <?php
    // ── Navigazione cartelle ───────────────────────────────────────
    $current_folder = isset($_GET['folder'])
        ? safeFolderParam($_GET['folder'])
        : '';
    $scan_path = $current_folder ? './' . $current_folder : '.';
    if (!is_dir($scan_path)) { $current_folder = ''; $scan_path = '.'; }

    if ($current_folder) {
        $parts = explode('/', $current_folder);
        echo '<div class="breadcrumb"><a href="?"><i class="fas fa-home"></i> Home</a>';
        $cum = '';
        foreach ($parts as $p) {
            $cum .= ($cum ? '/' : '') . $p;
            $label = htmlspecialchars(ucfirst(str_replace(['_','-'],' ',$p)));
            if ($cum === $current_folder) echo '<span>/</span><span class="current">' . $label . '</span>';
            else echo '<span>/</span><a href="?folder=' . urlencode($cum) . '">' . $label . '</a>';
        }
        echo '</div>';
    }

    // ── Toolbar admin ──────────────────────────────────────────────
    if ($UPLOAD_ENABLED && $is_authenticated) {
        echo '<div class="admin-toolbar">';
        echo '<button class="btn-new-folder" onclick="openMkdir()"><i class="fas fa-folder-plus"></i> Nuova cartella</button>';
        echo '<span class="toolbar-hint"><i class="fas fa-info-circle"></i> La cartella verrà creata qui: <strong>'
           . ($current_folder !== '' ? htmlspecialchars($current_folder) : 'cartella principale')
           . '</strong></span>';
        echo '</div>';
    }

    // ── Scansione directory ────────────────────────────────────────
    $items   = scandir($scan_path);
    $folders = [];
    $files   = [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $scan_path . '/' . $item;
        if (is_dir($full)) {
            if (!in_array($item, $EXCLUDED_FOLDERS)) $folders[] = $item;
        } elseif (is_file($full)) {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (!in_array($item, $EXCLUDED_FILES) && in_array($ext, $ALLOWED_EXTENSIONS)) {
                $files[] = [
                    'name'     => $item,
                    'ext'      => $ext,
                    'reserved' => isReservedFile($item, $RESERVED_PREFIXES)
                ];
            }
        }
    }

    sort($folders);
    usort($files, function($a, $b) {
        if ($a['reserved'] !== $b['reserved']) return $a['reserved'] ? -1 : 1;
        return strcasecmp($a['name'], $b['name']);
    });

    $show_actions = $UPLOAD_ENABLED && $is_authenticated;

    // ── Banner cartella pubblica ───────────────────────────────────
    $pub_expires_admin   = [];
    $pub_passwords_admin = [];
    // Mostra banner se siamo in stampa o in una sua sottocartella
    $is_in_pub_tree = ($current_folder === $PUBLIC_FOLDER)
        || (strpos($current_folder, $PUBLIC_FOLDER . '/') === 0);
    if ($is_authenticated && $is_in_pub_tree) {
        $_scheme  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $_baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        // Sottocartella relativa a PUBLIC_FOLDER
        $admin_sub = ($current_folder !== $PUBLIC_FOLDER)
            ? substr($current_folder, strlen($PUBLIC_FOLDER) + 1)
            : '';
        $pub_url  = $_scheme . '://' . $_SERVER['HTTP_HOST'] . $_baseDir . '?' . $PUBLIC_FOLDER
                  . ($admin_sub !== '' ? '&sub=' . urlencode($admin_sub) : '');
        $pub_expires_admin   = readExpires($current_folder);
        $pub_passwords_admin = readPasswords($current_folder);
        echo '<div class="pub-folder-banner">'
           . '<i class="fas fa-print" style="color:#ffc107;margin-right:6px;"></i>'
           . '<strong>Cartella pubblica' . ($admin_sub ? ' / ' . htmlspecialchars($admin_sub) : '') . ':</strong> i file qui sono accessibili a chiunque senza password.<br>'
           . 'Link: <a href="' . htmlspecialchars($pub_url) . '" target="_blank">' . htmlspecialchars($pub_url) . '</a>'
           . ' &nbsp;<button onclick="navigator.clipboard.writeText(\'' . htmlspecialchars($pub_url, ENT_QUOTES) . '\').then(()=>alert(\'Link copiato!\'))" style="font-size:12px;padding:2px 8px;cursor:pointer;border:1px solid #ffc107;border-radius:4px;background:#fff8e1;color:#7a5900;">'
           . '<i class="fas fa-copy"></i> Copia</button>'
           . '<br><i class="fas fa-clock" style="margin-right:4px;"></i>Ogni file ha la propria scadenza — modificabile con il tasto ⏱️'
           . '</div>';
    }

    // ── Render cartelle ────────────────────────────────────────────
    echo '<ul>';
    foreach ($folders as $folder) {
        $f_path       = $current_folder ? $current_folder . '/' . $folder : $folder;
        $f_label      = htmlspecialchars(ucfirst(str_replace(['_','-'],' ',$folder)));
        $f_path_esc   = htmlspecialchars($f_path, ENT_QUOTES);
        $f_name_esc   = htmlspecialchars($folder, ENT_QUOTES);
        $f_parent_esc = htmlspecialchars($current_folder, ENT_QUOTES);
        $is_pub = ($folder === $PUBLIC_FOLDER && $current_folder === '');
        $pub_badge = $is_pub ? ' <small style="background:#ffc107;color:#7a5900;border-radius:4px;padding:1px 6px;font-size:11px;font-weight:700;margin-left:6px;">🖨️ PUBBLICA</small>' : '';

        if ($show_actions) {
            echo '<li><div class="file-row">'
               . '<a href="?folder=' . urlencode($f_path) . '" class="folder resource-link' . ($is_pub ? ' folder-public' : '') . '" data-folder="' . $f_path_esc . '">'
               . '<span class="file-icon folder"><i class="fas fa-folder-open"></i></span> ' . $f_label . $pub_badge
               . '</a>'
               . ($is_pub
                  ? '<button class="btn-action btn-public" title="Copia link pubblico" onclick="copyPubLink()"><i class="fas fa-link"></i></button>'
                  : '')
               . '<button class="btn-action btn-rename" title="Rinomina cartella" onclick="openRenameFolder(\'' . $f_name_esc . '\',\'' . $f_parent_esc . '\')">'
               .   '<i class="fas fa-pencil-alt"></i>'
               . '</button>'
               . '<button class="btn-action btn-delete" title="Elimina cartella" onclick="confirmDeleteFolder(\'' . $f_name_esc . '\',\'' . $f_parent_esc . '\')">'
               .   '<i class="fas fa-trash-alt"></i>'
               . '</button>'
               . '</div></li>';
        } else {
            echo '<li><a href="?folder=' . urlencode($f_path) . '" class="folder resource-link" data-folder="' . $f_path_esc . '">'
               . '<span class="file-icon folder"><i class="fas fa-folder-open"></i></span> ' . $f_label
               . '</a></li>';
        }
    }

    // ── Render file ────────────────────────────────────────────────
    foreach ($files as $fd) {
        $fname    = $fd['name'];
        $ext      = $fd['ext'];
        $reserved = $fd['reserved'];

        $clean = pathinfo($fname, PATHINFO_FILENAME);
        $force_new = checkNewTabSuffix($clean, $NEW_TAB_SUFFIX);
        $disp_name = getDisplayName($clean . '.' . $ext, $RESERVED_PREFIXES);
        $path      = $current_folder ? $current_folder . '/' . $fname : $fname;

        $icon = 'fa-file'; $iclass = '';
        if ($ext === 'pdf')                         { $icon = 'fa-file-pdf';   $iclass = 'pdf'; }
        elseif (in_array($ext,['jpg','png','jpeg'])) { $icon = 'fa-image';     $iclass = 'jpg'; }
        elseif (in_array($ext,['docx','doc']))       { $icon = 'fa-file-word'; $iclass = 'docx'; }
        elseif (in_array($ext,['xlsx','xls']))       { $icon = 'fa-file-excel';$iclass = 'xlsx'; }
        elseif (in_array($ext,['php','html']))       { $icon = 'fa-code';      $iclass = 'code'; }

        $l_class = '';
        if ($reserved) { $icon = 'fa-lock'; $iclass = 'lock'; $l_class = 'reserved'; }

        $new_tab = $force_new || $OPEN_IN_NEW_TAB;
        $target  = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
        $link    = 'index.php?file=' . urlencode($path);

        if ($show_actions) {
            $safe_fname      = htmlspecialchars($fname, ENT_QUOTES);
            $safe_cur_folder = htmlspecialchars($current_folder, ENT_QUOTES);
            $in_pub_folder   = $is_in_pub_tree;
            $pub_file_url    = '';
            $expiry_html     = '';
            $lock_html       = '';
            if ($in_pub_folder) {
                $pub_file_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                              . '://' . $_SERVER['HTTP_HOST']
                              . strtok($_SERVER['REQUEST_URI'], '?')
                              . '?file=' . rawurlencode($current_folder . '/' . $fname);
                $exp_ts      = isset($pub_expires_admin[$fname]) ? (int)$pub_expires_admin[$fname] : 0;
                $rem_str     = formatRemaining($exp_ts);
                $badge_class = ($exp_ts === 0) ? 'never' : (($exp_ts - time() < 3600) ? 'soon' : 'ok');
                $expiry_html = '<span class="expiry-badge ' . $badge_class . '"><i class="fas fa-clock"></i> ' . htmlspecialchars($rem_str) . '</span>';
                $has_pw      = isset($pub_passwords_admin[$fname]);
                $lock_html   = $has_pw
                    ? '<span class="expiry-badge soon" title="Protetto da password"><i class="fas fa-lock"></i> 🔐</span>'
                    : '<span class="expiry-badge never" title="Nessuna password"><i class="fas fa-lock-open"></i> libero</span>';
            }
            echo '<li><div class="file-row" data-filename="' . $safe_fname . '" data-folder="' . $safe_cur_folder . '">'
               . '<a href="' . htmlspecialchars($link) . '" class="' . $l_class . ' resource-link"' . $target . '>'
               . '<span class="file-icon ' . $iclass . '"><i class="fas ' . $icon . '"></i></span> '
               . htmlspecialchars($disp_name) . $expiry_html . $lock_html
               . ($new_tab ? ' <i class="fas fa-external-link-alt" style="font-size:.8em;margin-left:5px;"></i>' : '')
               . '</a>'
               . ($in_pub_folder
                  ? '<button class="btn-action btn-public" title="Copia link diretto al file" onclick="navigator.clipboard.writeText(\'' . htmlspecialchars($pub_file_url, ENT_QUOTES) . '\').then(()=>alert(\'Link copiato!\'))"><i class="fas fa-link"></i></button>'
                    . '<button class="btn-action btn-expiry" title="Cambia scadenza" onclick="openExpiry(\'' . $safe_fname . '\')"><i class="fas fa-clock"></i></button>'
                    . '<button class="btn-action btn-password" title="' . ($has_pw ? 'Cambia/rimuovi password' : 'Imposta password') . '" onclick="openPassword(\'' . $safe_fname . '\',' . ($has_pw ? 'true' : 'false') . ')"><i class="fas fa-key"></i></button>'
                  : '')
               . '<button class="btn-action btn-copy" title="Copia in un\'altra cartella" onclick="openCopy(\'' . $safe_fname . '\',\'' . $safe_cur_folder . '\')">'
               .   '<i class="fas fa-copy" style="pointer-events:none;"></i>'
               . '</button>'
               . '<button class="btn-action btn-move" title="Sposta in cartella" onclick="openMove(\'' . $safe_fname . '\',\'' . $safe_cur_folder . '\')">'
               .   '<i class="fas fa-folder-arrow-down" style="pointer-events:none;"></i>'
               . '</button>'
               . '<button class="btn-action btn-rename" title="Rinomina" onclick="openRename(\'' . $safe_fname . '\')">'
               .   '<i class="fas fa-pencil-alt"></i>'
               . '</button>'
               . '<button class="btn-action btn-delete" title="Elimina" onclick="confirmDelete(\'' . $safe_fname . '\')">'
               .   '<i class="fas fa-trash-alt"></i>'
               . '</button>'
               . '</div></li>';
        } else {
            echo '<li><a href="' . htmlspecialchars($link) . '" class="' . $l_class . ' resource-link"' . $target . '>'
               . '<span class="file-icon ' . $iclass . '"><i class="fas ' . $icon . '"></i></span> '
               . htmlspecialchars($disp_name)
               . ($new_tab ? ' <i class="fas fa-external-link-alt" style="font-size:.8em;margin-left:5px;"></i>' : '')
               . '</a></li>';
        }
    }
    echo '</ul>';
    ?>

    <?php if ($LOGIN_REQUIRED): ?>
    <p style="margin-top:15px;">
        <a href="?logout=true" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </p>
    <?php endif; ?>

    <p style="margin-top:15px;font-size:12px;color:#6c757d;">
        <?= $CREDITS ?> <span id="data"></span>
    </p>
    <script>
        const m = ["Gennaio","Febbraio","Marzo","Aprile","Maggio","Giugno","Luglio","Agosto","Settembre","Ottobre","Novembre","Dicembre"];
        const d = new Date();
        document.getElementById("data").textContent = m[d.getMonth()] + " " + d.getFullYear();
    </script>
</div>

<!-- ── Modale Scadenza File (cartella pubblica) ──────────────── -->
<div class="modal-overlay" id="expiryModal">
    <div class="modal-box">
        <h3><i class="fas fa-clock" style="color:#ffc107;margin-right:6px;"></i> Cambia scadenza file</h3>
        <p style="font-size:13px;color:#6c757d;margin:0 0 10px;" id="expiryFileLabel"></p>
        <label style="font-size:13px;font-weight:600;color:#7a5900;display:block;margin-bottom:6px;">
            <i class="fas fa-calendar-alt"></i> Data e ora di scadenza:
        </label>
        <input type="datetime-local" id="expiryDatetimeInput"
               style="width:100%;padding:10px;border:1px solid #ffc107;border-radius:6px;font-size:15px;box-sizing:border-box;margin-bottom:8px;background:#fff8e1;color:#2c3e50;">
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;">
            <button type="button" class="quick-btn" onclick="setQuickExpiry(15,'min')">+15 min</button>
            <button type="button" class="quick-btn" onclick="setQuickExpiry(30,'min')">+30 min</button>
            <button type="button" class="quick-btn" onclick="setQuickExpiry(1,'h')">+1 h</button>
            <button type="button" class="quick-btn" onclick="setQuickExpiry(6,'h')">+6 h</button>
            <button type="button" class="quick-btn" onclick="setQuickExpiry(24,'h')">+24 h</button>
            <button type="button" class="quick-btn" onclick="setQuickExpiry(48,'h')">+48 h</button>
            <button type="button" class="quick-btn quick-never" onclick="clearExpiryDt()"><i class="fas fa-infinity"></i> Mai</button>
        </div>
        <p id="expiryPreview" style="font-size:12px;color:#888;margin:0 0 14px;min-height:16px;"></p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeExpiry()">Annulla</button>
            <button class="btn-confirm" style="background:#ffc107;color:#7a5900;" onclick="submitExpiry()"><i class="fas fa-check"></i> Salva</button>
        </div>
    </div>
</div>

<!-- ── Modale Password File (admin) ──────────────────────────── -->
<div class="modal-overlay" id="passwordModal">
    <div class="modal-box">
        <h3><i class="fas fa-key" style="color:#c060a0;margin-right:6px;"></i> <span id="passwordModalTitle">Imposta password file</span></h3>
        <p style="font-size:13px;color:#6c757d;margin:0 0 12px;" id="passwordFileLabel"></p>
        <label style="font-size:13px;font-weight:600;color:#7a1a5c;display:block;margin-bottom:6px;">
            <i class="fas fa-lock"></i> Nuova password:
        </label>
        <div style="position:relative;margin-bottom:6px;">
            <input type="password" id="adminPwInput" placeholder="Lascia vuoto per rimuovere la password"
                   style="width:100%;padding:10px 40px 10px 12px;border:2px solid #c060a0;border-radius:6px;font-size:15px;box-sizing:border-box;background:#fdf4fb;">
            <span onclick="toggleAdminPw()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#6c757d;">
                <i id="adminPwEye" class="fas fa-eye"></i>
            </span>
        </div>
        <p style="font-size:12px;color:#888;margin:0 0 14px;">
            Lascia vuoto e salva per <strong>rimuovere</strong> la password esistente.
        </p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closePassword()">Annulla</button>
            <button class="btn-confirm" style="background:#c060a0;" onclick="submitPassword()"><i class="fas fa-check"></i> Salva</button>
        </div>
    </div>
</div>

<!-- ── Modale Rinomina ──────────────────────────────────────────── -->
<div class="modal-overlay" id="renameModal">
    <div class="modal-box">
        <h3><i class="fas fa-pencil-alt" style="color:#6a11cb;margin-right:6px;"></i> <span id="renameModalTitle">Rinomina file</span></h3>
        <input type="text" id="renameInput" placeholder="Nuovo nome file">
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeRename()">Annulla</button>
            <button class="btn-confirm" onclick="submitRename()"><i class="fas fa-check"></i> Rinomina</button>
        </div>
    </div>
</div>

<!-- ── Modale Nuova Cartella ────────────────────────────────────── -->
<div class="modal-overlay" id="mkdirModal">
    <div class="modal-box">
        <h3><i class="fas fa-folder-plus" style="color:#6a11cb;margin-right:6px;"></i> Nuova cartella</h3>
        <p style="font-size:13px;color:#6c757d;margin:0 0 12px;">Solo lettere, numeri, trattini e underscore.</p>
        <input type="text" id="mkdirInput" placeholder="es. Circolari_2025">
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeMkdir()">Annulla</button>
            <button class="btn-confirm" onclick="submitMkdir()"><i class="fas fa-check"></i> Crea</button>
        </div>
    </div>
</div>

<!-- ── Modale Sposta File ────────────────────────────────────── -->
<div class="modal-overlay" id="moveModal">
    <div class="modal-box">
        <h3><i class="fas fa-folder-arrow-down" style="color:#0d6efd;margin-right:6px;"></i> Sposta file in…</h3>
        <p style="font-size:13px;color:#6c757d;margin:0 0 12px;" id="moveFileLabel"></p>
        <ul class="move-folder-list" id="moveFolderList"></ul>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeMove()">Annulla</button>
        </div>
    </div>
</div>

<!-- ── Modale Copia File ──────────────────────────────────────── -->
<div class="modal-overlay" id="copyModal">
    <div class="modal-box">
        <h3><i class="fas fa-copy" style="color:#28a745;margin-right:6px;"></i> Copia file in…</h3>
        <p style="font-size:13px;color:#6c757d;margin:0 0 4px;" id="copyFileLabel"></p>
        <p style="font-size:12px;color:#28a745;margin:0 0 12px;"><i class="fas fa-info-circle"></i> L'originale rimane al suo posto.</p>
        <ul class="move-folder-list" id="copyFolderList"></ul>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeMove2()">Annulla</button>
        </div>
    </div>
</div>

<!-- Form nascosto per tutte le azioni POST -->
<form id="actionForm" method="post" style="display:none;">
    <input type="hidden" name="action"          id="actionInput">
    <input type="hidden" name="filename"        id="filenameInput">
    <input type="hidden" name="new_filename"    id="newFilenameInput">
    <input type="hidden" name="folder"          id="folderInput">
    <input type="hidden" name="to_folder"       id="toFolderInput">
    <input type="hidden" name="folder_name"     id="folderNameInput">
    <input type="hidden" name="new_folder_name" id="newFolderNameInput">
    <input type="hidden" name="expire_datetime" id="expireDatetimeInput">
    <input type="hidden" name="file_password"   id="adminPwHidden">
</form>

<script>
// Cartella corrente e lista cartelle esposte a JS
const _currentFolder = <?= json_encode($current_folder) ?>;
const _allFolders    = <?= json_encode(array_map(function($f) {
    // Format label with spaces for readability, keep indentation hint via path depth
    $depth = substr_count($f['path'], '/');
    $indent = str_repeat('  ', $depth);
    $basename = basename($f['path']);
    $label = $indent . ucfirst(str_replace(['_','-'],' ',$basename));
    return ['label' => $label, 'path' => $f['path']];
}, getAllFoldersRecursive('.', '', $EXCLUDED_FOLDERS))) ?>;
const _pubStampaUrl  = <?= json_encode(
    ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '?' . $PUBLIC_FOLDER
) ?>;

function copyPubLink() {
    navigator.clipboard.writeText(_pubStampaUrl).then(() => alert('Link pubblico copiato!\n' + _pubStampaUrl));
}
function clearUploadExpiry() {
    const el = document.getElementById('uploadExpireDt');
    if (el) el.value = '';
}
function toggleUploadPw() {
    const i = document.getElementById('uploadPwInput');
    const e = document.getElementById('uploadPwEye');
    if (!i) return;
    i.type = i.type === 'password' ? 'text' : 'password';
    e.classList.toggle('fa-eye'); e.classList.toggle('fa-eye-slash');
}
function toggleAdminPw() {
    const i = document.getElementById('adminPwInput');
    const e = document.getElementById('adminPwEye');
    i.type = i.type === 'password' ? 'text' : 'password';
    e.classList.toggle('fa-eye'); e.classList.toggle('fa-eye-slash');
}

// ── Password per-file admin ──────────────────────────────────────
let _pwFile = '';
function openPassword(filename, hasPassword) {
    _pwFile = filename;
    document.getElementById('passwordModalTitle').textContent = hasPassword ? 'Cambia / rimuovi password' : 'Imposta password file';
    document.getElementById('passwordFileLabel').textContent  = '📄 ' + filename;
    document.getElementById('adminPwInput').value = '';
    document.getElementById('adminPwInput').type  = 'password';
    document.getElementById('adminPwEye').className = 'fas fa-eye';
    document.getElementById('passwordModal').classList.add('active');
    setTimeout(() => document.getElementById('adminPwInput').focus(), 60);
}
function closePassword() {
    document.getElementById('passwordModal').classList.remove('active');
    _pwFile = '';
}
function submitPassword() {
    const pw = document.getElementById('adminPwInput').value;
    document.getElementById('actionInput').value   = 'set_password';
    document.getElementById('filenameInput').value = _pwFile;
    document.getElementById('folderInput').value   = _currentFolder;
    document.getElementById('adminPwHidden').value = pw;
    document.getElementById('actionForm').submit();
}
let _currentFile           = '';
let _moveFromFolder        = '';
let _renameMode            = 'file';   // 'file' | 'folder'
let _currentFolderNameEdit = '';
let _currentFolderParent   = '';

// ── Rinomina FILE ─────────────────────────────────────────────────
function openRename(filename) {
    _renameMode  = 'file';
    _currentFile = filename;
    document.getElementById('renameModalTitle').textContent = 'Rinomina file';
    document.getElementById('renameInput').value = filename;
    document.getElementById('renameModal').classList.add('active');
    setTimeout(() => {
        const inp = document.getElementById('renameInput');
        inp.focus();
        const dot = inp.value.lastIndexOf('.');
        if (dot > 0) inp.setSelectionRange(0, dot);
        else inp.select();
    }, 50);
}

// ── Rinomina CARTELLA ─────────────────────────────────────────────
function openRenameFolder(folderName, parentFolder) {
    _renameMode            = 'folder';
    _currentFolderNameEdit = folderName;
    _currentFolderParent   = parentFolder;
    document.getElementById('renameModalTitle').textContent = 'Rinomina cartella';
    document.getElementById('renameInput').value = folderName;
    document.getElementById('renameModal').classList.add('active');
    setTimeout(() => {
        const inp = document.getElementById('renameInput');
        inp.focus();
        inp.select();
    }, 50);
}

function closeRename() {
    document.getElementById('renameModal').classList.remove('active');
    _currentFile = '';
    _currentFolderNameEdit = '';
    _renameMode = 'file';
}
function submitRename() {
    const newName = document.getElementById('renameInput').value.trim();
    if (!newName) { alert('Inserisci un nome valido.'); return; }
    if (_renameMode === 'folder') {
        document.getElementById('actionInput').value        = 'rename_folder';
        document.getElementById('folderNameInput').value    = _currentFolderNameEdit;
        document.getElementById('newFolderNameInput').value = newName;
        document.getElementById('folderInput').value        = _currentFolderParent;
    } else {
        document.getElementById('actionInput').value      = 'rename';
        document.getElementById('filenameInput').value    = _currentFile;
        document.getElementById('newFilenameInput').value = newName;
        document.getElementById('folderInput').value      = _currentFolder;
    }
    document.getElementById('actionForm').submit();
}

// ── Elimina FILE ──────────────────────────────────────────────────
function confirmDelete(filename) {
    if (!confirm('Eliminare definitivamente \u00ab' + filename + '\u00bb?\nQuesta operazione non \u00e8 reversibile.')) return;
    document.getElementById('actionInput').value   = 'delete';
    document.getElementById('filenameInput').value = filename;
    document.getElementById('folderInput').value   = _currentFolder;
    document.getElementById('actionForm').submit();
}

// ── Elimina CARTELLA ──────────────────────────────────────────────
function confirmDeleteFolder(folderName, parentFolder) {
    if (!confirm('Eliminare definitivamente la cartella \u00ab' + folderName + '\u00bb?\n\u26a0\ufe0f La cartella deve essere vuota.')) return;
    document.getElementById('actionInput').value     = 'delete_folder';
    document.getElementById('folderNameInput').value = folderName;
    document.getElementById('folderInput').value     = parentFolder;
    document.getElementById('actionForm').submit();
}

// ── Nuova cartella ───────────────────────────────────────────────
function openMkdir() {
    document.getElementById('mkdirInput').value = '';
    document.getElementById('mkdirModal').classList.add('active');
    setTimeout(() => document.getElementById('mkdirInput').focus(), 50);
}
function closeMkdir() {
    document.getElementById('mkdirModal').classList.remove('active');
}
function submitMkdir() {
    const name = document.getElementById('mkdirInput').value.trim();
    if (!name) { alert('Inserisci un nome valido.'); return; }
    document.getElementById('actionInput').value     = 'mkdir';
    document.getElementById('folderInput').value     = _currentFolder;
    document.getElementById('folderNameInput').value = name;
    document.getElementById('actionForm').submit();
}

// ── Sposta file (modale) ─────────────────────────────────────────
function openMove(filename, fromFolder) {
    _currentFile    = filename;
    _moveFromFolder = fromFolder;
    document.getElementById('moveFileLabel').textContent = '📄 ' + filename;
    const list = document.getElementById('moveFolderList');
    list.innerHTML = '';

    // Cartelle disponibili: tutte esclusa la cartella corrente del file
    const targets = [];
    if (fromFolder !== '') {
        targets.push({ label: '🏠 Cartella principale', path: '' });
    }
    _allFolders.forEach(f => {
        if (f.path !== fromFolder) {
            const depth = (f.path.match(/\//g) || []).length;
            const icon = depth === 0 ? '📁' : '↳'.repeat(depth) + ' 📂';
            targets.push({ label: icon + ' ' + f.label.trim(), path: f.path });
        }
    });

    if (targets.length === 0) {
        list.innerHTML = '<li><span class="no-folders">Nessuna altra cartella disponibile.</span></li>';
    } else {
        targets.forEach(t => {
            const li  = document.createElement('li');
            const btn = document.createElement('button');
            btn.textContent = t.label;
            btn.style.paddingLeft = ((t.path.split('/').length - 1) * 12 + 14) + 'px';
            btn.onclick = () => submitMove(t.path, t.label);
            li.appendChild(btn);
            list.appendChild(li);
        });
    }
    document.getElementById('moveModal').classList.add('active');
}
function closeMove() {
    document.getElementById('moveModal').classList.remove('active');
    _currentFile = '';
    _moveFromFolder = '';
}
function submitMove(toFolder, toLabel) {
    const cleanLabel = toFolder !== '' ? toFolder : 'Cartella principale';
    if (!confirm('Spostare «' + _currentFile + '»\nnella cartella «' + cleanLabel + '»?')) return;
    document.getElementById('actionInput').value   = 'move';
    document.getElementById('filenameInput').value = _currentFile;
    document.getElementById('folderInput').value   = _moveFromFolder;
    document.getElementById('toFolderInput').value = toFolder;
    document.getElementById('actionForm').submit();
}

// ── Copia file ───────────────────────────────────────────────────
let _copyFromFolder = '';
function openCopy(filename, fromFolder) {
    _currentFile    = filename;
    _copyFromFolder = fromFolder;
    document.getElementById('copyFileLabel').textContent = '📄 ' + filename;
    const list = document.getElementById('copyFolderList');
    list.innerHTML = '';

    // Tutte le cartelle disponibili (inclusa quella corrente — si può copiare nella stessa)
    const targets = [];
    if (fromFolder !== '') {
        targets.push({ label: '🏠 Cartella principale', path: '' });
    }
    _allFolders.forEach(f => {
        const depth = (f.path.match(/\//g) || []).length;
        const icon = depth === 0 ? '📁' : '↳'.repeat(depth) + ' 📂';
        targets.push({ label: icon + ' ' + f.label.trim(), path: f.path });
    });

    if (targets.length === 0) {
        list.innerHTML = '<li><span class="no-folders">Nessuna cartella disponibile.</span></li>';
    } else {
        targets.forEach(t => {
            const li  = document.createElement('li');
            const btn = document.createElement('button');
            const isPub = t.path.toLowerCase().includes('<?= addslashes($PUBLIC_FOLDER) ?>');
            const pubBadge = isPub ? ' <span style="font-size:11px;background:#ffc107;color:#7a5900;border-radius:4px;padding:1px 5px;margin-left:4px;">🖨️ pubblica</span>' : '';
            btn.innerHTML = t.label + pubBadge;
            btn.style.paddingLeft = ((t.path.split('/').length - 1) * 12 + 14) + 'px';
            btn.onclick = () => submitCopy(t.path, t.label);
            li.appendChild(btn);
            list.appendChild(li);
        });
    }
    document.getElementById('copyModal').classList.add('active');
}
function closeMove2() {
    document.getElementById('copyModal').classList.remove('active');
    _currentFile    = '';
    _copyFromFolder = '';
}
function submitCopy(toFolder, toLabel) {
    const dest = toFolder !== '' ? toFolder : 'Cartella principale';
    if (!confirm('Copiare «' + _currentFile + '»\nin «' + dest + '»?\nL\'originale rimane al suo posto.')) return;
    document.getElementById('actionInput').value   = 'copy_file';
    document.getElementById('filenameInput').value = _currentFile;
    document.getElementById('folderInput').value   = _copyFromFolder;
    document.getElementById('toFolderInput').value = toFolder;
    document.getElementById('actionForm').submit();
}

// ── Chiusura modali con click esterno / tasto ────────────────────
function _safeOn(id, event, fn) {
    const el = document.getElementById(id);
    if (el) el.addEventListener(event, fn);
}
_safeOn('renameModal',  'click', function(e) { if (e.target === this) closeRename(); });
_safeOn('renameInput',  'keydown', function(e) { if (e.key==='Enter') submitRename(); if (e.key==='Escape') closeRename(); });
_safeOn('mkdirModal',   'click', function(e) { if (e.target === this) closeMkdir(); });
_safeOn('mkdirInput',   'keydown', function(e) { if (e.key==='Enter') submitMkdir(); if (e.key==='Escape') closeMkdir(); });
_safeOn('moveModal',    'click', function(e) { if (e.target === this) closeMove(); });
_safeOn('copyModal',    'click', function(e) { if (e.target === this) closeMove2(); });
_safeOn('expiryModal',  'click', function(e) { if (e.target === this) closeExpiry(); });
_safeOn('expiryDatetimeInput', 'input', _updateExpiryPreview);
_safeOn('passwordModal','click', function(e) { if (e.target === this) closePassword(); });
_safeOn('adminPwInput', 'keydown', function(e) { if (e.key==='Enter') submitPassword(); if (e.key==='Escape') closePassword(); });

// ── Scadenza per-file (cartella pubblica) ────────────────────────
let _expiryFile = '';

function _dtLocalNow(offsetSec) {
    const d = new Date(Date.now() + offsetSec * 1000);
    // Format: YYYY-MM-DDTHH:MM (datetime-local)
    const pad = n => String(n).padStart(2,'0');
    return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate())
         + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
}

function _updateExpiryPreview() {
    const val = document.getElementById('expiryDatetimeInput').value;
    const p   = document.getElementById('expiryPreview');
    if (!val) {
        p.innerHTML = '<i class="fas fa-infinity"></i> Nessuna scadenza automatica';
        p.style.color = '#6c757d';
        return;
    }
    const ts  = new Date(val);
    const now = new Date();
    const diffMs = ts - now;
    if (diffMs <= 0) { p.textContent = '⚠️ La data è già passata'; p.style.color='#dc3545'; return; }
    const mins = Math.round(diffMs / 60000);
    let rem = mins < 60 ? mins + ' minuti'
            : mins < 1440 ? Math.round(mins/60) + ' ore'
            : Math.round(mins/1440) + ' giorni';
    p.innerHTML = '⏱️ Scade tra <strong>' + rem + '</strong> (' + ts.toLocaleString('it-IT') + ')';
    p.style.color = mins < 60 ? '#dc3545' : '#155724';
}

function setQuickExpiry(amount, unit) {
    const secs = unit === 'min' ? amount * 60 : amount * 3600;
    document.getElementById('expiryDatetimeInput').value = _dtLocalNow(secs);
    _updateExpiryPreview();
}
function clearExpiryDt() {
    document.getElementById('expiryDatetimeInput').value = '';
    _updateExpiryPreview();
}

function openExpiry(filename) {
    _expiryFile = filename;
    document.getElementById('expiryFileLabel').textContent = '📄 ' + filename;
    // Default: +24h
    document.getElementById('expiryDatetimeInput').value = _dtLocalNow(86400);
    document.getElementById('expiryDatetimeInput').min   = _dtLocalNow(0);
    _updateExpiryPreview();
    document.getElementById('expiryModal').classList.add('active');
}
function closeExpiry() {
    document.getElementById('expiryModal').classList.remove('active');
    _expiryFile = '';
}
function submitExpiry() {
    const val = document.getElementById('expiryDatetimeInput').value;
    if (val) {
        const ts = new Date(val);
        if (ts <= new Date()) {
            alert('La data selezionata è già passata. Scegli una data futura o lascia vuoto per nessuna scadenza.');
            return;
        }
    }
    document.getElementById('actionInput').value        = 'set_expiry';
    document.getElementById('filenameInput').value      = _expiryFile;
    document.getElementById('folderInput').value        = _currentFolder;
    document.getElementById('expireDatetimeInput').value = val;
    document.getElementById('actionForm').submit();
}
</script>
</body>
</html>
