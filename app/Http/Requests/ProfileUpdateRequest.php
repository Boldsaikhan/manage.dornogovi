<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // Утасны дугаараар нэвтрэхэд хэрэглэгдэнэ. Монголын дугаар 8 оронтой.
            'phone' => [
                'nullable',
                'string',
                'digits:8',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    /**
     * Дугаарыг шалгахаас өмнө зөвхөн цифр болгож цэвэрлэнэ — ингэснээр
     * "9911 1234", "+976 9911-1234" гэх мэт бичлэг ч хүлээн зөвшөөрөгдөнө.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => User::normalizePhone($this->input('phone'))]);
        }
    }

    public function attributes(): array
    {
        return [
            'phone' => 'утасны дугаар',
        ];
    }
}
