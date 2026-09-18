<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Exception\StatsUnavailableException;
use App\Repository\RequestStatRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StatisticsControllerTest extends WebTestCase
{
    public function testNothingRecordedYet(): void
    {
        $client = static::createClient();

        $client->request('GET', '/statistics');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(['request' => null, 'hits' => 0], $this->statistics($client));
    }

    public function testTheMostUsedRequestWithItsHits(): void
    {
        $client = static::createClient();
        $this->fizzbuzz($client, 'int1=3&int2=5&limit=100&str1=fizz&str2=buzz');
        $this->fizzbuzz($client, 'int1=3&int2=5&limit=100&str1=fizz&str2=buzz');
        $this->fizzbuzz($client, 'int1=5&int2=3&limit=100&str1=buzz&str2=fizz');

        $client->request('GET', '/statistics');

        self::assertSame([
            'request' => ['int1' => 3, 'int2' => 5, 'limit' => 100, 'str1' => 'fizz', 'str2' => 'buzz'],
            'hits' => 2,
        ], $this->statistics($client));
    }

    public function testParameterOrderInTheQueryStringDoesNotMatter(): void
    {
        $client = static::createClient();
        $this->fizzbuzz($client, 'int1=3&int2=5&limit=100&str1=fizz&str2=buzz');
        $this->fizzbuzz($client, 'str2=buzz&str1=fizz&limit=100&int2=5&int1=3');

        $client->request('GET', '/statistics');

        self::assertSame(2, $this->statistics($client)['hits']);
    }

    public function testTieGoesToTheRequestSeenFirst(): void
    {
        $client = static::createClient();
        $this->fizzbuzz($client, 'int1=2&int2=7&limit=20&str1=a&str2=b');
        $this->fizzbuzz($client, 'int1=3&int2=5&limit=100&str1=fizz&str2=buzz');

        $client->request('GET', '/statistics');

        self::assertSame(['int1' => 2, 'int2' => 7, 'limit' => 20, 'str1' => 'a', 'str2' => 'b'], $this->statistics($client)['request']);
    }

    public function testInvalidRequestsAreNotCounted(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fizzbuzz?int1=0&int2=5&limit=100&str1=fizz&str2=buzz');
        self::assertResponseStatusCodeSame(400);

        $client->request('GET', '/statistics');

        self::assertSame(0, $this->statistics($client)['hits']);
    }

    public function testStringsComeBackUnchanged(): void
    {
        $client = static::createClient();
        $this->fizzbuzz($client, 'int1=2&int2=3&limit=6&str1=f%C3%AFzz&str2=a/b');

        $client->request('GET', '/statistics');

        self::assertSame('fïzz', $this->statistics($client)['request']['str1'] ?? null);
        self::assertStringContainsString('"str1":"fïzz","str2":"a/b"', (string) $client->getResponse()->getContent());
    }

    public function testStatisticsAnswer503WhenTheStorageIsDown(): void
    {
        $client = static::createClient();
        $this->breakTheStorage();

        $client->request('GET', '/statistics');

        self::assertResponseStatusCodeSame(503);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function testFizzBuzzStillAnswersWhenTheStorageIsDown(): void
    {
        $client = static::createClient();
        $this->breakTheStorage();

        $client->request('GET', '/fizzbuzz?int1=3&int2=5&limit=15&str1=fizz&str2=buzz');

        self::assertResponseIsSuccessful();
        self::assertStringEndsWith('"fizzbuzz"]', $client->getInternalResponse()->getContent());
    }

    private function fizzbuzz(KernelBrowser $client, string $query): void
    {
        $client->request('GET', '/fizzbuzz?'.$query);
        self::assertResponseIsSuccessful();
    }

    private function breakTheStorage(): void
    {
        $repository = $this->createStub(RequestStatRepository::class);
        $repository->method('record')->willThrowException(StatsUnavailableException::because(new \RuntimeException('down')));
        $repository->method('mostUsed')->willThrowException(StatsUnavailableException::because(new \RuntimeException('down')));
        static::getContainer()->set(RequestStatRepository::class, $repository);
    }

    /**
     * @return array{request: array{int1: int, int2: int, limit: int, str1: string, str2: string}|null, hits: int}
     */
    private function statistics(KernelBrowser $client): array
    {
        /** @var array{request: array{int1: int, int2: int, limit: int, str1: string, str2: string}|null, hits: int} $statistics */
        $statistics = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $statistics;
    }
}
