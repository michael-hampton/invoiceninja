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

namespace App\Models;

use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Models\Filterable;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Credit extends BaseModel
{
    use MakesHash;
    use Filterable;
    use MakesDates;
    use SoftDeletes;
    
    protected $fillable = [
        'number',
        'discount',
        'po_number',
        'date',
        'due_date',
        'terms',
        'public_notes',
        'private_notes',
        'invoice_type_id',
        'tax_name1',
        'tax_rate1',
        'tax_name2',
        'tax_rate2',
        'tax_name3',
        'tax_rate3',
        'is_amount_discount',
        'footer',
        'partial',
        'partial_due_date',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'line_items',
        'client_id',
        'footer',
    ];

    protected $casts = [
        'line_items' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    const STATUS_DRAFT = 1;
    const STAUS_PARTIAL =  2;
    const STATUS_APPLIED = 3;

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function invitations()
    {
        return $this->hasMany(CreditInvitation::class);
    }

    /**
     * The invoice which the credit has been created from
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The invoice/s which the credit has
     * been applied to.
     */
    public function invoices()
    {
        return $this->belongsToMany(Invoice::class)->using(Paymentable::class);
    }

    public function payments()
    {
        return $this->morphToMany(Payment::class, 'paymentable');
    }

    /**
     * Access the invoice calculator object
     *
     * @return object The invoice calculator object getters
     */
    public function calc()
    {
        $credit_calc = null;

        if ($this->uses_inclusive_taxes) {
            $credit_calc = new InvoiceSumInclusive($this);
        } else {
            $credit_calc = new InvoiceSum($this);
        }

        return $credit_calc->build();
    }




    /**
     * @param float $balance_adjustment
     */
    public function updateBalance($balance_adjustment)
    {
        if ($this->is_deleted) {
            return;
        }

        $balance_adjustment = floatval($balance_adjustment);

        $this->balance = $this->balance + $balance_adjustment;

        if ($this->balance == 0) {
            $this->status_id = self::STATUS_APPLIED;
            $this->save();
            //event(new InvoiceWasPaid($this, $this->company));//todo

            return;
        }

        $this->save();
    }

    public function setStatus($status)
    {
        $this->status_id = $status;
        $this->save();
    }
    
}
