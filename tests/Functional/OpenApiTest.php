<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OpenApiTest extends WebTestCase
{
    public function testSpecificationDescribesEveryEndpointAndItsErrors(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/doc.json');

        self::assertResponseIsSuccessful();
        /** @var array{paths: array<string, array{get: array{parameters?: list<array{name: string}>, responses: array<int|string, mixed>}}>} $spec */
        $spec = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(['/fizzbuzz', '/health', '/statistics'], array_keys($spec['paths']));
        self::assertSame(
            ['int1', 'int2', 'limit', 'str1', 'str2'],
            array_column($spec['paths']['/fizzbuzz']['get']['parameters'] ?? [], 'name'),
        );
        self::assertSame([200, 400, 429, 503], array_keys($spec['paths']['/fizzbuzz']['get']['responses']));
        self::assertSame([200, 429, 503], array_keys($spec['paths']['/statistics']['get']['responses']));
    }

    public function testSwaggerUiIsServed(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/doc');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        self::assertStringContainsString('FizzBuzz API', (string) $client->getResponse()->getContent());
    }
}
