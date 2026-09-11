<?php

namespace App\Services;

use App\Models\Box;
use App\Models\Product;

/**
 * Формирует человекочитаемые объяснения для unpacked_items.
 * Не хранится в БД — генерируется на лету.
 */
class ReasonTranslator
{
    /**
     * @param Box[] $boxes
     * @return array{message: string, context: array}
     */
    public function translate(string $reason, Product $product, array $boxes = []): array
    {
        return match ($reason) {
            'no_compatible_container_dimensions' => $this->noCompatibleDimensions($product, $boxes),
            'insufficient_boxes'                 => $this->insufficientBoxes($product),
            'no_space_in_any_container'          => $this->noSpace($product),
            'weight_limit_exceeded'              => $this->weightExceeded($product),
            'insufficient_support'               => $this->insufficientSupport($product),
            default                              => [
                'message' => $reason,
                'context' => ['reason_code' => $reason],
            ],
        };
    }

    private function noCompatibleDimensions(Product $product, array $boxes): array
    {
        $biggestBox = collect($boxes)->sortByDesc('biggest_side')->first();

        $message = sprintf(
            '%s (%s) %d×%d×%d мм не влезает ни в одну из доступных коробок.',
            $product->name,
            $product->sku,
            $product->length,
            $product->width,
            $product->height,
        );

        $context = [
            'product' => [
                'sku'    => $product->sku,
                'name'   => $product->name,
                'length' => $product->length,
                'width'  => $product->width,
                'height' => $product->height,
            ],
        ];

        if ($biggestBox) {
            $message .= sprintf(
                ' Самая большая коробка: %s (%d×%d×%d мм).',
                $biggestBox->name,
                $biggestBox->inner_length,
                $biggestBox->inner_width,
                $biggestBox->inner_height,
            );

            $context['biggest_box'] = [
                'name'         => $biggestBox->name,
                'inner_length' => $biggestBox->inner_length,
                'inner_width'  => $biggestBox->inner_width,
                'inner_height' => $biggestBox->inner_height,
            ];
        }

        return ['message' => $message, 'context' => $context];
    }

    private function insufficientBoxes(Product $product): array
    {
        $message = sprintf(
            '%s (%s): доступных коробок нужного типа не хватило для размещения всех единиц товара.',
            $product->name,
            $product->sku,
        );

        return [
            'message' => $message,
            'context' => [
                'product' => ['sku' => $product->sku, 'name' => $product->name],
            ],
        ];
    }

    private function noSpace(Product $product): array
    {
        $message = sprintf(
            '%s (%s): не нашлось свободного места ни в одной из доступных коробок после размещения остальных товаров.',
            $product->name,
            $product->sku,
        );

        return [
            'message' => $message,
            'context' => [
                'product' => ['sku' => $product->sku, 'name' => $product->name],
            ],
        ];
    }

    private function weightExceeded(Product $product): array
    {
        $message = sprintf(
            '%s (%s) весом %d г: превышен максимально допустимый вес коробки.',
            $product->name,
            $product->sku,
            $product->weight,
        );

        return [
            'message' => $message,
            'context' => [
                'product' => [
                    'sku'    => $product->sku,
                    'name'   => $product->name,
                    'weight' => $product->weight,
                ],
            ],
        ];
    }

    private function insufficientSupport(Product $product): array
    {
        $message = sprintf(
            '%s (%s): не удалось обеспечить устойчивую опору при укладке — минимальная доля опоры не соблюдена.',
            $product->name,
            $product->sku,
        );

        return [
            'message' => $message,
            'context' => [
                'product' => ['sku' => $product->sku, 'name' => $product->name],
            ],
        ];
    }
}
