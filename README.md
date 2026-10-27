# Algebrakit SDK for PHP

[![Packagist](https://img.shields.io/packagist/v/algebrakit/sdk-php)](https://packagist.org/packages/algebrakit/sdk-php)
[![PHP](https://img.shields.io/packagist/dependency-v/algebrakit/sdk-php/php)](https://packagist.org/packages/algebrakit/sdk-php)
[![CI](https://github.com/algebrakit/sdk-php/actions/workflows/ci.yml/badge.svg)](https://github.com/algebrakit/sdk-php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

The official server-side SDK for the [Algebrakit](https://algebrakit.com) API. Your server uses it to create exercise sessions, get scores and manage sessions. Students then work on the exercises in your web page, through the Algebrakit component.

For how Algebrakit works, the full API reference and the component's options, see the [Algebrakit documentation](https://docs.algebrakit.com/).

> **Want to see it working first?** [algebrakit-examples](https://github.com/algebrakit/algebrakit-examples/tree/main/minimal/php) has a small web app built on this SDK that runs on localhost in a few minutes.

## Before you start

- PHP 8.1 or newer, and [Composer](https://getcomposer.org).
- An Algebrakit API key. Don't have one yet? [Contact Algebrakit](https://algebrakit.com/contact-us).

Keep the API key on your server. Never send it to the browser.

## Install

```bash
composer require algebrakit/sdk-php
```

## Quick start

Create a service and a session for an exercise:

```php
use Algebrakit\SDK\Models\Requests\CreateSessionRequest;
use Algebrakit\SDK\Models\Shared\ExerciseById;
use Algebrakit\SDK\Services\SessionService;

$sessionService = new SessionService(getenv('ALGEBRAKIT_API_KEY'));

$result = $sessionService->createSession(new CreateSessionRequest(
    exercises: [new ExerciseById('fa42e943-8213-41a6-8a91-8c22a929ffe9')]
))[0];
$sessionId = $result->sessions[0]->sessionId;
```

A session holds one student's work on one exercise. Store its ID with the student, so they can continue later and you can get their score.

### Show the session to a student

In your web page, the Algebrakit component shows the session:

```html
<script>
  AlgebraKIT = { config: { proxy: { url: '/proxy/algebrakit' } } };
</script>
<script src="https://widgets.algebrakit.com"></script>

<akit-exercise session-id="<?= htmlspecialchars($sessionId) ?>"></akit-exercise>
```

The component sends its API calls to `/proxy/algebrakit/*` on your server. Your server adds the API key and forwards them to the Algebrakit API. The [minimal PHP example](https://github.com/algebrakit/algebrakit-examples/tree/main/minimal/php) implements all of this; for production, see [Proxy setup](https://docs.algebrakit.com/pages/420-proxy-setup/).

## API

`SessionService` has one method per API call. Each takes a request object and returns a response object.

| Method | Endpoint | What it does |
|---|---|---|
| `createSession` | `POST /session/create` | Create sessions for one or more exercises: `ExerciseById`, `ExerciseBySpec` (an exercise defined in code) or `ExerciseBySession` (a copy of an existing session's exercise) |
| `getSessionScore` | `POST /session/score` | Get a session's score, and optionally lock it |
| `getSessionInfo` | `POST /session/info` | Get a session's details: its elements, the student's work and the scoring |
| `lockOrUnlockSession` | `POST /sessions/lock` | `LOCK`, `UNLOCK` or `FINALIZE` sessions |
| `retrieveSessions` | `POST /session/retrieve` | Get existing sessions by ID |
| `validateExercise` | `POST /exercise/validate` | Check an exercise ID or specification before you use it |
| `getExerciseInfo` | `POST /exercise/published-info` | Get the published versions and structure of an exercise |

### Get the score

```php
use Algebrakit\SDK\Models\Requests\SessionScoreRequest;

$scoring = $sessionService->getSessionScore(new SessionScoreRequest($sessionId, lockSession: true))->scoring;
echo "{$scoring->marksEarned} of {$scoring->marksTotal} marks\n";
```

### Lock a session

```php
use Algebrakit\SDK\Models\Requests\SessionLockRequest;

$sessionService->lockOrUnlockSession(new SessionLockRequest('LOCK', [$sessionId]));
```

## Configuration

Use named arguments for the optional settings:

```php
$sessionService = new SessionService(
    getenv('ALGEBRAKIT_API_KEY'),
    baseUrl: 'https://prod.api.sg-1.algebrakit.com', // optional, see the regions below
    timeout: 10, // optional, in seconds; the default is 30
);
```

| Region | Base URL |
|---|---|
| EU (default) | `https://api.algebrakit.com` |
| Singapore | `https://prod.api.sg-1.algebrakit.com` |

You can also pass your own Guzzle client as the second argument (`httpClient:`), for example to add logging. It must then send the `X-API-KEY` header itself.

## Error handling

The methods throw an `AlgebrakitApiException` when a request fails. Its message includes the API's explanation, and `getStatusCode()` returns the HTTP status (or `null` for a network error or timeout):

```php
use Algebrakit\SDK\Exceptions\AlgebrakitApiException;
use Algebrakit\SDK\Models\Requests\SessionInfoRequest;

try {
    $sessionService->getSessionInfo(new SessionInfoRequest($sessionId));
} catch (AlgebrakitApiException $e) {
    error_log($e->getMessage());
    // Algebrakit API error 401: Could not validate credentials: the provided api-key is not valid
}
```

`AlgebrakitApiException` extends `RuntimeException`, so existing code that catches `RuntimeException` keeps working.

## More

- [algebrakit-examples](https://github.com/algebrakit/algebrakit-examples): runnable examples in PHP, TypeScript and .NET
- [Documentation](https://docs.algebrakit.com/): [SDKs](https://docs.algebrakit.com/pages/320-SDK/), [API reference](https://docs.algebrakit.com/pages/310-API/), [web components](https://docs.algebrakit.com/pages/410-webcomponents/) and [learning events](https://docs.algebrakit.com/pages/450-learning-events/)
- [OpenAPI specification](https://api.algebrakit.com/openapi/yaml)
- SDKs for other languages: [TypeScript](https://github.com/algebrakit/sdk-typescript) and [.NET](https://github.com/algebrakit/sdk-dotnet)
- A tour of every API call: [`demo/tour.php`](demo/tour.php). Run it with `ALGEBRAKIT_API_KEY=<your API key> composer demo`.

## Development

```bash
composer install
composer test   # unit tests, no API key needed
```

To release: update [CHANGELOG.md](CHANGELOG.md), then push a tag such as `v1.1.0`. Packagist picks up the tag automatically, and GitHub Actions creates a GitHub release from the changelog.

## License

[MIT](LICENSE)
