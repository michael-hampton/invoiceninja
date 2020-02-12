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

namespace App\Utils\Traits;

use App\Libraries\MultiDB;
use Hashids\Hashids;

/**
 * Class MakesHash
 * @package App\Utils\Traits
 */
trait MakesHash
{
    /**
     * Creates a simple alphanumeric Hash
     * @return string - asd89f7as89df6asf78as6fds
     */
    public function createHash() : string
    {
        return \Illuminate\Support\Str::random(config('ninja.key_length'));
    }

    /**
     * Creates a simple alphanumeric Hash which is prepended with a encoded database prefix
     *
     * @param $db - Full database name
     * @return string 01-asfas8df76a78f6a78dfsdf
     */
    public function createDbHash($db) : string
    {
        return  $this->getDbCode($db) . '-' . \Illuminate\Support\Str::random(config('ninja.key_length'));
    }

    /**
     * @param $db - Full database name
     * @return string - hashed and encoded int 01,02,03,04
     */
    public function getDbCode($db) : string
    {
        $hashids = new Hashids('', 10);

        return $hashids->encode(str_replace(MultiDB::DB_PREFIX, "", $db));
    }

    public function encodePrimaryKey($value) : string
    {
        $hashids = new Hashids('', 10);

        return $hashids->encode($value);
    }

    public function decodePrimaryKey($value) : string
    {
    //    \Log::error("pre decode = {$value}");

        try {
            $hashids = new Hashids('', 10);

            $decoded_array =  $hashids->decode($value);

  //          \Log::error($decoded_array);

            return $decoded_array[0];
        } catch (\Exception $e) {
            return response()->json(['error'=>'Invalid primary key'], 400);
        }
    }

    public function transformKeys($keys)
    {
        if (is_array($keys)) {
            foreach ($keys as &$value) {
                $value = $this->decodePrimaryKey($value);
            }

            return $keys;
        } else {
            return $this->decodePrimaryKey($keys);
        }
    }
}
