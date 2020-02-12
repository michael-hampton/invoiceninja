<?php

use App\Models\GatewayType;
use Illuminate\Database\Seeder;

class GatewayTypesSeeder extends Seeder
{
    public function run()
    {
        Eloquent::unguard();

        $gateway_types = [
            ['id' => 1, 'alias' => 'credit_card', 'name' => 'Credit Card'],
            ['id' => 2, 'alias' => 'bank_transfer', 'name' => 'Bank Transfer'],
            ['id' => 3, 'alias' => 'paypal', 'name' => 'PayPal'],
            ['id' => 4, 'alias' => 'crypto', 'name' => 'Crypto'],
            ['id' => 5, 'alias' => 'dwolla', 'name' => 'Dwolla'],
            ['id' => 6, 'alias' => 'custom1', 'name' => 'Custom'],
            ['id' => 7, 'alias' => 'alipay', 'name' => 'Alipay'],
            ['id' => 8, 'alias' => 'sofort', 'name' => 'Sofort'],
            ['id' => 9, 'alias' => 'sepa', 'name' => 'SEPA'],
            ['id' => 10, 'alias' => 'gocardless', 'name' => 'GoCardless'],
            ['id' => 11, 'alias' => 'apple_pay', 'name' => 'Apple Pay'],
            ['id' => 12, 'alias' => 'custom2', 'name' => 'Custom'],
            ['id' => 13, 'alias' => 'custom3', 'name' => 'Custom'],
        ];

        foreach ($gateway_types as $gateway_type) {
            $record = GatewayType::where('alias', '=', $gateway_type['alias'])->first();
            if ($record) {
                $record->fill($gateway_type);
                $record->save();
            } else {
                GatewayType::create($gateway_type);
            }
        }
    }
}
