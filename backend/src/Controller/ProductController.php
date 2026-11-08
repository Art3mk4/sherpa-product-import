<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponder;
use App\Http\Presenter\ProductPresenter;
use App\Service\ProductFilter;
use App\Service\ProductService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Контроллеры тонкие: принять запрос → делегировать → вернуть ответ. */
final class ProductController
{
    public function __construct(
        private readonly ProductService $products,
        private readonly ProductPresenter $presenter,
        private readonly JsonResponder $json,
    ) {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filter = ProductFilter::fromQuery($request->getQueryParams());
        $result = $this->products->list($filter);

        $items = [];
        foreach ($result['items'] as $product) {
            $items[] = $this->presenter->listItem($product, $result['thumbs'][$product->getId()] ?? null);
        }

        return $this->json->respond($response, [
            'data' => $items,
            'meta' => [
                'page' => $filter->page,
                'limit' => $filter->limit,
                'total' => $result['total'],
                'totalPages' => max(1, (int) ceil($result['total'] / $filter->limit)),
            ],
        ]);
    }

    /** @param array<string, string> $args */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $product = $this->products->get((int) $args['id']);

        return $this->json->respond($response, $this->presenter->detail($product));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $product = $this->products->create((array) ($request->getParsedBody() ?: []));

        return $this->json->respond($response, $this->presenter->detail($product), 201);
    }

    /** @param array<string, string> $args */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $product = $this->products->update((int) $args['id'], (array) ($request->getParsedBody() ?: []));

        return $this->json->respond($response, $this->presenter->detail($product));
    }

    /** @param array<string, string> $args */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->products->delete((int) $args['id']);

        return $response->withStatus(204);
    }
}
