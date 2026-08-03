<?php

declare(strict_types=1);

namespace Modules\ERP\Import\Services;

use Modules\Core\Import\Enums\ExternalRecordState;
use Modules\Core\Import\Support\RecordOriginRegistry;
use Modules\ERP\Import\Data\ExternalExpenseAllocationInput;
use Modules\ERP\Import\Enums\ImportMutation;
use Modules\ERP\Import\Exceptions\ExternalIdentityConflict;
use Modules\ERP\Import\Exceptions\PostedImportConflict;
use Modules\ERP\Models\Movement;
use Modules\ERP\Models\PartnerPool;
use Modules\ERP\Services\Cash\PartnerPoolSettlementService;
use Modules\ERP\Support\ConnectionScopedModels;
use Modules\ERP\Support\ConnectionScopedTransaction;
use Modules\ERP\Support\ErpConnectionContext;

final readonly class ExternalExpenseAllocationService
{
    public function __construct(
        private RecordOriginRegistry $originRegistry,
        private ErpConnectionContext $connectionContext,
        private PartnerPoolSettlementService $settlementService,
    ) {}

    public function ingest(ExternalExpenseAllocationInput $input): ImportMutation
    {
        $prototype = $this->connectionContext->model(Movement::class);

        return ConnectionScopedTransaction::run($prototype, function (ConnectionScopedModels $models) use ($input): ImportMutation {
            $movement = $models->query(Movement::class)
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($input->movementId);
            $pool = $models->query(PartnerPool::class)
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($input->partnerPoolId);
            $identity = $input->identity();
            $registered_movement_id = $this->originRegistry->referableId(
                $movement,
                $identity->sourceKey,
                (string) $identity->externalId,
            );

            if ($registered_movement_id !== null && $registered_movement_id !== (int) $movement->getKey()) {
                throw new ExternalIdentityConflict(
                    $identity->sourceKey,
                    (string) $identity->externalId,
                    $registered_movement_id,
                    (int) $movement->getKey(),
                );
            }

            $state = $this->originRegistry->inspect($movement, $identity);

            if ($state === ExternalRecordState::Unchanged) {
                return ImportMutation::Skipped;
            }

            if ($movement->posted_journal_entry_id !== null) {
                throw new PostedImportConflict(
                    $identity->sourceKey,
                    (string) $identity->externalId,
                    (int) $movement->getKey(),
                );
            }

            $this->settlementService->allocate($movement, $pool, $input->shares);
            $this->originRegistry->register($movement, $identity);

            return $state === ExternalRecordState::Missing
                ? ImportMutation::Created
                : ImportMutation::Updated;
        });
    }
}
