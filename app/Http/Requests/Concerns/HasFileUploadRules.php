<?php

namespace App\Http\Requests\Concerns;

/**
 * Shared image/file upload rule so the same size/mime constraints (previously copy-pasted
 * as bare 'file|max:5120' strings across many controllers) live in one place. Keep this in
 * sync with resources/js/lib/validation/fileConstraints.ts, which mirrors the same limits
 * client-side.
 */
trait HasFileUploadRules
{
    protected function imageRules(bool $required = false, int $maxSizeKb = 5120): array
    {
        return [
            $required ? 'required' : 'nullable',
            'image',
            'max:' . $maxSizeKb,
        ];
    }

    protected function documentRules(bool $required = false, int $maxSizeKb = 5120): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:jpeg,png,jpg,pdf',
            'max:' . $maxSizeKb,
        ];
    }
}
