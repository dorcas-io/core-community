<?php

namespace App\Mail\Invoicing;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class Invoice extends Mailable implements ShouldQueue
{
    use SerializesModels;

    /** @var Company */
    public $company;

    /** @var Order  */
    public $order;

    /** @var Model */
    public $recipient;

    /** @var string  */
    public $invoiceUrl;

    /** @var string */
    public $payUrl;
    
    /** @var BankAccount  */
    public $account;
    
    /** @var bool  */
    public $customCopyrightFooter = false;
    
    /** @var string  */
    public $footerText = '';
    
    /**
     * Invoice constructor.
     *
     * @param                  $notifiable
     * @param Order            $order
     * @param string|null      $payUrl
     * @param BankAccount|null $account
     */
    public function __construct($notifiable, Order $order, string $payUrl = null, BankAccount $account = null)
    {
        $this->company = $order->company;
        $this->order = $order;
        $this->recipient = $order->customers()->where('id', $notifiable->id)->first() ?: $notifiable;
        $this->invoiceUrl = web_url('invoices/' . $order->uuid, ['customer' => $this->recipient->uuid]);
        $this->payUrl = $payUrl;
        $this->account = $account;
        $plan = $this->company->plan;
        $this->customCopyrightFooter = !empty($plan) && $plan->price_monthly > 0;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $sender = $this->company->users()->oldest()->first();
        # get the first member of the company
        $senderEmail = $this->company->email ?: $sender->email;
        # the sending email
        $logo = !empty($this->company->logo) ? $this->company->logo : \App\Http\Controllers\Invoicing\Invoice::DEFAULT_IMAGE;
        # the invoice logo
        $subject = 'Please pay Invoice #'.$this->order->invoice_number.' from '.$this->company->name;
        return $this->subject($subject)
                    ->from('hello@dorcas.io', $this->company->name)
                    ->replyTo($senderEmail, $this->company->name)
                    ->view('emails.invoicing.new-order')
                    ->with(['subject' => $subject, 'logo' => $logo]);
    }
}