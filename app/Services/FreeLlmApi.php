<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use LogicException;
use UnexpectedValueException;

class FreeLlmApi
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, ?string $model = null): string
    {
        $apiKey = config('services.freellmapi.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new LogicException('Set FREELLMAPI_API_KEY before calling FreeLLMAPI.');
        }

        $baseUrl = rtrim((string) config('services.freellmapi.base_url'), '/');
        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('services.freellmapi.timeout', 60))
            ->post($baseUrl.'/chat/completions', [
                'model' => $model ?? config('services.freellmapi.model', 'auto'),
                'messages' => $messages,
            ])
            ->throw();

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw new UnexpectedValueException('FreeLLMAPI returned no text in the first chat completion choice.');
        }

        return $content;
    }
}
