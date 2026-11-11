<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Party;
use App\Models\Prospect;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRM > Contacts (Contact / POC of the CRM changes Word file, built 14/10/2026): the people of a client,
 * a prospect or a customer. The Contact ID (CT00001) is made from the row id inside the same transaction,
 * so two people saving at once never get the same number. Each change is logged in the same transaction.
 */
class ContactService
{
    public function create(array $data, User $actor): Contact
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->lockClient($data);
            $c = new Contact($this->attributes($data));
            $c->created_by = $actor->id;
            $c->updated_by = $actor->id;
            $c->save();
            $c->contact_no = self::number($c->id);
            $c->saveQuietly();
            ActivityLogger::log('contact.created', $c, 'Added '.$c->activityLabel().' for '.$c->clientName(), ['fields' => $this->snapshot($c)]);

            return $c;
        });
    }

    public function update(Contact $c, array $data, User $actor): Contact
    {
        return DB::transaction(function () use ($c, $data, $actor) {
            $c = Contact::whereKey($c->id)->lockForUpdate()->firstOrFail();
            $this->lockClient($data);
            $before = $this->snapshot($c);
            $c->fill($this->attributes($data));
            $c->updated_by = $actor->id;
            $c->save();
            $c->unsetRelations();
            if ($changes = ActivityLogger::diff($before, $this->snapshot($c))) {
                ActivityLogger::log('contact.updated', $c, 'Updated '.$c->activityLabel(), $changes);
            }

            return $c;
        });
    }

    public function delete(Contact $c, User $actor): void
    {
        DB::transaction(function () use ($c) {
            $c = Contact::whereKey($c->id)->lockForUpdate()->firstOrFail();
            ActivityLogger::log('contact.deleted', $c, 'Deleted '.$c->activityLabel(), ['fields' => $this->snapshot($c)]);
            $c->delete();
        });
    }

    /** CT00001: the row id, at least five digits. */
    public static function number(int $id): string
    {
        return 'CT'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    /** Values shown in the log: what the user sees on the form. */
    public function snapshot(Contact $c): array
    {
        $c->loadMissing(['party', 'prospect']);

        return [
            'Contact ID' => $c->contact_no,
            'Company / Client' => $c->clientName() ?: null,
            'Name' => $c->name,
            'Designation' => $c->designation,
            'Department' => $c->department,
            'Mobile' => $c->mobile,
            'WhatsApp' => $c->whatsapp,
            'Email' => $c->email,
            'Alternate number' => $c->alternate_no,
            'Preferred communication' => $c->preferred_communication,
            'Roles' => $c->roleNames() ?: null,
            'Notes' => $c->notes,
        ];
    }

    /** Only what the form sent: a field hidden in Admin > Page setup keeps what is saved in it. */
    private function attributes(array $data): array
    {
        $out = array_intersect_key($data, array_flip(Contact::FIELDS));
        foreach (array_keys(Contact::ROLES) as $f) {
            if (array_key_exists($f, $out)) {
                $out[$f] = (bool) $out[$f];
            }
        }
        // The client is a customer or a prospect, never both.
        $out['party_id'] = ($data['party_id'] ?? null) ?: null;
        $out['prospect_id'] = ($data['prospect_id'] ?? null) ?: null;

        return $out;
    }

    /** The client must still exist; its row is shared-locked so a delete at the same moment waits. */
    private function lockClient(array $data): void
    {
        $exists = ! empty($data['party_id'])
            ? Party::whereKey((int) $data['party_id'])->sharedLock()->exists()
            : Prospect::whereKey((int) ($data['prospect_id'] ?? 0))->sharedLock()->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['prospect_id' => 'This client was deleted while you were working. Choose another client.']);
        }
    }
}
