<?php

namespace App\Services;

use App\Models\Box;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Packvium\Config\{PackingConfig, SolverProfile};
use Packvium\Domain\{Container, Dimensions, Item};
use Packvium\Packer;

class PackviumService
{
    private const TICKS_PER_MM = 16000;

    /** Конфигурации для сбора альтернатив */
    private const ALTERNATIVE_CONFIGS = [
        'balanced'    => ['profile' => SolverProfile::Balanced, 'objective' => 'default'],
        'max_fill'    => ['profile' => SolverProfile::Balanced, 'objective' => 'maximum_value'],
        'open_height' => ['profile' => SolverProfile::Balanced, 'objective' => 'open_dimension_height'],
        'quality'     => ['profile' => SolverProfile::Quality,  'objective' => 'default'],
    ];

    private array $productCache    = [];
    private array $dimensionsCache = [];

    public function calculate(Order $order): array
    {
        $order->loadMissing('items.product');
        $boxes = Box::all();

        // ─── 1. Отсеиваем заведомо невпихуемые ────────────────────────────
        $fitItems    = [];
        $preUnpacked = [];
        $skippedSkus = [];

        foreach ($order->items as $orderItem) {
            $p = $orderItem->product;

            if (! $this->fitsInAnyBox($p, $boxes)) {
                $skippedSkus[] = $p->sku;
                $preUnpacked[] = [
                    'item_id' => $p->sku,
                    'reason'  => 'no_compatible_container_dimensions',
                ];
                continue;
            }

            $fitItems[] = Item::create(
                $p->sku,
                Dimensions::mm(
                    (string) $p->length,
                    (string) $p->width,
                    (string) $p->height,
                ),
                quantity: $orderItem->quantity,
                weight: $p->weight,
            );
        }

        // ─── 2. Контейнеры ────────────────────────────────────────────────
        $containers = [];
        foreach ($boxes as $b) {
            $containers[] = Container::create(
                'BOX-' . $b->id,
                Dimensions::mm(
                    (string) $b->inner_length,
                    (string) $b->inner_width,
                    (string) $b->inner_height,
                ),
                maxPayload: $b->max_weight,
                quantity:   $b->available_quantity,
            );
        }

        // ─── 3. Упаковка + сбор альтернатив ───────────────────────────────
        [$result, $alternativesRaw] = $this->packWithAlternatives($fitItems, $containers);

        // ─── 4. Пост-компенсация available_quantity ───────────────────────
        [$keptContainers, $overflowUnpacked] = $this->enforceAvailableQuantity(
            $result->containers,
            $boxes,
        );

        // ─── 5. Всё, что вернул Packvium ──────────────────────────────────
        $returned = [];
        foreach ($keptContainers as $c) {
            foreach ($c->placements as $p) {
                $returned[] = $p->instance->id();
            }
        }

        $packviumUnpacked = [];
        foreach ($result->unpacked_items ?? [] as $u) {
            $returned[]         = $u->item_id;
            $packviumUnpacked[] = ['item_id' => $u->item_id, 'reason' => $u->reason];
        }

        // ─── 6. Компенсация потерянных ────────────────────────────────────
        $expectedInPackvium = [];
        foreach ($order->items as $orderItem) {
            $p = $orderItem->product;
            if (in_array($p->sku, $skippedSkus, true)) {
                continue;
            }
            for ($i = 1; $i <= $orderItem->quantity; $i++) {
                $expectedInPackvium[] = $p->sku . '#' . $i;
            }
        }

        $overflowIds = array_column($overflowUnpacked, 'item_id');
        $lost        = array_values(array_diff($expectedInPackvium, $returned, $overflowIds));

        $lostUnpacked = array_map(
            fn ($id) => ['item_id' => $id, 'reason' => 'no_space_in_any_container'],
            $lost,
        );

        // ─── 7. Пост-проверка веса ────────────────────────────────────────
        $weightOverflow = $this->checkWeightOverflow($keptContainers, $boxes);

        // ─── 8. Итог ──────────────────────────────────────────────────────
        $unpacked = array_merge(
            $preUnpacked,
            $packviumUnpacked,
            $overflowUnpacked,
            $lostUnpacked,
            $weightOverflow,
        );

        // ─── 9. Контракт CalculateResponse + alternatives ────────────────
        return [
            'status' => $result->status->value,
            'containers' => array_map(function ($c) {
                $containerId = $c->container->id;
                $placements  = $this->sortPlacements($c->placements);

                $enriched = array_map(function ($p) {
                    return array_merge($p, [
                        'support_ratio'     => $p['support_ratio'] ?? 1.0,
                        'supported_by'      => $p['supported_by'] ?? [],
                        'stability_warning' => $p['stability_warning'] ?? null,
                    ]);
                }, $placements);

                return [
                    'container_id' => $containerId,
                    'placements'   => $enriched,
                    'layers'       => $this->buildLayers($enriched),
                    'steps'        => $this->buildSteps($enriched),
                ];
            }, $keptContainers),
            'unpacked_items' => $unpacked,
            'score'          => $result->score,
            'alternatives'   => $alternativesRaw,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Сбор альтернатив через несколько objective
    // ─────────────────────────────────────────────────────────────────────

    private function packWithAlternatives(array $fitItems, array $containers): array
    {
        if (empty($fitItems)) {
            $result = (new Packer(PackingConfig::balanced()))->pack([], $containers);
            return [$result, []];
        }

        $seen    = [];
        $raw     = [];
        $primary = null;

        foreach (self::ALTERNATIVE_CONFIGS as $name => $spec) {
            $config = new PackingConfig(
                profile: $spec['profile'],
                objective: $spec['objective'],
                topK: 1,
                timeLimitMs: $spec['profile'] === SolverProfile::Quality ? 5000 : 1000,
            );

            $packer = new Packer($config);
            $result = $packer->pack($fitItems, $containers);

            $containerIds = array_map(fn ($c) => $c->container->id, $result->containers);
            $hash = md5(json_encode(array_map(
                fn ($c) => $c->container->id . ':' . count($c->placements),
                $result->containers,
            )));

            Log::info("packWithAlternatives [{$name}]", [
                'objective'     => $spec['objective'],
                'profile'       => $spec['profile']->value,
                'containers'    => $containerIds,
                'containers_n'  => count($result->containers),
                'hash'          => $hash,
                'score'         => $result->score,
                'is_duplicate'  => isset($seen[$hash]),
                'is_primary'    => $primary === null,
            ]);

            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;

            if ($primary === null) {
                $primary = $result;
                continue;
            }

            $raw[] = [
                'objective' => $name,
                'result'    => $result,
            ];
        }

        if ($primary === null) {
            Log::warning('packWithAlternatives: primary is null — fallback to balanced');
            $primary = (new Packer(PackingConfig::balanced()))->pack($fitItems, $containers);
        }

        $summaries = [];
        foreach ($raw as $alt) {
            $summaries[] = $this->summarizeOne($alt['result'], $alt['objective']);
        }

        Log::info('packWithAlternatives: результат', [
            'alternatives_count' => count($summaries),
            'primary_containers' => count($primary->containers),
        ]);

        return [$primary, $summaries];
    }

    private function summarizeOne($result, string $objective): array
    {
        $containers = $result->containers ?? [];

        $sumFill        = 0.0;
        $sumItemsVolume = 0;
        $sumBoxesVolume = 0;
        $totalWeight    = 0;
        $boxesSummary   = [];

        foreach ($containers as $c) {
            $boxId = null;
            if (preg_match('/^BOX-(\d+)/', $c->container->id, $m)) {
                $boxId = (int) $m[1];
            }
            $box = $boxId ? Box::find($boxId) : null;

            $boxVolume   = $box?->volume ?? 0;
            $itemsVolume = 0;
            $boxWeight   = 0;

            foreach ($c->placements as $p) {
                $sku     = preg_replace('/#\d+$/', '', $p->instance->id());
                $product = $this->productBySku($sku);
                if (! $product) {
                    continue;
                }
                $itemsVolume += $product->length * $product->width * $product->height;
                $boxWeight   += $product->weight;
            }

            $fillRatio = $boxVolume > 0 ? $itemsVolume / $boxVolume : 0.0;

            $sumFill        += $fillRatio;
            $sumItemsVolume += $itemsVolume;
            $sumBoxesVolume += $boxVolume;
            $totalWeight    += $boxWeight;

            $boxesSummary[] = [
                'container_id' => $c->container->id,
                'box_id'       => $boxId,
                'box_name'     => $box?->name,
                'fill_ratio'   => round($fillRatio, 4),
                'total_weight' => $boxWeight,
                'items_count'  => count($c->placements),
            ];
        }

        $boxesCount = count($containers);

        return [
            'objective'           => $objective,
            'boxes_count'         => $boxesCount,
            'average_fill_ratio'  => $boxesCount > 0 ? round($sumFill / $boxesCount, 4) : 0.0,
            'weighted_fill_ratio' => $sumBoxesVolume > 0 ? round($sumItemsVolume / $sumBoxesVolume, 4) : 0.0,
            'total_weight'        => $totalWeight,
            'score'               => $result->score ?? [],
            'boxes_summary'       => $boxesSummary,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Пост-компенсация available_quantity
    // ─────────────────────────────────────────────────────────────────────

    private function enforceAvailableQuantity($containers, Collection $boxes): array
    {
        $keptCount = [];
        $kept      = [];
        $overflow  = [];

        foreach ($containers as $c) {
            if (! preg_match('/^BOX-(\d+)/', $c->container->id, $m)) {
                $kept[] = $c;
                continue;
            }

            $boxId     = (int) $m[1];
            $available = $boxes->firstWhere('id', $boxId)?->available_quantity ?? 0;
            $keptCount[$boxId] = ($keptCount[$boxId] ?? 0) + 1;

            if ($keptCount[$boxId] <= $available) {
                $kept[] = $c;
            } else {
                foreach ($c->placements as $p) {
                    $overflow[] = [
                        'item_id' => $p->instance->id(),
                        'reason'  => 'insufficient_boxes',
                    ];
                }
            }
        }

        return [$kept, $overflow];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Пост-проверка веса
    // ─────────────────────────────────────────────────────────────────────

    private function checkWeightOverflow($containers, Collection $boxes): array
    {
        $overflow = [];
        $seen     = [];

        foreach ($containers as $c) {
            if (! preg_match('/^BOX-(\d+)/', $c->container->id, $m)) {
                continue;
            }
            $boxId = (int) $m[1];
            $box   = $boxes->firstWhere('id', $boxId);
            if (! $box) {
                continue;
            }

            $totalWeight = 0;
            foreach ($c->placements as $p) {
                $sku     = preg_replace('/#\d+$/', '', $p->instance->id());
                $product = $this->productBySku($sku);
                if ($product) {
                    $totalWeight += $product->weight;
                }
            }

            if ($totalWeight > $box->max_weight) {
                foreach ($c->placements as $p) {
                    $id = $p->instance->id();
                    if (isset($seen[$id])) {
                        continue;
                    }
                    $seen[$id] = true;
                    $overflow[] = [
                        'item_id' => $id,
                        'reason'  => 'weight_limit_exceeded',
                    ];
                }
            }
        }

        return $overflow;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Сортировка placements
    // ─────────────────────────────────────────────────────────────────────

    private function sortPlacements($placements): array
    {
        $items = [];
        foreach ($placements as $p) {
            $sku     = preg_replace('/#\d+$/', '', $p->instance->id());
            $product = $this->productBySku($sku);
            $volume  = $product
                ? ($product->length * $product->width * $product->height)
                : 0;

            $items[] = [
                'placement' => $p,
                'volume'    => $volume,
                'x'         => $p->position->x,
                'y'         => $p->position->y,
                'z'         => $p->position->z,
            ];
        }

        usort($items, function ($a, $b) {
            if ($a['z'] !== $b['z']) {
                return $a['z'] <=> $b['z'];
            }
            if ($a['volume'] !== $b['volume']) {
                return $b['volume'] <=> $a['volume'];
            }
            if ($a['y'] !== $b['y']) {
                return $a['y'] <=> $b['y'];
            }
            return $a['x'] <=> $b['x'];
        });

        return array_map(fn ($it) => [
            'item_id'  => $it['placement']->instance->id(),
            'rotation' => $it['placement']->rotation->value,
            'x'        => intdiv($it['placement']->position->x, self::TICKS_PER_MM),
            'y'        => intdiv($it['placement']->position->y, self::TICKS_PER_MM),
            'z'        => intdiv($it['placement']->position->z, self::TICKS_PER_MM),
        ], $items);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Слои
    // ─────────────────────────────────────────────────────────────────────

    private function buildLayers(array $placements): array
    {
        $byZ = [];
        foreach ($placements as $p) {
            $byZ[$p['z']][] = $p;
        }
        ksort($byZ);

        $layers = [];
        $index  = 0;
        foreach ($byZ as $z => $items) {
            $index++;
            $layers[] = [
                'index' => $index,
                'z'     => $z,
                'items' => array_values($items),
            ];
        }
        return $layers;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Шаги
    // ─────────────────────────────────────────────────────────────────────

    private function buildSteps(array $placements): array
    {
        $steps   = [];
        $stepNum = 0;

        foreach ($placements as $p) {
            $stepNum++;
            $sku     = preg_replace('/#\d+$/', '', $p['item_id']);
            $product = $this->productBySku($sku);

            $hint = $this->relationHint($p, $placements);

            $steps[] = [
                'step'              => $stepNum,
                'sku'               => $sku,
                'product_name'      => $product?->name ?? $sku,
                'orientation'       => $p['rotation'],
                'coordinates'       => ['x' => $p['x'], 'y' => $p['y'], 'z' => $p['z']],
                'position_hint'     => $hint['position_hint'],
                'supported_by'      => $hint['supported_by'],
                'neighbors'         => $hint['neighbors'],
                'on_floor'          => $p['z'] === 0,
                'layer_z'           => $p['z'],
                'support_ratio'     => $p['support_ratio'] ?? 1.0,
                'stability_warning' => $p['stability_warning'] ?? null,
                'text'              => $this->stepText($product?->name ?? $sku, $sku, $p, $hint),
            ];
        }

        return $steps;
    }

    private function relationHint(array $p, array $placements): array
    {
        $supportedBy = [];
        $neighbors   = [];

        foreach ($placements as $other) {
            if ($other['item_id'] === $p['item_id']) {
                continue;
            }

            $oDims = $this->dimsBySku(preg_replace('/#\d+$/', '', $other['item_id']));
            $pDims = $this->dimsBySku(preg_replace('/#\d+$/', '', $p['item_id']));
            if (! $oDims || ! $pDims) {
                continue;
            }

            [$oL, $oW, $oH] = $oDims;
            [$pL, $pW, $pH] = $pDims;

            if ($other['z'] + $oH === $p['z']
                && $this->overlaps($p['x'], $p['x'] + $pL, $other['x'], $other['x'] + $oL)
                && $this->overlaps($p['y'], $p['y'] + $pW, $other['y'], $other['y'] + $oW)
            ) {
                $product = $this->productBySku(preg_replace('/#\d+$/', '', $other['item_id']));
                $supportedBy[] = [
                    'sku'          => preg_replace('/#\d+$/', '', $other['item_id']),
                    'product_name' => $product?->name,
                    'relation'     => 'под ним',
                ];
                continue;
            }

            if ($this->overlaps($p['z'], $p['z'] + $pH, $other['z'], $other['z'] + $oH)) {
                $product = $this->productBySku(preg_replace('/#\d+$/', '', $other['item_id']));

                if ($p['x'] + $pL === $other['x']) {
                    $neighbors[] = ['sku' => preg_replace('/#\d+$/', '', $other['item_id']), 'product_name' => $product?->name, 'relation' => 'справа'];
                } elseif ($other['x'] + $oL === $p['x']) {
                    $neighbors[] = ['sku' => preg_replace('/#\d+$/', '', $other['item_id']), 'product_name' => $product?->name, 'relation' => 'слева'];
                } elseif ($p['y'] + $pW === $other['y']) {
                    $neighbors[] = ['sku' => preg_replace('/#\d+$/', '', $other['item_id']), 'product_name' => $product?->name, 'relation' => 'за ним'];
                } elseif ($other['y'] + $oW === $p['y']) {
                    $neighbors[] = ['sku' => preg_replace('/#\d+$/', '', $other['item_id']), 'product_name' => $product?->name, 'relation' => 'перед ним'];
                }
            }
        }

        $parts = [];
        if ($p['z'] === 0) {
            $parts[] = 'на дне коробки';
        } elseif (! empty($supportedBy)) {
            $names = array_map(fn ($s) => $s['product_name'] ?? $s['sku'], $supportedBy);
            $parts[] = 'над ' . implode(' и ', $names);
        }

        if (! empty($neighbors)) {
            $neighborText = [];
            foreach ($neighbors as $n) {
                $neighborText[] = ($n['product_name'] ?? $n['sku']) . ' (' . $n['relation'] . ')';
            }
            $parts[] = 'рядом: ' . implode(', ', $neighborText);
        }

        $positionHint = empty($parts) ? 'в коробке' : implode(', ', $parts);

        return [
            'position_hint' => $positionHint,
            'supported_by'  => $supportedBy,
            'neighbors'     => $neighbors,
        ];
    }

    private function stepText(?string $name, string $sku, array $p, array $hint): string
    {
        $text = "Положите {$name} (SKU: {$sku})";
        if (! empty($hint['position_hint'])) {
            $text .= ' — ' . $hint['position_hint'];
        }
        $text .= '. Ориентация: ' . $p['rotation'] . '.';
        $text .= " Координаты: X={$p['x']}, Y={$p['y']}, Z={$p['z']} мм.";
        if (! empty($p['stability_warning'])) {
            $text .= ' ⚠ ' . $p['stability_warning'] . '.';
        }
        return $text;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Утилиты
    // ─────────────────────────────────────────────────────────────────────

    private function overlaps(int $a1, int $a2, int $b1, int $b2): bool
    {
        return max($a1, $b1) < min($a2, $b2);
    }

    private function productBySku(string $sku): ?Product
    {
        if (! isset($this->productCache[$sku])) {
            $this->productCache[$sku] = Product::where('sku', $sku)->first();
        }
        return $this->productCache[$sku];
    }

    private function dimsBySku(string $sku): ?array
    {
        if (! isset($this->dimensionsCache[$sku])) {
            $p = $this->productBySku($sku);
            $this->dimensionsCache[$sku] = $p
                ? [$p->length, $p->width, $p->height]
                : null;
        }
        return $this->dimensionsCache[$sku];
    }

    private function fitsInAnyBox(Product $p, Collection $boxes): bool
    {
        $item = [$p->length, $p->width, $p->height];
        if ($p->can_rotate) {
            sort($item);
        }

        foreach ($boxes as $b) {
            $box = [$b->inner_length, $b->inner_width, $b->inner_height];
            sort($box);

            if ($item[0] <= $box[0] && $item[1] <= $box[1] && $item[2] <= $box[2]) {
                return true;
            }
        }

        return false;
    }
}
