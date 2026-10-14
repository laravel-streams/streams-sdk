<?php

namespace Streams\Sdk\Console\Commands;

use Illuminate\Console\Command;

class StreamsTap extends Command
{
    /**
     * @inheritDoc
     *
     * @var string
     */
    protected $signature = 'streams:tap
        {tap : The tap URL.}
        {input? : Query string formatted input.}';

    protected $description = 'Call a tap URL with query-string input.';

    public function handle(): int
    {
        $target = $this->target((string) $this->argument('tap'), $this->argument('input'));

        if ($target === null) {
            $this->error('The tap must be an http or https URL.');

            return self::FAILURE;
        }

        $url = $target['url'];

        if ($target['query'] !== []) {
            $url .= '?'.http_build_query($target['query']);
        }

        $result = $this->fetch($url);

        if ($result['status'] === null || $result['status'] >= 400) {
            $this->error($result['status'] === null
                ? 'The tap URL could not be reached.'
                : 'Tap failed with status '.$result['status'].'.');

            return self::FAILURE;
        }

        if ($result['body'] !== '') {
            $this->line($result['body']);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{url: string, query: array<string, mixed>}|null
     */
    public function target(string $tap, mixed $input): ?array
    {
        $parts = parse_url($tap);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || ($parts['host'] ?? '') === '') {
            return null;
        }

        $query = [];

        if (isset($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        if (is_string($input) && $input !== '') {
            $extra = [];
            parse_str(ltrim($input, '?'), $extra);
            $query = array_merge($query, $extra);
        }

        $url = $scheme.'://'.$parts['host'];

        if (isset($parts['port'])) {
            $url .= ':'.$parts['port'];
        }

        $url .= $parts['path'] ?? '';

        return [
            'url' => $url,
            'query' => $query,
        ];
    }

    /**
     * @return array{status: int|null, body: string}
     */
    protected function fetch(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
        ]);

        $headers = null;
        $body = @file_get_contents($url, false, $context);

        if (isset($http_response_header) && is_array($http_response_header)) {
            $headers = $http_response_header;
        }

        return [
            'status' => $this->statusCode($headers),
            'body' => is_string($body) ? $body : '',
        ];
    }

    /**
     * @param  array<int, string>|null  $headers
     */
    protected function statusCode(?array $headers): ?int
    {
        if ($headers === null) {
            return null;
        }

        $status = null;

        foreach ($headers as $header) {
            if (preg_match('#\AHTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        return $status;
    }
}
