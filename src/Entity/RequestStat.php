<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RequestStatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequestStatRepository::class, readOnly: true)]
#[ORM\Table(name: 'request_stats')]
#[ORM\Index(name: 'idx_request_stats_hits', columns: ['hits'])]
#[ORM\UniqueConstraint(name: 'uniq_request_stats_hash', columns: ['request_hash'])]
class RequestStat
{
    // BIGINT: the upsert consumes one auto-increment value per request, not per row
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private int $id;

    #[ORM\Column(type: Types::BINARY, length: 32, options: ['fixed' => true])]
    private string $requestHash;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $int1;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $int2;

    #[ORM\Column(name: 'limit_value', options: ['unsigned' => true])]
    private int $limit;

    #[ORM\Column(length: 64)]
    private string $str1;

    #[ORM\Column(length: 64)]
    private string $str2;

    #[ORM\Column(options: ['unsigned' => true, 'default' => 1])]
    private int $hits;

    /**
     * @return array{int1: int, int2: int, limit: int, str1: string, str2: string}
     */
    public function request(): array
    {
        return [
            'int1' => $this->int1,
            'int2' => $this->int2,
            'limit' => $this->limit,
            'str1' => $this->str1,
            'str2' => $this->str2,
        ];
    }

    public function hits(): int
    {
        return $this->hits;
    }
}
