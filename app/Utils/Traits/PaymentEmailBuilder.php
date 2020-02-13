<?php

namespace App\Utils\Traits;

use App\Models\ClientContact;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use League\CommonMark\CommonMarkConverter;
use Parsedown;

/**
 * Class PaymentEmailBuilder
 * @package App\Utils\Traits
 */
trait PaymentEmailBuilder
{


    /**
     * Builds the correct template to send
     * @param string $reminder_template The template name ie reminder1
     * @return array
     */
    public function getEmailData($reminder_template = null, $contact = null): array
    {
        //client
        //$client = $this->client;


        //Need to determine which email template we are producing
        return $this->generateTemplateData($reminder_template, $contact);
    }

    private function generateTemplateData(string $reminder_template, $contact): array
    {
        $data = [];

        $client = $this->client;

        $body_template = $client->getSetting('email_template_' . $reminder_template);

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans('texts.payment_message',
                ['amount' => $this->present()->amount(), 'account' => $this->company->present()->name()], null,
                $this->client->locale());
        }

        $subject_template = $client->getSetting('payment_subject');

        if (iconv_strlen($subject_template) == 0) {
            $subject_template = trans('texts.invoice_subject',
                ['number' => $this->present()->invoice_number(), 'account' => $this->company->present()->name()], null,
                $this->client->locale());
        }

        $data['body'] = $this->parseTemplate($body_template, true, $contact);
        $data['subject'] = $this->parseTemplate($subject_template, false, $contact);

        if ($client->getSetting('pdf_email_attachment') !== false) {
            $data['files'][] = $this->pdf_file_path();
        }

        return $data;
    }

    private function parseTemplate(string $template_data, bool $is_markdown = true, $contact): string
    {
        $invoice_variables = $this->makeValues($contact);

        //process variables
        $data = str_replace(array_keys($invoice_variables), array_values($invoice_variables), $template_data);

        //process markdown
        if ($is_markdown) {
            //$data = Parsedown::instance()->line($data);

            $converter = new CommonMarkConverter([
                'html_input' => 'allow',
                'allow_unsafe_links' => true,
            ]);

            $data = $converter->convertToHtml($data);
        }

        return $data;
    }
}
