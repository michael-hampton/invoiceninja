<?php

namespace Tests\Feature;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Quote;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\QuoteController
 */
    
class QuoteTest extends TestCase
{

    use MakesHash;
    use DatabaseTransactions;

    public function setUp() :void
    {

        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();


    }

    public function testQuoteList()
    {
        $data = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'name' => $this->faker->company,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'ALongAndBrilliantPassword123',
            '_token' => csrf_token(),
            'privacy_policy' => 1,
            'terms_of_service' => 1
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
            ])->post('/api/v1/signup?include=account', $data);

        $acc = $response->json();

        $account = Account::find($this->decodePrimaryKey($acc['data'][0]['account']['id']));                

        $company_token = $account->default_company->tokens()->first();
        $token = $company_token->token;
        $company = $company_token->company;

        $user = $company_token->user;

        $this->assertNotNull($company_token);
        $this->assertNotNull($token);
        $this->assertNotNull($user);
        $this->assertNotNull($company);
        //$this->assertNotNull($user->token->company);

        factory(\App\Models\Client::class, 1)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company){

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1
            ]);

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id
            ]);

        });
        $client = Client::all()->first();

        factory(\App\Models\Quote::class, 1)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->get('/api/v1/quotes');

        $response->assertStatus(200);

    }

    public function testQuoteRESTEndPoints()
    {
        $data = [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
          'name' => $this->faker->company,
        'email' => $this->faker->unique()->safeEmail,
            'password' => 'ALongAndBrilliantPassword123',
            '_token' => csrf_token(),
            'privacy_policy' => 1,
            'terms_of_service' => 1
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
            ])->post('/api/v1/signup?include=account', $data);

        $acc = $response->json();

        $account = Account::find($this->decodePrimaryKey($acc['data'][0]['account']['id']));                

        $company_token = $account->default_company->tokens()->first();
        $token = $company_token->token;
        $company = $company_token->company;

        $user = $company_token->user;

        $this->assertNotNull($company_token);
        $this->assertNotNull($token);
        $this->assertNotNull($user);
        $this->assertNotNull($company);
        //$this->assertNotNull($user->token->company);

        factory(\App\Models\Client::class, 1)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company){

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1
            ]);

            factory(\App\Models\ClientContact::class,1)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id
            ]);

        });
        $client = Client::all()->first();

        factory(\App\Models\Quote::class, 1)->create(['user_id' => $user->id, 'company_id' => $company->id, 'client_id' => $client->id]);

        $quote = Quote::where('user_id',$user->id)->first();
        $quote->save();

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->get('/api/v1/quotes/'.$this->encodePrimaryKey($quote->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->get('/api/v1/quotes/'.$this->encodePrimaryKey($quote->id).'/edit');

        $response->assertStatus(200);

        $quote_update = [
            'status_id' => Quote::STATUS_APPROVED,
         //   'client_id' => $this->encodePrimaryKey($quote->client_id),
        ];

        $this->assertNotNull($quote);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->put('/api/v1/quotes/'.$this->encodePrimaryKey($quote->id), $quote_update);

        $response->assertStatus(200);

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->delete('/api/v1/quotes/'.$this->encodePrimaryKey($quote->id));

        $response->assertStatus(200);

        $client_contact = ClientContact::whereClientId($client->id)->first();

        $data = [
            'client_id' => $this->encodePrimaryKey($client->id),
            'date' => "2019-12-14",
            'line_items' => [],
            'invitations' => [
                ['client_contact_id' => $this->encodePrimaryKey($client_contact->id)]
            ],
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $token,
            ])->post('/api/v1/quotes', $data);

        $response->assertStatus(200);
    }

}


