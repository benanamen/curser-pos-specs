<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Location\LocationRepository;
use PerfectApp\Routing\Route;

final class LocationController
{
    public function __construct(
        private readonly LocationRepository $locationRepository
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/locations', ['GET'])]
    public function list(string $slug): void
    {
        $locations = $this->locationRepository->findAll();
        $this->json(200, array_map(fn ($l) => $this->locationToArray($l), $locations));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/locations/([0-9a-fA-F-]{36})', ['GET'])]
    public function show(string $slug, string $id): void
    {
        $location = $this->locationRepository->findById($id);
        if ($location === null) {
            $this->json(404, ['error' => 'Location not found']);
            return;
        }
        $this->json(200, $this->locationToArray($location));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/locations', ['POST'])]
    public function create(string $slug): void
    {
        $input = $this->getJsonInput();
        $name = $input['name'] ?? '';
        if ($name === '') {
            $this->json(400, ['error' => 'Name is required']);
            return;
        }
        $address = $input['address'] ?? '';
        $taxRates = $input['tax_rates'] ?? [];
        $taxRates = is_array($taxRates) ? $taxRates : [];

        $id = $this->locationRepository->create($name, $address, $taxRates);
        $location = $this->locationRepository->findById($id);
        $this->json(201, $location !== null ? $this->locationToArray($location) : ['id' => $id]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/locations/([0-9a-fA-F-]{36})', ['PUT', 'PATCH'])]
    public function update(string $slug, string $id): void
    {
        $location = $this->locationRepository->findById($id);
        if ($location === null) {
            $this->json(404, ['error' => 'Location not found']);
            return;
        }
        $input = $this->getJsonInput();
        $name = $input['name'] ?? $location->name;
        $address = $input['address'] ?? $location->address;
        $taxRates = $input['tax_rates'] ?? $location->taxRates;
        $taxRates = is_array($taxRates) ? $taxRates : $location->taxRates;

        $this->locationRepository->update($id, $name, $address, $taxRates);
        $updated = $this->locationRepository->findById($id);
        $this->json(200, $updated !== null ? $this->locationToArray($updated) : []);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/locations/([0-9a-fA-F-]{36})', ['DELETE'])]
    public function delete(string $slug, string $id): void
    {
        $location = $this->locationRepository->findById($id);
        if ($location === null) {
            $this->json(404, ['error' => 'Location not found']);
            return;
        }
        $this->locationRepository->delete($id);
        $this->json(204, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function locationToArray(object $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'address' => $location->address,
            'tax_rates' => $location->taxRates,
            'created_at' => $location->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $location->updatedAt->format(\DateTimeInterface::ATOM),
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
        if ($data !== []) {
            echo json_encode($data);
        }
    }
}
