<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Register\RegisterCashDropRepository;
use CurserPos\Domain\Register\RegisterRepository;
use CurserPos\Http\RequestContextHolder;
use PDO;
use PerfectApp\Routing\Route;

final class RegisterController
{
    public function __construct(
        private readonly RegisterRepository $registerRepository,
        private readonly RegisterCashDropRepository $cashDropRepository,
        private readonly PDO $pdo
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/registers', ['GET'])]
    public function list(string $slug): void
    {
        $registers = $this->registerRepository->findAll();
        $this->json(200, array_map(fn ($r) => [
            'id' => $r->id,
            'location_id' => $r->locationId,
            'register_id' => $r->registerId,
            'status' => $r->status,
            'opening_cash' => $r->openingCash,
            'closing_cash' => $r->closingCash,
            'opened_at' => $r->openedAt?->format(\DateTimeInterface::ATOM),
            'closed_at' => $r->closedAt?->format(\DateTimeInterface::ATOM),
        ], $registers));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/registers', ['POST'])]
    public function create(string $slug): void
    {
        $input = $this->getJsonInput();
        $locationId = $input['location_id'] ?? '';
        $registerId = $input['register_id'] ?? ('REG' . substr(uniqid(), -4));
        if ($locationId === '') {
            $this->json(400, ['error' => 'location_id is required']);
            return;
        }
        $id = $this->registerRepository->create($locationId, $registerId);
        $reg = $this->registerRepository->findById($id);
        $this->json(201, $reg !== null ? ['id' => $reg->id, 'register_id' => $reg->registerId, 'status' => $reg->status] : ['id' => $id]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/registers/([0-9a-fA-F-]{36})/open', ['POST'])]
    public function open(string $slug, string $id): void
    {
        $user = RequestContextHolder::get()?->user;
        if ($user === null) {
            $this->json(401, ['error' => 'Authentication required']);
            return;
        }
        $input = $this->getJsonInput();
        $openingCash = (float) ($input['opening_cash'] ?? 0);
        $reg = $this->registerRepository->findById($id);
        if ($reg === null) {
            $this->json(404, ['error' => 'Register not found']);
            return;
        }
        $this->registerRepository->open($id, $user->id, $openingCash);
        $updated = $this->registerRepository->findById($id);
        $this->json(200, $updated !== null ? ['status' => $updated->status, 'opened_at' => $updated->openedAt?->format(\DateTimeInterface::ATOM)] : []);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/registers/([0-9a-fA-F-]{36})/close', ['POST'])]
    public function close(string $slug, string $id): void
    {
        $input = $this->getJsonInput();
        $closingCash = (float) ($input['closing_cash'] ?? 0);
        $reg = $this->registerRepository->findById($id);
        if ($reg === null) {
            $this->json(404, ['error' => 'Register not found']);
            return;
        }
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM held_sales');
        $heldCount = (int) $stmt->fetchColumn();
        if ($heldCount > 0) {
            $this->json(400, ['error' => 'Cannot close register while there are held sales']);
            return;
        }
        $this->registerRepository->close($id, $closingCash);
        $updated = $this->registerRepository->findById($id);
        $this->json(200, $updated !== null ? ['status' => $updated->status, 'closed_at' => $updated->closedAt?->format(\DateTimeInterface::ATOM)] : []);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/registers/([0-9a-fA-F-]{36})/cash-drop', ['POST'])]
    public function cashDrop(string $slug, string $id): void
    {
        $reg = $this->registerRepository->findById($id);
        if ($reg === null) {
            $this->json(404, ['error' => 'Register not found']);
            return;
        }
        $input = $this->getJsonInput();
        $amount = (float) ($input['amount'] ?? 0);
        if ($amount <= 0) {
            $this->json(400, ['error' => 'amount must be greater than 0']);
            return;
        }
        $dropId = $this->cashDropRepository->record($id, $amount);
        $this->json(201, ['id' => $dropId, 'register_id' => $id, 'amount' => $amount]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/registers/([0-9a-fA-F-]{36})/summary', ['GET'])]
    public function summary(string $slug, string $id): void
    {
        $reg = $this->registerRepository->findById($id);
        if ($reg === null) {
            $this->json(404, ['error' => 'Register not found']);
            return;
        }
        $openedAt = $reg->openedAt?->format('Y-m-d H:i:s');
        $cashDropsTotal = $openedAt !== null ? $this->cashDropRepository->totalByRegisterSince($id, $openedAt) : 0.0;
        $cashDropsList = $this->cashDropRepository->listByRegister($id, 20);

        $salesTotal = 0.0;
        $salesCount = 0;
        if ($openedAt !== null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*), COALESCE(SUM(total), 0) FROM sales WHERE register_id = ? AND status = ? AND created_at >= ?');
            $stmt->execute([$id, 'completed', $openedAt]);
            $row = $stmt->fetch(PDO::FETCH_NUM);
            if ($row !== false) {
                $salesCount = (int) $row[0];
                $salesTotal = (float) $row[1];
            }
        }

        $this->json(200, [
            'register_id' => $id,
            'status' => $reg->status,
            'opening_cash' => $reg->openingCash,
            'closing_cash' => $reg->closingCash,
            'opened_at' => $reg->openedAt?->format(\DateTimeInterface::ATOM),
            'closed_at' => $reg->closedAt?->format(\DateTimeInterface::ATOM),
            'sales_count' => $salesCount,
            'sales_total' => $salesTotal,
            'cash_drops_total' => $cashDropsTotal,
            'cash_drops' => $cashDropsList,
        ]);
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
