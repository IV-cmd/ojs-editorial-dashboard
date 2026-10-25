<?php

namespace App\Services\Ojs;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class HttpOjsClient implements OjsClientInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiToken,
        private readonly int $timeout,
    ) {}

    public function getSubmissions(): array
    {
        $submissions = [];
        $offset = 0;

        do {
            $response = $this->get('submissions', [
                'count' => 100,
                'offset' => $offset,
            ]);
            $payload = $response->json();

            if (! is_array($payload)
                || ! is_int($payload['itemsMax'] ?? null)
                || ! is_array($payload['items'] ?? null)) {
                throw $this->apiException($response->status(), $response->body());
            }

            $items = $payload['items'];

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    throw $this->apiException($response->status(), $response->body());
                }

                $submissions[] = $item;
            }

            $offset += count($items);
        } while (count($submissions) < $payload['itemsMax']);

        return $submissions;
    }

    public function getSubmission(int $id): array
    {
        $response = $this->get("submissions/{$id}");
        $submission = $response->json();

        if (! is_array($submission)) {
            throw $this->apiException($response->status(), $response->body());
        }

        return $submission;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->apiToken)
            ->acceptJson()
            ->timeout($this->timeout);
    }

    private function get(string $uri, array $query = []): Response
    {
        try {
            return $this->request()->get($uri, $query)->throw();
        } catch (ConnectionException) {
            throw $this->apiException(0, 'Connection failed.');
        } catch (RequestException $exception) {
            throw $this->apiException($exception->response->status(), $exception->response->body());
        }
    }

    private function apiException(int $statusCode, string $responseBody): OjsApiException
    {
        if ($this->apiToken !== '') {
            $responseBody = str_replace($this->apiToken, '[redacted]', $responseBody);
        }

        return new OjsApiException($statusCode, $responseBody);
    }
}
