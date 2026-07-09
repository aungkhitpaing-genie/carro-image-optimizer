<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateVariantRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'disk' => ['required', 'string', Rule::in(array_keys(config('filesystems.disks')))],
            'path' => ['required', 'string'],
            'mime_type' => ['sometimes', 'nullable', 'string', 'starts_with:image/'],
            'file_name' => ['sometimes', 'nullable', 'string'],
            'variants' => ['sometimes', 'array'],
            'variants.*' => ['string', Rule::in(array_keys(config('image-variants.variants')))],
            'overwrite' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'disk.in' => 'The selected disk is not configured.',
            'variants.*.in' => 'One or more variant names are invalid.',
        ];
    }
}
