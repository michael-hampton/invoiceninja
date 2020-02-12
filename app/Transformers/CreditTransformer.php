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

namespace App\Transformers;

use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Transformers\CreditInvitationTransformer;
use App\Utils\Traits\MakesHash;

class CreditTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [];

    protected $availableIncludes = [
        'invitations',
    //    'payments',
    //    'client',
    //    'documents',
    ];

    public function includeInvitations(Credit $credit)
    {
        $transformer = new CreditInvitationTransformer($this->serializer);

        return $this->includeCollection($credit->invitations, $transformer, CreditInvitation::class);
    }
    /*
        public function includePayments(quote $credit)
        {
            $transformer = new PaymentTransformer($this->account, $this->serializer, $credit);
    
            return $this->includeCollection($credit->payments, $transformer, ENTITY_PAYMENT);
        }
    
        public function includeClient(quote $credit)
        {
            $transformer = new ClientTransformer($this->account, $this->serializer);
    
            return $this->includeItem($credit->client, $transformer, ENTITY_CLIENT);
        }
    
        public function includeExpenses(quote $credit)
        {
            $transformer = new ExpenseTransformer($this->account, $this->serializer);
    
            return $this->includeCollection($credit->expenses, $transformer, ENTITY_EXPENSE);
        }
    
        public function includeDocuments(quote $credit)
        {
            $transformer = new DocumentTransformer($this->account, $this->serializer);
    
            $credit->documents->each(function ($document) use ($credit) {
                $document->setRelation('quote', $credit);
            });
    
            return $this->includeCollection($credit->documents, $transformer, ENTITY_DOCUMENT);
        }
    */
    public function transform(Credit $credit)
    {
        return [
            'id' => $this->encodePrimaryKey($credit->id),
            'user_id' => $this->encodePrimaryKey($credit->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($credit->assigned_user_id),
            'amount' => (float) $credit->amount,
            'balance' => (float) $credit->balance,
            'client_id' => (string) $this->encodePrimaryKey($credit->client_id),
            'status_id' => (string) ($credit->status_id ?: 1),
            'design_id' => (string) ($credit->design_id ?: 1),
            'invoice_id' => (string) ($credit->invoice_id ?: 1),
            'updated_at' => (int)$credit->updated_at,
            'archived_at' => (int)$credit->deleted_at,
            'number' => $credit->number ?: '',
            'discount' => (float) $credit->discount,
            'po_number' => $credit->po_number ?: '',
            'date' => $credit->date ?: '',
            'last_sent_date' => $credit->last_sent_date ?: '',
            'next_send_date' => $credit->date ?: '',
            'due_date' => $credit->due_date ?: '',
            'terms' => $credit->terms ?: '',
            'public_notes' => $credit->public_notes ?: '',
            'private_notes' => $credit->private_notes ?: '',
            'is_deleted' => (bool) $credit->is_deleted,
            'uses_inclusive_taxes' => (bool) $credit->uses_inclusive_taxes,
            'tax_name1' => $credit->tax_name1 ? $credit->tax_name1 : '',
            'tax_rate1' => (float) $credit->tax_rate1,
            'tax_name2' => $credit->tax_name2 ? $credit->tax_name2 : '',
            'tax_rate2' => (float) $credit->tax_rate2,
            'tax_name3' => $credit->tax_name3 ? $credit->tax_name3 : '',
            'tax_rate3' => (float) $credit->tax_rate3,
            'total_taxes' => (float) $credit->total_taxes,
            'is_amount_discount' => (bool) ($credit->is_amount_discount ?: false),
            'footer' => $credit->footer ?: '',
            'partial' => (float) ($credit->partial ?: 0.0),
            'partial_due_date' => $credit->partial_due_date ?: '',
            'custom_value1' => (string) $credit->custom_value1 ?: '',
            'custom_value2' => (string) $credit->custom_value2 ?: '',
            'custom_value3' => (string) $credit->custom_value3 ?: '',
            'custom_value4' => (string) $credit->custom_value4 ?: '',
            'has_tasks' => (bool) $credit->has_tasks,
            'has_expenses' => (bool) $credit->has_expenses,
            'custom_surcharge1' => (float)$credit->custom_surcharge1,
            'custom_surcharge2' => (float)$credit->custom_surcharge2,
            'custom_surcharge3' => (float)$credit->custom_surcharge3,
            'custom_surcharge4' => (float)$credit->custom_surcharge4,
            'custom_surcharge_taxes' => (bool) $credit->custom_surcharge_taxes,
            'line_items' => $credit->line_items ?: (array)[],
            'backup' => $credit->backup ?: '',
        ];
    }
}
