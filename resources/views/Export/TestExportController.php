<?php

namespace App\Http\Controllers\ArticlePerformance;

use Carbon\Carbon;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Exports\ExportArticlesPerfomanceData;


class TestExportDataController extends Controller
{
    
    public function __invoke(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');

        $hersteller = $request->hersteller;
        $verkaufsplattform = $request->verkaufsplattform;
        $country = $request->country;
        $searchTerm = $request->searchTerm;
        $inputDatum = $request->daterange;
        $exportType = $request->exportType;
        $user = Auth::user();

        if (($inputDatum)) {
            $daterangeExplode = explode(' - ', $inputDatum);
            $startDate = $daterangeExplode[0];
            $endDate = $daterangeExplode[1];

            $startDate = Carbon::createFromFormat('d.m.Y', $startDate)->startOfDay();
            $endDate = Carbon::createFromFormat('d.m.Y', $endDate)->endOfDay();
        } else {
            $startDate = Carbon::now()->subDays(30)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
        }


        $articleFilterData = $this->getArticleFilterData($user, $hersteller, $verkaufsplattform, $country, $searchTerm, $startDate, $endDate);

        if ($exportType === 'xlsx') {
            $fileType = 'xlsx';
        } else {
            $fileType = 'csv';
        }

        $articleFilterData = $articleFilterData->map(function ($item) {
            return $item->toArray();
        })->toArray();

        $fileName = 'articlesPerformanceData_' . rand() . '.' . $fileType;

        $storagePath = 'articlesPerformance/' . $fileName;

        return Excel::download(new ExportArticlesPerfomanceData($articleFilterData), $fileName);
    }


    private function getArticleFilterData($user, $hersteller, $verkaufsplattform, $country, $searchTerm, $startDate, $endDate)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $query = Transaction::query();

        $query->select(
            'articles.kVaterArtikel as kVaterArtikel',
            'articles.han as MPN',
            'articles.sku as SKU',
            'articles.gtin as gtin',
            'articles.name as name',
            DB::raw('SUM(IF(transaktion = "Sale",  menge, 0)) AS sales'),
            DB::raw('SUM(IF(transaktion = "Retoure",  menge*-1, 0)) AS returns'),
            DB::raw('CASE
                       WHEN SUM(IF(transaktion = "Sale",  menge, 0)) = 0
                           THEN SUM(IF(transaktion = "Retoure",  menge*-1, 0)) * 100
                       WHEN SUM(IF(transaktion = "Sale",  menge, 0)) > 0
                           THEN SUM(IF(transaktion = "Retoure",  menge*-1, 0)) /
                               SUM(IF(transaktion = "Sale",  menge, 0)) *100 END
                       AS return_rate'),
            DB::raw('SUM(IF(transaktion = "Sale",  menge*vkBrutto, 0)) +
               SUM(IF(transaktion = "Retoure",  menge*vkBrutto, 0)) AS turnover')
        )
            ->join('articles', 'transactions.kArtikel', '=', 'articles.kArtikel')
            ->where('articles.kVaterArtikel', NULL)
            ->where(function ($query) use ($searchTerm) {
                $query->where('articles.han', 'like', '%' . $searchTerm . '%')
                    ->orWhere('articles.vaterArtikelHan', 'like', '%' . $searchTerm . '%')
                    ->orWhere('articles.sku', 'like', '%' . $searchTerm . '%')
                    ->orWhere('articles.name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('articles.gtin', 'like', '%' . $searchTerm . '%');
            })
            ->whereBetween('datum', [$startDate, $endDate])
            ->withFilters2($hersteller, $verkaufsplattform, $country)
            ->groupBy('articles.han');

        $user->isClient() ?
            $articleFilterData =  $query->whereIn('articles.kLieferant',  explode(",", $user->LieferantID))
            :
            $articleFilterData = $query;

        $vaterartikel = Transaction::query();
        $vaterartikel
            ->select(
                'articles.kVaterArtikel as kVaterArtikel',
                'parent.han as MPN',
                'parent.sku as SKU',
                'parent.gtin as gtin',
                'parent.name as ProductName',
                DB::raw('SUM(IF(transaktion = "Sale",  menge, 0)) AS sales'),
                DB::raw('SUM(IF(transaktion = "Retoure",  menge*-1, 0)) AS returns'),
                DB::raw('CASE
               WHEN SUM(IF(transaktion = "Sale",  menge, 0)) = 0
                   THEN SUM(IF(transaktion = "Retoure",  menge*-1, 0)) * 100
               WHEN SUM(IF(transaktion = "Sale",  menge, 0)) > 0
                   THEN SUM(IF(transaktion = "Retoure",  menge*-1, 0)) /
                       SUM(IF(transaktion = "Sale",  menge, 0)) *100 END
               AS return_rate'),
                DB::raw('SUM(IF(transaktion = "Sale",  menge*vkBrutto, 0)) +
               SUM(IF(transaktion = "Retoure",  menge*vkBrutto, 0)) AS turnover')
            )
            ->join('articles', 'transactions.kArtikel', '=', 'articles.kArtikel')
            ->join('articles as parent', 'articles.kVaterartikel', '=', 'parent.kArtikel')
            ->whereIn('articles.kArtikel', function ($query) use ($searchTerm) {
                $query->select('kArtikel')
                ->from('articles')
                ->whereIn('kVaterArtikel', function ($subquery) use ($searchTerm) {
                    $subquery->select('kArtikel')
                    ->from('articles')
                    ->where('han', 'like', '%' . $searchTerm . '%')
                        ->orWhere('vaterArtikelHan', 'like', '%' . $searchTerm . '%')
                        ->orWhere('sku', 'like', '%' . $searchTerm . '%')
                        ->orWhere('name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('gtin', 'like', '%' . $searchTerm . '%');
                });
            })
            ->whereBetween('datum', [$startDate, $endDate])
            ->withFilters2($hersteller, $verkaufsplattform, $country)
            ->groupBy('articles.kVaterArtikel');

        $user->isClient() ?
            $vaterartikel =  $vaterartikel->whereIn('articles.kLieferant',  explode(",",  $user->LieferantID))
            :
            $vaterartikel = $vaterartikel;

        $kinderartikel = Transaction::query();
        $kinderartikel
            ->select(
                'articles.kVaterArtikel as kVaterArtikel',
                'parent.han as MPN',
                'parent.sku as SKU',
                'parent.gtin as gtin',
                'parent.name as ProductName',
                DB::raw('SUM(IF(transaktion = "Sale",  menge, 0)) AS sales'),
                DB::raw('SUM(IF(transaktion = "Retoure",  menge*-1, 0)) AS returns'),
                DB::raw('CASE
               WHEN SUM(IF(transaktion = "Sale",  menge, 0)) = 0
                   THEN SUM(IF(transaktion = "Retoure",  menge*-1, 0)) * 100
               WHEN SUM(IF(transaktion = "Sale",  menge, 0)) > 0
                   THEN SUM(IF(transaktion = "Retoure",  menge*-1, 0)) /
                       SUM(IF(transaktion = "Sale",  menge, 0)) *100 END
               AS return_rate'),
                DB::raw('SUM(IF(transaktion = "Sale",  menge*vkBrutto, 0)) +
               SUM(IF(transaktion = "Retoure",  menge*vkBrutto, 0)) AS turnover')
            )
            ->join('articles', 'transactions.kArtikel', '=', 'articles.kArtikel')
            ->join('articles as parent', 'articles.kVaterartikel', '=', 'parent.kArtikel')
            ->whereIn('articles.kVaterArtikel', function ($query) use ($searchTerm) {
                $query->select('kVaterArtikel')
                ->from('articles')
                ->where(function ($subquery) use ($searchTerm) {
                    $subquery->where('han', 'like', '%' . $searchTerm . '%')
                        ->orWhere('sku', 'like', '%' . $searchTerm . '%')
                        ->orWhere('name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('gtin', 'like', '%' . $searchTerm . '%');
                });
            })
            ->whereBetween('datum', [$startDate, $endDate])
            ->withFilters2($hersteller, $verkaufsplattform, $country)
            ->groupBy('articles.kVaterArtikel');


        $user->isClient() ?
            $kinderartikel =  $kinderartikel->whereIn('articles.kLieferant',  explode(",", $user->LieferantID))
            :
            $kinderartikel = $kinderartikel;

        $parentArticles = $articleFilterData->union($vaterartikel)->union($kinderartikel)->get();

        // Collect all unique kVaterArtikel values from the initial query
        $uniqueKVaterArtikel = $parentArticles->pluck('kVaterArtikel')->unique()->toArray();

        // Fetch lifetimedata for each unique kVaterArtikel value
        $lifetimeArticleData = Transaction::query()
            ->select(
                'articles.kVaterArtikel',
                DB::raw('SUM(IF(transaktion = "Sale",  menge, 0)) AS lifetime_sales'),
                DB::raw('SUM(IF(transaktion = "Retoure",  menge*-1, 0)) AS lifetime_returns'),
                DB::raw('
                       CASE
                           WHEN SUM(IF(transaktion = "Sale", menge, 0)) = 0
                               THEN SUM(IF(transaktion = "Retoure", menge * -1, 0)) * 100
                           WHEN SUM(IF(transaktion = "Sale", menge, 0)) > 0
                               THEN SUM(IF(transaktion = "Retoure", menge * -1, 0)) /
                                   SUM(IF(transaktion = "Sale", menge, 0)) * 100
                       END AS lifetime_return_rate')
            )
            ->join('articles', 'transactions.kArtikel', '=', 'articles.kArtikel')
            ->whereIn('articles.kVaterArtikel', $uniqueKVaterArtikel)
            ->withFilters2($hersteller, $verkaufsplattform, $country)
            ->groupBy('articles.kVaterArtikel')
            ->get();

        // Create a dictionary for easier lookup based on kVaterArtikel
        $lifetimeDataDictionary = $lifetimeArticleData->keyBy('kVaterArtikel');

        // Use map to transform each item in $parentArticles
        $parentArticles = $parentArticles->map(function ($item) use ($lifetimeDataDictionary) {
            // Get kVaterArtikel for the current item
            $kVaterArtikel = $item['kVaterArtikel'];

            // Check if there is corresponding lifetime data
            $lifetimeData = $lifetimeDataDictionary->get($kVaterArtikel, []);

            // Add lifetime_sales, lifetime_returns, and lifetime_return_rate to the item
            $item['lifetime_sales'] = $lifetimeData['lifetime_sales'] ?? 0;
            $item['lifetime_returns'] = $lifetimeData['lifetime_returns'] ?? 0;
            $item['lifetime_return_rate'] = $lifetimeData['lifetime_return_rate'] ?? 0;

            return $item;
        });

        $parentArticleKeys = $parentArticles->pluck('kVaterArtikel')->toArray();

        // Fetch all child articles for each parent article
        $childArticles = Transaction::query();
        $childArticles =  $childArticles->select(
            DB::raw('articles.kVaterArtikel As "kVaterArtikel"'),
            DB::raw('articles.han as MPN,  articles.sku as SKU, articles.name AS name, articles.gtin as gtin'),
            DB::raw('SUM(IF(transaktion = "Sale",  menge, 0)) AS child_sales'),
            DB::raw('
                           SUM(IF(transaktion = "Retoure",  menge*-1, 0)) AS child_returns,
                           CASE
                               WHEN SUM(IF(transaktion = "Sale", menge, 0)) = 0
                                   THEN SUM(IF(transaktion = "Retoure", menge * -1, 0)) * 100
                               WHEN SUM(IF(transaktion = "Sale", menge, 0)) > 0
                                   THEN SUM(IF(transaktion = "Retoure", menge * -1, 0)) / SUM(IF(transaktion = "Sale", menge, 0)) * 100
                               END AS "child_return_rate",
                           SUM(IF(transaktion = "Sale",  menge*vkBrutto, 0)) +
                           SUM(IF(transaktion = "Retoure",  menge*vkBrutto, 0))  AS child_turnover')
        )
            ->join('articles', 'transactions.kArtikel', '=', 'articles.kArtikel')
            ->whereIn('articles.kVaterArtikel', $parentArticleKeys)
            ->orderBy('articles.han')
            ->groupBy('articles.han');

        $childArticles = $childArticles
            ->withFilters2($hersteller, $verkaufsplattform, $country)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                return $query->whereBetween('datum', [$startDate, $endDate]);
            });

        $user->isClient() ?
            $childArticles =  $childArticles->whereIn('articles.kLieferant',  explode(",", $user->LieferantID))
            ->get() :
            $childArticles = $childArticles->get();


        $childLifetimeReturnRate = Transaction::query()
            ->select(
                DB::raw('articles.han as MPN'),
                DB::raw('
                           CASE
                               WHEN SUM(IF(transaktion = "Sale", menge, 0)) = 0
                                   THEN SUM(IF(transaktion = "Retoure", menge * -1, 0)) * 100
                               WHEN SUM(IF(transaktion = "Sale", menge, 0)) > 0
                                   THEN SUM(IF(transaktion = "Retoure", menge * -1, 0)) / SUM(IF(transaktion = "Sale", menge, 0)) * 100
                           END AS child_lifetime_return_rate')
            )
            ->join('articles', 'transactions.kArtikel', '=', 'articles.kArtikel')
            ->whereIn('articles.han', $childArticles->pluck('MPN')->unique())
            ->withFilters2($hersteller, $verkaufsplattform, $country)
            ->orderBy('articles.kVaterArtikel')
            ->groupBy('articles.han')
            ->get();

        $childArticles = $childArticles->map(function ($childArticle) use ($childLifetimeReturnRate) {
            $childLifetimeReturnRateItem = $childLifetimeReturnRate->where('MPN', $childArticle->MPN)->first();

            // Add the required fields from $childLifetimeReturnRateItem to $childArticle
            $childArticle->child_lifetime_return_rate = $childLifetimeReturnRateItem ? $childLifetimeReturnRateItem->child_lifetime_return_rate : 0;

            return $childArticle;
        });
        // Add child articles to the $parentArticles array
        foreach ($parentArticles as $parentArticle) {
            // Find child articles for the current parent article
            $matchingChildArticles = $childArticles->where('kVaterArtikel', $parentArticle->kVaterArtikel);

            // Add child data to the parent article
            $parentArticle->child_articles = $matchingChildArticles;
        }

        $articleFilterData = collect($parentArticles);
        // Return the processed article filter data
        return $articleFilterData;
    }
}