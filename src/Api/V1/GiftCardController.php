<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Sale\GiftCardRepository;
use PerfectApp\Routing\Route;

final class GiftCardController
{
    public function __construct(
        private readonly GiftCardRepository $giftCardRepository
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/gift-cards/lookup', ['GET'])]
    public function lookup(string $slug): void
    {
        $code = $_GET['code'] ?? '';
        if ($code === '') {
            $this->json(400, ['error' => 'code query parameter required']);
            return;
        }
        $gc = $this->giftCardRepository->findByCode($code);
        if ($gc === null) {
            $this->json(404, ['error' => 'Gift card not found']);
            return;
        }
        $this->json(200, ['id' => $gc['id'], 'code' => $gc['code'], 'balance' => (float) $gc['balance'], 'status' => $gc['status']]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/gift-cards/([0-9a-fA-F-]{36})', ['GET'])]
    public function show(string $slug, string $id): void
    {
        $gc = $this->giftCardRepository->findById($id);
        if ($gc === null) {
            $this->json(404, ['error' => 'Gift card not found']);
            return;
        }
        $this->json(200, $gc);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/gift-cards', ['POST'])]
    public function create(string $slug): void
    {
        $input = $this->getJsonInput();
        $code = $input['code'] ?? '';
        if ($code === '') {
            $this->json(400, ['error' => 'code is required']);
            return;
        }
        $amount = (float) ($input['amount'] ?? 0);
        if ($amount <= 0) {
            $this->json(400, ['error' => 'amount must be greater than 0']);
            return;
        }
        try {
            $id = $this->giftCardRepository->create($code, $amount);
            $gc = $this->giftCardRepository->findById($id);
            $this->json(201, $gc !== null ? $gc : ['id' => $id, 'code' => $code, 'balance' => $amount]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), 'Duplicate')) {
                $this->json(400, ['error' => 'Gift card code already exists']);
                return;
            }
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonInput(): array
    {
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
