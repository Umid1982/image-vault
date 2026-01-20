<?php

namespace App\Http\Requests\Image;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ImageFilterRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => 'sometimes|integer|max:100',
        ];
    }

    public function filters(): array
    {
        return [
            'user_id' => $this->user()->id,
            'per_page' => $this->input('per_page', 20),
        ];
    }
}
