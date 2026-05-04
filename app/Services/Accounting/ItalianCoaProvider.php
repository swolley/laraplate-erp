<?php

declare(strict_types=1);

namespace Modules\ERP\Services\Accounting;

use Modules\ERP\Casts\AccountKind;
use Modules\ERP\Contracts\ChartOfAccountsProvider;
use Override;

/**
 * Full "small business" Italian-style chart of accounts (PGC-inspired hierarchy,
 * civilistico-oriented meta hints where helpful).
 *
 * Suitable as the default {@see ChartOfAccountsProvider} for domestic SMEs operating
 * in EUR; swap the interface binding for other jurisdictions.
 */
final class ItalianCoaProvider implements ChartOfAccountsProvider
{
    #[Override]
    public function definitions(): array
    {
        $d = static fn (string $code, string $name, AccountKind $kind, ?string $parent, array $meta = []): array => [
            'code' => $code,
            'name' => $name,
            'kind' => $kind,
            'parent_code' => $parent,
            ...($meta === [] ? [] : ['meta' => $meta]),
        ];

        return [
            // Attivo (assets)
            $d('1', 'ATTIVO', AccountKind::Asset, null, ['pgc_section' => 'BS-ASSETS']),
            $d('11', 'Crediti e disponibilità finanziarie', AccountKind::Asset, '1'),
            $d('110', 'Disponibilità liquide', AccountKind::Asset, '11'),
            $d('1101', 'Cassa', AccountKind::Asset, '110', ['civilistico' => 'Cassa']),
            $d('1102', 'Assegni', AccountKind::Asset, '110'),
            $d('1103', 'Banche c/c attive', AccountKind::Asset, '110', ['civilistico' => 'Banche c/c']),
            $d('1104', 'Banche c/c esteri', AccountKind::Asset, '110'),
            $d('1105', 'Carte di credito aziendali', AccountKind::Asset, '110'),
            $d('120', 'Crediti commerciali', AccountKind::Asset, '11'),
            $d('1201', 'Clienti', AccountKind::Asset, '120', ['civilistico' => 'Clienti']),
            $d('1202', 'Clienti (fatture da emettere)', AccountKind::Asset, '120'),
            $d('1203', 'Crediti verso soci per finanziamenti', AccountKind::Asset, '120'),
            $d('1204', 'Crediti diversi', AccountKind::Asset, '120'),
            $d('130', 'IVA e crediti tributari', AccountKind::Asset, '11'),
            $d('1301', 'IVA acquisti indetraibile', AccountKind::Asset, '130'),
            $d('1302', 'IVA acquisti detraibile (credito)', AccountKind::Asset, '130'),
            $d('1303', 'Crediti per imposte anticipate', AccountKind::Asset, '130'),
            $d('1304', 'Crediti verso l’Erario', AccountKind::Asset, '130'),
            $d('140', 'Altri crediti', AccountKind::Asset, '1'),
            $d('1401', 'Anticipi a fornitori', AccountKind::Asset, '140'),
            $d('1402', 'Crediti verso dipendenti', AccountKind::Asset, '140'),
            $d('1403', 'Altri crediti operativi', AccountKind::Asset, '140'),
            $d('15', 'Immobilizzazioni e accantonamenti attivi', AccountKind::Asset, '1'),
            $d('150', 'Immobilizzazioni immateriali', AccountKind::Asset, '15'),
            $d('1501', 'Software e licenze', AccountKind::Asset, '150'),
            $d('1502', 'Avviamento', AccountKind::Asset, '150'),
            $d('160', 'Immobilizzazioni materiali', AccountKind::Asset, '15'),
            $d('1601', 'Attrezzature e macchinari', AccountKind::Asset, '160'),
            $d('1602', 'Arredi e mobili', AccountKind::Asset, '160'),
            $d('1603', 'Automezzi', AccountKind::Asset, '160'),
            $d('170', 'Immobilizzazioni finanziarie', AccountKind::Asset, '15'),
            $d('1701', 'Partecipazioni', AccountKind::Asset, '170'),
            $d('1702', 'Altri titoli', AccountKind::Asset, '170'),
            $d('180', 'Fondo ammortamento immobilizzazioni', AccountKind::Asset, '15', ['note' => 'Contra-asset technical account or paired expense per policy']),
            $d('1801', 'Ammortamento accumulato — attrezzature', AccountKind::Asset, '180'),
            $d('1802', 'Ammortamento accumulato — arredi', AccountKind::Asset, '180'),
            $d('1803', 'Ammortamento accumulato — automezzi', AccountKind::Asset, '180'),
            $d('190', 'Ratei e risconti attivi', AccountKind::Asset, '1'),
            $d('1901', 'Ratei attivi', AccountKind::Asset, '190'),
            $d('1902', 'Risconti attivi', AccountKind::Asset, '190'),
            $d('146', 'Magazzino merci', AccountKind::Asset, '1', ['erp_role' => 'inventory_merchandise']),

            // Passivo (liabilities)
            $d('2', 'PASSIVO', AccountKind::Liability, null, ['pgc_section' => 'BS-LIAB']),
            $d('21', 'Debiti commerciali e finanziari', AccountKind::Liability, '2'),
            $d('210', 'Debiti verso fornitori', AccountKind::Liability, '21'),
            $d('2101', 'Fornitori', AccountKind::Liability, '210', ['civilistico' => 'Fornitori']),
            $d('2102', 'Fornitori (fatture da ricevere)', AccountKind::Liability, '210'),
            $d('220', 'Debiti tributari', AccountKind::Liability, '21'),
            $d('2201', 'Erario c/IVA vendite', AccountKind::Liability, '220'),
            $d('2202', 'Erario c/ritenute', AccountKind::Liability, '220'),
            $d('2203', 'INPS e altri enti previdenziali', AccountKind::Liability, '220'),
            $d('230', 'Altri debiti', AccountKind::Liability, '2'),
            $d('2301', 'Clienti (anticipi)', AccountKind::Liability, '230'),
            $d('2302', 'Debiti verso dipendenti', AccountKind::Liability, '230'),
            $d('2303', 'Debiti diversi operativi', AccountKind::Liability, '230'),
            $d('240', 'Finanziamenti a breve', AccountKind::Liability, '2'),
            $d('2401', 'Mutui e prestiti a breve', AccountKind::Liability, '240'),
            $d('250', 'Finanziamenti a medio/lungo termine', AccountKind::Liability, '2'),
            $d('2501', 'Mutui e prestiti a lungo', AccountKind::Liability, '250'),
            $d('260', 'Ratei e risconti passivi', AccountKind::Liability, '2'),
            $d('2601', 'Ratei passivi', AccountKind::Liability, '260'),
            $d('2602', 'Risconti passivi', AccountKind::Liability, '260'),

            // Patrimonio netto
            $d('3', 'PATRIMONIO NETTO', AccountKind::Equity, null, ['pgc_section' => 'BS-EQUITY']),
            $d('310', 'Capitale', AccountKind::Equity, '3'),
            $d('3101', 'Capitale sociale', AccountKind::Equity, '310'),
            $d('320', 'Riserve', AccountKind::Equity, '3'),
            $d('3201', 'Riserve legali e statutarie', AccountKind::Equity, '320'),
            $d('330', 'Utile (perdita) d’esercizio', AccountKind::Equity, '3'),
            $d('3301', 'Utile d’esercizio', AccountKind::Equity, '330'),
            $d('3302', 'Perdita d’esercizio', AccountKind::Equity, '330'),

            // Ricavi
            $d('4', 'RICAVI', AccountKind::Revenue, null, ['pgc_section' => 'PL-REV']),
            $d('410', 'Ricavi delle vendite e delle prestazioni', AccountKind::Revenue, '4'),
            $d('4101', 'Vendite merci e servizi', AccountKind::Revenue, '410'),
            $d('4102', 'Prestazioni professionali', AccountKind::Revenue, '410'),
            $d('420', 'Altri ricavi e proventi', AccountKind::Revenue, '4'),
            $d('4201', 'Proventi accessori', AccountKind::Revenue, '420'),
            $d('4202', 'Oneri deducibili da recuperare', AccountKind::Revenue, '420'),

            // Costi
            $d('5', 'COSTI', AccountKind::Expense, null, ['pgc_section' => 'PL-EXP']),
            $d('510', 'Acquisti', AccountKind::Expense, '5'),
            $d('5101', 'Acquisto di materie prime', AccountKind::Expense, '510'),
            $d('5102', 'Acquisto di merci', AccountKind::Expense, '510'),
            $d('520', 'Servizi', AccountKind::Expense, '5'),
            $d('5201', 'Servizi generali esterni', AccountKind::Expense, '520'),
            $d('5202', 'Servizi professionali e consulenze', AccountKind::Expense, '520'),
            $d('5203', 'Servizi software e cloud', AccountKind::Expense, '520'),
            $d('530', 'Godimento beni di terzi', AccountKind::Expense, '5'),
            $d('5301', 'Locazioni e canoni', AccountKind::Expense, '530'),
            $d('5302', 'Utilities (energia, acqua, telefonia)', AccountKind::Expense, '530'),
            $d('540', 'Personale', AccountKind::Expense, '5'),
            $d('5401', 'Salari e stipendi', AccountKind::Expense, '540'),
            $d('5402', 'Oneri sociali aziendali', AccountKind::Expense, '540'),
            $d('5403', 'Contributi e assicurazioni dipendenti', AccountKind::Expense, '540'),
            $d('550', 'Ammortamenti e svalutazioni', AccountKind::Expense, '5'),
            $d('5501', 'Ammortamento immobilizzazioni immateriali', AccountKind::Expense, '550'),
            $d('5502', 'Ammortamento immobilizzazioni materiali', AccountKind::Expense, '550'),
            $d('5503', 'Svalutazione crediti', AccountKind::Expense, '550'),
            $d('560', 'Oneri diversi di gestione', AccountKind::Expense, '5'),
            $d('5601', 'Assicurazioni generali', AccountKind::Expense, '560'),
            $d('5602', 'Spese di rappresentanza', AccountKind::Expense, '560'),
            $d('5603', 'Spese postali e bancarie', AccountKind::Expense, '560'),
            $d('570', 'Oneri finanziari', AccountKind::Expense, '5'),
            $d('5701', 'Interessi passivi su mutui/prestiti', AccountKind::Expense, '570'),
            $d('5702', 'Commissioni e spese finanziarie', AccountKind::Expense, '570'),
            $d('580', 'Imposte e tasse sull’esercizio', AccountKind::Expense, '5'),
            $d('5801', 'Imposte dirette', AccountKind::Expense, '580'),
            $d('5802', 'Tributi minori', AccountKind::Expense, '580'),
            $d('590', 'Costo delle merci vendute', AccountKind::Expense, '5', ['erp_role' => 'cost_of_goods_sold']),
        ];
    }
}
