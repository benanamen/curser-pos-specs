<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Booth\ConsignorBoothAssignmentRepository;
use CurserPos\Domain\Booth\RentDeductionRepository;
use CurserPos\Domain\Consignor\ConsignorRepository;
use CurserPos\Http\RequestContextHolder;
use CurserPos\Service\BoothRentalService;
use CurserPos\Service\ConsignorService;
use PDO;
use PerfectApp\Routing\Route;

final class ConsignorController
{
    public function __construct(
        private readonly ConsignorRepository $consignorRepository,
        private readonly ConsignorService $consignorService,
        private readonly BoothRentalService $boothRentalService,
        private readonly ConsignorBoothAssignmentRepository $assignmentRepository,
        private readonly RentDeductionRepository $rentDeductionRepository,
        private readonly PDO $pdo
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors', ['GET'])]
    public function list(string $slug): void
    {
        $status = $_GET['status'] ?? 'active';
        $consignors = $this->consignorRepository->findAll($status);
        $result = [];
        foreach ($consignors as $c) {
            $arr = $this->consignorToArray($c);
            $balance = $this->consignorService->getBalance($c->id);
            $arr['balance'] = $balance->balance;
            $arr['pending_sales'] = $balance->pendingSales;
            $arr['paid_out'] = $balance->paidOut;
            $result[] = $arr;
        }
        $this->json(200, $result);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors/([0-9a-fA-F-]{36})', ['GET'])]
    public function show(string $slug, string $id): void
    {
        $consignor = $this->consignorRepository->findById($id);
        if ($consignor === null) {
            $this->json(404, ['error' => 'Consignor not found']);
            return;
        }
        $arr = $this->consignorToArray($consignor);
        $balance = $this->consignorService->getBalance($id);
        $arr['balance'] = $balance->balance;
        $arr['pending_sales'] = $balance->pendingSales;
        $arr['paid_out'] = $balance->paidOut;
        $assignment = $this->assignmentRepository->getActiveByConsignorId($id);
        $arr['booth_assignment'] = $assignment !== null ? [
            'booth_id' => $assignment->boothId,
            'started_at' => $assignment->startedAt->format('Y-m-d'),
            'monthly_rent' => $assignment->monthlyRent,
        ] : null;
        $this->json(200, $arr);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors', ['POST'])]
    public function create(string $slug): void
    {
        $input = $this->getJsonInput();
        $name = $input['name'] ?? '';
        $slugInput = $input['slug'] ?? $this->slugFromName($name);
        if ($name === '') {
            $this->json(400, ['error' => 'Name is required']);
            return;
        }
        $customId = isset($input['custom_id']) && $input['custom_id'] !== '' ? (string) $input['custom_id'] : null;
        $email = isset($input['email']) && $input['email'] !== '' ? (string) $input['email'] : null;
        $phone = isset($input['phone']) && $input['phone'] !== '' ? (string) $input['phone'] : null;
        $address = isset($input['address']) && $input['address'] !== '' ? (string) $input['address'] : null;
        $commission = (float) ($input['default_commission_pct'] ?? 50.0);
        $agreementSignedAt = isset($input['agreement_signed_at']) && $input['agreement_signed_at'] !== ''
            ? new \DateTimeImmutable((string) $input['agreement_signed_at'])
            : null;
        $notes = isset($input['notes']) && $input['notes'] !== '' ? (string) $input['notes'] : null;

        try {
            $consignor = $this->consignorService->createConsignor($slugInput, $name, $customId, $email, $phone, $address, $commission, $agreementSignedAt, $notes);
            $this->json(201, $this->consignorToArray($consignor));
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors/import', ['POST'])]
    public function bulkImport(string $slug): void
    {
        $body = file_get_contents('php://input');
        if ($body === false || trim($body) === '') {
            $this->json(400, ['error' => 'CSV body required']);
            return;
        }
        try {
            $result = $this->consignorService->bulkImportFromCsv($body);
            $this->json(200, $result);
        } catch (\Throwable $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors/([0-9a-fA-F-]{36})', ['PUT', 'PATCH'])]
    public function update(string $slug, string $id): void
    {
        $consignor = $this->consignorRepository->findById($id);
        if ($consignor === null) {
            $this->json(404, ['error' => 'Consignor not found']);
            return;
        }
        $input = $this->getJsonInput();
        $slugInput = $input['slug'] ?? $consignor->slug;
        $name = $input['name'] ?? $consignor->name;
        $customId = array_key_exists('custom_id', $input)
            ? ($input['custom_id'] !== '' ? (string) $input['custom_id'] : null)
            : $consignor->customId;
        $email = array_key_exists('email', $input) ? ($input['email'] !== '' ? (string) $input['email'] : null) : $consignor->email;
        $phone = array_key_exists('phone', $input) ? ($input['phone'] !== '' ? (string) $input['phone'] : null) : $consignor->phone;
        $address = array_key_exists('address', $input) ? ($input['address'] !== '' ? (string) $input['address'] : null) : $consignor->address;
        $commission = array_key_exists('default_commission_pct', $input) ? (float) $input['default_commission_pct'] : $consignor->defaultCommissionPct;
        $agreementSignedAt = array_key_exists('agreement_signed_at', $input)
            ? ($input['agreement_signed_at'] !== '' ? new \DateTimeImmutable((string) $input['agreement_signed_at']) : null)
            : $consignor->agreementSignedAt;
        $notes = array_key_exists('notes', $input) ? ($input['notes'] !== '' ? (string) $input['notes'] : null) : $consignor->notes;

        try {
            $updated = $this->consignorService->updateConsignor($id, $slugInput, $name, $customId, $email, $phone, $address, $commission, $agreementSignedAt, $notes);
            $this->json(200, $this->consignorToArray($updated));
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors/([0-9a-fA-F-]{36})/balance-adjustment', ['POST'])]
    public function balanceAdjustment(string $slug, string $id): void
    {
        $consignor = $this->consignorRepository->findById($id);
        if ($consignor === null) {
            $this->json(404, ['error' => 'Consignor not found']);
            return;
        }
        $input = $this->getJsonInput();
        $amount = (float) ($input['amount'] ?? 0);
        $reason = $input['reason'] ?? 'Manual adjustment';
        if ($amount === 0.0) {
            $this->json(400, ['error' => 'Amount is required and cannot be zero']);
            return;
        }

        $this->consignorService->recordManualAdjustment($id, $amount, $reason);
        $balance = $this->consignorService->getBalance($id);
        $this->json(200, [
            'consignor_id' => $id,
            'balance' => $balance->balance,
            'pending_sales' => $balance->pendingSales,
            'paid_out' => $balance->paidOut,
        ]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors/([0-9a-fA-F-]{36})/booth-assignment', ['POST'])]
    public function assignBooth(string $slug, string $id): void
    {
        $consignor = $this->consignorRepository->findById($id);
        if ($consignor === null) {
            $this->json(404, ['error' => 'Consignor not found']);
            return;
        }
        $input = $this->getJsonInput();
        $boothId = isset($input['booth_id']) && $input['booth_id'] !== '' ? (string) $input['booth_id'] : null;
        if ($boothId === null) {
            $this->json(400, ['error' => 'booth_id is required']);
            return;
        }
        $monthlyRentOverride = array_key_exists('monthly_rent', $input) ? (float) $input['monthly_rent'] : null;
        $startedAt = isset($input['started_at']) && $input['started_at'] !== '' ? new \DateTimeImmutable((string) $input['started_at']) : null;
        try {
            $assignmentId = $this->boothRentalService->assignToBooth($id, $boothId, $monthlyRentOverride, $startedAt);
            $assignment = $this->assignmentRepository->getActiveByConsignorId($id);
            $this->json(200, [
                'assignment_id' => $assignmentId,
                'consignor_id' => $id,
                'booth_id' => $boothId,
                'started_at' => $assignment?->startedAt->format('Y-m-d'),
                'monthly_rent' => $assignment?->monthlyRent ?? $monthlyRentOverride,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors/([0-9a-fA-F-]{36})/booth-assignment/end', ['POST'])]
    public function endBoothAssignment(string $slug, string $id): void
    {
        $consignor = $this->consignorRepository->findById($id);
        if ($consignor === null) {
            $this->json(404, ['error' => 'Consignor not found']);
            return;
        }
        $input = $this->getJsonInput();
        $endedAt = isset($input['ended_at']) && $input['ended_at'] !== '' ? new \DateTimeImmutable((string) $input['ended_at']) : null;
        $this->boothRentalService->endAssignment($id, $endedAt);
        $this->json(200, ['message' => 'Booth assignment ended', 'consignor_id' => $id]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors/([0-9a-fA-F-]{36})/rent-due', ['GET'])]
    public function rentDue(string $slug, string $id): void
    {
        $consignor = $this->consignorRepository->findById($id);
        if ($consignor === null) {
            $this->json(404, ['error' => 'Consignor not found']);
            return;
        }
        $rentDue = $this->boothRentalService->getRentDue($id);
        if ($rentDue === null) {
            $this->json(200, ['consignor_id' => $id, 'rent_due' => 0, 'period_start' => null, 'period_end' => null]);
            return;
        }
        $this->json(200, [
            'consignor_id' => $id,
            'rent_due' => $rentDue['amount'],
            'period_start' => $rentDue['period_start']->format('Y-m-d'),
            'period_end' => $rentDue['period_end']->format('Y-m-d'),
        ]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors/([0-9a-fA-F-]{36})/rent-deductions', ['GET'])]
    public function rentDeductions(string $slug, string $id): void
    {
        $consignor = $this->consignorRepository->findById($id);
        if ($consignor === null) {
            $this->json(404, ['error' => 'Consignor not found']);
            return;
        }
        $limit = (int) ($_GET['limit'] ?? 50);
        $list = $this->rentDeductionRepository->listByConsignor($id, $limit);
        $this->json(200, ['consignor_id' => $id, 'rent_deductions' => $list]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors/([0-9a-fA-F-]{36})/portal-token', ['POST'])]
    public function createPortalToken(string $slug, string $id): void
    {
        $context = RequestContextHolder::get();
        if ($context === null || $context->tenant === null) {
            $this->json(401, ['error' => 'Authentication required']);
            return;
        }
        if (!$this->tenantHasConsignorPortal($context->tenant->id)) {
            $this->json(403, ['error' => 'Consignor portal is not available on your plan']);
            return;
        }
        $consignor = $this->consignorRepository->findById($id);
        if ($consignor === null) {
            $this->json(404, ['error' => 'Consignor not found']);
            return;
        }
        $token = $this->consignorRepository->generatePortalToken($id);
        $this->json(200, [
            'consignor_id' => $id,
            'portal_token' => $token,
            'portal_url' => sprintf('/t/%s/api/v1/portal/me?token=%s', $slug, $token),
        ]);
    }

    private function tenantHasConsignorPortal(string $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.features FROM tenants t JOIN plans p ON p.id = t.plan_id WHERE t.id = ?'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !isset($row['features'])) {
            return false;
        }
        $features = is_string($row['features']) ? json_decode($row['features'], true) : $row['features'];
        return isset($features['consignor_portal']) && $features['consignor_portal'] === true;
    }

    /**
     * @return array<string, mixed>
     */
    private function consignorToArray(object $consignor): array
    {
        return [
            'id' => $consignor->id,
            'slug' => $consignor->slug,
            'custom_id' => $consignor->customId,
            'name' => $consignor->name,
            'email' => $consignor->email,
            'phone' => $consignor->phone,
            'address' => $consignor->address,
            'default_commission_pct' => $consignor->defaultCommissionPct,
            'agreement_signed_at' => $consignor->agreementSignedAt?->format('Y-m-d'),
            'status' => $consignor->status,
            'notes' => $consignor->notes,
            'created_at' => $consignor->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $consignor->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    private function slugFromName(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($name))) ?: 'consignor-' . substr(uniqid(), -6);
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
        if ($data !== []) {
            echo json_encode($data);
        }
    }
}
