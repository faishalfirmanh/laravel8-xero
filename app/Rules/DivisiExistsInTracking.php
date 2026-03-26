<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

use Illuminate\Support\Facades\DB;


class DivisiExistsInTracking implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        //
      if (empty($value)) return true;

        return DB::table('tracking_categories')
            ->whereJsonContains('lines_category', [['item_uuid_category' => $value]])
            ->orWhereJsonContains('lines_category', ['item_uuid_category' => $value])
            ->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */

    public function message()
    {
        return 'Divisi yang dipilih tidak terdaftar di master tracking category.';
    }



}
