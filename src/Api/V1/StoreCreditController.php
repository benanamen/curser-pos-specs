<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Sale\StoreCreditRepository;
use PerfectApp\Routing\Route;

final class StoreCreditController
{
    public function __construct(
        private readonly StoreCreditRepository $storeCreditRepository
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/store-credits', ['GET'])]
    public function list(string $slug): void
    {
        $consignorId = isset($_GET['consignor_id']) && $_GET['consignor_id'] !== '' ? (string) $_GET['consignor_id'] : null;
        if ($consignorId === null) {
            $this->json(400, ['error' => 'consignor_id query parameter required']);
            return;
        }
        $list = $this->storeCreditRepository->getByConsignorId($consignorId);
        $this->json(200, ['store_credits' => $list]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/store-credits/([0-9a-fA-F-]{36})', ['GET'])]
    public function show(string $slug, string $id): void
    {
        $sc = $this->storeCreditRepository->findById($id);
        if ($sc === null) {
            $this->json(404, ['error' => 'Store credit not found']);
            return;
        }
        $this->json(200, $sc);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/store-credits', ['POST'])]
    public function create(string $slug): void
    {
        $input = $this->getJsonInput();
        $consignorId = isset($input['consignor_id']) && $input['consignor_id'] !== '' ? (string) $input['consignor_id'] : null;
        $amount = (float) ($input['amount'] ?? 0);
        if ($amount <= 0) {
            $this->json(400, ['error' => 'amount must be greater than 0']);
            return;
        }
        $id = $this->storeCreditRepository->create($consignorId, $amount);
        $sc = $this->storeCreditRepository->findById($id);
        $this->json(201, $sc !== null ? $sc : ['id' => $id, 'balance' => $amount]);
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
