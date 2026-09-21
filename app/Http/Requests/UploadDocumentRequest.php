<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:general,departments,doctors,services,admission,facilities,policies'],
            // 'mimetypes' inspects the file's actual content (via finfo), unlike
            // 'mimes' which only trusts the extension — a renamed .exe would
            // pass 'mimes:pdf' but fail this.
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
        ];
    }
}
