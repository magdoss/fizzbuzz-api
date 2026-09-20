<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Every error leaves the API as RFC 7807 Problem Details: Symfony renders
 * them as JSON once the request format is json, this only fixes the media type.
 */
final class ProblemDetailsListener
{
    #[AsEventListener(priority: 100)]
    public function onRequest(RequestEvent $event): void
    {
        $event->getRequest()->setRequestFormat('json');
    }

    #[AsEventListener]
    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if ($response->getStatusCode() >= 400 && 'application/json' === $response->headers->get('Content-Type')) {
            $response->headers->set('Content-Type', 'application/problem+json');
        }
    }
}
