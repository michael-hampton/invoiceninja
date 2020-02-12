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

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Country;
use App\Models\Currency;
use App\Models\DateFormat;
use App\Models\DatetimeFormat;
use App\Models\Filterable;
use App\Models\GatewayType;
use App\Models\GroupSetting;
use App\Models\Language;
use App\Models\Timezone;
use App\Models\User;
use App\Services\Client\ClientService;
use App\Utils\Traits\CompanyGatewaySettings;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Laracasts\Presenter\PresentableTrait;

class Client extends BaseModel implements HasLocalePreference
{
    use PresentableTrait;
    use MakesHash;
    use MakesDates;
    use SoftDeletes;
    use Filterable;
    use GeneratesCounter;
    
    protected $presenter = 'App\Models\Presenters\ClientPresenter';

    protected $hidden = [
        'id',
        'private_notes',
        'user_id',
        'company_id',
        'backup',
        'settings',
        'last_login',
        'private_notes'
    ];
   
    protected $fillable = [
        'currency_id',
        'name',
        'website',
        'private_notes',
        'industry_id',
        'size_id',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country_id',
        'settings',
        'payment_terms',
        'vat_number',
        'id_number',
        'group_settings_id',
    ];
    
    
    protected $with = [
        //'currency',
        // 'primary_contact',
        // 'country',
        // 'contacts',
        // 'shipping_country',
        // 'company',
    ];
    
    protected $casts = [
        'is_deleted' => 'boolean',
        'country_id' => 'string',
        'settings' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    public function gateway_tokens()
    {
        return $this->hasMany(ClientGatewayToken::class);
    }

    /**
     * Retrieves the specific payment token per
     * gateway - per payment method
     *
     * Allows the storage of multiple tokens
     * per client per gateway per payment_method
     *
     * @param  int $company_gateway_id  The company gateway ID
     * @param  int $payment_method_id   The payment method ID
     * @return ClientGatewayToken       The client token record
     */
    public function gateway_token($company_gateway_id, $payment_method_id)
    {
        return $this->gateway_tokens()
                    ->whereCompanyGatewayId($company_gateway_id)
                    ->whereGatewayTypeId($payment_method_id)
                    ->first();
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function contacts()
    {
        return $this->hasMany(ClientContact::class)->orderBy('is_primary', 'desc');
    }

    public function primary_contact()
    {
        return $this->hasMany(ClientContact::class)->whereIsPrimary(true);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function shipping_country()
    {
        return $this->belongsTo(Country::class, 'shipping_country_id', 'id');
    }

    public function timezone()
    {
        return Timezone::find($this->getSetting('timezone_id'));
    }

    public function language()
    {
        return Language::find($this->getSetting('language_id'));
    }

    public function locale()
    {
        return $this->language()->locale ?:  'en';
    }

    public function date_format()
    {
        $date_formats = Cache::get('date_formats');
        
        return $date_formats->filter(function ($item) {
            return $item->id == $this->getSetting('date_format_id');
        })->first()->format;

        //return DateFormat::find($this->getSetting('date_format_id'))->format;
    }

    public function currency()
    {
        $currencies = Cache::get('currencies');
        
        return $currencies->filter(function ($item) {
            return $item->id == $this->getSetting('currency_id');
        })->first();
    }

    public function service() :ClientService
    {
        return new ClientService($this);
    }

    public function updateBalance($amount) :ClientService
    {
        return $this->service()->updateBalance($amount);
    }

    /**
     * Adjusts client "balances" when a client
     * makes a payment that goes on file, but does
     * not effect the client.balance record
     *    
     * @param  float $amount Adjustment amount
     * @return Client         
     */
    public function processUnappliedPayment($amount) :Client
    {

        return $this->service()->updatePaidToDate($amount)
                                ->adjustCreditBalance($amount)
                                ->save();
    }

    /**
     *
     * Returns the entire filtered set
     * of settings which have been merged from
     * Client > Group > Company levels
     *
     * @return object stdClass object of settings
     */
    public function getMergedSettings() :object
    {
        if ($this->group_settings !== null) {
            $group_settings = ClientSettings::buildClientSettings($this->group_settings->settings, $this->settings);

            return ClientSettings::buildClientSettings($this->company->settings, $group_settings);
        }

        return CompanySettings::setProperties(ClientSettings::buildClientSettings($this->company->settings, $this->settings));
    }

    /**
     *
     * Returns a single setting
     * which cascades from
     * Client > Group > Company
     *
     * @param  string $setting The Setting parameter
     * @return mixed          The setting requested
     */
    public function getSetting($setting)
    {
        /*Client Settings*/
        if ($this->settings && (property_exists($this->settings, $setting) !== false) && (isset($this->settings->{$setting}) !== false)) {

            /*need to catch empty string here*/
            if (is_string($this->settings->{$setting}) && (iconv_strlen($this->settings->{$setting}) >=1)) {
                return $this->settings->{$setting};
            }
        }

        /*Group Settings*/
        if ($this->group_settings && (property_exists($this->group_settings->settings, $setting) !== false) && (isset($this->group_settings->settings->{$setting}) !== false)) {
            return $this->group_settings->settings->{$setting};
        }

        /*Company Settings*/
        if ((property_exists($this->company->settings, $setting) != false) && (isset($this->company->settings->{$setting}) !== false)) {
            return $this->company->settings->{$setting};
        }

        throw new \Exception("Settings corrupted", 1);
    }

    public function getSettingEntity($setting)
    {
        /*Client Settings*/
        if ($this->settings && (property_exists($this->settings, $setting) !== false) && (isset($this->settings->{$setting}) !== false)) {
            /*need to catch empty string here*/
            if (is_string($this->settings->{$setting}) && (iconv_strlen($this->settings->{$setting}) >=1)) {
                return $this;
            }
        }

        /*Group Settings*/
        if ($this->group_settings && (property_exists($this->group_settings->settings, $setting) !== false) && (isset($this->group_settings->settings->{$setting}) !== false)) {
            return $this->group_settings;
        }

        /*Company Settings*/
        if ((property_exists($this->company->settings, $setting) != false) && (isset($this->company->settings->{$setting}) !== false)) {
            return $this->company;
        }

        throw new \Exception("Could not find a settings object", 1);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function group_settings()
    {
        return $this->belongsTo(GroupSetting::class);
    }

    /**
     * Returns the first Credit Card Gateway
     *
     * @return NULL|CompanyGateway The Priority Credit Card gateway
     */
    public function getCreditCardGateway() :?CompanyGateway
    {
        $company_gateways = $this->getSetting('company_gateway_ids');
        
        if ($company_gateways) {
            $gateways = $this->company->company_gateways->whereIn('id', $payment_gateways);
        } else {
            $gateways = $this->company->company_gateways;
        }

        foreach ($gateways as $gateway) {
            if (in_array(GatewayType::CREDIT_CARD, $gateway->driver($this)->gatewayTypes())) {
                return $gateway;
            }
        }

        return null;
    }

    public function getCurrencyCode()
    {
        if ($this->currency()) {
            return $this->currency()->code;
        }

        return 'USD';
    }
    /**
     * Generates an array of payment urls per client
     * for a given amount.
     *
     * The route produced will provide the
     * company_gateway and payment_type ids
     *
     * The invoice/s will need to be injected
     * upstream of this method as they are not
     * included in this logic.
     *
     * @param  float $amount The amount to be charged
     * @return array         Array of payment labels and urls
     */
    public function getPaymentMethods($amount) :array
    {
        //this method will get all the possible gateways a client can pay with
        //but we also need to consider payment methods that are already stored
        //so we MUST filter the company gateways and remove duplicates.
//
        //Also need to harvest the list of client gateway tokens and present these
        //for instant payment

        $company_gateways = $this->getSetting('company_gateway_ids');

        if ($company_gateways) {
            $gateways = $this->company->company_gateways->whereIn('id', $payment_gateways);
        } else {
            $gateways = $this->company->company_gateways;
        }

        $gateways->filter(function ($method) use ($amount) {
            if ($method->min_limit !==  null && $amount < $method->min_limit) {
                return false;
            }

            if ($method->max_limit !== null && $amount > $method->min_limit) {
                return false;
            }
        });

        $payment_methods = [];

        foreach ($gateways as $gateway) {
            foreach ($gateway->driver($this)->gatewayTypes() as $type) {
                $payment_methods[] = [$gateway->id => $type];
            }
        }
            

        $payment_methods_collections = collect($payment_methods);

        //** Plucks the remaining keys into its own collection
        $payment_methods_intersect = $payment_methods_collections->intersectByKeys($payment_methods_collections->flatten(1)->unique());

        $payment_urls = [];

        foreach ($payment_methods_intersect as $key => $child_array) {
            foreach ($child_array as $gateway_id => $gateway_type_id) {
                $gateway = $gateways->where('id', $gateway_id)->first();

                $fee_label = $gateway->calcGatewayFeeLabel($amount, $this);

                $payment_urls[] = [
                    'label' => ctrans('texts.' . $gateway->getTypeAlias($gateway_type_id)) . $fee_label,
                    'company_gateway_id'  => $gateway_id,
                    'gateway_type_id' => $gateway_type_id
                            ];
            }
        }

        return $payment_urls;
    }

    public function preferredLocale()
    {
        $languages = Cache::get('languages');
        
        return $languages->filter(function ($item) {
            return $item->id == $this->client->getSetting('language_id');
        })->first()->locale;

        //$lang = Language::find($this->client->getSetting('language_id'));

        //return $lang->locale;
    }

}
