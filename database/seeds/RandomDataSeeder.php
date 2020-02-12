<?php

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\DataMapper\DefaultSettings;
use App\Events\Invoice\InvoiceWasMarkedSent;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Events\Payment\PaymentWasCreated;
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Jobs\Company\UpdateCompanyLedgerWithInvoice;
use App\Jobs\Invoice\UpdateInvoicePayment;
use App\Listeners\Credit\CreateCreditInvitation;
use App\Listeners\Invoice\CreateInvoiceInvitation;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\Credit;
use App\Models\GatewayType;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\User;
use App\Models\UserAccount;
use App\Repositories\CreditRepository;
use App\Repositories\InvoiceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class RandomDataSeeder extends Seeder
{
    use \App\Utils\Traits\MakesHash;
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        /* Warm up the cache !*/
        $cached_tables = config('ninja.cached_tables');

        foreach ($cached_tables as $name => $class) {
            if (! Cache::has($name)) {
                // check that the table exists in case the migration is pending
                if (! Schema::hasTable((new $class())->getTable())) {
                    continue;
                }
                if ($name == 'payment_terms') {
                    $orderBy = 'num_days';
                } elseif ($name == 'fonts') {
                    $orderBy = 'sort_order';
                } elseif (in_array($name, ['currencies', 'industries', 'languages', 'countries', 'banks'])) {
                    $orderBy = 'name';
                } else {
                    $orderBy = 'id';
                }
                $tableData = $class::orderBy($orderBy)->get();
                if ($tableData->count()) {
                    Cache::forever($name, $tableData);
                }
            }
        }


        $this->command->info('Running RandomDataSeeder');

        Eloquent::unguard();

        $faker = Faker\Factory::create();

        $account = factory(\App\Models\Account::class)->create();
        $company = factory(\App\Models\Company::class)->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = factory(\App\Models\User::class)->create([
            'email'             => $faker->email,
           // 'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default'))
        ]);

        $company_token = CompanyToken::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'account_id' => $account->id,
            'name' => 'test token',
            'token' => \Illuminate\Support\Str::random(64),
        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'settings' => DefaultSettings::userSettings(),
        ]);

        $client = factory(\App\Models\Client::class)->create([
            'user_id' => $user->id,
            'company_id' => $company->id
        ]);


        ClientContact::create([
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
            'email' => config('ninja.testvars.username'),
            'company_id' => $company->id,
            'password' => Hash::make(config('ninja.testvars.password')),
            'email_verified_at' => now(),
            'client_id' =>$client->id,
            'is_primary' => true,
            'contact_key' => \Illuminate\Support\Str::random(40),
        ]);


        factory(\App\Models\Client::class, 10)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company){

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1
            ]);

            factory(\App\Models\ClientContact::class,5)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id
            ]);

        });

        /** Product Factory */
        factory(\App\Models\Product::class,20)->create(['user_id' => $user->id, 'company_id' => $company->id]);

        /** Invoice Factory */
        factory(\App\Models\Invoice::class,20)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

        $invoices = Invoice::cursor();
        $invoice_repo = new InvoiceRepository();

        $invoices->each(function ($invoice) use($invoice_repo, $user, $company, $client){

            $invoice_calc = null;

            if($invoice->uses_inclusive_taxes)
                $invoice_calc = new InvoiceSumInclusive($invoice);
            else
                $invoice_calc = new InvoiceSum($invoice);

            $invoice = $invoice_calc->build()->getInvoice();

            $invoice->save();

            event(new CreateInvoiceInvitation($invoice));

            UpdateCompanyLedgerWithInvoice::dispatchNow($invoice, $invoice->balance, $invoice->company);

            $invoice->service()->markSent()->save();

            event(new InvoiceWasMarkedSent($invoice, $company));

            if(rand(0, 1)) {
                $payment = App\Models\Payment::create([
                    'date' => now(),
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'client_id' => $client->id,
                    'amount' => $invoice->balance,
                    'transaction_reference' => rand(0,500),
                    'type_id' => PaymentType::CREDIT_CARD_OTHER,
                    'status_id' => Payment::STATUS_COMPLETED,
                ]);

                $payment->invoices()->save($invoice);

                event(new PaymentWasCreated($payment, $payment->company));

                UpdateInvoicePayment::dispatchNow($payment, $payment->company);
            }

        });

        /*Credits*/
        factory(\App\Models\Credit::class,20)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

        $credits = Credit::cursor();
        $credit_repo = new CreditRepository();

        $credits->each(function ($credit) use($credit_repo, $user, $company, $client){

            $credit_calc = null;

            if($credit->uses_inclusive_taxes)
                $credit_calc = new InvoiceSumInclusive($credit);
            else
                $credit_calc = new InvoiceSum($credit);

            $credit = $credit_calc->build()->getInvoice();

            $credit->save();

            event(new CreateCreditInvitation($credit));

            //$invoice->markSent()->save();

        });

        /** Recurring Invoice Factory */
        factory(\App\Models\RecurringInvoice::class,10)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

       // factory(\App\Models\Payment::class,20)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id, 'settings' => ClientSettings::buildClientSettings($company->settings, $client->settings)]);



        $clients = Client::all();

        foreach($clients as $client)
        {
            //$client->getNextClientNumber($client);
            $client->id_number = $client->getNextClientNumber($client);
            $client->save();
        }


        GroupSetting::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'settings' =>  ClientSettings::buildClientSettings(CompanySettings::defaults(), ClientSettings::defaults()),
            'name' => 'Default Client Settings',
        ]);


        if(config('ninja.testvars.stripe'))
        {

            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $cg->require_cvv = true;
            $cg->show_billing_address = true;
            $cg->show_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.stripe'));
            $cg->save();

            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $cg->require_cvv = true;
            $cg->show_billing_address = true;
            $cg->show_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.stripe'));
            $cg->save();
        }

        if(config('ninja.testvars.paypal'))
        {
            $cg = new CompanyGateway;
            $cg->company_id = $company->id;
            $cg->user_id = $user->id;
            $cg->gateway_key = '38f2c48af60c7dd69e04248cbb24c36e';
            $cg->require_cvv = true;
            $cg->show_billing_address = true;
            $cg->show_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.paypal'));
            $cg->save();
        }

    }


}
