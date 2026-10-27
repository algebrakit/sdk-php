<?php

namespace Algebrakit\SDK\Tests;

use Algebrakit\SDK\Exceptions\AlgebrakitApiException;
use Algebrakit\SDK\Models\Requests\CreateSessionRequest;
use Algebrakit\SDK\Models\Requests\ExerciseInfoRequest;
use Algebrakit\SDK\Models\Requests\ExerciseValidateRequest;
use Algebrakit\SDK\Models\Requests\SessionInfoRequest;
use Algebrakit\SDK\Models\Requests\SessionLockRequest;
use Algebrakit\SDK\Models\Requests\SessionRetrieveRequest;
use Algebrakit\SDK\Models\Requests\SessionScoreRequest;
use Algebrakit\SDK\Models\Shared\ExerciseById;
use Algebrakit\SDK\Services\SessionService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

class SessionServiceTest extends TestCase
{
    /** Requests sent by the service created with service() */
    private array $history = [];

    /** A SessionService whose requests are answered by $responses, in order, and recorded in $this->history. */
    private function service(Response|ConnectException ...$responses): SessionService
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        return new SessionService('test-key', new Client(['handler' => $stack, 'base_uri' => 'https://api.test']));
    }

    private static function json(mixed $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    private static function httpClientOf(SessionService $service): Client
    {
        return (new ReflectionProperty(SessionService::class, 'httpClient'))->getValue($service);
    }

    public function testUsesTheProductionApiAndSendsTheKeyByDefault(): void
    {
        $client = self::httpClientOf(new SessionService('my-key'));

        $this->assertSame('https://api.algebrakit.com', (string) $client->getConfig('base_uri'));
        $this->assertSame('my-key', $client->getConfig('headers')['X-API-KEY']);
        $this->assertSame(30.0, $client->getConfig('timeout'));
    }

    public function testBaseUrlAndTimeoutCanBeSetWithNamedArguments(): void
    {
        $client = self::httpClientOf(new SessionService('key', baseUrl: 'https://prod.api.sg-1.algebrakit.com', timeout: 5));

        $this->assertSame('https://prod.api.sg-1.algebrakit.com', (string) $client->getConfig('base_uri'));
        $this->assertSame(5.0, $client->getConfig('timeout'));
    }

    public function testCallsTheRightEndpointForEachMethod(): void
    {
        $service = $this->service(
            self::json([['success' => true, 'sessions' => []]]),
            self::json([]),
            self::json([]),
            self::json([]),
            self::json([]),
            self::json(['valid' => true]),
            self::json([]),
        );

        $service->createSession(new CreateSessionRequest(exercises: [new ExerciseById('e1')]));
        $service->getSessionScore(new SessionScoreRequest('s1'));
        $service->lockOrUnlockSession(new SessionLockRequest('LOCK', ['s1']));
        $service->getSessionInfo(new SessionInfoRequest('s1'));
        $service->retrieveSessions(new SessionRetrieveRequest(['s1']));
        $service->validateExercise(new ExerciseValidateRequest(exerciseId: 'e1'));
        $service->getExerciseInfo(new ExerciseInfoRequest('e1'));

        $this->assertSame(
            [
                'POST /session/create',
                'POST /session/score',
                'POST /sessions/lock', // 1.0.0 used /session/lock, which doesn't exist
                'POST /session/info',
                'POST /session/retrieve',
                'POST /exercise/validate',
                'POST /exercise/published-info',
            ],
            array_map(fn (array $t) => $t['request']->getMethod() . ' ' . $t['request']->getUri()->getPath(), $this->history)
        );
    }

    public function testCreateSessionSendsApiVersion2(): void
    {
        $this->service(self::json([['success' => true, 'sessions' => []]]))
            ->createSession(new CreateSessionRequest(exercises: [new ExerciseById('e1')]));

        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame(2, $body['apiVersion']);
        $this->assertSame([['exerciseId' => 'e1', 'version' => 'latest']], $body['exercises']);
    }

    public function testErrorsIncludeTheApiMessageAndStatus(): void
    {
        $service = $this->service(self::json(
            ['success' => false, 'error' => 'Could not validate credentials: the provided api-key is not valid'],
            401
        ));

        try {
            $service->getSessionInfo(new SessionInfoRequest('s1'));
            $this->fail('Expected an AlgebrakitApiException');
        } catch (AlgebrakitApiException $e) {
            $this->assertSame(
                'Algebrakit API error 401: Could not validate credentials: the provided api-key is not valid',
                $e->getMessage()
            );
            $this->assertSame(401, $e->getStatusCode());
            $this->assertInstanceOf(RuntimeException::class, $e); // existing catch (RuntimeException) blocks keep working
        }
    }

    public function testNetworkErrorsHaveNoStatusCode(): void
    {
        $service = $this->service(new ConnectException('Connection refused', new Request('POST', '/session/info')));

        try {
            $service->getSessionInfo(new SessionInfoRequest('s1'));
            $this->fail('Expected an AlgebrakitApiException');
        } catch (AlgebrakitApiException $e) {
            $this->assertSame('Algebrakit API request failed: Connection refused', $e->getMessage());
            $this->assertNull($e->getStatusCode());
        }
    }

    public function testInvalidJsonIsReported(): void
    {
        $this->expectException(AlgebrakitApiException::class);
        $this->expectExceptionMessage('Could not decode the Algebrakit API response');

        $this->service(new Response(200, [], 'not json'))->getSessionInfo(new SessionInfoRequest('s1'));
    }
}
