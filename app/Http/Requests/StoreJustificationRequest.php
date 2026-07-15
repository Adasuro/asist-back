<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJustificationRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'asistencia_id' => ['required', 'exists:asistencias,id'],
            'motivo' => ['required', 'string'],
            'documento_url' => ['nullable', 'string'],
            'documento' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:2048'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'motivo' => trim(strip_tags($this->input('motivo'))),
            'documento_url' => $this->has('documento_url') && is_string($this->input('documento_url')) ? trim(strip_tags($this->input('documento_url'))) : null,
        ]);
    }
}
