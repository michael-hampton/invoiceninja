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

namespace App\Http\Controllers;

use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Factory\CloneInvoiceFactory;
use App\Factory\CloneInvoiceToQuoteFactory;
use App\Factory\InvoiceFactory;
use App\Filters\InvoiceFilters;
use App\Http\Requests\Invoice\ActionInvoiceRequest;
use App\Http\Requests\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Invoice\DestroyInvoiceRequest;
use App\Http\Requests\Invoice\EditInvoiceRequest;
use App\Http\Requests\Invoice\ShowInvoiceRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Jobs\Invoice\CreateInvoicePdf;
use App\Jobs\Invoice\EmailInvoice;
use App\Jobs\Invoice\StoreInvoice;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use App\Transformers\InvoiceTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

/**
 * Class InvoiceController
 * @package App\Http\Controllers\InvoiceController
 */

class InvoiceController extends BaseController {

	use MakesHash;

	protected $entity_type = Invoice::class ;

	protected $entity_transformer = InvoiceTransformer::class ;

	/**
	 * @var InvoiceRepository
	 */
	protected $invoice_repo;

	/**
	 * InvoiceController constructor.
	 *
	 * @param      \App\Repositories\InvoiceRepository  $invoice_repo  The invoice repo
	 */
	public function __construct(InvoiceRepository $invoice_repo) {
		parent::__construct();

		$this->invoice_repo = $invoice_repo;
	}

	/**
	 * Show the list of Invoices
	 *
	 * @param      \App\Filters\InvoiceFilters  $filters  The filters
	 *
	 * @return \Illuminate\Http\Response
	 *
	 * @OA\Get(
	 *      path="/api/v1/invoices",
	 *      operationId="getInvoices",
	 *      tags={"invoices"},
	 *      summary="Gets a list of invoices",
	 *      description="Lists invoices, search and filters allow fine grained lists to be generated.

	Query parameters can be added to performed more fine grained filtering of the invoices, these are handled by the InvoiceFilters class which defines the methods available",
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
	 *      @OA\Parameter(ref="#/components/parameters/include"),
	 *      @OA\Response(
	 *          response=200,
	 *          description="A list of invoices",
	 *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
	 *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
	 *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
	 *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
	 *       ),
	 *       @OA\Response(
	 *          response=422,
	 *          description="Validation error",
	 *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

	 *       ),
	 *       @OA\Response(
	 *           response="default",
	 *           description="Unexpected Error",
	 *           @OA\JsonContent(ref="#/components/schemas/Error"),
	 *       ),
	 *     )
	 *
	 */
	public function index(InvoiceFilters $filters) {
		$invoices = Invoice::filter($filters);

		return $this->listResponse($invoices);
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @param      \App\Http\Requests\Invoice\CreateInvoiceRequest  $request  The request
	 *
	 * @return \Illuminate\Http\Response
	 *
	 *
	 * @OA\Get(
	 *      path="/api/v1/invoices/create",
	 *      operationId="getInvoicesCreate",
	 *      tags={"invoices"},
	 *      summary="Gets a new blank invoice object",
	 *      description="Returns a blank object with default values",
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
	 *      @OA\Parameter(ref="#/components/parameters/include"),
	 *      @OA\Response(
	 *          response=200,
	 *          description="A blank invoice object",
	 *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
	 *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
	 *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
	 *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
	 *       ),
	 *       @OA\Response(
	 *          response=422,
	 *          description="Validation error",
	 *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
	 *
	 *       ),
	 *       @OA\Response(
	 *           response="default",
	 *           description="Unexpected Error",
	 *           @OA\JsonContent(ref="#/components/schemas/Error"),
	 *       ),
	 *     )
	 *
	 */
	public function create(CreateInvoiceRequest $request) {
		$invoice = InvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id);

		return $this->itemResponse($invoice);
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param      \App\Http\Requests\Invoice\StoreInvoiceRequest  $request  The request
	 *
	 * @return \Illuminate\Http\Response
	 *
	 *
	 * @OA\Post(
	 *      path="/api/v1/invoices",
	 *      operationId="storeInvoice",
	 *      tags={"invoices"},
	 *      summary="Adds a invoice",
	 *      description="Adds an invoice to the system",
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
	 *      @OA\Parameter(ref="#/components/parameters/include"),
	 *      @OA\Response(
	 *          response=200,
	 *          description="Returns the saved invoice object",
	 *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
	 *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
	 *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
	 *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
	 *       ),
	 *       @OA\Response(
	 *          response=422,
	 *          description="Validation error",
	 *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
	 *
	 *       ),
	 *       @OA\Response(
	 *           response="default",
	 *           description="Unexpected Error",
	 *           @OA\JsonContent(ref="#/components/schemas/Error"),
	 *       ),
	 *     )
	 *
	 */
	public function store(StoreInvoiceRequest $request) {
		$invoice = $this->invoice_repo->save($request->all(), InvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id));

		$invoice = StoreInvoice::dispatchNow($invoice, $request->all(), $invoice->company);//todo potentially this may return mixed ie PDF/$invoice... need to revisit when we implement UI

		event(new InvoiceWasCreated($invoice, $invoice->company));

		return $this->itemResponse($invoice);
	}

	/**
	 * Display the specified resource.
	 *
	 * @param      \App\Http\Requests\Invoice\ShowInvoiceRequest  $request  The request
	 * @param      \App\Models\Invoice                            $invoice  The invoice
	 *
	 * @return \Illuminate\Http\Response
	 *
	 *
	 * @OA\Get(
	 *      path="/api/v1/invoices/{id}",
	 *      operationId="showInvoice",
	 *      tags={"invoices"},
	 *      summary="Shows an invoice",
	 *      description="Displays an invoice by id",
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
	 *      @OA\Parameter(ref="#/components/parameters/include"),
	 *      @OA\Parameter(
	 *          name="id",
	 *          in="path",
	 *          description="The Invoice Hashed ID",
	 *          example="D2J234DFA",
	 *          required=true,
	 *          @OA\Schema(
	 *              type="string",
	 *              format="string",
	 *          ),
	 *      ),
	 *      @OA\Response(
	 *          response=200,
	 *          description="Returns the invoice object",
	 *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
	 *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
	 *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
	 *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
	 *       ),
	 *       @OA\Response(
	 *          response=422,
	 *          description="Validation error",
	 *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
	 *
	 *       ),
	 *       @OA\Response(
	 *           response="default",
	 *           description="Unexpected Error",
	 *           @OA\JsonContent(ref="#/components/schemas/Error"),
	 *       ),
	 *     )
	 *
	 */
	public function show(ShowInvoiceRequest $request, Invoice $invoice) {
		return $this->itemResponse($invoice);
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param      \App\Http\Requests\Invoice\EditInvoiceRequest  $request  The request
	 * @param      \App\Models\Invoice                            $invoice  The invoice
	 *
	 * @return \Illuminate\Http\Response
	 *
	 * @OA\Get(
	 *      path="/api/v1/invoices/{id}/edit",
	 *      operationId="editInvoice",
	 *      tags={"invoices"},
	 *      summary="Shows an invoice for editting",
	 *      description="Displays an invoice by id",
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
	 *      @OA\Parameter(ref="#/components/parameters/include"),
	 *      @OA\Parameter(
	 *          name="id",
	 *          in="path",
	 *          description="The Invoice Hashed ID",
	 *          example="D2J234DFA",
	 *          required=true,
	 *          @OA\Schema(
	 *              type="string",
	 *              format="string",
	 *          ),
	 *      ),
	 *      @OA\Response(
	 *          response=200,
	 *          description="Returns the invoice object",
	 *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
	 *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
	 *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
	 *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
	 *       ),
	 *       @OA\Response(
	 *          response=422,
	 *          description="Validation error",
	 *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
	 *
	 *       ),
	 *       @OA\Response(
	 *           response="default",
	 *           description="Unexpected Error",
	 *           @OA\JsonContent(ref="#/components/schemas/Error"),
	 *       ),
	 *     )
	 *
	 */
	public function edit(EditInvoiceRequest $request, Invoice $invoice) {
		return $this->itemResponse($invoice);
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param      \App\Http\Requests\Invoice\UpdateInvoiceRequest  $request  The request
	 * @param      \App\Models\Invoice                              $invoice  The invoice
	 *
	 * @return \Illuminate\Http\Response
	 *
	 *
	 * @OA\Put(
	 *      path="/api/v1/invoices/{id}",
	 *      operationId="updateInvoice",
	 *      tags={"invoices"},
	 *      summary="Updates an invoice",
	 *      description="Handles the updating of an invoice by id",
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
	 *      @OA\Parameter(ref="#/components/parameters/include"),
	 *      @OA\Parameter(
	 *          name="id",
	 *          in="path",
	 *          description="The Invoice Hashed ID",
	 *          example="D2J234DFA",
	 *          required=true,
	 *          @OA\Schema(
	 *              type="string",
	 *              format="string",
	 *          ),
	 *      ),
	 *      @OA\Response(
	 *          response=200,
	 *          description="Returns the invoice object",
	 *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
	 *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
	 *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
	 *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
	 *       ),
	 *       @OA\Response(
	 *          response=422,
	 *          description="Validation error",
	 *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
	 *
	 *       ),
	 *       @OA\Response(
	 *           response="default",
	 *           description="Unexpected Error",
	 *           @OA\JsonContent(ref="#/components/schemas/Error"),
	 *       ),
	 *     )
	 *
	 */
	public function update(UpdateInvoiceRequest $request, Invoice $invoice) {
		if ($request->entityIsDeleted($invoice)) {
			return $request->disallowUpdate();
		}

		$invoice = $this->invoice_repo->save($request->all(), $invoice);

		event(new InvoiceWasUpdated($invoice, $invoice->company));

		return $this->itemResponse($invoice);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param      \App\Http\Requests\Invoice\DestroyInvoiceRequest  $request
	 * @param      \App\Models\Invoice                               $invoice
	 *
	 * @return     \Illuminate\Http\Response
	 *
	 * @OA\Delete(
	 *      path="/api/v1/invoices/{id}",
	 *      operationId="deleteInvoice",
	 *      tags={"invoices"},
	 *      summary="Deletes a invoice",
	 *      description="Handles the deletion of an invoice by id",
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
	 *      @OA\Parameter(ref="#/components/parameters/include"),
	 *      @OA\Parameter(
	 *          name="id",
	 *          in="path",
	 *          description="The Invoice Hashed ID",
	 *          example="D2J234DFA",
	 *          required=true,
	 *          @OA\Schema(
	 *              type="string",
	 *              format="string",
	 *          ),
	 *      ),
	 *      @OA\Response(
	 *          response=200,
	 *          description="Returns a HTTP status",
	 *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
	 *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
	 *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
	 *       ),
	 *       @OA\Response(
	 *          response=422,
	 *          description="Validation error",
	 *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
	 *
	 *       ),
	 *       @OA\Response(
	 *           response="default",
	 *           description="Unexpected Error",
	 *           @OA\JsonContent(ref="#/components/schemas/Error"),
	 *       ),
	 *     )
	 *
	 */
	public function destroy(DestroyInvoiceRequest $request, Invoice $invoice) {
		$invoice->delete();

		return response()->json([], 200);
	}

	/**
	 * Perform bulk actions on the list view
	 *
	 * @return Collection
	 *
	 * @OA\Post(
	 *      path="/api/v1/invoices/bulk",
	 *      operationId="bulkInvoices",
	 *      tags={"invoices"},
	 *      summary="Performs bulk actions on an array of invoices",
	 *      description="",
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
	 *      @OA\Parameter(ref="#/components/parameters/index"),
	 *      @OA\RequestBody(
	 *         description="User credentials",
	 *         required=true,
	 *         @OA\MediaType(
	 *             mediaType="application/json",
	 *             @OA\Schema(
	 *                 type="array",
	 *                 @OA\Items(
	 *                     type="integer",
	 *                     description="Array of hashed IDs to be bulk 'actioned",
	 *                     example="[0,1,2,3]",
	 *                 ),
	 *             )
	 *         )
	 *     ),
	 *      @OA\Response(
	 *          response=200,
	 *          description="The Company User response",
	 *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
	 *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
	 *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
	 *          @OA\JsonContent(ref="#/components/schemas/CompanyUser"),
	 *       ),
	 *       @OA\Response(
	 *          response=422,
	 *          description="Validation error",
	 *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

	 *       ),
	 *       @OA\Response(
	 *           response="default",
	 *           description="Unexpected Error",
	 *           @OA\JsonContent(ref="#/components/schemas/Error"),
	 *       ),
	 *     )
	 *
	 */
	public function bulk() {
		$action = request()->input('action');

		$ids = request()->input('ids');

		$invoices = Invoice::withTrashed()->whereIn('id', $this->transformKeys($ids));

		if (!$invoices) {
			return response()->json(['message' => 'No Invoices Found']);
		}

		$invoices->each(function ($invoice, $key) use ($action) {

				//      $this->invoice_repo->{$action}($invoice);

				if (auth()->user()->can('edit', $invoice)) {
					$this->performAction($invoice, $action, true);
				}
			});

		return $this->listResponse(Invoice::withTrashed()->whereIn('id', $this->transformKeys($ids)));
	}

	/**
	 *
	 * @OA\Get(
	 *      path="/api/v1/invoices/{id}/{action}",
	 *      operationId="actionInvoice",
	 *      tags={"invoices"},
	 *      summary="Performs a custom action on an invoice",
	 *      description="Performs a custom action on an invoice.

	The current range of actions are as follows
	- clone_to_invoice
	- clone_to_quote
	- history
	- delivery_note
	- mark_paid
	- download
	- archive
	- delete
	- email",
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
	 *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
	 *      @OA\Parameter(ref="#/components/parameters/include"),
	 *      @OA\Parameter(
	 *          name="id",
	 *          in="path",
	 *          description="The Invoice Hashed ID",
	 *          example="D2J234DFA",
	 *          required=true,
	 *          @OA\Schema(
	 *              type="string",
	 *              format="string",
	 *          ),
	 *      ),
	 *      @OA\Parameter(
	 *          name="action",
	 *          in="path",
	 *          description="The action string to be performed",
	 *          example="clone_to_quote",
	 *          required=true,
	 *          @OA\Schema(
	 *              type="string",
	 *              format="string",
	 *          ),
	 *      ),
	 *      @OA\Response(
	 *          response=200,
	 *          description="Returns the invoice object",
	 *          @OA\Header(header="X-API-Version", ref="#/components/headers/X-API-Version"),
	 *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
	 *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
	 *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
	 *       ),
	 *       @OA\Response(
	 *          response=422,
	 *          description="Validation error",
	 *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
	 *
	 *       ),
	 *       @OA\Response(
	 *           response="default",
	 *           description="Unexpected Error",
	 *           @OA\JsonContent(ref="#/components/schemas/Error"),
	 *       ),
	 *     )
	 *
	 */
	public function action(ActionInvoiceRequest $request, Invoice $invoice, $action) {
		return $this->performAction($invoice, $action);
	}

	private function performAction(Invoice $invoice, $action, $bulk = false) {
		/*If we are using bulk actions, we don't want to return anything */
		switch ($action) {
			case 'clone_to_invoice':
				$invoice = CloneInvoiceFactory::create($invoice, auth()->user()->id);
				return $this->itemResponse($invoice);
				break;
			case 'clone_to_quote':
				$quote = CloneInvoiceToQuoteFactory::create($invoice, auth()->user()->id);
				// todo build the quote transformer and return response here
				break;
			case 'history':
				# code...
				break;
			case 'delivery_note':
				# code...
				break;
			case 'mark_paid':
				if ($invoice->balance < 0 || $invoice->status_id == Invoice::STATUS_PAID || $invoice->is_deleted === true) {
					return $this->errorResponse(['message' => 'Invoice cannot be marked as paid'], 400);
				}

				$invoice = $invoice->service()->markPaid();

				if (!$bulk) {
					return $this->itemResponse($invoice);
				}
				break;
			case 'mark_sent':
				$invoice->service()->markSent()->save();

				if (!$bulk) {
					return $this->itemResponse($invoice);
				}
				break;
			case 'download':
				return response()->download(public_path($invoice->pdf_file_path()));
				break;
			case 'archive':
				$this->invoice_repo->archive($invoice);

				if (!$bulk) {
					return $this->listResponse($invoice);
				}
				break;
			case 'delete':
				$this->invoice_repo->delete($invoice);

				if (!$bulk) {
					return $this->listResponse($invoice);
				}
				break;
			case 'email':
				EmailInvoice::dispatch($invoice, $invoice->company);
				if (!$bulk) {
					return response()->json(['message' => 'email sent'], 200);
				}
				break;

			default:
				return response()->json(['message' => "The requested action `{$action}` is not available."], 400);
			break;
		}
	}

	public function downloadPdf($invitation_key) {

		$invitation = InvoiceInvitation::whereKey($invitation_key)->company()->first();
		$contact    = $invitation->contact;
		$invoice    = $invitation->invoice;

		$file_path = CreateInvoicePdf::dispatchNow($invoice, $invoice->company, $contact);

		return response()->download($file_path);

		//return response()->json($invitation_key);
	}
}
