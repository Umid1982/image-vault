<?php declare(strict_types=1);

namespace App\Http\Requests\Image;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ImageUploadRequest extends FormRequest
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
            'image' => [
                'required',
                'file',
                'mimes:jpeg,png',
                'max:5120', // 5 MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Файл изображения обязателен',
            'image.mimes' => 'Допустимы только JPEG и PNG',
            'image.max' => 'Максимальный размер файла — 5 МБ',
        ];
    }

    public function toArray(): array
    {
        /** @var UploadedFile $file */
        $file = $this->file('image');

        return [
            'user_id'       => $this->user()->id,
            'file'          => $file,
            'original_name' => $file->getClientOriginalName(),
            'mime'          => $file->getMimeType(),
            'size'          => $file->getSize(),
        ];
    }
}
