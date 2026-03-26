<?php
/**
 * FILE MANAGER v6.1 - Sebastiano Basile – superscuola.com – sostegno.t.me
 * File autonomo: non richiede download.php né verifica-accesso.php
 */
session_start();

// ╔══════════════════════════════════════════════════════════════╗
// ║                      CONFIGURAZIONE                         ║
// ╠══════════════════════════════════════════════════════════════╣
// ║  Modifica solo questo blocco per personalizzare il tool     ║
// ╚══════════════════════════════════════════════════════════════╝

// ── 🔐 ACCESSO ──────────────────────────────────────────────────
$LOGIN_REQUIRED = true;       // true = richiede password | false = accesso libero
$PASSWORD       = "cambiami";    // Password di accesso (testo in chiaro)

// ── 📤 UPLOAD ────────────────────────────────────────────────────
// ⚠️  Se LOGIN_REQUIRED = false, imposta UPLOAD_ENABLED = false (sito pubblico!)
$UPLOAD_ENABLED     = true;        // true = abilita upload (solo se autenticato)
$UPLOAD_FOLDER      = 'upload';    // Nome cartella di destinazione
$UPLOAD_MAX_SIZE_MB = 20;          // Dimensione massima in MB
$UPLOAD_ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'doc', 'xls', 'zip', 'json', 'af'];

// ── 🪟 APERTURA FILE ─────────────────────────────────────────────
$OPEN_IN_NEW_TAB = true;       // true = nuova scheda | false = stessa finestra
                                // Nota: aggiungendo _NUOVO al nome file si forza sempre nuova scheda
                                //       indipendentemente da questa impostazione

// ── 📁 FILE E CARTELLE NASCOSTI ──────────────────────────────────
$EXCLUDED_FILES   = ['index.php', '.htaccess', 'test.php'];
$EXCLUDED_FOLDERS = ['Ammucciata', 'Test'];

// ── 📄 ESTENSIONI ────────────────────────────────────────────────
$ALLOWED_EXTENSIONS   = ['php', 'pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'doc', 'xls', 'html', 'mid', 'zip', 'json'];
$INLINE_EXTENSIONS    = ['pdf', 'html', 'jpg', 'jpeg', 'png', 'mid'];
$DOWNLOAD_EXTENSIONS  = ['docx', 'xlsx', 'doc', 'xls', 'zip', 'json'];

// ── 🏷️ FILE RISERVATI (mostrano il lucchetto 🔒) ─────────────────
$RESERVED_PREFIXES = ['RISERVATO_', '🔐', 'PRIV_'];
$NEW_TAB_SUFFIX    = '_NUOVO';

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
// 1. AUTENTICAZIONE
// ================================================================

$id_folder   = md5(__DIR__);
$cookie_name = "auth_" . $id_folder;
$errore_login = '';

if ($LOGIN_REQUIRED && isset($_POST['password'])) {
    if ($_POST['password'] === $PASSWORD) {
        $_SESSION[$cookie_name] = true;
        setcookie($cookie_name, "1", time() + 3600, "/");
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

$is_authenticated = (!$LOGIN_REQUIRED)
    || (isset($_SESSION[$cookie_name]) && $_SESSION[$cookie_name] === true)
    || isset($_COOKIE[$cookie_name]);


// ================================================================
// 1b. GESTIONE UPLOAD
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_FILES['upload_file'])) {

    $upload_dir = rtrim($UPLOAD_FOLDER, '/') . '/';

    // Crea la cartella se non esiste
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
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
        $dest      = $upload_dir . $safe_name;

        // Evita sovrascrittura
        if (file_exists($dest)) {
            $safe_name = time() . '_' . $safe_name;
            $dest      = $upload_dir . $safe_name;
        }

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $upload_msg = ['success', '✅ File caricato: <strong>' . htmlspecialchars($safe_name) . '</strong>'];
        } else {
            $upload_msg = ['error', '❌ Impossibile salvare il file. Controlla i permessi della cartella.'];
        }
    }

    // Redirect per evitare ri-submit al refresh
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
    $del_file = isset($_POST['filename']) ? basename(str_replace(['..', '\\', '/'], '', $_POST['filename'])) : '';
    $del_path = rtrim($UPLOAD_FOLDER, '/') . '/' . $del_file;

    if ($del_file && file_exists($del_path) && is_file($del_path)) {
        if (unlink($del_path)) {
            $_SESSION['upload_msg'] = ['success', '🗑️ File eliminato: <strong>' . htmlspecialchars($del_file) . '</strong>'];
        } else {
            $_SESSION['upload_msg'] = ['error', '❌ Impossibile eliminare il file.'];
        }
    } else {
        $_SESSION['upload_msg'] = ['error', '❌ File non trovato.'];
    }
    header('Location: index.php?folder=' . urlencode($UPLOAD_FOLDER));
    exit;
}


// ================================================================
// 1d. GESTIONE RINOMINA FILE
// ================================================================

if ($UPLOAD_ENABLED && $is_authenticated && isset($_POST['action']) && $_POST['action'] === 'rename') {
    $old_file = isset($_POST['filename'])     ? basename(str_replace(['..', '\\', '/'], '', $_POST['filename']))     : '';
    $new_name = isset($_POST['new_filename']) ? trim($_POST['new_filename']) : '';

    $old_path = rtrim($UPLOAD_FOLDER, '/') . '/' . $old_file;
    $old_ext  = strtolower(pathinfo($old_file, PATHINFO_EXTENSION));

    // Assicura che il nuovo nome abbia la stessa estensione
    $new_ext  = strtolower(pathinfo($new_name, PATHINFO_EXTENSION));
    if ($new_ext !== $old_ext) {
        $new_name = pathinfo($new_name, PATHINFO_FILENAME) . '.' . $old_ext;
    }

    $safe_new = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $new_name);
    $new_path = rtrim($UPLOAD_FOLDER, '/') . '/' . $safe_new;

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
    header('Location: index.php?folder=' . urlencode($UPLOAD_FOLDER));
    exit;
}


// ================================================================
// 2. GESTIONE FILE (sostituisce download.php)
// ================================================================

if (isset($_GET['file'])) {

    if ($LOGIN_REQUIRED && !$is_authenticated) {
        header('Location: index.php');
        exit;
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
        /* ── Azioni file (rinomina / elimina) ── */
        .file-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
        .file-row a.resource-link { flex:1; margin-bottom:0; }
        .btn-action { display:inline-flex; align-items:center; justify-content:center; padding:8px 10px; border:none; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; text-decoration:none; transition:all .2s; flex-shrink:0; }
        .btn-rename { background:#fff3cd; color:#856404; border:1px solid #ffc107; }
        .btn-rename:hover { background:#ffc107; color:#fff; }
        .btn-delete { background:#f8d7da; color:#721c24; border:1px solid #dc3545; }
        .btn-delete:hover { background:#dc3545; color:#fff; }
        /* modale rinomina */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; justify-content:center; align-items:center; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:#fff; border-radius:12px; padding:24px 20px; max-width:380px; width:90%; box-shadow:0 10px 30px rgba(0,0,0,.3); }
        .modal-box h3 { margin:0 0 14px; font-size:16px; color:#2c3e50; }
        .modal-box input { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; font-size:15px; box-sizing:border-box; margin-bottom:14px; }
        .modal-actions { display:flex; gap:8px; justify-content:flex-end; }
        .modal-actions button { padding:8px 16px; border:none; border-radius:6px; cursor:pointer; font-weight:600; font-size:14px; }
        .btn-cancel { background:#e9ecef; color:#495057; }
        .btn-confirm { background:#6a11cb; color:#fff; }
        .btn-confirm:hover { background:#2575fc; }
    </style>
</head>
<body>
<div class="container">
    <div class="title-group"><h1><?= $MAIN_TITLE ?></h1></div>
    <div class="info-box"><strong>💡 Nota:</strong> <?= $MAIN_NOTE ?></div>

    <?php if ($UPLOAD_ENABLED && $is_authenticated): ?>

        <?php if ($upload_msg): ?>
            <div class="<?= $upload_msg[0] === 'success' ? 'upload-msg-ok' : 'upload-msg-err' ?>">
                <?= $upload_msg[1] ?>
            </div>
        <?php endif; ?>

        <div class="upload-box">
            <h3><i class="fas fa-upload"></i> Carica un file nella cartella <em><?= htmlspecialchars($UPLOAD_FOLDER) ?></em></h3>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="upload_file" required>
                <button type="submit"><i class="fas fa-cloud-upload-alt"></i> Carica</button>
            </form>
            <small style="color:#6c757d;display:block;margin-top:8px;">Estensioni ammesse: <?= implode(', ', $UPLOAD_ALLOWED_EXT) ?> &nbsp;·&nbsp; Max <?= $UPLOAD_MAX_SIZE_MB ?> MB</small>
        </div>

    <?php endif; ?>

    <?php
    // ── Navigazione cartelle ───────────────────────────────────────
    $current_folder = isset($_GET['folder'])
        ? str_replace(['..', '\\'], '', trim($_GET['folder'], '/'))
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

    // ── Render cartelle ────────────────────────────────────────────
    echo '<ul>';
    foreach ($folders as $folder) {
        $f_path  = $current_folder ? $current_folder . '/' . $folder : $folder;
        $f_label = htmlspecialchars(ucfirst(str_replace(['_','-'],' ',$folder)));
        echo '<li><a href="?folder=' . urlencode($f_path) . '" class="folder resource-link">'
           . '<span class="file-icon folder"><i class="fas fa-folder-open"></i></span> ' . $f_label
           . '</a></li>';
    }

    // ── Render file ────────────────────────────────────────────────
    // Mostra i pulsanti azione solo nella cartella upload (e solo se autenticato)
    $in_upload_folder = ($current_folder === $UPLOAD_FOLDER);
    $show_actions     = $UPLOAD_ENABLED && $is_authenticated && $in_upload_folder;

    foreach ($files as $fd) {
        $fname    = $fd['name'];
        $ext      = $fd['ext'];
        $reserved = $fd['reserved'];

        $clean = pathinfo($fname, PATHINFO_FILENAME);
        $force_new = checkNewTabSuffix($clean, $NEW_TAB_SUFFIX);
        $disp_name = getDisplayName($clean . '.' . $ext, $RESERVED_PREFIXES);
        $path      = $current_folder ? $current_folder . '/' . $fname : $fname;

        $icon = 'fa-file'; $iclass = '';
        if ($ext === 'pdf')                        { $icon = 'fa-file-pdf';    $iclass = 'pdf'; }
        elseif (in_array($ext,['jpg','png','jpeg'])){ $icon = 'fa-image';      $iclass = 'jpg'; }
        elseif (in_array($ext,['docx','doc']))     { $icon = 'fa-file-word';   $iclass = 'docx'; }
        elseif (in_array($ext,['xlsx','xls']))     { $icon = 'fa-file-excel';  $iclass = 'xlsx'; }
        elseif (in_array($ext,['php','html']))     { $icon = 'fa-code';        $iclass = 'code'; }

        $l_class = '';
        if ($reserved) { $icon = 'fa-lock'; $iclass = 'lock'; $l_class = 'reserved'; }

        $new_tab = $force_new || $OPEN_IN_NEW_TAB;
        $target  = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

        $link = 'index.php?file=' . urlencode($path);

        if ($show_actions) {
            $safe_fname = htmlspecialchars($fname, ENT_QUOTES);
            echo '<li><div class="file-row">'
               . '<a href="' . htmlspecialchars($link) . '" class="' . $l_class . ' resource-link"' . $target . '>'
               . '<span class="file-icon ' . $iclass . '"><i class="fas ' . $icon . '"></i></span> '
               . htmlspecialchars($disp_name)
               . ($new_tab ? ' <i class="fas fa-external-link-alt" style="font-size:.8em;margin-left:5px;"></i>' : '')
               . '</a>'
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

<!-- ── Modale Rinomina ──────────────────────────────────── -->
<div class="modal-overlay" id="renameModal">
    <div class="modal-box">
        <h3><i class="fas fa-pencil-alt" style="color:#6a11cb;margin-right:6px;"></i> Rinomina file</h3>
        <input type="text" id="renameInput" placeholder="Nuovo nome file">
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeRename()">Annulla</button>
            <button class="btn-confirm" onclick="submitRename()"><i class="fas fa-check"></i> Rinomina</button>
        </div>
    </div>
</div>

<!-- Form nascosto per azioni POST -->
<form id="actionForm" method="post" style="display:none;">
    <input type="hidden" name="action"       id="actionInput">
    <input type="hidden" name="filename"     id="filenameInput">
    <input type="hidden" name="new_filename" id="newFilenameInput">
</form>

<script>
let _currentFile = '';

function openRename(filename) {
    _currentFile = filename;
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

function closeRename() {
    document.getElementById('renameModal').classList.remove('active');
    _currentFile = '';
}

function submitRename() {
    const newName = document.getElementById('renameInput').value.trim();
    if (!newName) { alert('Inserisci un nome valido.'); return; }
    document.getElementById('actionInput').value      = 'rename';
    document.getElementById('filenameInput').value    = _currentFile;
    document.getElementById('newFilenameInput').value = newName;
    document.getElementById('actionForm').submit();
}

function confirmDelete(filename) {
    if (!confirm('Eliminare definitivamente \u00ab' + filename + '\u00bb?\nQuesta operazione non \u00e8 reversibile.')) return;
    document.getElementById('actionInput').value   = 'delete';
    document.getElementById('filenameInput').value = filename;
    document.getElementById('actionForm').submit();
}

document.getElementById('renameModal').addEventListener('click', function(e) {
    if (e.target === this) closeRename();
});
document.getElementById('renameInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') submitRename();
    if (e.key === 'Escape') closeRename();
});
</script>
</body>
</html>
