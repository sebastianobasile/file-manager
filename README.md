# 📁 PHP File Manager — File Unico

> Navigatore di file e cartelle per server PHP, completamente autonomo in un unico file.  
> Ideale per pubblicare documenti su siti scolastici o intranet.

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)
![Licenza](https://img.shields.io/badge/Licenza-MIT-green)
![Versione](https://img.shields.io/badge/Versione-6.0-blue)
![Scuole italiane](https://img.shields.io/badge/Destinatari-Scuole%20italiane-red)

---

## 📸 Screenshot

| Schermata di login | Interfaccia principale |
|:---:|:---:|
| ![Login](screenshots/login.png) | ![Home](screenshots/home.png) |

---

## ✨ Caratteristiche

- **File unico** — tutto il necessario è in `index.php`, nessun file esterno richiesto
- **Navigazione ricorsiva** di cartelle e sottocartelle
- **Protezione password** opzionale (attivabile/disattivabile in un'unica riga)
- **Apertura file inline o download** configurabile per tipo di estensione
- **File e cartelle nascosti** definibili con semplici array
- **File riservati** segnalati con icona 🔒 tramite prefisso nel nome
- **Apertura in nuova scheda** controllabile globalmente o per singolo file
- **Interfaccia responsive** compatibile con desktop e mobile
- **Nessun database** — lavora direttamente sul filesystem

---

## 🚀 Installazione

1. Scarica `index.php`
2. Caricalo nella cartella del server che vuoi esporre
3. Apri il browser e visita quella cartella

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

---

## 🏷️ Convenzioni sui nomi dei file

| Convenzione | Effetto |
|---|---|
| Nome con prefisso `RISERVATO_` | Mostra icona 🔒 (accesso visivamente riservato) |
| Nome con prefisso `PRIV_` | Stessa cosa (alias alternativo) |
| Nome con suffisso `_NUOVO` | Forza apertura in nuova scheda (ignora impostazione globale) |

I prefissi vengono rimossi dal nome visualizzato all'utente.

---

## 🔒 Sicurezza

- Il parametro `?file=` viene sanitizzato per impedire **directory traversal**
- Solo le estensioni definite in `$ALLOWED_EXTENSIONS` vengono servite
- Se `$LOGIN_REQUIRED = true`, i file non sono accessibili nemmeno via URL diretto senza autenticazione
- La sessione scade dopo **1 ora** di inattività

> ⚠️ La password è memorizzata **in chiaro** nel file. Per ambienti ad alta sicurezza si consiglia l'hash MD5/bcrypt o l'autenticazione del server web.

---

## 📋 Requisiti

- PHP **7.4** o superiore
- Estensione `session` abilitata (standard in quasi tutti i server)
- Accesso in lettura alla directory da servire

---

## 📂 Struttura tipica del progetto

```
public_html/documenti/
├── index.php           ← questo file
├── Circolare_01.pdf
├── Avvisi/
│   ├── Avviso_febbraio.pdf
│   └── RISERVATO_Verbale.pdf
└── Modulistica/
    └── Modulo_iscrizione.docx
```

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
