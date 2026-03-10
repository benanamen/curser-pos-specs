<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Category\CategoryRepository;
use PerfectApp\Routing\Route;

final class CategoryController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/categories', ['GET'])]
    public function list(string $slug): void
    {
        $categories = $this->categoryRepository->findAll();
        $this->json(200, array_map(fn ($c) => $this->categoryToArray($c), $categories));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/categories/([0-9a-fA-F-]{36})', ['GET'])]
    public function show(string $slug, string $id): void
    {
        $category = $this->categoryRepository->findById($id);
        if ($category === null) {
            $this->json(404, ['error' => 'Category not found']);
            return;
        }
        $this->json(200, $this->categoryToArray($category));
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/categories', ['POST'])]
    public function create(string $slug): void
    {
        $input = $this->getJsonInput();
        $name = $input['name'] ?? '';
        if ($name === '') {
            $this->json(400, ['error' => 'Name is required']);
            return;
        }
        $parentId = isset($input['parent_id']) && $input['parent_id'] !== '' ? (string) $input['parent_id'] : null;
        $sortOrder = (int) ($input['sort_order'] ?? 0);
        $taxExempt = ($input['tax_exempt'] ?? false) === true;

        $id = $this->categoryRepository->create($name, $parentId, $sortOrder, $taxExempt);
        $category = $this->categoryRepository->findById($id);
        $this->json(201, $category !== null ? $this->categoryToArray($category) : ['id' => $id]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/categories/([0-9a-fA-F-]{36})', ['PUT', 'PATCH'])]
    public function update(string $slug, string $id): void
    {
        $category = $this->categoryRepository->findById($id);
        if ($category === null) {
            $this->json(404, ['error' => 'Category not found']);
            return;
        }
        $input = $this->getJsonInput();
        $name = $input['name'] ?? $category->name;
        $parentId = isset($input['parent_id']) ? ($input['parent_id'] !== '' ? (string) $input['parent_id'] : null) : $category->parentId;
        $sortOrder = isset($input['sort_order']) ? (int) $input['sort_order'] : $category->sortOrder;
        $taxExempt = isset($input['tax_exempt']) ? $input['tax_exempt'] === true : $category->taxExempt;

        $this->categoryRepository->update($id, $name, $parentId, $sortOrder, $taxExempt);
        $updated = $this->categoryRepository->findById($id);
        $this->json(200, $updated !== null ? $this->categoryToArray($updated) : []);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/categories/([0-9a-fA-F-]{36})', ['DELETE'])]
    public function delete(string $slug, string $id): void
    {
        $category = $this->categoryRepository->findById($id);
        if ($category === null) {
            $this->json(404, ['error' => 'Category not found']);
            return;
        }
        $this->categoryRepository->delete($id);
        $this->json(204, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryToArray(object $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parentId,
            'name' => $category->name,
            'sort_order' => $category->sortOrder,
            'tax_exempt' => $category->taxExempt,
            'created_at' => $category->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $category->updatedAt->format(\DateTimeInterface::ATOM),
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
