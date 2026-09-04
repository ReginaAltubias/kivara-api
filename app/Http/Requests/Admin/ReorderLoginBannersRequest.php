<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderLoginBannersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'banners' => ['required', 'array', 'min:1'],
            'banners.*.id' => ['required', 'uuid', 'exists:login_banners,id'],
            'banners.*.order' => ['required', 'integer', 'min:0'],
        ];
    }
}
