<?php

namespace App\Http\Controllers;

use App\Exports\ArticlesExport;
use App\Models\Article;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Response;

class ArticlesController extends Controller
{
    function index(Request $request){
        $startDate = '2016-01-16 19:38:25';
        $endDate = '2016-10-16 19:38:25';
        $hersteller = '';
        $verkaufsplattform = '';
        $country = '';
        $searchTerm = '';

        $parentArticles = Article::with(['children' => function ($query) use ($startDate,$endDate,$hersteller,$verkaufsplattform,$country,$searchTerm) {
            $query->leftJoin('transactions', function($join) {
                $join->on('articles.kArtikel', '=', 'transactions.kArtikel'); // Assuming there's a deleted_at column to check soft deletes
            })
                ->select(
                    'articles.kArtikel as kArtikel',
                    'articles.kVaterArtikel as kVaterArtikel',
                    'articles.han as MPN',
                    'articles.sku as SKU',
                    'articles.gtin as gtin',
                    'articles.name as name',
                    DB::raw('SUM(CASE WHEN transactions.transaktion = "Sale" THEN transactions.menge ELSE 0 END) AS sales'),
                    DB::raw('SUM(CASE WHEN transactions.transaktion = "Retoure" THEN -transactions.menge ELSE 0 END) AS returns'),
                    DB::raw('CASE
                   WHEN SUM(CASE WHEN transactions.transaktion = "Sale" THEN transactions.menge ELSE 0 END) = 0
                       THEN SUM(CASE WHEN transactions.transaktion = "Retoure" THEN -transactions.menge ELSE 0 END) * 100
                   WHEN SUM(CASE WHEN transactions.transaktion = "Sale" THEN transactions.menge ELSE 0 END) > 0
                       THEN SUM(CASE WHEN transactions.transaktion = "Retoure" THEN -transactions.menge ELSE 0 END) /
                           SUM(CASE WHEN transactions.transaktion = "Sale" THEN transactions.menge ELSE 0 END) * 100
                   ELSE 0
               END AS return_rate'),
                    DB::raw('SUM(CASE WHEN transactions.transaktion = "Sale" THEN transactions.menge * transactions.vkBrutto
                     WHEN transactions.transaktion = "Retoure" THEN -transactions.menge * transactions.vkBrutto
                     ELSE 0 END) AS turnover')
                )
                ->groupBy('articles.kArtikel', 'articles.kVaterArtikel', 'articles.han', 'articles.sku', 'articles.gtin', 'articles.name')
//                ->whereBetween('transactions.datum', [$startDate, $endDate])
//                ->withFilters2($hersteller, $verkaufsplattform, $country)
                ->when($searchTerm, function ($query, $searchTerm) {
                    return $query->where(function ($query) use ($searchTerm) {
                        $query->where('articles.han', 'like', '%' . $searchTerm . '%')
                            ->orWhere('articles.vaterArtikelHan', 'like', '%' . $searchTerm . '%')
                            ->orWhere('articles.sku', 'like', '%' . $searchTerm . '%')
                            ->orWhere('articles.name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('articles.gtin', 'like', '%' . $searchTerm . '%');
                    });
                });
        }])
            ->leftJoin('transactions', function($join) {
                $join->on('articles.kArtikel', '=', 'transactions.kArtikel');
            })
            ->select(
                'articles.kArtikel as kArtikel',
                'articles.kVaterArtikel as kVaterArtikel',
                'articles.han as MPN',
                'articles.sku as SKU',
                'articles.gtin as gtin',
                'articles.name as name',
            )
            ->groupBy('articles.kArtikel', 'articles.kVaterArtikel', 'articles.han', 'articles.sku', 'articles.gtin', 'articles.name')
            ->whereNull('articles.kVaterArtikel')
//            ->whereBetween('transactions.datum', [$startDate, $endDate])
//            ->withFilters2($hersteller, $verkaufsplattform, $country)
            ->when($searchTerm, function ($parentArticles, $searchTerm) {
                return $parentArticles->where(function ($parentArticles) use ($searchTerm) {
                    $parentArticles->where('articles.han', 'like', '%' . $searchTerm . '%')
                        ->orWhere('articles.vaterArtikelHan', 'like', '%' . $searchTerm . '%')
                        ->orWhere('articles.sku', 'like', '%' . $searchTerm . '%')
                        ->orWhere('articles.name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('articles.gtin', 'like', '%' . $searchTerm . '%');
                });
            })
            ->limit(100)
            ->get();

//        dd($parentArticles);

        $articlesData = $parentArticles->map(function ($parent) {
            // Calculate total values from child articles
            $totalSales = $parent->children->sum('sales');
            $totalReturns = $parent->children->sum('returns');
            $totalTurnover = $parent->children->sum('turnover');

            // Calculate return rate
            $returnRate = $totalSales != 0 ? ($totalReturns / $totalSales) * 100 : 0;

            $displayedReturnRateParent = $returnRate > 100 || ($totalSales == 0 && $totalReturns > 0) ? '> 100 %' : number_format($returnRate, 2, ',', '.');

            // Prepare parent data
            $parentData = [
                'kArtikel' => $parent->kArtikel,
                'kVaterArtikel' => $parent->kVaterArtikel,
                'MPN' => $parent->MPN,
                'SKU' => $parent->SKU,
                'gtin' => $parent->gtin,
                'name' => $parent->name,
                'sales' => $totalSales,
                'returns' => $totalReturns,
                'return_rate' => $displayedReturnRateParent,
                'life_time_return_rate' => '',
                'turnover' => $totalTurnover,
                'is_child' => false,
            ];

            // Prepare child data
            $childrenData = $parent->children->map(function ($child) {
                $displayedReturnRateChild = $child->return_rate > 100 || ($child->sales == 0 && $child->returns > 0) ? '> 100 %' : number_format($child->return_rate, 2, ',', '.');
                return [
                    'kArtikel' => $child->kArtikel,
                    'kVaterArtikel' => $child->kVaterArtikel,
                    'MPN' => $child->MPN,
                    'SKU' => $child->SKU,
                    'gtin' => $child->gtin,
                    'name' => $child->name,
                    'sales' => $child->sales,
                    'returns' => $child->returns,
                    'return_rate' => $displayedReturnRateChild,
                    'life_time_return_rate' => '',
                    'turnover' => $child->turnover,
                    'is_child' => true,
                ];
            });

            // Merge parent and child data
            return array_merge([$parentData], $childrenData->toArray());
        })->flatten(1);

//        dd($articlesData);

        $export = new ArticlesExport($articlesData);
        return Excel::download($export, 'articles.xlsx');

//        return Response::stream(function () use ($export) {
//            $export->store('articles.xlsx', 'php://output');
//        }, 200, [
//            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
//            'Content-Disposition' => 'attachment; filename="articles.xlsx"',
//        ]);

    }
}
