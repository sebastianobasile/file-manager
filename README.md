# 📁 PHP File Manager — File Unico

> Navigatore di file e cartelle per server PHP, completamente autonomo in un unico file.  
> Ideale per pubblicare documenti su siti scolastici o intranet.

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)
![Licenza](https://img.shields.io/badge/Licenza-MIT-green)
![Versione](https://img.shields.io/badge/Versione-6.3-blue)
![Scuole italiane](https://img.shields.io/badge/Destinatari-Scuole%20italiane-red)

---

## 📸 Screenshot

| Schermata di login | Interfaccia principale | Azioni File e Cartelle |
|:---:|:---:|:---:|
| ![Login](screenshots/login.png) | ![Home](screenshots/home.png) | ![Azioni](screenshots/Azioni_file_e_cartelle.jpg) |

---

## ✨ Caratteristiche

- **File unico** — tutto il necessario è in `index.php`, nessun file esterno richiesto
- **Navigazione ricorsiva** di cartelle e sottocartelle
- **Protezione password** opzionale (attivabile/disattivabile in un'unica riga)
- **Apertura file inline o download** configurabile per tipo di estensione
- **📤 Upload** nella cartella corrente o direttamente nella root, con controllo su dimensione ed estensioni
- **📁 Gestione cartelle completa** — crea, rinomina ed elimina cartelle direttamente dall'interfaccia
- **📦 Sposta file** tra cartelle tramite modale dedicata, affidabile su tutti i browser e dispositivi
- **📋 Copia file** tra cartelle, con rinomina automatica anti-sovrascrittura
- **🖨️ Cartella pubblica** (`stampa`) — sezione accessibile senza login tramite link diretto, ideale per condividere documenti con genitori e studenti
- **⏱️ Scadenza per-file** — ogni file nella cartella pubblica può avere un timer di scadenza personalizzato; i file scaduti vengono eliminati automaticamente
- **🔐 Password per-file** — protezione individuale con hash bcrypt sui singoli file della cartella pubblica, con modale di sblocco integrata
- **File riservati** segnalati con icona 🔒 tramite prefisso nel nome
- **Apertura in nuova scheda** controllabile globalmente o per singolo file
- **Pagine di errore personalizzate** — 403 e 404 servite dalla cartella `/errore/`
- **Interfaccia responsive** compatibile con desktop e mobile
- **Nessun database** — lavora direttamente sul filesystem

---

## 🚀 Installazione

1. Scarica `index.php` e `.htaccess`
2. Caricali **entrambi** nella stessa cartella del server che vuoi esporre
3. (Facoltativo) Carica le pagine `403.html` e `404.html` nella sottocartella `/errore/`
4. (Facoltativo) Crea manualmente la cartella `stampa/` per la sezione pubblica
5. Apri il browser e visita quella cartella

> ⚠️ **Importante:** caricare sempre `.htaccess` insieme a `index.php`. Senza di esso, i file nella cartella sono accessibili direttamente tramite URL anche senza password.

Nessuna dipendenza da installare. Nessuna configurazione del database.

---

## ⚙️ Configurazione

Tutta la configurazione si trova **nelle prime righe di `index.php`**, nel blocco `CONFIGURAZIONE`.

```php
// ── 🔐 ACCESSO ──────────────────────────────────────────────────
$LOGIN_REQUIRED = false;       // true = richiede password | false = accesso libero
$PASSWORD       = "la_tua_password";

// ── 🪟 APERTURA FILE ─────────────────────────────────────────────
$OPEN_IN_NEW_TAB = true;       // true = nuova scheda | false = stessa finestra

// ── 📁 FILE E CARTELLE NASCOSTI ──────────────────────────────────
$EXCLUDED_FILES   = ['index.php', '.htaccess'];
$EXCLUDED_FOLDERS = ['NomeCartellaNascosta'];

// ── 📄 ESTENSIONI AMMESSE ────────────────────────────────────────
$ALLOWED_EXTENSIONS  = ['pdf', 'jpg', 'docx', 'xlsx', 'html'];
$INLINE_EXTENSIONS   = ['pdf', 'html', 'jpg', 'jpeg', 'png'];
$DOWNLOAD_EXTENSIONS = ['docx', 'xlsx', 'doc', 'xls'];

// ── 📤 UPLOAD ────────────────────────────────────────────────────
$UPLOAD_ENABLED     = true;
$UPLOAD_FOLDER      = '';      // '' = cartella radice | 'upload' = sottocartella
$UPLOAD_MAX_SIZE_MB = 10;
$UPLOAD_ALLOWED_EXT = ['pdf', 'jpg', 'docx', 'xlsx'];

// ── 🖨️ CARTELLA PUBBLICA ────────────────────────────────────────
$PUBLIC_FOLDER              = 'stampa'; // Cartella accessibile senza login
$PUBLIC_FOLDER_EXPIRE_HOURS = 24;       // Scadenza default in ore (0 = mai)
```

### Opzioni principali

| Parametro | Valori | Descrizione |
|---|---|---|
| `$LOGIN_REQUIRED` | `true` / `false` | Attiva o disattiva la schermata di login |
| `$PASSWORD` | testo | Password di accesso in chiaro |
| `$OPEN_IN_NEW_TAB` | `true` / `false` | Apertura globale in nuova scheda |
| `$EXCLUDED_FILES` | array | File da non mostrare nella lista |
| `$EXCLUDED_FOLDERS` | array | Cartelle da non mostrare |
| `$ALLOWED_EXTENSIONS` | array | Estensioni visibili e scaricabili |
| `$INLINE_EXTENSIONS` | array | Estensioni aperte nel browser |
| `$DOWNLOAD_EXTENSIONS` | array | Estensioni scaricate direttamente |
| `$UPLOAD_ENABLED` | `true` / `false` | Attiva o disattiva la funzione di upload |
| `$UPLOAD_MAX_SIZE_MB` | numero intero | Dimensione massima file in MB |
| `$UPLOAD_ALLOWED_EXT` | array | Estensioni accettate in upload |
| `$PUBLIC_FOLDER` | testo | Nome della cartella pubblica (default: `stampa`) |
| `$PUBLIC_FOLDER_EXPIRE_HOURS` | numero intero | Scadenza automatica di default in ore (`0` = mai) |

---

## 🏷️ Convenzioni sui nomi dei file

| Convenzione | Effetto |
|---|---|
| Nome con prefisso `RISERVATO_` | Mostra icona 🔒 (accesso visivamente riservato) |
| Nome con prefisso `PRIV_` | Stessa cosa (alias alternativo) |
| Nome con suffisso `_NUOVO` | Forza apertura in nuova scheda (ignora impostazione globale) |

I prefissi vengono rimossi dal nome visualizzato all'utente.

---

## 📦 Sposta e copia file

I file possono essere **spostati o copiati tra cartelle** tramite modale dedicata:

1. Clicca sull'icona di sposta (📦) o copia (📋) accanto al file
2. Si apre una modale con l'elenco delle cartelle disponibili
3. Seleziona la destinazione e conferma

Se il file di destinazione esiste già, la copia viene rinominata automaticamente con un suffisso timestamp (`_copia_AAAAMMGG_HHMMSS`). Lo spostamento invece segnala il conflitto senza sovrascrivere.

> ℹ️ Quando un file viene **spostato nella cartella pubblica**, gli viene assegnata automaticamente la scadenza default configurata in `$PUBLIC_FOLDER_EXPIRE_HOURS`.  
> Quando un file **esce dalla cartella pubblica**, scadenza e password vengono rimosse automaticamente.

---

## 📁 Gestione cartelle

Dall'interfaccia è possibile, senza uscire dal browser:

| Azione | Descrizione |
|---|---|
| **Nuova cartella** | Crea una sottocartella nella directory corrente |
| **Rinomina** | Modifica il nome di una cartella esistente |
| **Elimina** | Rimuove una cartella (solo se vuota) |

---

## 🖨️ Cartella pubblica

La cartella pubblica (default: `stampa/`) è accessibile da chiunque abbia il link **senza bisogno di password**, ideale per condividere circolari o documenti con genitori e studenti.

**Come attivarla:**
1. Crea manualmente la cartella `stampa/` sul server (via FTP o cPanel)
2. Accedila tramite `https://tuosito.it/documenti/?stampa`

**Funzionalità disponibili per i file nella cartella pubblica:**

| Funzione | Descrizione |
|---|---|
| **⏱️ Scadenza** | Imposta data e ora di scadenza; scaduto, il file viene eliminato automaticamente |
| **🔐 Password** | Proteggi il singolo file con una password (hash bcrypt); l'utente la inserisce in una modale |
| **Pulsanti rapidi** | Scadenza preimpostata in 30 min / 1 h / 3 h / 24 h con un click |

> ⚠️ Se `$LOGIN_REQUIRED = false` (sito completamente pubblico), imposta `$UPLOAD_ENABLED = false` per non esporre la funzione di upload.

---

## 🔒 Sicurezza

Il tool adotta **due livelli di protezione** che lavorano insieme:

1. **`.htaccess`** (lato server Apache) — instrada tutte le richieste attraverso `index.php`, bloccando l'accesso diretto a qualsiasi file tramite URL. Anche chi conosce il percorso esatto del file non riesce a scaricarlo senza passare da `index.php`.
2. **`index.php`** (lato PHP) — verifica l'autenticazione prima di servire qualsiasi file tramite il parametro `?file=`. Blocca anche eventuali tentativi di directory traversal.

Ulteriori dettagli:
- Solo le estensioni definite in `$ALLOWED_EXTENSIONS` vengono servite
- La sessione scade dopo **1 ora** di inattività
- Le password per-file sono salvate come hash **bcrypt** in `.passwords.json` (mai in chiaro)
- I file di sistema (`index.php`, `.htaccess`, `.expires.json`, `.passwords.json`, ecc.) sono esclusi anche se richiesti via URL

> ⚠️ La password principale (`$PASSWORD`) è memorizzata **in chiaro** nel file. Per ambienti ad alta sicurezza si consiglia l'autenticazione a livello di server web.

---

## 🗂️ Pagine di errore personalizzate

Il tool supporta pagine di errore personalizzate per i codici **403** e **404**.  
Basta creare una sottocartella `/errore/` con i file:

```
errore/
├── 403.html
└── 404.html
```

Il file `.htaccess` incluso reindirizza automaticamente gli errori a queste pagine.

---

## 📋 Requisiti

- PHP **7.4** o superiore
- Server Apache con `mod_rewrite` abilitato e `AllowOverride All`
- Estensione `session` abilitata (standard in quasi tutti i server)
- Accesso in lettura (e scrittura, per upload/gestione cartelle) alla directory da servire

---

## 📂 Struttura tipica del progetto

```
public_html/documenti/
├── index.php                   ← gestore principale
├── .htaccess                   ← blocca accesso diretto ai file ⚠️ necessario
├── errore/
│   ├── 403.html                ← pagina errore accesso negato (facoltativa)
│   └── 404.html                ← pagina errore non trovato (facoltativa)
├── stampa/                     ← cartella pubblica (accesso senza login)
│   ├── .expires.json           ← scadenze per-file (generato automaticamente)
│   └── .passwords.json         ← hash password per-file (generato automaticamente)
├── Circolare_01.pdf
├── Avvisi/
│   ├── Avviso_febbraio.pdf
│   └── RISERVATO_Verbale.pdf
└── Modulistica/
    └── Modulo_iscrizione.docx
```

---

## 📋 Changelog

### v6.3
- **Spostamento file via modale** — rimosso il drag-and-drop (poco affidabile su mobile e alcuni browser) in favore di una modale dedicata, più precisa e accessibile
- **Copia file** tra cartelle, con rinomina automatica anti-sovrascrittura (`_copia_AAAAMMGG_HHMMSS`)
- **Cartella pubblica** (`stampa/`) — accesso senza login tramite `?stampa`, ideale per condivisione con genitori e studenti
- **Scadenza per-file** — timer personalizzabile con pulsanti rapidi (30 min / 1 h / 3 h / 24 h) e auto-pulizia dei file scaduti
- **Password per-file** — protezione individuale con hash bcrypt, modale di sblocco integrata nella pagina pubblica
- Gestione automatica di scadenza e password all'ingresso/uscita dalla cartella pubblica

### v6.2
- Aggiunta azione **rinomina cartella** con modale condivisa
- Aggiunta azione **elimina cartella** (solo se vuota)
- Fix bug drag-and-drop: gli elementi `<a>` figli non intercettavano più l'evento `dragstart`

### v6.1
- Upload diretto nella **cartella root**
- **Creazione cartelle** dall'interfaccia senza FTP
- **Drag-and-drop** per spostare file tra cartelle
- Pagine di errore personalizzate **403/404** in `/errore/`
- Fix tipo MIME per file `.mid`/`.midi`
- Routing completo tramite `.htaccess` — tutti i percorsi passano per `index.php`

### v6.0
- Prima versione consolidata in file unico (`index.php`)
- Navigazione ricorsiva, protezione password, apertura inline/download

---

## ☕ Supporta il progetto

Se questo strumento ti è utile, puoi offrire un contributo volontario tramite PayPal:

[![Donate PayPal](https://img.shields.io/badge/Dona%20con-PayPal-009cde?logo=paypal&logoColor=white)](https://www.paypal.com/paypalme/superscuola)

Ogni contributo, anche piccolo, aiuta a mantenere e migliorare il progetto. Grazie! 🙏

---

## 📜 Licenza

Distribuito con licenza **MIT** — vedi il file [LICENSE](LICENSE).  
Uso libero, anche in contesto scolastico istituzionale.

---

## 👤 Autore

**Sebastiano Basile**  
[basile.superscuola.com](https://basile.superscuola.com/contatti) · [GitHub](https://github.com/sebastianobasile)
