<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    // Labels shown on the Activity log page.
    public const ACTIONS = [
        'auth.login' => 'Logged in',
        'auth.logout' => 'Logged out',
        'auth.failed' => 'Login failed',
        'user.created' => 'User created',
        'user.updated' => 'User updated',
        'user.password_changed' => 'Password changed',
        'user.role_assigned' => 'Role assigned',
        'user.permissions_updated' => 'User permissions changed',
        'user.deleted' => 'User deleted',
        'role.created' => 'Role created',
        'role.updated' => 'Role renamed',
        'role.permissions_updated' => 'Role permissions changed',
        'role.deleted' => 'Role deleted',
        'prospect.created' => 'Prospect added',
        'prospect.updated' => 'Prospect updated',
        'prospect.deleted' => 'Prospect deleted',
        'prospect.imported' => 'Prospects imported',
        'quotation.created' => 'Quotation created',
        'quotation.updated' => 'Quotation updated',
        'quotation.revised' => 'Quotation revised',
        'quotation.deleted' => 'Quotation deleted',
        'quotation.imported' => 'Quotations imported',
        'quotation.status_changed' => 'Quotation status changed',
        'quotation.sent' => 'Quotation sent',
        'enquiry.created' => 'Enquiry added',
        'enquiry.updated' => 'Enquiry updated',
        'enquiry.deleted' => 'Enquiry deleted',
        'enquiry.followup_added' => 'Follow-up recorded',
        'enquiry.status_changed' => 'Enquiry status changed',
        'site_visit.created' => 'Site visit added',
        'site_visit.updated' => 'Site visit updated',
        'site_visit.deleted' => 'Site visit deleted',
        'customer_po.created' => 'PO / agreement entered',
        'customer_po.updated' => 'PO / agreement updated',
        'customer_po.deleted' => 'PO / agreement deleted',
        'document.uploaded' => 'Document uploaded',
        'document.deleted' => 'Document deleted',
        'master.created' => 'Master entry added',
        'master.updated' => 'Master entry updated',
        'master.deleted' => 'Master entry deleted',
        'employee.created' => 'Employee added',
        'employee.updated' => 'Employee updated',
        'employee.deleted' => 'Employee deleted',
        'page_setup.updated' => 'Page setup changed',
        'modules.updated' => 'Optional parts switched',
        'contact.created' => 'Contact added',
        'contact.updated' => 'Contact updated',
        'contact.deleted' => 'Contact deleted',
        'party.created' => 'Party added',
        'party.updated' => 'Party updated',
        'party.deleted' => 'Party deleted',
        'party.imported' => 'Parties imported',
        'item.created' => 'Item added',
        'item.updated' => 'Item updated',
        'item.deleted' => 'Item deleted',
        'item.imported' => 'Items imported',
        'company.updated' => 'Company details changed',
        'sales_order.created' => 'Sales order added',
        'sales_order.updated' => 'Sales order updated',
        'sales_order.deleted' => 'Sales order deleted',
        'sales_order.imported' => 'Sales orders imported',
        'sales_order.status_changed' => 'Sales order status changed',
        'sales_invoice.created' => 'Invoice added',
        'sales_invoice.updated' => 'Invoice updated',
        'sales_invoice.cancelled' => 'Invoice cancelled',
        'stock_transfer.created' => 'Stock transfer added',
        'stock_transfer.updated' => 'Stock transfer updated',
        'stock_transfer.deleted' => 'Stock transfer deleted',
        'stock_transfer.status_changed' => 'Stock transfer status changed',
        'purchase_order.created' => 'Purchase order added',
        'purchase_order.updated' => 'Purchase order updated',
        'purchase_order.deleted' => 'Purchase order deleted',
        'purchase_order.status_changed' => 'Purchase order status changed',
        'grn.created' => 'GRN added',
        'grn.updated' => 'GRN updated',
        'grn.deleted' => 'GRN deleted',
        'grn.matched' => 'GRN matched',
        'grn.quality' => 'GRN quality approval given',
        'grn.cancelled' => 'GRN cancelled',
        'approval.updated' => 'Approval rule changed',
        'settings.updated' => 'Config changed',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The record the operation was done on (a user, a role, later an order or invoice). */
    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * The "Recent changes" box of a record: its latest rows, leaving out those of an optional part that is switched
     * off (detailed test S1): BOQ and Costing approval document uploads and deletes while BOQ or costing is off,
     * site visit rows while site visits are off, and changes that touched only cost details while costing is off.
     * Admin › Activity log keeps every row: it is the audit trail.
     */
    public static function history(Model $subject, int $limit = 6): \Illuminate\Support\Collection
    {
        if (! $subject->exists) {
            return collect();
        }
        $hiddenDocs = \App\Services\DocumentService::hiddenCategories();
        $costingOff = ! \App\Support\Modules::on('costing');
        $query = self::forSubject($subject)
            ->when(! \App\Support\Modules::on('site_visit'), fn ($q) => $q->where('action', 'not like', 'site_visit.%'))
            ->when($hiddenDocs, fn ($q) => $q->where(fn ($w) => $w->whereNotIn('action', ['document.uploaded', 'document.deleted'])
                ->orWhere(fn ($d) => collect($hiddenDocs)->each(fn ($c) => $d->where('description', 'not like', '% '.addcslashes($c, '%_\\').' "%')))))
            ->latest('id');

        // Rows that changed only cost details are found in PHP (they are JSON), so read a few more than shown.
        return $query->limit($costingOff ? $limit * 3 : $limit)->get()
            // getAttribute(): inside this class "$log->changes" would read Eloquent's own $changes property instead.
            ->reject(fn (self $log) => $costingOff && $log->action === 'quotation.updated' && ($c = (array) $log->getAttribute('changes')) !== []
                && collect(array_keys($c))->every(fn ($k) => str_starts_with((string) $k, 'Cost: ')))
            ->take($limit)->values();
    }

    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey());
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function badgeClass(): string
    {
        return match (true) {
            str_ends_with($this->action, 'deleted'), $this->action === 'auth.failed' => 'b-err',
            str_ends_with($this->action, 'created'), str_ends_with($this->action, 'imported') => 'b-ok',
            str_starts_with($this->action, 'auth.') => 'b-neu',
            default => 'b-info',
        };
    }
}
