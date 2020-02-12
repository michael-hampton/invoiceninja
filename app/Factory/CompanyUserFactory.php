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

namespace App\Factory;

use App\Models\CompanyUser;

class CompanyUserFactory
{
    public static function create($user_id, $company_id, $account_id) :CompanyUser
    {
        $company_user = new CompanyUser;
        $company_user->user_id = $user_id;
        $company_user->company_id = $company_id;
        $company_user->account_id = $account_id;
        
        return $company_user;
    }
}
