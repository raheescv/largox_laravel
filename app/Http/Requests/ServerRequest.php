<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'scheme' => ['nullable', 'in:http,https'],
            'agent_secret' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'min:32'],
            'ssh_user' => ['nullable', 'string', 'max:64'],
            'ssh_port' => ['nullable', 'integer', 'between:1,65535'],
        ];
    }
}
