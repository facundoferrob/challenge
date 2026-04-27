<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los query params del listado de productos.
 *
 * Sin esto, Laravel acepta arrays / strings vacíos / valores no numéricos
 * y los coerciona silenciosamente — devolviendo resultados confusos en
 * lugar de errores claros.
 */
class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'brand'     => ['nullable', 'string', 'min:1', 'max:255'],
            'reference' => ['nullable', 'string', 'min:1', 'max:255'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:200'],
            'page'      => ['nullable', 'integer', 'min:1'],
        ];
    }
}
