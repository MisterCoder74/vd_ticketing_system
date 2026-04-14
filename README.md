# VD Ticketing System

Un sistema di gestione ticket leggero e portabile, progettato per offrire una soluzione semplice ed efficiente per il supporto tecnico.

## 📝 Descrizione
VD Ticketing System è una soluzione PHP minimalista che permette di gestire le richieste di supporto senza la complessità di un database SQL tradizionale. Utilizzando file JSON per la persistenza dei dati, il sistema è estremamente facile da installare e spostare tra diversi ambienti.

## 🚀 Funzionalità Principali
- **Gestione Ticket**: Creazione, modifica e tracciamento dei ticket di supporto.
- **Sistema di Commenti**: Comunicazione integrata all'interno di ogni ticket.
- **Ruoli Utente (RBAC)**: Accesso granulare basato su tre livelli di autorizzazione (Admin, Tecnico, Utente).
- **Interfaccia Moderna**: Design pulito e responsive basato sul font Inter, con una user experience fluida simile a una Single Page Application (SPA).
- **Zero Database**: Persistenza dei dati basata interamente su file JSON.

## 🛠 Requisiti
- **PHP**: 7.4 o superiore.
- **Permessi**: Accesso in scrittura alla cartella `data/` per il processo del server web.

## ⚙️ Installazione
1. Clonare il repository nella cartella del proprio server web (es. `/var/www/html`).
2. Configurare i permessi della cartella `data/` affinché il server web possa scriverci:
   ```bash
   chmod -R 775 data/
   # Se necessario (es. su Ubuntu/Debian):
   # sudo chown -R www-data:www-data data/
   ```
3. Aprire il browser all'indirizzo corrispondente (es. `http://localhost/vd_ticketing_system`).

## 🔐 Credenziali di Test
È possibile testare le diverse funzionalità del sistema utilizzando i seguenti account:

| Ruolo | Username | Password | Descrizione |
| :--- | :--- | :--- | :--- |
| **Amministratore** | `admin` | `admin` | Accesso completo a tutto il sistema. |
| **Tecnico** | `tech` | `tech` | Può gestire tutti i ticket e aggiornarne lo stato. |
| **Utente** | `user` | `user` | Può creare e visualizzare solo i propri ticket. |

## 🏗 Dettagli Tecnici

### Archiviazione Dati
Il sistema utilizza una struttura flat-file JSON situata nella directory `data/`:
- `users.json`: Anagrafica utenti e credenziali (password hashate).
- `tickets.json`: Contenuto e stato delle richieste di supporto.
- `comments.json`: Storico delle conversazioni per ogni ticket.

### Ruoli e Autorizzazioni
L'applicazione implementa un sistema di **Role-Based Access Control (RBAC)**:
- **Admin**: Gestione totale.
- **Technician (Tecnico)**: Visualizzazione globale dei ticket, modifica dello stato e aggiunta di commenti tecnici.
- **User (Utente)**: Creazione di nuovi ticket, visualizzazione e commento limitati ai propri inserimenti.

### Styling e Design System
Il design è stato curato per essere professionale e leggibile:
- **Tipografia**: Utilizzo del font "Inter" via Google Fonts per una leggibilità ottimale.
- **Color Palette**: Temi basati su tonalità di verde e giallo, gestiti tramite **variabili CSS** per una facile manutenzione.
- **Layout**: Completamente responsive, adattabile a smartphone, tablet e desktop.

## 📂 Struttura File
```text
.
├── index.php         # Punto di ingresso e layout principale
├── api.php           # Gestore delle richieste API (CRUD)
├── includes/
│   ├── auth.php      # Logica di autenticazione e sessioni
│   └── helpers.php   # Funzioni di utilità e gestione JSON
├── data/             # Database JSON (necessita permessi di scrittura)
├── css/
│   └── style.css     # Design e personalizzazione UI
├── js/
│   └── app.js        # Logica applicativa lato client (Vanilla JS)
└── screenshots/      # Immagini dimostrative del sistema
```
