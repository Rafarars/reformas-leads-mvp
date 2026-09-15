<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Reformas\Leads\Classification\Domain\ClassifierNotConfiguredException;
use Reformas\Leads\Classification\Domain\ClassifierUnavailableException;
use Reformas\Leads\Classification\Domain\InvalidClassifierOutputException;
use Reformas\Leads\Classification\Domain\LeadToClassify;
use Reformas\Leads\Classification\Infrastructure\Gemini\GeminiLeadClassifier;

function gemini(?string $apiKey = 'test-key'): GeminiLeadClassifier
{
    return new GeminiLeadClassifier($apiKey, 'gemini-3.5-flash', 'https://gemini.test/v1beta', 5);
}

function leadToClassify(): LeadToClassify
{
    return new LeadToClassify('bathroom', 'Valencia', 8_500, 'Fuga bajo la ducha');
}

function validGeminiOutput(array $overrides = []): array
{
    return [
        'category' => 'bathroom',
        'urgency' => 'high',
        'confidence' => 0.92,
        'reason' => 'Fuga activa.',
        'summary' => 'Cambiar el plato de ducha por una fuga.',
        'missing_information' => ['Medidas del baño'],
        'contradictions' => [],
        'customer_message' => 'Hola {nombre}, gracias por escribirnos.',
        ...$overrides,
    ];
}

function geminiAnswer(mixed $output): array
{
    return [
        'candidates' => [['content' => ['parts' => [['text' => is_string($output) ? $output : json_encode($output)]]]]],
        'modelVersion' => 'gemini-3.5-flash-001',
    ];
}

it('turns a valid answer into a proposal', function () {
    Http::fake(['gemini.test/*' => Http::response(geminiAnswer(validGeminiOutput()))]);

    $proposal = gemini()->classify(leadToClassify());

    expect([$proposal->category->value, $proposal->urgency->value, $proposal->confidence->value, $proposal->reason->value, $proposal->model])
        ->toBe(['bathroom', 'high', 0.92, 'Fuga activa.', 'gemini-3.5-flash-001'])
        ->and([$proposal->summary->value, $proposal->missingInformation->items, $proposal->contradictions->items, $proposal->customerMessage->value])
        ->toBe(['Cambiar el plato de ducha por una fuga.', ['Medidas del baño'], [], 'Hola {nombre}, gracias por escribirnos.']);
});

it('sends the key in a header, forces the output schema and sends no personal data', function () {
    Http::fake(['gemini.test/*' => Http::response(geminiAnswer(validGeminiOutput(['confidence' => 1])))]);

    gemini()->classify(leadToClassify());

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://gemini.test/v1beta/models/gemini-3.5-flash:generateContent'
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && ! str_contains($request->url(), 'test-key')
            && $body['generationConfig']['responseMimeType'] === 'application/json'
            && $body['generationConfig']['responseSchema']['properties']['category']['enum'] === ['kitchen', 'bathroom', 'full_home', 'facade', 'installations', 'other']
            && $body['generationConfig']['responseSchema']['required'] === ['category', 'urgency', 'confidence', 'reason', 'summary', 'missing_information', 'contradictions', 'customer_message']
            && json_decode($body['contents'][0]['parts'][0]['text'], true) === [
                'tipo_marcado_por_la_persona' => 'bathroom',
                'zona' => 'Valencia',
                'presupuesto_euros' => 8500,
                'descripcion' => 'Fuga bajo la ducha',
            ];
    });
});

it('reports an outage the queue can retry', function (Closure $fake) {
    Http::fake($fake());

    gemini()->classify(leadToClassify());
})->with([
    'server error' => [fn () => ['gemini.test/*' => Http::response([], 503)]],
    'quota exceeded' => [fn () => ['gemini.test/*' => Http::response([], 429)]],
    'timeout on their side' => [fn () => ['gemini.test/*' => Http::response([], 408)]],
    'no connection' => [fn () => fn () => throw new ConnectionException('Connection refused')],
])->throws(ClassifierUnavailableException::class);

it('rejects an answer outside the contract instead of guessing', function (mixed $output) {
    Http::fake(['gemini.test/*' => Http::response(geminiAnswer($output))]);

    gemini()->classify(leadToClassify());
})->with([
    'free text' => ['Creo que es un baño'],
    'missing field' => [array_diff_key(validGeminiOutput(), ['reason' => true])],
    'unknown category' => [validGeminiOutput(['category' => 'roof'])],
    'confidence as text' => [validGeminiOutput(['confidence' => 'alta'])],
    'confidence above 1' => [validGeminiOutput(['confidence' => 9])],
    'findings as text' => [validGeminiOutput(['missing_information' => 'Medidas'])],
    'findings with numbers' => [validGeminiOutput(['contradictions' => [1, 2]])],
    'too many findings' => [validGeminiOutput(['missing_information' => ['a', 'b', 'c', 'd', 'e', 'f']])],
    'empty message' => [validGeminiOutput(['customer_message' => ''])],
])->throws(InvalidClassifierOutputException::class);

it('rejects an answer without candidates, as when the content is blocked', function () {
    Http::fake(['gemini.test/*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])]);

    gemini()->classify(leadToClassify());
})->throws(InvalidClassifierOutputException::class);

it('does not call anything without an API key', function (?string $key) {
    try {
        gemini($key)->classify(leadToClassify());
    } catch (ClassifierNotConfiguredException) {
        Http::assertNothingSent();

        return;
    }

    throw new RuntimeException('Se esperaba ClassifierNotConfiguredException');
})->with(['null' => [null], 'empty' => ['']]);

it('does not retry a request the model rejects, since it would fail the same way', function (int $status) {
    Http::fake(['gemini.test/*' => Http::response([], $status)]);

    gemini()->classify(leadToClassify());
})->with([
    'invalid argument' => [400],
    'invalid key' => [403],
    'retired model' => [404],
])->throws(ClassifierNotConfiguredException::class);
