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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\UpdateContactRequest;
use App\Http\Requests\ClientPortal\UpdateClientRequest;
use App\Jobs\Util\UploadAvatar;
use App\Models\ClientContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(ClientContact $client_contact)
    {
        /* Dropzone configuration */
        $data = [
            'params' => [
                'is_avatar' => true,
            ],
            'url' => '/client/document',
            'multi_upload' => false,
        ];
        
        return view('portal.default.profile.index', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateContactRequest $request, ClientContact $client_contact)
    {
        $client_contact->fill($request->all());

        //update password if needed
        if ($request->input('password')) {
            $client_contact->password = Hash::make($request->input('password'));
        }

        $client_contact->save();

        // auth()->user()->fresh();

        return back();
    }

    public function updateClient(UpdateClientRequest $request, ClientContact $client_contact)
    {
        $client = $client_contact->client;

        //update avatar if needed
        if ($request->file('logo')) {
            $path = UploadAvatar::dispatchNow($request->file('logo'), auth()->user()->client->client_hash);

            if ($path) {
                $client->logo = $path;
            }
        }

        $client->fill($request->all());
        $client->save();

        return back();
    }
}
