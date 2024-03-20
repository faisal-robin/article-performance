<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Queue\ShouldQueue;


class ExportArticlesPerfomanceData implements FromView, ShouldQueue
{
    use Exportable;

    protected $articleFilterData;

    public function __construct($articleFilterData)
    {
        $this->articleFilterData = $articleFilterData;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        return view('backend.pages.articlePerformance.exportArticlesPerformanceData', ['articleFilterData' => $this->articleFilterData]);
    }
}
