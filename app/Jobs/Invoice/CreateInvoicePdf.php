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

namespace App\Jobs\Invoice;

use App\Designs\Designer;
use App\Designs\Modern;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Invoice;

use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\App;

use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class CreateInvoicePdf implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter, MakesInvoiceHtml;

	public $invoice;

	public $company;

	public $contact;
	/**
	 * Create a new job instance.
	 *
	 * @return void
	 */
	public function __construct(Invoice $invoice, Company $company, ClientContact $contact) {
		$this->invoice = $invoice;

		$this->company = $company;

		$this->contact = $contact;
	}

	public function handle() {
		MultiDB::setDB($this->company->db);

		App::setLocale($this->contact->preferredLocale());

		$this->invoice->load('client');
		$path      = 'public/'.$this->invoice->client->client_hash.'/invoices/';
		$file_path = $path.$this->invoice->number.'.pdf';

		$modern   = new Modern();
		$designer = new Designer($modern, $this->invoice->client->getSetting('invoice_variables'));

		//get invoice design
		$html = $this->generateInvoiceHtml($designer->build($this->invoice)->getHtml(), $this->invoice, $this->contact);

		//todo - move this to the client creation stage so we don't keep hitting this unnecessarily
		Storage::makeDirectory($path, 0755);

		//\Log::error($html);
		//create pdf
		$pdf = $this->makePdf(null, null, $html);

		$path = Storage::put($file_path, $pdf);

		return $path;
	}

	/**
	 * Returns a PDF stream
	 *
	 * @param  string $header Header to be included in PDF
	 * @param  string $footer Footer to be included in PDF
	 * @param  string $html   The HTML object to be converted into PDF
	 *
	 * @return string        The PDF string
	 */
	private function makePdf($header, $footer, $html) {
		return Browsershot::html($html)
		//->showBrowserHeaderAndFooter()
		//->headerHtml($header)
		//->footerHtml($footer)
			->deviceScaleFactor(1)
			->showBackground()
			->waitUntilNetworkIdle(true)	->pdf();
		//->margins(10,10,10,10)
		//->savePdf('test.pdf');
	}
}
