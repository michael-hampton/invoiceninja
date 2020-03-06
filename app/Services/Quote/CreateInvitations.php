<?php
namespace App\Services\Quote;

use App\Factory\QuoteInvitationFactory;
use App\Models\QuoteInvitation;

class CreateInvitations extends AbstractService
{

    private $quote;

    public function __construct($quote)
    {
        $this->quote = $quote;
    }

    public function run($quote)
    {

        $this->quote->client->contacts->each(function ($contact) {
            $invitation = QuoteInvitation::whereCompanyId($this->quote->company_id)
                ->whereClientContactId($contact->id)
                ->whereQuoteId($this->quote->id)
                ->first();

            if (!$invitation && $contact->send_email) {
                $ii = QuoteInvitationFactory::create($this->quote->company_id, $this->quote->user_id);
                $ii->quote_id = $this->quote->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && !$contact->send_email) {
                $invitation->delete();
            }
        });

        return $this->quote->fresh();
    }
}
