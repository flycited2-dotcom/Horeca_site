<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * Заказ списком (ТЗ §11, §15.7): вставленные строки или файл XLSX до 1 МБ — одно из двух.
 * Размер списка в строках проверяет ParseBulkOrderList.
 */
class BulkOrderRequest extends FormRequest
{
    public const int MAX_FILE_KB = 1024;

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
            'list' => ['nullable', 'string', 'max:50000', 'required_without:file'],
            'file' => ['nullable', 'required_without:list', File::types(['xlsx'])->max(self::MAX_FILE_KB)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'list.required_without' => __('shop.bulk.errors.empty'),
            'file.required_without' => __('shop.bulk.errors.empty'),
            'file.mimes' => __('shop.bulk.errors.file_type'),
            'file.max' => __('shop.bulk.errors.file_size'),
            'list.max' => __('shop.bulk.errors.too_long'),
        ];
    }
}
