<?php

namespace App\Jobs\Payment;

use App\Account;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Payment\PaymentWasEmailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;
use App\Helpers\Email\BuildEmail;
use App\Jobs\Utils\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\TemplateEmail;
use App\Payment;
use App\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EmailPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payment;

    public $emailBuilder;

    private $account;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Payment $payment, Account $account, BuildEmail $emailBuilder)
    {
        $this->payment = $payment;
        $this->emailBuilder = $emailBuilder;
        $this->account = $account;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        $template_style = $this->payment->customer->getSetting('email_style');
        $emailBuilder = $this->emailBuilder;

        $this->payment->customer->contacts->each(function ($contact) use ($emailBuilder) {
            if ($contact->email) {

                //change the runtime config of the mail provider here:

                $recipients = $emailBuilder->getRecipients();

                //send message
                Mail::to($recipients[0]['email'], $recipients[0]['name'])
                    ->send(new TemplateEmail($emailBuilder, $contact->user, $contact->customer));

                if (count(Mail::failures()) > 0) {
                    event(new PaymentWasEmailedAndFailed($this->payment, Mail::failures()));

                    return $this->logMailError($errors);
                }

                //fire any events
                event(new PaymentWasEmailed($this->payment));

                //sleep(5);
            }
        });
    }

    private function logMailError($errors)
    {
        SystemLogger::dispatch(
            $errors,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_SEND,
            SystemLog::TYPE_FAILURE,
            $this->payment->customer
        );
    }
}
