<?php

namespace Streams\Sdk\Console\Make\Reports;

/**
 * One edit that could not be applied to a generated file.
 */
class FailedEdit
{
    public function __construct(
        public string $path,
        public string $file,
        public string $reason,
        public ?string $suggestedChange = null,
    ) {}

    /**
     * @param  array{path: string, file: string, reason: string}|array{path: string, file: string, reason: string, suggestedChange?: ?string}  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (string) $row['path'],
            (string) $row['file'],
            (string) $row['reason'],
            isset($row['suggestedChange']) ? (string) $row['suggestedChange'] : null,
        );
    }

    /**
     * @return array{path: string, file: string, reason: string, suggestedChange: ?string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'file' => $this->file,
            'reason' => $this->reason,
            'suggestedChange' => $this->suggestedChange,
        ];
    }
}
