<?php

namespace App\Http\Requests;

use App\Helpers\GetValues;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
        $method = request()->method;
        $isRequired = $method == 'POST' ? 'required' : 'sometimes';
        $isForbidden = $method !== 'POST' ? 'prohibited' : 'required';
        return [
            'name'         => sprintf('%s|min:4|string', $isRequired),
            'email'        => sprintf('%s|email|string|unique:users', $isRequired),
            'type_user_id' => [$isForbidden, Rule::in(GetValues::listOfKeysTypeUserEnum())],
        ];
    }
    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'           => 'O campo nome é obrigatório.',
            'name.string'             => 'O campo nome deve ser uma string.',
            'name.min'                => 'O campo nome deve ter no mínimo 4 caracteres.',
            'email.email'             => 'Email invalido.',
            'email.required'          => 'O campo e-mail e obrigatório.',
            'email.string'            => 'O campo email deve ser uma string.',
            'type_user_id.in'         => 'O valor passado em type_user_id nao existe',
            'email.unique'            => 'Esse e-mail ja esta cadastrado',
            'type_user_id.prohibited' => 'Esse campo não pode ser atualizado',
        ];
    }
    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['errors' => $validator->errors()], 422));
    }
}
