<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FrontlinerReceiptMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public string $receiptLink,
        public string $displayOrderId,
        public string $pdfBinary
    ) {
    }

    public function build(): self
    {
        $mail = $this
            ->subject('Struk Pembelian KedaiKlik - ' . $this->displayOrderId)
            ->view('emails.frontliner-receipt')
            ->with([
                'order' => $this->order,
                'receiptLink' => $this->receiptLink,
                'displayOrderId' => $this->displayOrderId,
            ]);

        if ($this->pdfBinary !== '') {
            $mail->attachData(
                $this->pdfBinary,
                'struk-' . $this->displayOrderId . '.pdf',
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }
}
