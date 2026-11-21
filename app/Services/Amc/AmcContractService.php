<?php

namespace App\Services\Amc;

use App\Models\Amc\AmcContract;
use App\Models\Amc\AmcFile;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Customer PO / AMC (21/11/2026): add, change and delete a contract. Each change writes its log row in the same transaction. */
class AmcContractService
{
    public const FIELDS = ['amc_client_id', 'amc_type_id', 'start_date', 'end_date', 'billing_cycle', 'visit_plan', 'amount', 'operation_manager_id', 'gardener_id', 'status'];

    public function save(?AmcContract $c, array $data, User $actor): AmcContract
    {
        return DB::transaction(function () use ($c, $data, $actor) {
            $isNew = ! $c;
            $c = $isNew ? new AmcContract() : AmcContract::whereKey($c->id)->lockForUpdate()->firstOrFail();
            $before = $isNew ? [] : $this->snapshot($c);
            $c->fill(array_intersect_key($data, array_flip(self::FIELDS)));
            if ($isNew) {
                $c->created_by = $actor->id;
            }
            $c->updated_by = $actor->id;
            $c->save();
            $c->unsetRelations();
            if ($isNew) {
                ActivityLogger::log('amc_contract.created', $c, 'Added AMC contract '.$c->activityLabel(), ['fields' => $this->snapshot($c)], $actor);
            } elseif ($changes = ActivityLogger::diff($before, $this->snapshot($c))) {
                ActivityLogger::log('amc_contract.updated', $c, 'Updated AMC contract '.$c->activityLabel(), $changes, $actor);
            }

            return $c;
        });
    }

    /** Deletes the contract, its file rows and then the files themselves. */
    public function delete(AmcContract $c, User $actor): void
    {
        $paths = DB::transaction(function () use ($c, $actor) {
            $c = AmcContract::whereKey($c->id)->lockForUpdate()->firstOrFail();
            $paths = AmcFile::where('amc_contract_id', $c->id)->pluck('path')->all();
            ActivityLogger::log('amc_contract.deleted', $c, 'Deleted AMC contract '.$c->activityLabel().' and its '.count($paths).' file(s)', ['fields' => $this->snapshot($c)], $actor);
            AmcFile::where('amc_contract_id', $c->id)->delete();
            $c->delete();

            return $paths;
        });
        AmcFiles::disk()->delete($paths);
    }

    public function snapshot(AmcContract $c): array
    {
        $c->loadMissing(['client', 'type', 'operationManager', 'gardener']);
        $df = config('nursery.date_format');

        return [
            'Customer' => $c->client?->name,
            'AMC type' => $c->type?->name,
            'Start date' => $c->start_date?->format($df),
            'End date' => $c->end_date?->format($df),
            'Billing cycle' => $c->billing_cycle,
            'Visit plan' => $c->visit_plan,
            'AMC amount' => $c->amount !== null ? Money::indian($c->amount, 2) : null,
            'Operation manager' => $c->operationManager?->label(),
            'Gardener' => $c->gardener?->label(),
            'Status' => $c->status,
        ];
    }
}
