<?php

declare(strict_types=1);

namespace App\Http\Requests\Cases;

use Illuminate\Foundation\Http\FormRequest;

class SendBackCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('case');

        return $this->user()?->can('sendBack', $case) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
