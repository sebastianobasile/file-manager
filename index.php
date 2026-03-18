<?php
/**
 * FILE MANAGER v6.0 - Sebastiano Basile – superscuola.com – sostegno.t.me
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
$PASSWORD       = "elisab";    // Password di accesso (testo in chiaro)

// ── 🪟 APERTURA FILE ─────────────────────────────────────────────
$OPEN_IN_NEW_TAB = true;       // true = nuova scheda | false = stessa finestra
                                // Nota: aggiungendo _NUOVO al nome file si forza sempre nuova scheda
                                //       indipendentemente da questa impostazione

// ── 📁 FILE E CARTELLE NASCOSTI ──────────────────────────────────
// Aggiungi o rimuovi voci per nascondere elementi dalla lista
$EXCLUDED_FILES   = ['index.php', '.htaccess', 'test.php'];
$EXCLUDED_FOLDERS = ['Ammucciata', 'Test'];

// ── 📄 ESTENSIONI ────────────────────────────────────────────────
// $ALLOWED_EXTENSIONS   → estensioni visibili nella lista
// $INLINE_EXTENSIONS    → si aprono direttamente nel browser (inline)
// $DOWNLOAD_EXTENSIONS  → vengono scaricati
$ALLOWED_EXTENSIONS   = ['php', 'pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'doc', 'xls', 'html', 'mid', 'zip'];
$INLINE_EXTENSIONS    = ['pdf', 'html', 'jpg', 'jpeg', 'png', 'mid', 'zip'];
$DOWNLOAD_EXTENSIONS  = ['docx', 'xlsx', 'doc', 'xls'];

// ── 🏷️ FILE RISERVATI (mostrano il lucchetto 🔒) ─────────────────
$RESERVED_PREFIXES = ['RISERVATO_', '🔐', 'PRIV_'];
$NEW_TAB_SUFFIX    = '_NUOVO';   // Suffisso per forzare apertura in nuova scheda

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
// 2. GESTIONE FILE (sostituisce download.php)
// ================================================================

if (isset($_GET['file'])) {

    // Blocca accesso se autenticazione richiesta e utente non autenticato
    if ($LOGIN_REQUIRED && !$is_authenticated) {
        header('Location: index.php');
        exit;
    }

    // Sanitizza il percorso: impedisce directory traversal
    $requested = ltrim(str_replace(['..', '\\'], '', $_GET['file']), '/');
    $file_path  = './' . $requested;

    // Blocca esplicitamente i file di sistema anche se qualcuno li passa via ?file=
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

    // File PHP: eseguito direttamente tramite include
    if ($ext === 'php') {
        include $file_path;
        exit;
    }

    // Mappa MIME type
    $mime_map = [
        'pdf'  => 'application/pdf',
        'html' => 'text/html; charset=UTF-8',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'doc'  => 'application/msword',
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
    </style>
</head>
<body>
<div class="container">
    <div class="title-group"><h1><?= $MAIN_TITLE ?></h1></div>
    <div class="info-box"><strong>💡 Nota:</strong> <?= $MAIN_NOTE ?></div>

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
    foreach ($files as $fd) {
        $fname    = $fd['name'];
        $ext      = $fd['ext'];
        $reserved = $fd['reserved'];

        $clean = pathinfo($fname, PATHINFO_FILENAME);
        $force_new = checkNewTabSuffix($clean, $NEW_TAB_SUFFIX);
        $disp_name = getDisplayName($clean . '.' . $ext, $RESERVED_PREFIXES);
        $path      = $current_folder ? $current_folder . '/' . $fname : $fname;

        // Icona per tipo
        $icon = 'fa-file'; $iclass = '';
        if ($ext === 'pdf')                        { $icon = 'fa-file-pdf';    $iclass = 'pdf'; }
        elseif (in_array($ext,['jpg','png','jpeg'])){ $icon = 'fa-image';      $iclass = 'jpg'; }
        elseif (in_array($ext,['docx','doc']))     { $icon = 'fa-file-word';   $iclass = 'docx'; }
        elseif (in_array($ext,['xlsx','xls']))     { $icon = 'fa-file-excel';  $iclass = 'xlsx'; }
        elseif (in_array($ext,['php','html']))     { $icon = 'fa-code';        $iclass = 'code'; }

        $l_class = '';
        if ($reserved) { $icon = 'fa-lock'; $iclass = 'lock'; $l_class = 'reserved'; }

        // Apertura: nuova scheda se forzata dal suffisso, o se OPEN_IN_NEW_TAB=true
        $new_tab = $force_new || $OPEN_IN_NEW_TAB;
        $target  = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

        $link = 'index.php?file=' . urlencode($path);

        echo '<li><a href="' . htmlspecialchars($link) . '" class="' . $l_class . ' resource-link"' . $target . '>'
           . '<span class="file-icon ' . $iclass . '"><i class="fas ' . $icon . '"></i></span> '
           . htmlspecialchars($disp_name)
           . ($new_tab ? ' <i class="fas fa-external-link-alt" style="font-size:.8em;margin-left:5px;"></i>' : '')
           . '</a></li>';
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
</body>
</html>
