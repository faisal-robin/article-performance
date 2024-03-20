<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ArticlesExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data)->map(function ($item) {
            return [
                'MPN' => $item['MPN'],
                'SKU' => $item['SKU'],
                'gtin' => $item['gtin'],
                'Product name' =>$item['name'],
                'Units sold' => $item['sales'],
                'Units returned' =>  $item['returns'],
                'Return rate' => $item['return_rate'],
                'Revenue after returns' => $item['turnover'],
            ];

        });
    }

    public function headings(): array
    {
        return [
            'MPN',
            'SKU',
            'gtin',
            'Product name',
            'Units sold',
            'Units returned',
            'Return rate',
            'Revenue after returns',
        ];
    }
}


