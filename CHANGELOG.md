# Changelog

All notable changes to `algebrakit/sdk-php` are listed here. The project follows [semantic versioning](https://semver.org).

## 1.1.0

### Fixed
- `lockOrUnlockSession` called `/session/lock`, which doesn't exist, so it always failed with a 404. It now calls `/sessions/lock`.
- The required PHP version is now 8.1. The SDK uses enums, which don't exist in PHP 8.0.

### Added
- `AlgebrakitApiException`, thrown when a request fails. Its message includes the API's explanation, for example `Algebrakit API error 401: Could not validate credentials: the provided api-key is not valid`, and `getStatusCode()` returns the HTTP status. It extends `RuntimeException`, so existing `catch` blocks keep working.
- A request timeout, 30 seconds by default, set with the new `timeout` constructor argument.
- `SessionService::DEFAULT_BASE_URL`.
- Unit tests and CI.

### Changed
- Requires Guzzle 7.9 or newer.
- Repository links point to `github.com/algebrakit/sdk-php`.
- The README covers how to show a session in a web page, all API methods, regions and error handling.

### Removed
- The web demo `demo/play-exercise.php`, replaced by the [minimal PHP example](https://github.com/algebrakit/algebrakit-examples/tree/main/minimal/php) in algebrakit-examples. `demo/various-functions.php` is replaced by `demo/tour.php`, which reads the API key from `ALGEBRAKIT_API_KEY`.

## 1.0.0

- First release: `createSession`, `getSessionScore`, `lockOrUnlockSession`, `getSessionInfo`, `retrieveSessions`, `validateExercise` and `getExerciseInfo`.
