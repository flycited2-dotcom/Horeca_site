<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Products of the admin table as an XLSX file (TZ §12), written row by row by OpenSpout,
 * so that the whole catalog fits into memory easily.
 */
final class ExportProductsXlsx
{
    /**
     * @param  list<int>  $productIds
     */
    public function handle(array $productIds): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'products-');
        $writer = new Writer;
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues($this->headings(), (new Style)->setFontBold()));

        foreach (array_chunk($productIds, 500) as $chunk) {
            $products = Product::withTrashed()
                ->with(['brand:id,name', 'category:id,name'])
                ->whereKey($chunk)
                ->orderBy('name')
                ->get();

            foreach ($products as $product) {
                $writer->addRow(Row::fromValues($this->row($product)));
            }
        }

        $writer->close();

        return response()
            ->download($path, 'tovary-'.now()->format('Y-m-d_H-i').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * @return list<string>
     */
    private function headings(): array
    {
        return [
            __('admin.product.sku'),
            __('admin.product.supplier_code'),
            __('admin.product.name'),
            __('admin.product.brand'),
            __('admin.product.category'),
            __('admin.product.unit'),
            __('admin.product.rrp_price'),
            __('admin.product.retail_price'),
            __('admin.product.availability'),
            __('admin.product.is_visible'),
        ];
    }

    /**
     * Prices go out as numbers, so the spreadsheet can sum them. The division is for display
     * only: nothing is calculated with the result, and a double holds kopecks exactly enough.
     *
     * @return list<string|float|null>
     */
    private function row(Product $product): array
    {
        return [
            $product->sku,
            $product->supplier_code,
            $product->name,
            $product->brand?->name,
            $product->category?->name,
            $product->unit,
            $product->rrp_price === null ? null : $product->rrp_price->kopecks / 100,
            $product->retail_price === null ? __('admin.product.price_on_request') : $product->retail_price->kopecks / 100,
            $product->availability->getLabel(),
            $product->is_visible ? __('admin.yes') : __('admin.no'),
        ];
    }
}
