<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuxiliarRequest extends FormRequest
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
        $userId = $this->route('id');

        return [
            'nombre_completo' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:usuarios,email,' . $userId],
            'dni' => ['required', 'string', 'size:8', 'unique:usuarios,dni,' . $userId],
            'grado_id' => ['required', 'uuid', 'exists:grados,id'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'nombre_completo' => trim(strip_tags($this->input('nombre_completo'))),
            'email' => trim($this->input('email')),
            'dni' => trim($this->input('dni')),
        ]);
    }
}
