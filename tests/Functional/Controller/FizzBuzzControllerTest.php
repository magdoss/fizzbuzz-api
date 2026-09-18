<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FizzBuzzControllerTest extends WebTestCase
{
    public function testExampleFromTheExercise(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fizzbuzz?int1=3&int2=5&limit=15&str1=fizz&str2=buzz');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(
            '["1","2","fizz","4","buzz","fizz","7","8","fizz","buzz","11","fizz","13","14","fizzbuzz"]',
            $this->body($client),
        );
    }

    public function testStringsAreReturnedUnescaped(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fizzbuzz?int1=2&int2=3&limit=6&str1=fïzz&str2=a/b');

        self::assertResponseIsSuccessful();
        self::assertSame('["1","fïzz","a/b","fïzz","5","fïzza/b"]', $this->body($client));
    }

    public function testLimitEqualToTheCapIsAccepted(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fizzbuzz?int1=3&int2=5&limit=100000&str1=fizz&str2=buzz');

        self::assertResponseIsSuccessful();
        self::assertStringEndsWith(',"buzz"]', $this->body($client));
    }

    public function testLeadingZeroIsAccepted(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fizzbuzz?int1=03&int2=5&limit=5&str1=fizz&str2=buzz');

        self::assertResponseIsSuccessful();
        self::assertSame('["1","2","fizz","4","buzz"]', $this->body($client));
    }

    public function testRepeatedParameterKeepsTheLastValue(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fizzbuzz?int1=3&int1=5&int2=5&limit=5&str1=fizz&str2=buzz');

        self::assertResponseIsSuccessful();
        self::assertSame('["1","2","3","4","fizzbuzz"]', $this->body($client));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'missing parameter' => ['int1=3&int2=5&limit=10&str1=fizz', 'str2'];
        yield 'zero' => ['int1=0&int2=5&limit=10&str1=fizz&str2=buzz', 'int1'];
        yield 'negative' => ['int1=3&int2=-5&limit=10&str1=fizz&str2=buzz', 'int2'];
        yield 'not a number' => ['int1=abc&int2=5&limit=10&str1=fizz&str2=buzz', 'int1'];
        yield 'plus sign' => ['int1=%2B3&int2=5&limit=10&str1=fizz&str2=buzz', 'int1'];
        yield 'array' => ['int1[]=3&int2=5&limit=10&str1=fizz&str2=buzz', 'int1'];
        yield 'empty string' => ['int1=3&int2=5&limit=10&str1=&str2=buzz', 'str1'];
        yield 'too long string' => ['int1=3&int2=5&limit=10&str1='.str_repeat('a', 65).'&str2=buzz', 'str1'];
        yield 'invalid utf-8' => ['int1=3&int2=5&limit=10&str1=%FF&str2=buzz', 'str1'];
        yield 'above the cap' => ['int1=3&int2=5&limit=100001&str1=fizz&str2=buzz', 'limit'];
        yield 'int1 above limit' => ['int1=500&int2=5&limit=100&str1=fizz&str2=buzz', 'int1'];
        yield 'huge integer' => ['int1=99999999999999999999&int2=5&limit=100&str1=fizz&str2=buzz', 'int1'];
    }

    #[DataProvider('invalidQueries')]
    public function testInvalidQueryIsRejectedWithProblemDetails(string $query, string $propertyPath): void
    {
        $client = static::createClient();

        $client->request('GET', '/fizzbuzz?'.$query);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        $problem = $this->problem($client);
        self::assertSame(400, $problem['status']);
        self::assertContains($propertyPath, array_column($problem['violations'], 'propertyPath'));
        self::assertSame(['propertyPath', 'message'], array_keys($problem['violations'][0]));
    }

    /**
     * After rejecting "3.5", the serializer still instantiates the DTO with the raw
     * string to collect the other errors, which makes PHP emit a precision deprecation.
     */
    #[IgnoreDeprecations]
    public function testDecimalIsRejected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fizzbuzz?int1=3&int2=5&limit=3.5&str1=fizz&str2=buzz');

        self::assertResponseStatusCodeSame(400);
        self::assertContains('limit', array_column($this->problem($client)['violations'], 'propertyPath'));
    }

    public function testEmptyQueryIsRejected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/fizzbuzz');

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function testUnknownRouteIsAProblemDetails(): void
    {
        $client = static::createClient();

        $client->request('GET', '/nope');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame(404, $this->problem($client)['status']);
    }

    public function testWrongMethodIsAProblemDetails(): void
    {
        $client = static::createClient();

        $client->request('POST', '/fizzbuzz?int1=3&int2=5&limit=15&str1=fizz&str2=buzz');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    private function body(KernelBrowser $client): string
    {
        return $client->getInternalResponse()->getContent();
    }

    /**
     * @return array{status: int, violations: list<array{propertyPath: string, message: string}>}
     */
    private function problem(KernelBrowser $client): array
    {
        /** @var array{status: int, violations?: list<array{propertyPath: string, message: string}>} $problem */
        $problem = json_decode($this->body($client), true, 512, \JSON_THROW_ON_ERROR);

        return $problem + ['violations' => []];
    }
}
