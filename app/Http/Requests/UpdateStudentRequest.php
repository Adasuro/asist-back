<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
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
            'nombre_completo' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'digits:8'],
            'seccion_id' => ['required', 'exists:secciones,id'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'telefono' => ['nullable', 'string'],
            'direccion' => ['nullable', 'string'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'nombre_completo' => trim(strip_tags($this->input('nombre_completo'))),
            'direccion' => $this->has('direccion') ? trim(strip_tags($this->input('direccion'))) : null,
            'telefono' => $this->has('telefono') ? trim(strip_tags($this->input('telefono'))) : null,
        ]);
    }
}
