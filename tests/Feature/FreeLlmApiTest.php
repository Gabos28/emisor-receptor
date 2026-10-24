<?php

namespace Tests\Feature;

use App\Services\FreeLlmApi;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

class FreeLlmApiTest extends TestCase
{
    public function test_it_sends_chat_completions_to_freellmapi_and_returns_the_answer(): void
    {
        config([
            'services.freellmapi.base_url' => 'http://localhost:3001/v1/',
            'services.freellmapi.api_key' => 'test-key',
            'services.freellmapi.model' => 'auto',
        ]);

        Http::fake([
            'http://localhost:3001/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Hola desde FreeLLMAPI']],
                ],
            ]),
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Hola'],
        ];

        $answer = app(FreeLlmApi::class)->chat($messages);

        $this->assertSame('Hola desde FreeLLMAPI', $answer);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://localhost:3001/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'auto'
            && $request['messages'] === $messages);
    }

    public function test_it_requires_an_api_key_before_making_a_request(): void
    {
        config(['services.freellmapi.api_key' => null]);

        Http::fake();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Set FREELLMAPI_API_KEY');

        try {
            app(FreeLlmApi::class)->chat([
                ['role' => 'user', 'content' => 'Hola'],
            ]);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_surfaces_http_errors_from_freellmapi(): void
    {
        config(['services.freellmapi.api_key' => 'test-key']);
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->expectException(RequestException::class);

        app(FreeLlmApi::class)->chat([
            ['role' => 'user', 'content' => 'Hola'],
        ]);
    }
}
