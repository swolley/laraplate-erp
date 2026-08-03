<?php

declare(strict_types=1);

namespace Modules\ERP\Import\Services;

use LogicException;
use Modules\Core\Import\Enums\ExternalRecordState;
use Modules\Core\Import\Support\RecordOriginRegistry;
use Modules\ERP\Import\Data\ExternalCashMovementInput;
use Modules\ERP\Import\Enums\ImportMutation;
use Modules\ERP\Import\Exceptions\PostedImportConflict;
use Modules\ERP\Import\ValueObjects\CashMovementImportResult;
use Modules\ERP\Models\Account;
use Modules\ERP\Models\Movement;
use Modules\ERP\Services\Cash\MovementPostingService;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Modules\ERP\Support\ErpConnectionContext;

final readonly class ExternalCashMovementImportService
{
    public function __construct(
        private RecordOriginRegistry $origin_registry,
        private ErpConnectionContext $connection_context,
        private MovementPostingService $movement_posting_service,
    ) {}

    public function ingest(ExternalCashMovementInput $input): CashMovementImportResult
    {
        $prototype = $this->connection_context->model(Movement::class);

        return ConnectionScopedTransaction::run($prototype, function (ConnectionScopedModels $models) use ($input, $prototype): CashMovementImportResult {
            $state = $this->origin_registry->inspect($prototype, $input->identity());

            if ($state === ExternalRecordState::Unchanged) {
                $movement_id = $this->origin_registry->referableId(
                    $prototype,
                    $input->identity()->sourceKey,
                    (string) $input->identity()->externalId,
                );

                if ($movement_id === null) {
                    throw new LogicException('Unchanged external movement identity has no local referable.');
                }

                return new CashMovementImportResult(ImportMutation::Skipped, $movement_id);
            }

            $attributes = [
                'company_id' => $input->companyId,
                'type' => $input->type,
                'occurred_on' => $input->occurredOn->toDateString(),
                'amount_doc' => $input->amount,
                'currency_doc' => $input->currency,
                'counterparty_account_id' => $input->counterpartyAccountId,
                'description' => $input->description,
            ];
            $mutation = ImportMutation::Created;

            if ($state === ExternalRecordState::Changed) {
                $movement_id = $this->origin_registry->referableId(
                    $prototype,
                    $input->identity()->sourceKey,
                    (string) $input->identity()->externalId,
                );

                if ($movement_id === null) {
                    throw new LogicException('Changed external movement identity has no local referable.');
                }

                $movement = $models->query(Movement::class)
                    ->withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail($movement_id);

                if ($movement->posted_journal_entry_id !== null) {
                    $identity = $input->identity();

                    throw new PostedImportConflict(
                        $identity->sourceKey,
                        (string) $identity->externalId,
                        (int) $movement->getKey(),
                    );
                }

                $movement->fill($attributes);
                $movement->save();
                $mutation = ImportMutation::Updated;
            } else {
                $movement = $models->query(Movement::class)->withoutGlobalScopes()->create($attributes);
            }

            $counterparty = $models->query(Account::class)
                ->withoutGlobalScopes()
                ->findOrFail($movement->counterparty_account_id);
            $this->movement_posting_service->validateCounterparty($movement, $counterparty);

            $journal_entry_id = null;

            if ($input->post) {
                $journal_entry_id = (int) $this->movement_posting_service->post($movement)->getKey();
            }

            $this->origin_registry->register($movement, $input->identity());

            return new CashMovementImportResult(
                $mutation,
                (int) $movement->getKey(),
                $journal_entry_id,
            );
        });
    }
}
