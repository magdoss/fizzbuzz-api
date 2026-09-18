<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\FizzBuzzQuery;
use App\Entity\RequestStat;
use App\Exception\StatsUnavailableException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RequestStat>
 */
class RequestStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RequestStat::class);
    }

    public function record(FizzBuzzQuery $query): void
    {
        try {
            $this->getEntityManager()->getConnection()->executeStatement(
                'INSERT INTO request_stats (request_hash, `int1`, `int2`, limit_value, str1, str2, hits)
                 VALUES (:hash, :int1, :int2, :limit, :str1, :str2, 1)
                 ON DUPLICATE KEY UPDATE hits = hits + 1',
                [
                    'hash' => self::hash($query),
                    'int1' => $query->int1,
                    'int2' => $query->int2,
                    'limit' => $query->limit,
                    'str1' => $query->str1,
                    'str2' => $query->str2,
                ],
                ['hash' => ParameterType::BINARY],
            );
        } catch (DbalException $e) {
            throw StatsUnavailableException::because($e);
        }
    }

    public function mostUsed(): ?RequestStat
    {
        try {
            $mostUsed = $this->createQueryBuilder('s')
                ->where('s.hits = (SELECT MAX(m.hits) FROM '.RequestStat::class.' m)')
                ->orderBy('s.id', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            \assert(null === $mostUsed || $mostUsed instanceof RequestStat);

            return $mostUsed;
        } catch (DbalException $e) {
            throw StatsUnavailableException::because($e);
        }
    }

    public static function hash(FizzBuzzQuery $query): string
    {
        $key = json_encode([$query->int1, $query->int2, $query->limit, $query->str1, $query->str2], \JSON_THROW_ON_ERROR);

        return hash('sha256', $key, true);
    }
}
