<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'target_id' => ['required', 'string', 'max:255'],
            'server_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Product harus dipilih',
            'product_id.exists' => 'Product tidak ditemukan',
            'target_id.required' => 'Target ID (nomor tujuan) harus diisi',
            'target_id.string' => 'Target ID harus berupa text',
        ];
    }
}
