# Marrison Master

Marrison Master è il componente principale del sistema di gestione remota di WordPress che consente di controllare e aggiornare più installazioni WordPress da un'unica interfaccia centralizzata.

## Caratteristiche

- **Gestione Multi-Sito**: Controlla e gestisci più installazioni WordPress da un'unica dashboard
- **Aggiornamenti Centralizzati**: Aggiorna plugin, temi e traduzioni su tutti i client connessi
- **Sistema di Backup**: Gestione completa dei backup con possibilità di ripristino remoto
- **Monitoraggio Stato**: Visualizza in tempo reale lo stato di tutti i client connessi
- **Repository Privati**: Supporta repository privati di plugin e temi
- **Interfaccia Intuitiva**: Dashboard user-friendly con indicatori LED di stato

## Installazione

1. Scarica l'ultima versione dal [repository GitHub](https://github.com/marrisonlab/marrison-master)
2. Carica il plugin nella directory `/wp-content/plugins/` del tuo sito WordPress
3. Attiva il plugin tramite il pannello di amministrazione WordPress
4. Configura le impostazioni nella pagina "Marrison Master" → "Impostazioni"

## Requisiti

- WordPress 5.0 o superiore
- PHP 7.0 o superiore
- Connessione internet per comunicare con i client

## Configurazione

### Repository Privati

Per configurare repository privati di plugin e temi:

1. Vai a "Marrison Master" → "Impostazioni"
2. Inserisci l'URL del tuo repository privato di plugin
3. Inserisci l'URL del tuo repository privato di temi
4. Salva le modifiche

### Client

Per connettere un client al master:

1. Installa il plugin [Marrison Agent](https://github.com/marrisonlab/marrison-agent) sul client
2. Configura il client per comunicare con l'URL del master
3. Il client apparirà automaticamente nella dashboard del master

## Utilizzo

### Dashboard Principale

La dashboard principale mostra:
- Elenco di tutti i client connessi
- Stato degli aggiornamenti (plugin, temi, traduzioni)
- Indicatori LED di stato (verde, giallo, rosso, nero)
- Ultima sincronizzazione
- Azioni rapide (Sync, Aggiorna, Ripristina, Cancella)

### Dettagli Client

Clicca su una riga del client per espandere i dettagli:
- Plugin installati e loro stato
- Temi installati
- Backup disponibili
- Traduzioni

### Operazioni di Gruppo

Seleziona più client per eseguire operazioni di gruppo:
- Sync massiva
- Aggiornamento massivo
- Operazioni di backup

## Indicatori di Stato

- **Verde** ✅: Tutto aggiornato
- **Giallo** ⚠️: Plugin disattivati presenti
- **Rosso** ❌: Aggiornamenti disponibili
- **Nero** ⚫: Client non raggiungibile

## Backup e Ripristino

### Creare Backup
I backup vengono creati automaticamente dal client prima di ogni aggiornamento.

### Ripristinare Backup
1. Espandi i dettagli di un client
2. Seleziona un backup dalla lista
3. Clicca su "Ripristina"
4. Conferma l'operazione

## Sicurezza

- Tutte le comunicazioni tra master e client sono sicure
- Autenticazione tramite nonce WordPress
- Controllo degli accessi basato sui ruoli WordPress

## Supporto

Per supporto e documentazione aggiuntiva:
- [Repository GitHub](https://github.com/marrisonlab/marrison-master)
- [Issue Tracker](https://github.com/marrisonlab/marrison-master/issues)
- Visita [marrisonlab.com](https://marrisonlab.com)

## Sviluppo

Questo plugin è open source e contribuzioni sono benvenute!

### Installazione per Sviluppo

1. Clona il repository: `git clone https://github.com/marrisonlab/marrison-master.git`
2. Attiva il plugin nel tuo ambiente di sviluppo WordPress
3. Contribuisci seguendo le linee guida standard di WordPress

## Licenza

Questo plugin è rilasciato sotto licenza GPL v2 o successiva.

## Autore

**Angelo Marra**  
Sito web: [marrisonlab.com](https://marrisonlab.com)  
GitHub: [marrisonlab](https://github.com/marrisonlab)

---

Per ulteriori informazioni, visita il [sito ufficiale del progetto](https://github.com/marrisonlab/marrison-master).