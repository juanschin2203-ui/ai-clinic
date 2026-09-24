<?php

declare(strict_types=1);

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\File::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:51200',             // 50 MB — dictation WAVs fit; charts PDFs fit
            ],
            'kind' => [
                'required',
                'string',
                Rule::in(['chart', 'dictation', 'batch', 'invoicePdf', 'emrExport']),
            ],
            'ownerType' => [
                'required',
                'string',
                Rule::in(['clinic', 'medical_case', 'patient', 'appointment', 'invoice']),
            ],
            'ownerId' => ['required', 'uuid'],
        ];
    }
}
