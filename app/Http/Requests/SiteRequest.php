<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $serverId = $this->input('server_id');
        $siteId   = $this->route('site')?->id;

        return [
            'server_id'      => ['required', 'exists:servers,id'],
            'domain'         => [
                'required', 'string', 'max:253',
                Rule::unique('sites', 'domain')->where('server_id', $serverId)->ignore($siteId),
            ],
            'path'           => ['required', 'string', 'max:255', 'regex:#^/(home|var|srv|opt)/[A-Za-z0-9._/\-]+$#'],
            'repository'     => ['nullable', 'string', 'regex:#^(git@|https://)[A-Za-z0-9._:/\-]+\.git$#'],
            'branch'         => ['nullable', 'string', 'max:120', 'regex:#^[A-Za-z0-9._/\-]+$#'],
            'composer'       => ['boolean'],
            'npm_build'      => ['boolean'],
            'artisan_cmds'   => ['nullable', 'array'],
            'artisan_cmds.*' => ['string', 'regex:#^[a-z0-9:_\-]+$#'],
            'php_service'    => ['nullable', 'string', 'max:64'],
            'queue_program'  => ['nullable', 'string', 'max:64'],
        ];
    }
}
