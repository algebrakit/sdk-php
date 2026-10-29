<?php
/**
 * Algebrakit SDK tour: calls each part of the API once and prints a short summary.
 *
 *   composer install
 *   ALGEBRAKIT_API_KEY=<your API key> composer demo
 *
 * To show an exercise to a student in the browser, see the runnable web examples:
 * https://github.com/algebrakit/algebrakit-examples
 */

require __DIR__ . '/../vendor/autoload.php';

use Algebrakit\SDK\Exceptions\AlgebrakitApiException;
use Algebrakit\SDK\Models\AkExercise\AK_ContentBlock;
use Algebrakit\SDK\Models\AkExercise\AK_Element;
use Algebrakit\SDK\Models\AkExercise\AK_Exercise;
use Algebrakit\SDK\Models\AkExercise\AK_InteractionBlock;
use Algebrakit\SDK\Models\AkExercise\AK_InteractionMultistep;
use Algebrakit\SDK\Models\AkExercise\AK_MultistepPart;
use Algebrakit\SDK\Models\AkExercise\AK_QuestionMode;
use Algebrakit\SDK\Models\AkExercise\AK_Symbol;
use Algebrakit\SDK\Models\AkExercise\AK_SymbolType;
use Algebrakit\SDK\Models\AkExercise\AK_TaskSimplify;
use Algebrakit\SDK\Models\Requests\CreateSessionRequest;
use Algebrakit\SDK\Models\Requests\ExerciseValidateRequest;
use Algebrakit\SDK\Models\Requests\SessionInfoRequest;
use Algebrakit\SDK\Models\Requests\SessionLockRequest;
use Algebrakit\SDK\Models\Requests\SessionScoreRequest;
use Algebrakit\SDK\Models\Shared\ExerciseById;
use Algebrakit\SDK\Models\Shared\ExerciseBySpec;
use Algebrakit\SDK\Services\SessionService;

const EXERCISE_ID = 'fa42e943-8213-41a6-8a91-8c22a929ffe9'; // "Solve the equation 6(p - 1) = 4p + 10"

$apiKey = getenv('ALGEBRAKIT_API_KEY');
if (!$apiKey) {
    fwrite(STDERR, "Set ALGEBRAKIT_API_KEY first, for example: ALGEBRAKIT_API_KEY=<your API key> composer demo\n");
    exit(1);
}
$apiUrl = getenv('ALGEBRAKIT_API_URL') ?: SessionService::DEFAULT_BASE_URL;
$service = new SessionService($apiKey, baseUrl: $apiUrl);

// An exercise defined in code instead of in the Algebrakit CMS (AK_Exercise format)
$exerciseSpec = new AK_Exercise(
    studentProfile: 'uk_KS5',
    questionMode: AK_QuestionMode::ALL_AT_ONCE,
    symbols: [new AK_Symbol(name: 'x', type: AK_SymbolType::VARIABLE)],
    elements: [
        new AK_Element(blocks: [
            new AK_ContentBlock(content: 'Simplify the following expression.'),
            new AK_InteractionBlock(interaction: new AK_InteractionMultistep(
                solutionPart: new AK_MultistepPart(task: new AK_TaskSimplify(expression: '2x + 3x'))
            )),
        ]),
    ]
);

function step(string $title): void
{
    echo "\n$title\n";
}

try {
    echo "Algebrakit SDK tour\nAPI: $apiUrl\n";

    step('1. Validate an exercise (validateExercise)');
    $validation = $service->validateExercise(new ExerciseValidateRequest(exerciseId: EXERCISE_ID));
    echo '   valid: ' . var_export($validation->valid, true) . ", marks: {$validation->marks}\n";

    step('2. Create a session (createSession)');
    $result = $service->createSession(new CreateSessionRequest(exercises: [new ExerciseById(EXERCISE_ID)]))[0];
    if (!$result->success) {
        throw new RuntimeException($result->msg ?? 'Session creation failed');
    }
    $sessionId = $result->sessions[0]->sessionId;
    echo "   session ID: $sessionId\n";
    echo "   show it in a page with: <akit-exercise session-id=\"$sessionId\"></akit-exercise>\n";

    step('3. Get session info (getSessionInfo)');
    $info = $service->getSessionInfo(new SessionInfoRequest($sessionId));
    echo '   elements: ' . count($info->elements) . "\n";

    step('4. Get the score (getSessionScore)');
    $scoring = $service->getSessionScore(new SessionScoreRequest($sessionId))->scoring;
    echo "   marks: {$scoring->marksEarned} of {$scoring->marksTotal}, finished: " . var_export($scoring->finished, true) . "\n";

    step('5. Lock and unlock the session (lockOrUnlockSession)');
    $service->lockOrUnlockSession(new SessionLockRequest('LOCK', [$sessionId]));
    echo "   locked: the student can no longer change their answers\n";
    $service->lockOrUnlockSession(new SessionLockRequest('UNLOCK', [$sessionId]));
    echo "   unlocked\n";

    step('6. Create a session from an exercise defined in code (ExerciseBySpec)');
    $specValidation = $service->validateExercise(new ExerciseValidateRequest(exerciseSpec: $exerciseSpec));
    echo '   valid: ' . var_export($specValidation->valid, true) . ", marks: {$specValidation->marks}\n";
    $specResult = $service->createSession(new CreateSessionRequest(exercises: [new ExerciseBySpec($exerciseSpec)]))[0];
    echo "   session ID: {$specResult->sessions[0]->sessionId}\n";

    echo "\nDone.\n";
} catch (AlgebrakitApiException $e) {
    fwrite(STDERR, "\nDemo failed: {$e->getMessage()}\n");
    exit(1);
}
