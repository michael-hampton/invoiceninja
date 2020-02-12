<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Company;

use App\Factory\CompanyLedgerFactory;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Class for update company ledger with payment.
 */
class UpdateCompanyLedgerWithPayment
{
    use Dispatchable;

    public $adjustment;

    public $payment;

    private $company;
    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(Payment $payment, float $adjustment, Company $company)
    {
        $this->payment = $payment;

        $this->adjustment = $adjustment;

        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDB($this->company->db);


        $balance = 0;
        
        /* Get the last record for the client and set the current balance*/
        $ledger = CompanyLedger::whereClientId($this->payment->client_id)
                                ->whereCompanyId($this->payment->company_id)
                                ->orderBy('id', 'DESC')
                                ->first();

        if ($ledger) {
            $balance = $ledger->balance;
        }


        $company_ledger = CompanyLedgerFactory::create($this->payment->company_id, $this->payment->user_id);
        $company_ledger->client_id = $this->payment->client_id;
        $company_ledger->adjustment = $this->adjustment;
        $company_ledger->balance = $balance + $this->adjustment;
        $company_ledger->save();

        $this->payment->company_ledger()->save($company_ledger); //todo add model directive here
    }
}
