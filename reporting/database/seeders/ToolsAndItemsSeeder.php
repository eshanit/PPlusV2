<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ToolsAndItemsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tools = $this->loadTools();

        DB::transaction(function () use ($tools): void {
            foreach ($tools as $toolIndex => $tool) {
                DB::table('tools')->updateOrInsert(
                    ['slug' => $tool['slug']],
                    [
                        'label' => $tool['label'],
                        'sort_order' => $toolIndex + 1,
                    ],
                );

                $toolId = (int) DB::table('tools')
                    ->where('slug', $tool['slug'])
                    ->value('id');

                $categoryIds = $this->syncCategories($toolId, $tool['items']);

                foreach ($tool['items'] as $itemIndex => $item) {
                    DB::table('evaluation_items')->updateOrInsert(
                        ['slug' => $item['slug']],
                        [
                            'tool_id' => $toolId,
                            'category_id' => $categoryIds[$item['category']],
                            'number' => $item['number'],
                            'title' => $item['title'],
                            'is_advanced' => $item['isAdvanced'],
                            'sort_order' => $itemIndex + 1,
                        ],
                    );
                }
            }
        });
    }

    /**
     * @return array<int, array{
     *     slug: string,
     *     label: string,
     *     items: array<int, array{
     *         slug: string,
     *         number: string,
     *         isAdvanced: bool,
     *         category: string,
     *         title: string
     *     }>
     * }>
     */
    private function loadTools(): array
    {
        $path = database_path('data/evaluation_items.json');

        if (! is_file($path)) {
            throw new RuntimeException("Tool seed data not found at [{$path}].");
        }

        $tools = json_decode((string) file_get_contents($path), true);

        if (! is_array($tools)) {
            throw new RuntimeException("Tool seed data at [{$path}] is not valid JSON.");
        }

        return $tools;
    }

    /**
     * @param  array<int, array{category: string}>  $items
     * @return array<string, int>
     */
    private function syncCategories(int $toolId, array $items): array
    {
        $categoryIds = [];
        $sortOrder = 1;

        foreach ($items as $item) {
            $category = $item['category'];

            if (array_key_exists($category, $categoryIds)) {
                continue;
            }

            DB::table('tool_categories')->updateOrInsert(
                [
                    'tool_id' => $toolId,
                    'name' => $category,
                ],
                ['sort_order' => $sortOrder],
            );

            $categoryIds[$category] = (int) DB::table('tool_categories')
                ->where('tool_id', $toolId)
                ->where('name', $category)
                ->value('id');

            $sortOrder++;
        }

        return $categoryIds;
    }
}
