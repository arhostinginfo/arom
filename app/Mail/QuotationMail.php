<?php

namespace App\Mail;

use App\Models\Quotation;
use App\Services\DocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The quotation email: a short note, the quotation PDF and every BOQ document of the revision. */
class QuotationMail extends Mailable
{
    use Queueable;

    public function __construct(public Quotation $quotation, public ?string $note)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Quotation '.$this->quotation->quotation_no.' Rev '.$this->quotation->revision_no);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.quotation');
    }

    public function attachments(): array
    {
        $q = $this->quotation;
        $pdf = Pdf::loadView('pdf.quotation', ['q' => $q])->setPaper('a4', 'portrait')->setOption('isFontSubsettingEnabled', true)->output();
        $files = [Attachment::fromData(fn () => $pdf, str_replace('/', '-', $q->quotation_no).'-rev'.$q->revision_no.'.pdf')->withMime('application/pdf')];
        // BOQ Preparation is an optional part: no BOQ files go out while it is switched off.
        foreach (\App\Support\Modules::on('boq') ? $q->documents->where('category', 'BOQ') : [] as $doc) {
            $files[] = Attachment::fromStorageDisk(DocumentService::diskName(), $doc->path)->as($doc->original_name);
        }

        return $files;
    }
}
