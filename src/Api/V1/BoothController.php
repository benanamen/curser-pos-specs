<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Booth\BoothRepository;
use PerfectApp\Routing\Route;

final class BoothController
{
    public function __construct(
        private readonly BoothRepository $boothRepository
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/booths', ['GET'])]
    public function list(string $slug): void
    {
        $status = $_GET['status'] ?? 'active';
        $booths = $this->boothRepository->findAll($status);
        $this->json(200, array_map(fn ($b) => $this->boothToArray($b), $booths));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/booths/([0-9a-fA-F-]{36})', ['GET'])]
    public function show(string $slug, string $id): void
    {
        $booth = $this->boothRepository->findById($id);
        if ($booth === null) {
            $this->json(404, ['error' => 'Booth not found']);
            return;
        }
        $this->json(200, $this->boothToArray($booth));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/booths', ['POST'])]
    public function create(string $slug): void
    {
        $input = $this->getJsonInput();
        $name = $input['name'] ?? '';
        if ($name === '') {
            $this->json(400, ['error' => 'Name is required']);
            return;
        }
        $locationId = isset($input['location_id']) && $input['location_id'] !== '' ? (string) $input['location_id'] : null;
        $monthlyRent = (float) ($input['monthly_rent'] ?? 0);

        $id = $this->boothRepository->create($name, $locationId, $monthlyRent);
        $booth = $this->boothRepository->findById($id);
        $this->json(201, $booth !== null ? $this->boothToArray($booth) : ['id' => $id]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/booths/([0-9a-fA-F-]{36})', ['PUT', 'PATCH'])]
    public function update(string $slug, string $id): void
    {
        $booth = $this->boothRepository->findById($id);
        if ($booth === null) {
            $this->json(404, ['error' => 'Booth not found']);
            return;
        }
        $input = $this->getJsonInput();
        $name = $input['name'] ?? $booth->name;
        $locationId = array_key_exists('location_id', $input) ? ($input['location_id'] !== '' ? (string) $input['location_id'] : null) : $booth->locationId;
        $monthlyRent = array_key_exists('monthly_rent', $input) ? (float) $input['monthly_rent'] : $booth->monthlyRent;
        $status = $input['status'] ?? $booth->status;

        $this->boothRepository->update($id, $name, $locationId, $monthlyRent, $status);
        $updated = $this->boothRepository->findById($id);
        $this->json(200, $updated !== null ? $this->boothToArray($updated) : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function boothToArray(object $booth): array
    {
        return [
            'id' => $booth->id,
            'name' => $booth->name,
            'location_id' => $booth->locationId,
            'monthly_rent' => $booth->monthlyRent,
            'status' => $booth->status,
            'created_at' => $booth->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $booth->updatedAt->format(\DateTimeInterface::ATOM),
        ];
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
