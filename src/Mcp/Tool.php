<?php

namespace Streams\Sdk\Mcp;

use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool as BaseTool;
use Streams\Core\Support\Facades\Streams;
use Streams\Core\Stream\Stream;
use Streams\Sdk\Support\WriteFailure;
use Throwable;

/**
 * Shared plumbing for the SDK's MCP tools. Every tool answers with the same
 * shape: JSON a caller can act on, and when something is refused, the reason
 * plus what to run instead. Tools stay thin on purpose — the logic lives in
 * Streams\Sdk\Support so the TUI and the CLI report the same facts.
 */
abstract class Tool extends BaseTool
{
    protected function stream(string $id): ?Stream
    {
        try {
            return Streams::exists($id) ? Streams::make($id) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The answer an agent needs when it guesses a stream name: what exists, and
     * the closest real handle.
     */
    protected function unknownStream(string $id): Response
    {
        $known = Streams::collection()->keys()->map(fn ($key) => (string) $key)->sort()->values();

        $close = $known
            ->filter(fn (string $handle) => Str::contains($handle, Str::lower($id)) || Str::contains(Str::lower($id), $handle))
            ->take(5)
            ->all();

        return Response::error(
            'Stream ['.$id.'] is not registered.'
            .($close !== [] ? ' Did you mean: '.implode(', ', $close).'?' : '')
            .' Registered: '.($known->implode(', ') ?: 'none — run make-stream, or php artisan streams:onboard').'.'
        );
    }

    /**
     * Refusals carry their reason and the fix, never a stack trace.
     */
    protected function failure(Throwable $e): Response
    {
        if ($e instanceof WriteFailure) {
            return Response::error($e->getMessage().($this->bullets($e->errors())));
        }

        return Response::error('Failed: '.$e->getMessage());
    }

    /**
     * @param  array<int, string>  $lines
     */
    protected function bullets(array $lines): string
    {
        $lines = array_values(array_filter(array_map('strval', $lines)));

        if ($lines === []) {
            return '';
        }

        return "\n".implode("\n", array_map(fn (string $line) => '- '.$line, $lines));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function data(array $payload, ?string $message = null): Response|ResponseFactory
    {
        if ($message !== null && $message !== '') {
            $payload = ['message' => $message] + $payload;
        }

        return Response::structured($payload);
    }

    /**
     * Markdown for a human reader (and it still parses for an agent).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function text(array $payload): Response|ResponseFactory
    {
        return Response::text((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
