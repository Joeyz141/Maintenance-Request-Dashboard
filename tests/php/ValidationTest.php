<?php
// tests/php/ValidationTest.php
// Unit tests for src/validation.php (the rules for new requests, edits and filters).
//
// Run them from the project folder:
//     vendor\bin\phpunit
// PHPUnit reads phpunit.xml, finds this file, and runs every public method whose name starts with "test".
//
// Same 3 parts as the Python tests: build a known input -> call ONE function -> check the result.
// $this->assertSame(expected, actual) = "these must be exactly equal (same value AND same type)",
// like === in PHP. If not, PHPUnit shows both values and marks the test as failed.
// No database is needed: these functions only look at the arrays we give them.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/validation.php';

// "extends TestCase" = this class gets all of PHPUnit's assert...() methods.
// final = nobody builds a subclass of this test class (a common habit for tests).
final class ValidationTest extends TestCase
{
    // --- Helper: a VALID new request, so each test only changes the one field it is about ---
    // private = only this class can call it; it isn't a test (its name doesn't start with "test").
    private function validRequest(array $changes = []): array
    {
        $valid = [
            'title' => 'Brake noise',
            'vehicle_id' => '2',
            'description' => '',
            'priority' => 'medium',
        ];

        // array_merge: the values in $changes replace the defaults with the same key
        return array_merge($valid, $changes);
    }

    // The vehicle ids that "exist" in these tests (as text, like create.php builds them).
    private const VEHICLE_IDS = ['1', '2', '3'];

    // --- validate_request() -------------------------------------------------------------

    public function testValidRequestHasNoErrors(): void
    {
        $errors = validate_request($this->validRequest(), self::VEHICLE_IDS);

        $this->assertSame([], $errors);
    }

    public function testEmptyTitleIsRequired(): void
    {
        $errors = validate_request($this->validRequest(['title' => '']), self::VEHICLE_IDS);

        $this->assertSame('Title is required.', $errors['title']);
    }

    public function testTitleOf150CharactersIsAllowed(): void
    {
        // str_repeat('a', 150) = "aaaa..." (150 times): exactly the limit
        $errors = validate_request($this->validRequest(['title' => str_repeat('a', 150)]), self::VEHICLE_IDS);

        $this->assertArrayNotHasKey('title', $errors);
    }

    public function testTitleOf151CharactersIsTooLong(): void
    {
        $errors = validate_request($this->validRequest(['title' => str_repeat('a', 151)]), self::VEHICLE_IDS);

        $this->assertSame('Title must be 150 characters or fewer.', $errors['title']);
    }

    public function testTitleCountsCharactersNotBytes(): void
    {
        // 'é' is 1 character but 2 bytes. 150 of them = 300 bytes, still 150 characters: allowed.
        // (strlen() would count 300 and wrongly reject it; mb_strlen() counts characters.)
        $errors = validate_request($this->validRequest(['title' => str_repeat('é', 150)]), self::VEHICLE_IDS);

        $this->assertArrayNotHasKey('title', $errors);
    }

    public function testVehicleMustExist(): void
    {
        $errors = validate_request($this->validRequest(['vehicle_id' => '999']), self::VEHICLE_IDS);

        $this->assertSame('Please choose a vehicle.', $errors['vehicle_id']);
    }

    public function testEmptyVehicleIsRejected(): void
    {
        $errors = validate_request($this->validRequest(['vehicle_id' => '']), self::VEHICLE_IDS);

        $this->assertArrayHasKey('vehicle_id', $errors);
    }

    public function testPriorityMustMatchExactly(): void
    {
        // Strict matching: 'HIGH' is not 'high', and 'urgent' isn't a priority at all.
        foreach (['HIGH', 'urgent', ''] as $badPriority) {
            $errors = validate_request($this->validRequest(['priority' => $badPriority]), self::VEHICLE_IDS);

            // The 3rd argument is the message PHPUnit shows if this check fails, so we know WHICH value broke it.
            $this->assertArrayHasKey('priority', $errors, "'$badPriority' should be rejected");
        }
    }

    public function testDescriptionIsOptional(): void
    {
        $errors = validate_request($this->validRequest(['description' => '']), self::VEHICLE_IDS);

        $this->assertArrayNotHasKey('description', $errors);
    }

    public function testDescriptionOf2000CharactersIsAllowed(): void
    {
        $errors = validate_request($this->validRequest(['description' => str_repeat('a', 2000)]), self::VEHICLE_IDS);

        $this->assertArrayNotHasKey('description', $errors);
    }

    public function testDescriptionOver2000CharactersIsTooLong(): void
    {
        $errors = validate_request($this->validRequest(['description' => str_repeat('a', 2001)]), self::VEHICLE_IDS);

        $this->assertSame('Description must be 2000 characters or fewer.', $errors['description']);
    }

    public function testSeveralErrorsAreReportedTogether(): void
    {
        $input = ['title' => '', 'vehicle_id' => '', 'description' => '', 'priority' => 'urgent'];

        $errors = validate_request($input, self::VEHICLE_IDS);

        // array_keys() = just the field names that have an error
        $this->assertSame(['title', 'vehicle_id', 'priority'], array_keys($errors));
    }

    // --- form_text() --------------------------------------------------------------------

    public function testFormTextTrimsText(): void
    {
        $this->assertSame('Brake noise', form_text(['title' => '  Brake noise  '], 'title'));
    }

    public function testFormTextMissingFieldIsEmpty(): void
    {
        $this->assertSame('', form_text([], 'title'));
    }

    public function testFormTextArrayBecomesEmptyInsteadOfCrashing(): void
    {
        // title[]=x in a crafted POST: trim() would crash with a TypeError; form_text() returns ''.
        $this->assertSame('', form_text(['title' => ['x']], 'title'));
    }

    // --- validate_status_update() -------------------------------------------------------

    public function testValidStatusUpdateHasNoErrors(): void
    {
        $errors = validate_status_update(['status' => 'in_progress', 'priority' => 'low']);

        $this->assertSame([], $errors);
    }

    public function testUnknownStatusIsRejected(): void
    {
        $errors = validate_status_update(['status' => 'cancelled', 'priority' => 'low']);

        $this->assertSame('Please choose a valid status.', $errors['status']);
        $this->assertArrayNotHasKey('priority', $errors);
    }

    public function testUnknownPriorityIsRejected(): void
    {
        $errors = validate_status_update(['status' => 'open', 'priority' => 'urgent']);

        $this->assertSame('Please choose a valid priority.', $errors['priority']);
    }

    // --- clean_request_filters() --------------------------------------------------------

    public function testNoFiltersGivesThreeEmptyValues(): void
    {
        $this->assertSame(['q' => '', 'status' => '', 'priority' => ''], clean_request_filters([]));
    }

    public function testValidFiltersAreKept(): void
    {
        $filters = clean_request_filters(['q' => 'brake', 'status' => 'open', 'priority' => 'high']);

        $this->assertSame(['q' => 'brake', 'status' => 'open', 'priority' => 'high'], $filters);
    }

    public function testSearchIsTrimmedAndCutAt100Characters(): void
    {
        $filters = clean_request_filters(['q' => '  ' . str_repeat('x', 120) . '  ']);

        $this->assertSame(str_repeat('x', 100), $filters['q']);
    }

    public function testBadStatusAndPriorityAreIgnoredNotErrors(): void
    {
        // Forgiving on purpose: a bad filter just means "no filter".
        $filters = clean_request_filters(['status' => 'OPEN', 'priority' => 'urgent']);

        $this->assertSame('', $filters['status']);
        $this->assertSame('', $filters['priority']);
    }

    public function testArraysInTheUrlAreIgnored(): void
    {
        // ?q[]=x&status[]=open makes PHP give us ARRAYS instead of text.
        $filters = clean_request_filters(['q' => ['x'], 'status' => ['open'], 'priority' => ['high']]);

        $this->assertSame(['q' => '', 'status' => '', 'priority' => ''], $filters);
    }

    // --- The lists must match the database (contract test) -------------------------------
    // schema.sql is the single source of truth. If a value is added there but not here
    // (or the other way round), this test fails before a user ever sees the problem.

    // Reads the ENUM values of one column straight from database/schema.sql.
    private function enumValuesFromSchema(string $column): array
    {
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');

        // Finds e.g.   status      ENUM('open', 'in_progress', 'completed')
        preg_match('/' . $column . '\s+ENUM\(([^)]*)\)/', $schema, $match);
        $this->assertNotEmpty($match, "No ENUM found for $column in schema.sql");

        // Pulls every 'value' out of the brackets.
        preg_match_all("/'([^']*)'/", $match[1], $values);
        return $values[1];
    }

    public function testStatusOptionsMatchSchema(): void
    {
        $this->assertSame($this->enumValuesFromSchema('status'), array_keys(STATUS_OPTIONS));
    }

    public function testPriorityOptionsMatchSchema(): void
    {
        $this->assertSame($this->enumValuesFromSchema('priority'), array_keys(PRIORITY_OPTIONS));
    }
}
