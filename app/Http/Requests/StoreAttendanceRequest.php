<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
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
            'estudiante_id' => ['required_without:codigo_sistema', 'exists:estudiantes,id'],
            'codigo_sistema' => ['required_without:estudiante_id', 'string'],
            'estado' => ['nullable', 'in:presente,tardanza,falta'],
            'metodo_registro' => ['required', 'in:codigo,manual'],
            'observacion' => ['nullable', 'string'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'codigo_sistema' => $this->has('codigo_sistema') ? trim(strip_tags($this->input('codigo_sistema'))) : null,
            'observacion' => $this->has('observacion') ? trim(strip_tags($this->input('observacion'))) : null,
        ]);
    }
}
