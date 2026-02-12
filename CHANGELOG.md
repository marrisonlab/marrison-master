# Changelog

Tutti i cambiamenti significativi a questo progetto saranno documentati in questo file.

Il formato è basato su [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
e questo progetto aderisce a [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.6] - 2026-02-12
### Aggiunto
- Feedback visivo immediato (notifiche admin) per il controllo aggiornamenti GitHub
- Messaggi di errore dettagliati in caso di fallimento della chiamata API GitHub
- Migliore gestione degli errori HTTP e API nel modulo di aggiornamento

## [1.0.3] - 2026-02-12
### Aggiunto
- Anteprima dei contenuti (plugin e temi) nella pagina delle impostazioni inserendo l'URL del repository
- Pulsante per aggiornare manualmente la cache e i dati dei repository privati nella pagina impostazioni

## [1.0.2] - 2026-02-12
### Aggiunto
- Pulsante "Cerca Aggiornamenti su GitHub" nel pannello admin per forzare il controllo versione
- Supporto per tag di versione con o senza prefisso "v" (es. v1.0.2 o 1.0.2)

### Rimosso
- Funzionalità "Forza Refresh Repository" obsoleta e non utilizzata

## [1.0.1] - 2026-02-12
### Modificato
- Aggiornati metadati plugin (Autore, URI)

## [1.0.0] - 2024-02-12

### Aggiunto
- Versione iniziale del plugin Marrison Master
- Dashboard principale per la gestione multi-sito
- Sistema di indicatori LED per lo stato dei client (verde, giallo, rosso, nero)
- Operazioni di sincronizzazione remota con i client
- Sistema di aggiornamento centralizzato per plugin, temi e traduzioni
- Gestione completa dei backup con ripristino remoto
- Supporto per repository privati di plugin e temi
- Operazioni di gruppo (sync massiva, aggiornamento massivo)
- Interfaccia di dettaglio per ogni client
- Sistema di notifiche e messaggi di stato
- Pulsante di refresh forzato della cache repository
- Protezione contro operazioni duplicate con disabilitazione bottoni
- Auto-sync dopo operazioni di ripristino
- Gestione degli errori con messaggi dettagliati
- Supporto per la gestione dei plugin disattivati
- Logica di priorità per indicatori di stato (nero > rosso > giallo > verde)

### Modificato
- Migliorata la gestione delle versioni PHP nel core di aggiornamento
- Ottimizzata la gestione degli URL di download con sanitizzazione
- Migliorata la gestione degli errori di rete e timeout
- Ottimizzate le prestazioni per operazioni bulk

### Corretto
- Risolto problema di aggiornamento con versioni PHP richieste troppo alte
- Corretto gestione URL di download malformati
- Risolto problema di visualizzazione dopo pulizia cache
- Corretto conteggio client per operazioni di gruppo
- Risolti vari bug di compatibilità con diverse versioni di WordPress

### Sicurezza
- Implementata validazione rigorosa dei dati in entrata
- Aggiunti nonce per tutte le operazioni AJAX
- Implementato controllo dei permessi basato sui ruoli WordPress
- Sanitizzazione di tutti i dati di output

## [Pre-1.0.0] - Fasi di sviluppo iniziali

Le versioni precedenti alla 1.0.0 erano fasi di sviluppo e test interno.

---

## Come aggiornare

Per aggiornare il plugin:

1. **Backup**: Crea sempre un backup prima di aggiornare
2. **Download**: Scarica la nuova versione dal [repository GitHub](https://github.com/marrisonlab/marrison-master)
3. **Installazione**: Sostituisci i file del plugin con la nuova versione
4. **Test**: Verifica che tutto funzioni correttamente
5. **Sync**: Esegui una sincronizzazione completa con tutti i client

## Segnalazione problemi

Se incontri problemi con questo plugin:

1. Verifica di avere la versione più recente
2. Controlla i requisiti di sistema
3. Consulta la [documentazione](README.md)
4. Apri una [issue su GitHub](https://github.com/marrisonlab/marrison-master/issues)

---

**Autore**: Angelo Marra  
**Sito**: [marrisonlab.com](https://marrisonlab.com)  
**Repository**: [marrisonlab/marrison-master](https://github.com/marrisonlab/marrison-master)