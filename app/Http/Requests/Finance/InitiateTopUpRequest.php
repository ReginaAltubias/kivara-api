<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateTopUpRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:500'],
            'payment_method' => ['required', Rule::in(['multicaixa_express'])],
            'phone' => ['required', 'string', 'regex:/^(\+?244)?9\d{8}$/', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'O valor mínimo de carregamento é 500 AOA.',
            'phone.regex' => 'Indica um número de telemóvel angolano válido para Multicaixa Express.',
        ];
    }
}
