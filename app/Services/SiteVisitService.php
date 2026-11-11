<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\SiteVisit;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** CRM > Site visit / requirement gathering (CRM changes). Each change is logged in the same transaction. */
class SiteVisitService
{
    public function create(array $data, User $actor): SiteVisit
    {
        return DB::transaction(function () use ($data, $actor) {
            $enquiry = $this->lockEnquiry((int) $data['enquiry_id']);
            $v = new SiteVisit($this->attributes($data));
            $v->unit_id = $enquiry->unit_id;
            $v->created_by = $actor->id;
            $v->updated_by = $actor->id;
            $v->save();
            ActivityLogger::log('site_visit.created', $v, 'Added a site visit for '.$enquiry->enquiry_no, ['fields' => $this->snapshot($v)]);

            return $v;
        });
    }

    public function update(SiteVisit $v, array $data, User $actor): SiteVisit
    {
        return DB::transaction(function () use ($v, $data, $actor) {
            $v = SiteVisit::whereKey($v->id)->lockForUpdate()->firstOrFail();
            if ((int) $data['enquiry_id'] !== $v->enquiry_id) {
                // Moved to an enquiry of another unit: the visit moves with it (code review S7).
                $v->unit_id = $this->lockEnquiry((int) $data['enquiry_id'])->unit_id;
            }
            $before = $this->snapshot($v);
            // Visit time hidden in Admin > Page setup: the saved time stays as it was.
            if (! array_key_exists('visit_time', $data)) {
                $data['visit_time'] = $v->visit_at?->format('H:i');
            }
            $v->fill($this->attributes($data));
            $v->updated_by = $actor->id;
            $v->save();
            $v->unsetRelations();
            if ($changes = ActivityLogger::diff($before, $this->snapshot($v))) {
                ActivityLogger::log('site_visit.updated', $v, 'Updated '.$v->activityLabel(), $changes);
            }

            return $v;
        });
    }

    public function delete(SiteVisit $v, User $actor): void
    {
        DB::transaction(function () use ($v) {
            $v = SiteVisit::whereKey($v->id)->lockForUpdate()->firstOrFail();
            ActivityLogger::log('site_visit.deleted', $v, 'Deleted '.$v->activityLabel(), ['fields' => $this->snapshot($v)]);
            $v->delete();
        });
    }

    public function snapshot(SiteVisit $v): array
    {
        $v->loadMissing(['enquiry', 'technicalPerson']);

        return [
            'Enquiry' => $v->enquiry?->enquiry_no,
            'Visit date and time' => $v->visit_at?->format(config('nursery.datetime_format')),
            'POC' => $v->poc,
            'Technical person' => $v->technicalPerson?->name,
            'Site address' => $v->site_address,
            'Area / measurements' => $v->area_measurements,
            'Floors' => $v->floors,
            'Indoor / outdoor area' => $v->indoor_outdoor_area,
            'Existing plants' => $v->existing_plants,
            'Existing planters' => $v->existing_planters,
            'Lighting condition' => $v->lighting_condition,
            'Water availability' => $v->water_availability,
            'Access restrictions' => $v->access_restrictions,
            'Customer requirement' => $v->customer_requirement,
            'Remarks' => $v->remarks,
            'Next action' => $v->next_action,
            'Status' => $v->status,
        ];
    }

    private function attributes(array $data): array
    {
        $out = array_intersect_key($data, array_flip(SiteVisit::FIELDS));
        $out['visit_at'] = Dates::toDbDateTime($data['visit_date'], $data['visit_time'] ?? null);

        return $out;
    }

    /** The enquiry must still exist; its row is locked so a delete at the same moment waits. */
    private function lockEnquiry(int $id): Enquiry
    {
        $e = Enquiry::whereKey($id)->sharedLock()->first();
        if (! $e) {
            throw ValidationException::withMessages(['enquiry_id' => 'This enquiry was deleted while you were working. Choose another enquiry.']);
        }

        return $e;
    }
}
