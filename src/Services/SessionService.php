<?php

namespace Algebrakit\SDK\Services;

use Algebrakit\SDK\Exceptions\AlgebrakitApiException;
use Algebrakit\SDK\Models\Requests\CreateSessionRequest;
use Algebrakit\SDK\Models\Requests\ExerciseInfoRequest;
use Algebrakit\SDK\Models\Requests\ExerciseValidateRequest;
use Algebrakit\SDK\Models\Requests\SessionInfoRequest;
use Algebrakit\SDK\Models\Requests\SessionLockRequest;
use Algebrakit\SDK\Models\Requests\SessionRetrieveRequest;
use Algebrakit\SDK\Models\Requests\SessionScoreRequest;
use Algebrakit\SDK\Models\Responses\CreateSessionResponse;
use Algebrakit\SDK\Models\Responses\ExerciseInfoResponse;
use Algebrakit\SDK\Models\Responses\ExerciseValidateResponse;
use Algebrakit\SDK\Models\Responses\SessionInfoResponse;
use Algebrakit\SDK\Models\Responses\SessionLockResponse;
use Algebrakit\SDK\Models\Responses\SessionRetrieveResponse;
use Algebrakit\SDK\Models\Responses\SessionScoreResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use JsonSerializable;

/**
 * Client for the Algebrakit web service API.
 *
 * Example:
 *     $sessionService = new SessionService(getenv('ALGEBRAKIT_API_KEY'));
 */
class SessionService
{
    /** The Algebrakit API in the default region (Ireland). */
    public const DEFAULT_BASE_URL = 'https://api.algebrakit.com';

    private Client $httpClient;
    private string $apiKey;

    /**
     * @param string $apiKey Your Algebrakit API key. Keep it on your server; never send it to the browser.
     * @param Client|null $httpClient Your own Guzzle client, if you need one. It must send the API key itself.
     * @param string $baseUrl Base URL of the Algebrakit API, for example to use another region.
     * @param float $timeout Request timeout in seconds. Ignored if you pass your own $httpClient.
     */
    public function __construct(
        string $apiKey,
        ?Client $httpClient = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = 30.0
    ) {
        $this->apiKey = $apiKey;
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $baseUrl,
            'timeout' => $timeout,
            'headers' => [
                'X-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Creates new exercise sessions.
     *
     * @param CreateSessionRequest $request The exercises to create sessions for
     * @return CreateSessionResponse[] For each exercise, whether it succeeded and the created sessions
     * @throws AlgebrakitApiException If the request fails
     */
    public function createSession(CreateSessionRequest $request): array
    {
        return array_map(
            fn (array $item) => CreateSessionResponse::fromArray($item),
            $this->post('/session/create', $request)
        );
    }

    /**
     * Retrieves scores for a specific session.
     *
     * @param SessionScoreRequest $request The session ID, and whether to lock the session
     * @return SessionScoreResponse The response containing session scores
     * @throws AlgebrakitApiException If the request fails
     */
    public function getSessionScore(SessionScoreRequest $request): SessionScoreResponse
    {
        return SessionScoreResponse::fromArray($this->post('/session/score', $request));
    }

    /**
     * Locks or unlocks exercise sessions.
     *
     * @param SessionLockRequest $request The action ('LOCK', 'UNLOCK' or 'FINALIZE') and the session IDs
     * @return SessionLockResponse The response for the lock or unlock action
     * @throws AlgebrakitApiException If the request fails
     */
    public function lockOrUnlockSession(SessionLockRequest $request): SessionLockResponse
    {
        return SessionLockResponse::fromArray($this->post('/sessions/lock', $request));
    }

    /**
     * Retrieves information for a specific session.
     *
     * @param SessionInfoRequest $request The request containing session ID
     * @return SessionInfoResponse The response containing session information
     * @throws AlgebrakitApiException If the request fails
     */
    public function getSessionInfo(SessionInfoRequest $request): SessionInfoResponse
    {
        return SessionInfoResponse::fromArray($this->post('/session/info', $request));
    }

    /**
     * Retrieves existing sessions by their IDs.
     *
     * @param SessionRetrieveRequest $request The request containing session IDs
     * @return SessionRetrieveResponse The response containing session data
     * @throws AlgebrakitApiException If the request fails
     */
    public function retrieveSessions(SessionRetrieveRequest $request): SessionRetrieveResponse
    {
        return SessionRetrieveResponse::fromArray($this->post('/session/retrieve', $request));
    }

    /**
     * Validates an exercise.
     *
     * @param ExerciseValidateRequest $request The exercise ID or specification to validate
     * @return ExerciseValidateResponse The response containing validation results
     * @throws AlgebrakitApiException If the request fails
     */
    public function validateExercise(ExerciseValidateRequest $request): ExerciseValidateResponse
    {
        return ExerciseValidateResponse::fromArray($this->post('/exercise/validate', $request));
    }

    /**
     * Retrieves published information about an exercise.
     *
     * @param ExerciseInfoRequest $request The request containing exercise ID
     * @return ExerciseInfoResponse The response containing exercise information
     * @throws AlgebrakitApiException If the request fails
     */
    public function getExerciseInfo(ExerciseInfoRequest $request): ExerciseInfoResponse
    {
        return ExerciseInfoResponse::fromArray($this->post('/exercise/published-info', $request));
    }

    /**
     * Sends a request to the API and returns the decoded JSON response.
     *
     * @throws AlgebrakitApiException If the request fails or the response is not valid JSON
     */
    private function post(string $path, JsonSerializable $request): array
    {
        try {
            $response = $this->httpClient->post($path, ['json' => $request->jsonSerialize()]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $status = $e->getResponse()->getStatusCode();
                $message = self::errorMessage((string) $e->getResponse()->getBody());
                throw new AlgebrakitApiException("Algebrakit API error $status: $message", $status, $e);
            }
            throw new AlgebrakitApiException('Algebrakit API request failed: ' . $e->getMessage(), null, $e);
        } catch (GuzzleException $e) {
            throw new AlgebrakitApiException('Algebrakit API request failed: ' . $e->getMessage(), null, $e);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        if ($status >= 400) {
            // Only reached with a custom client that has 'http_errors' => false
            throw new AlgebrakitApiException("Algebrakit API error $status: " . self::errorMessage($body), $status);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new AlgebrakitApiException('Could not decode the Algebrakit API response: ' . json_last_error_msg(), $status);
        }
        return $data;
    }

    /**
     * Extracts the error message from an API error response body,
     * for example {"success":false,"error":"Could not validate credentials: ..."}.
     */
    private static function errorMessage(string $body): string
    {
        $data = json_decode($body, true);
        if (is_array($data)) {
            foreach (['error', 'msg', 'message'] as $key) {
                if (isset($data[$key]) && is_string($data[$key])) {
                    return $data[$key];
                }
            }
        }
        return $body !== '' ? $body : 'no response body';
    }
}
