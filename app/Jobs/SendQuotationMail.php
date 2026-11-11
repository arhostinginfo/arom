<?php

namespace App\Jobs;

use App\Mail\QuotationMail;
use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a quotation PDF with its BOQ attachments (CRM changes: "send by mail along with quotation").
 * Runs on the queue, because making the PDF and talking to the mail server is slow work that should
 * not hold up the page (docs/ARCHITECTURE.md).
 */
class SendQuotationMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $quotationId,
        public string $to,
        public ?string $cc,
        public ?string $note,
        public int $senderId,
    ) {
    }

    public function handle(): void
    {
        $q = Quotation::with(['lines', 'party', 'prospect', 'salesPerson', 'preparer', 'currency', 'unit', 'enquiry', 'documents'])->find($this->quotationId);
        if (! $q) {
            return;
        }
        $mail = Mail::to($this->to);
        if ($this->cc) {
            $mail->cc(array_map('trim', explode(',', $this->cc)));
        }
        $mail->send(new QuotationMail($q, $this->note));
    }
}
