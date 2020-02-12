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

namespace App\PaymentDrivers;

use App\Events\Payment\PaymentWasCreated;
use App\Jobs\Invoice\UpdateInvoicePayment;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Omnipay\Common\Item;

/**
 * Response array
 * (
  'TOKEN' => 'EC-50V302605X606694D',
  'SUCCESSPAGEREDIRECTREQUESTED' => 'false',
  'TIMESTAMP' => '2019-09-30T22:21:21Z',
  'CORRELATIONID' => '9e0da63193090',
  'ACK' => 'SuccessWithWarning',
  'VERSION' => '119.0',
  'BUILD' => '53688488',
  'L_ERRORCODE0' => '11607',
  'L_SHORTMESSAGE0' => 'Duplicate Request',
  'L_LONGMESSAGE0' => 'A successful transaction has already been completed for this token.',
  'L_SEVERITYCODE0' => 'Warning',
  'INSURANCEOPTIONSELECTED' => 'false',
  'SHIPPINGOPTIONISDEFAULT' => 'false',
  'PAYMENTINFO_0_TRANSACTIONID' => '5JE20141KL116573G',
  'PAYMENTINFO_0_TRANSACTIONTYPE' => 'expresscheckout',
  'PAYMENTINFO_0_PAYMENTTYPE' => 'instant',
  'PAYMENTINFO_0_ORDERTIME' => '2019-09-30T22:20:57Z',
  'PAYMENTINFO_0_AMT' => '31260.37',
  'PAYMENTINFO_0_TAXAMT' => '0.00',
  'PAYMENTINFO_0_CURRENCYCODE' => 'USD',
  'PAYMENTINFO_0_EXCHANGERATE' => '0.692213615971749',
  'PAYMENTINFO_0_PAYMENTSTATUS' => 'Pending',
  'PAYMENTINFO_0_PENDINGREASON' => 'unilateral',
  'PAYMENTINFO_0_REASONCODE' => 'None',
  'PAYMENTINFO_0_PROTECTIONELIGIBILITY' => 'Ineligible',
  'PAYMENTINFO_0_PROTECTIONELIGIBILITYTYPE' => 'None',
  'PAYMENTINFO_0_ERRORCODE' => '0',
  'PAYMENTINFO_0_ACK' => 'Success',
)
 */

class PayPalExpressPaymentDriver extends BasePaymentDriver
{
    use MakesHash;

    protected $refundable = false;

    protected $token_billing = false;

    protected $can_authorise_credit_card = false;

    protected $customer_reference = '';


    public function gatewayTypes()
    {
        return [
            GatewayType::PAYPAL,
        ];
    }

    /**
     * Processes the payment with this gateway
     *
     * @var $data['invoices']
     * @var $data['amount']
     * @var $data['fee']
     * @var $data['amount_with_fee']
     * @var $data['token']
     * @var $data['payment_method_id']
     * @var $data['hashed_ids']
     *
     * @param  array  $data variables required to build payment page
     * @return view   Gateway and payment method specific view
     */
    public function processPaymentView(array $data)
    {
        $response = $this->purchase($this->paymentDetails($data), $this->paymentItems($data));


        if ($response->isRedirect()) {
            // redirect to offsite payment gateway
            $response->redirect();
        } elseif ($response->isSuccessful()) {
            // payment was successful: update database
            /* for this driver this method wont be hit*/
        } else {
            // payment failed: display message to customer

            SystemLogger::dispatch(
                [
              'server_response' => $response->getData(),
              'data' => $data
            ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYPAL,
                $this->client
            );

            throw new \Exception("Error Processing Payment", 1);
        }
    }

    public function processPaymentResponse($request)
    {
        $response = $this->completePurchase($request->all());

        $transaction_reference = $response->getTransactionReference() ?: $request->input('token');

        if ($response->isCancelled()) {
            return redirect()->route('client.invoices.index')->with('warning', ctrans('texts.status_voided'));
        } elseif ($response->isSuccessful()) {
            SystemLogger::dispatch(
                [
                'server_response' => $response->getData(),
                'data' => $request->all()
              ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_PAYPAL,
                $this->client
            );
        } elseif (! $response->isSuccessful()) {
            SystemLogger::dispatch(
                [
              'data' => $request->all(),
              'server_response' => $response->getData()
            ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYPAL,
                $this->client
            );

            throw new \Exception($response->getMessage());
        }

        $payment = $this->createPayment($response->getData());

        $this->attachInvoices($payment, $request->input('hashed_ids'));

        event(new PaymentWasCreated($payment, $payment->company));

        UpdateInvoicePayment::dispatchNow($payment, $payment->company);

        return redirect()->route('client.payments.show', ['payment'=>$this->encodePrimaryKey($payment->id)]);
    }

    protected function paymentDetails($input) :array
    {
        $data = parent::paymentDetails($input);

        $data['amount'] = $input['amount_with_fee'];
        $data['returnUrl'] = $this->buildReturnUrl($input);
        $data['cancelUrl'] = $this->buildCancelUrl($input);
        $data['description'] = $this->buildDescription($input);
        $data['transactionId'] = $this->buildTransactionId($input);

        $data['ButtonSource'] = 'InvoiceNinja_SP';
        $data['solutionType'] = 'Sole'; // show 'Pay with credit card' option
        $data['transactionId'] = $data['transactionId'] . '-' . time();

        return $data;
    }

    private function buildReturnUrl($input) : string
    {
        $url = $this->client->company->domain() . "client/payments/process/response";
        $url .= "?company_gateway_id={$this->company_gateway->id}&gateway_type_id=".GatewayType::PAYPAL;
        $url .= "&hashed_ids=" . implode(",", $input['hashed_ids']);
        $url .= "&amount=".$input['amount'];
        $url .= "&fee=".$input['fee'];

        return $url;
    }

    private function buildCancelUrl($input) : string
    {
        $url = $this->client->company->domain() . '/client/invoices';

        return $url;
    }

    private function buildDescription($input) : string
    {
        $invoice_numbers = "";

        foreach ($input['invoices'] as $invoice) {
            $invoice_numbers .= $invoice->number." ";
        }

        return ctrans('texts.invoice_number'). ": {$invoice_numbers}";
    }

    private function buildTransactionId($input) : string
    {
        return implode(",", $input['hashed_ids']);
    }

    private function paymentItems($input) : array
    {
        $items = [];
        $total = 0;

        foreach ($input['invoices'] as $invoice) {
            foreach ($invoice->line_items as $invoiceItem) {
                // Some gateways require quantity is an integer
                if (floatval($invoiceItem->quantity) != intval($invoiceItem->quantity)) {
                    return null;
                }

                $item = new Item([
                    'name' => $invoiceItem->product_key,
                    'description' => substr($invoiceItem->notes, 0, 100),
                    'price' => $invoiceItem->cost,
                    'quantity' => $invoiceItem->quantity,
                ]);

                $items[] = $item;

                $total += $invoiceItem->cost * $invoiceItem->quantity;
            }
        }

        if ($total != $input['amount_with_fee']) {
            $item = new Item([
                'name' => trans('texts.taxes_and_fees'),
                'description' => '',
                'price' => $input['amount_with_fee'] - $total,
                'quantity' => 1,
            ]);

            $items[] = $item;
        }

        return $items;
    }

    public function createPayment($data) : Payment
    {
        $payment = parent::createPayment($data);

        $client_contact = $this->getContact();
        $client_contact_id = $client_contact ? $client_contact->id : null;

        $payment->amount = $data['PAYMENTINFO_0_AMT'];
        $payment->type_id = PaymentType::PAYPAL;
        $payment->transaction_reference = $data['PAYMENTINFO_0_TRANSACTIONID'];
        $payment->client_contact_id = $client_contact_id;
        $payment->save();

        return $payment;
    }
}
