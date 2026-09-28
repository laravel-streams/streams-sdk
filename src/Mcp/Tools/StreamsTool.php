<?php

namespace Streams\Sdk\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Validation\ValidationException;

/**
 * Base class for Streams MCP tools.
 *
 * Expected failures (unknown streams, missing entries, bad input)
 * are returned to the agent as tool errors with their message,
 * even when app.debug is off, so the agent can correct itself.
 */
abstract class StreamsTool extends Tool
{
    abstract protected function respond(Request $request): Response;

    public function handle(Request $request): Response
    {
        try {
            return $this->respond($request);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return Response::error(class_basename($e) . ': ' . $e->getMessage());
        }
    }

    protected function json(mixed $data): Response
    {
        return Response::text(json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        ));
    }
}
