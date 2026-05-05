# Guida semplice a un ERP

## 1) Cos'e un ERP (in parole semplici)

Un ERP e un sistema che mette insieme, in un solo flusso, le attivita principali di un'azienda:

- vendite
- acquisti
- magazzino
- contabilita
- controllo e report

L'idea chiave e questa: **i dati si inseriscono una volta sola** e poi alimentano tutti i processi successivi.

Esempio pratico:

1. crei un ordine cliente
2. prepari la consegna
3. scarichi il magazzino
4. emetti fattura
5. registri automaticamente i movimenti contabili

In un ERP ben fatto, questi passaggi sono collegati e coerenti tra loro.

---

## 2) Concetti base da capire subito

### Documento

Un "documento" e un evento aziendale tracciato (es. ordine, DDT, fattura).

- Ha una **testata** (data, cliente/fornitore, stato, note)
- Ha delle **righe** (cosa, quanto, prezzo)

### Stato

Ogni documento passa da stati diversi:

- bozza
- confermato
- parziale
- completato / chiuso

Gli stati servono per sapere "a che punto siamo" e per bloccare modifiche non piu lecite.

### Quantita evase

Nei documenti di vendita/acquisto non basta sapere "ordinato", serve sapere anche:

- quanto e stato consegnato/ricevuto
- quanto e stato fatturato
- quanto resta da evadere

### Registrazione contabile (posting)

"Postare" significa trasformare un fatto operativo in scrittura contabile ufficiale.

- Prima del posting: il documento e operativo
- Dopo il posting: ha effetto economico/contabile

### Tracciabilita

Ogni passaggio deve poter essere ricostruito:

- da quale documento nasce un altro
- quale riga ha generato quale movimento
- quale scrittura contabile e collegata a quale evento operativo

---

## 3) Le entita principali (modello mentale)

Di seguito il modello tipico (semplificato):

- **Company**: azienda/tenant (base di tutto)
- **Customer**: cliente (in futuro puo convergere in "Party")
- **Item**: articolo/prodotto/servizio gestito
- **Warehouse**: magazzino
- **StockLevel**: giacenza attuale per articolo+magazzino
- **StockMovement**: movimento di carico/scarico
- **Quotation**: preventivo
- **SalesOrder** + **SalesOrderLine**: ordine cliente
- **DeliveryNote** + **DeliveryNoteLine**: DDT di consegna
- **PurchaseOrder** + **PurchaseOrderLine**: ordine fornitore
- **GoodsReceipt** + **GoodsReceiptLine**: ricezione merce
- **Invoice** + **InvoiceLine**: fattura
- **JournalEntry** + **JournalEntryLine**: registrazione di partita doppia
- **Account**: conto del piano dei conti
- **DocumentSequence**: numeratori documentali

---

## 4) Workflow principali (semplici ma completi)

## 4.1 Workflow vendita (Order to Cash)

1. Preventivo (opzionale)
2. Ordine cliente
3. DDT di consegna
4. Fattura
5. Incasso (fase successiva)

Effetti tipici:

- il DDT scarica magazzino
- il DDT puo aggiornare lo stato di evasione ordine
- la fattura genera contabilita ricavi/IVA/clienti
- il costo del venduto (COGS) puo essere registrato al DDT o in fattura (scelta di processo)

## 4.2 Workflow acquisti (Procure to Pay)

1. Ordine fornitore
2. Ricezione merce (GR)
3. Fattura fornitore
4. Pagamento (fase successiva)

Effetti tipici:

- la ricezione carica magazzino
- la ricezione aggiorna quantita ricevute su ordine
- la fattura acquisto genera contabilita costi/IVA/fornitori
- poi segue la riconciliazione ordine-ricezione-fattura (3-way match)

## 4.3 Workflow magazzino

- Carico: aumenta giacenza
- Scarico: diminuisce giacenza
- Ogni movimento deve avere origine chiara (documento sorgente)
- Le giacenze sono derivate dai movimenti, non "inventate a mano"

---

## 5) Relazioni fondamentali tra entita

## 5.1 Relazioni documento -> documento

- Un `SalesOrder` puo avere piu `DeliveryNote`
- Un `DeliveryNote` puo riferire righe di `SalesOrderLine`
- Un `PurchaseOrder` puo avere piu `GoodsReceipt`

Questo crea la catena di processo.

## 5.2 Relazioni documento -> magazzino

- `DeliveryNoteLine` -> crea `StockMovement` OUT
- `GoodsReceiptLine` -> crea `StockMovement` IN

Le righe operative sono il ponte verso il magazzino.

## 5.3 Relazioni documento -> contabilita

- un documento operativo puo generare `JournalEntry`
- `JournalEntry` puo mantenere un riferimento al documento sorgente (`reference_type`, `reference_id`)

Questo permette audit e riconciliazione.

---

## 6) Regole ERP importanti (business rules)

### Coerenza azienda (company)

Tutto deve appartenere alla stessa company:

- cliente
- articoli
- magazzini
- documenti
- registrazioni contabili

### No quantità impossibili

- non puoi consegnare piu di quanto ordinato
- non puoi scaricare piu stock di quello disponibile

### Idempotenza

Se un documento gia postato viene salvato di nuovo, non deve duplicare i movimenti.

### Immutabilita contabile (dopo posting)

Una registrazione contabile postata non dovrebbe essere modificata liberamente:

- si rettifica con storni/rettifiche
- non con edit silenziosi

### Numerazione documentale

Ogni tipo documento ha il suo numeratore.

- sequenziale
- univoco
- con regole sui "buchi" (gap allowed / not allowed) in base al tipo

---

## 7) Differenza tra operativo e contabile

### Operativo

Risponde a: "cosa sta succedendo nel lavoro quotidiano?"

- ordini
- consegne
- ricezioni
- progressi delle quantita

### Contabile

Risponde a: "che impatto economico e finanziario ha?"

- ricavi/costi
- debiti/crediti
- IVA
- bilancio

Un ERP maturo collega bene i due livelli.

---

## 8) Perche servono i riferimenti tra oggetti

Senza riferimenti chiari, dopo qualche mese non capisci piu:

- da dove nasce un valore in bilancio
- quale documento ha mosso il magazzino
- perche un ordine risulta "parziale"

Con riferimenti e regole:

- fai audit
- fai troubleshooting
- fai report affidabili

---

## 9) KPI e controlli minimi da monitorare

- ordini aperti vs evasi
- consegne in ritardo
- valore magazzino
- rotazione magazzino
- margine lordo (ricavi - costo del venduto)
- fatture emesse/non pagate
- fatture fornitore da pagare

---

## 10) Errori comuni da evitare in un ERP

- aggiornare numeri "a mano" senza workflow
- saltare gli stati documento
- permettere edit su documenti gia contabilizzati senza regole
- non tracciare la fonte dei movimenti
- duplicare processi (stesso fatto registrato due volte)

---

## 11) Come leggere il sistema ERP di questo progetto

Approccio consigliato:

1. capisci la catena documento -> documento
2. guarda dove nasce il movimento di magazzino
3. guarda dove nasce la scrittura contabile
4. verifica gli stati e i lock
5. verifica i test: raccontano il comportamento atteso

---

## 12) Mini glossario veloce

- **DDT**: documento di trasporto/consegna
- **GR (Goods Receipt)**: ricezione merce da fornitore
- **COGS**: costo del venduto
- **Posting**: registrazione ufficiale in contabilita
- **Partita doppia**: ogni scrittura ha dare/avere bilanciati
- **3-way match**: confronto ordine, ricezione, fattura fornitore

---

## 13) Conclusione pratica

Un ERP non e solo "fare documenti": e garantire che tutto resti coerente nel tempo tra:

- processo operativo
- magazzino
- contabile
- controllo gestionale

Se vuoi, nel prossimo step posso prepararti anche:

- una versione **visuale** con diagrammi semplici (flussi + relazioni)
- un "percorso guidato" documento per documento usando i nomi reali del tuo modulo.
