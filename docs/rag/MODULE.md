# Modulo ERP — operazioni aziendali, magazzino e contabilità

## In parole semplici

Il modulo **ERP** (Enterprise Resource Planning) raccoglie la logica **gestionale** di Laraplate: anagrafiche societarie, clienti, opportunità di vendita, ordini, magazzino, movimenti di stock, documenti di acquisto e vendita, contabilità in partita doppia, codici fiscali/IVA, periodi e anni fiscali, fino agli aspetti legati alla fatturazione elettronica dove implementati. È il punto di incontro tra **operatività commerciale** e **rappresentazione contabile**.

## A chi serve

- **Commerciale / project manager**: clienti, progetti, attività, lead, opportunità, preventivi, ordini di vendita.
- **Logistica / magazzino**: articoli (`Item`), giacenze (`StockLevel`), movimenti (`StockMovement`), magazzini, documenti di **consegna** (delivery note) e **ricezione merce** (goods receipt), ordini di acquisto.
- **Amministrazione e ragioniere / commercialista** (lettura semi-tecnica): piano dei conti, registrazioni in **prima nota** (`JournalEntry` con righe), fatture e relative righe, codici imposta, chiusure di periodo fiscale, numerazioni documentali, eventuali submission e-fattura. Questa documentazione **non** sostituisce normativa o adeguamenti legali: descrive **cosa fa il software** nel modello dati, non cosa obbliga il codice civile o il Sistema di Interscambio in un dato anno solare.
- **Sviluppatore**: servizi di dominio (posting, tasse, magazzino), modelli Eloquent, Filament resources, observer e migrazioni nel modulo.

## Funzionalità principali (panorama)

### Anagrafiche e CRM leggero

- **Company**, **Customer**, **Contact**, **Site** — struttura organizzativa e relazioni commerciali.
- **Lead**, **Opportunity**, **OpportunityStage**, **Activity**, **Task**, **TimeEntry** — pipeline vendite e tracciamento attività.
- **Project** — collegamento tra opportunità/contratti e lavoro erogato.

### Vendite e acquisti

- **Quotation** / **QuotationItem** — offerte commerciali.
- **SalesOrder** / **SalesOrderLine** — ordini cliente vincolanti per logistica e fatturazione.
- **PurchaseOrder** / **PurchaseOrderLine** — ordini a fornitore.
- **DeliveryNote** / **DeliveryNoteLine** — documenti di uscita merce dal magazzino verso il cliente (con servizi dedicati al posting di magazzino quando il documento viene confermato/postato, secondo implementazione).
- **GoodsReceipt** / **GoodsReceiptLine** — ingressi merce collegati agli acquisti, con servizi di aggiornamento giacenze.

### Magazzino e costi

- **Warehouse**, **StockLevel**, **StockMovement**, **StockCostLayer** — tracciamento quantità, valorizzazioni e storico movimenti (utile per analisi e, dove previsto, per metodi di valorizzazione).

### Contabilità e fiscalità

- **Account** — piano dei conti; il provider predefinito nel `ERPServiceProvider` punta a un **piano dei conti italiano** (`ItalianCoaProvider`) installabile tramite `ChartOfAccountsInstaller`.
- **JournalEntry** / **JournalEntryLine** — registrazioni contabili; il servizio `JournalPostingService` orchestra la scrittura coerente con le regole del dominio.
- **FiscalYear**, **FiscalPeriod** — struttura temporale degli esercizi; `FiscalCalendarInstaller` e `FiscalPeriodCloser` supportano setup e chiusure di periodo (da usare con procedure interne e revisione del commercialista).
- **TaxCode**, **TaxLineCalculator**, **TaxCodeSupersessionService** — gestione aliquote / nature e successioni normative tra codici IVA.
- **Invoice** / **InvoiceLine**, **EInvoiceSubmission** — ciclo fatture e tracciamento invii dove il modulo è esteso verso l’e-fattura.

### Amministrazione documenti e valute

- **DocumentSequence** + `DocumentNumberAllocator` — numerazioni progressive per tipi documento (coerenza legale e di audit).
- **CurrencyConverter** — binding di default a `NoopCurrencyConverter` (nessuna conversione): in progetti multi-valuta si sostituisce l’implementazione con un servizio reale (API di cambio, tabella giornaliera, ecc.).

### Altri elementi di dominio

- **PriceList** / **PriceListItem** — listini.
- **Preset** e pivot **Presettable** — meccanismi di applicazione rapida di configurazioni a più entità (riduzione errori di data entry ripetuto).

## Interfaccia amministrativa (Filament)

Il modulo espone numerose **Filament Resources** sotto `Modules/ERP/app/Filament/Resources/` (clienti, aziende, progetti, ordini, fatture, prima nota, codici IVA, periodi fiscali, articoli, giacenze, documenti logistici, sequenze numeriche, ecc.). L’esperienza utente è quella standard Filament v5: elenchi filtrabili, form con relazioni, azioni di tabella e pagine dedicate a creazione/modifica/visualizzazione.

## Come si usa in pratica (flussi tipici)

1. **Setup contabile iniziale**: caricare piano dei conti, definire anni e periodi fiscali, configurare codici IVA coerenti con l’attività; definire sequenze numeriche per fatture, ordini e registrazioni.
2. **Vendita**: creare anagrafica cliente → opportunità / preventivo → ordine di vendita; in parallelo definire articoli e listini se necessario.
3. **Evasione**: generare documenti di consegna collegati agli ordini; alla **conferma/posting** (secondo le regole implementate) il magazzino si aggiorna tramite i servizi `DeliveryNoteInventoryService` e affini.
4. **Acquisto**: ordine fornitore → ricezione merce con `GoodsReceiptInventoryService` per riflettere ingressi in stock.
5. **Contabilità**: generare o importare registrazioni in prima nota; chiudere periodi solo dopo controllo reconciliazione con estratti conto e policy aziendali.

## Estensioni per lo sviluppatore

- Sostituire `CurrencyConverter` con un’implementazione concreta se serve multi-valuta.
- Rispettare gli **observer** e i **service** già presenti quando si aggiungono nuovi stati documento: duplicare la logica di magazzino o IVA nei controller Filament senza passare dai servizi centralizza il rischio di inconsistenze contabili.

## Dipendenze

- **Core** (autenticazione, permessi, infrastruttura).
- **AI** opzionale per funzionalità intelligenti trasversali (ricerca, assistenti) se abilitate a livello progetto.

## Avvertenza

I numeri prodotti dal modulo ERP hanno impatto **fiscale e civilistico**. Ogni installazione richiede **parametrizzazione**, **test** e **validazione** da parte del responsabile amministrativo prima dell’uso in produzione.
