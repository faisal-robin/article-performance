<?php

namespace App\Traits;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

trait exportArticlesPerformanceDataQuery
{
    function getArticleFilterData($user, $hersteller, $verkaufsplattform, $country, $searchTerm, $startDate, $endDate)
    {
        set_time_limit(0);
        ini_set('memory_limit', '2048M');

        $articleFilterData = Transaction::query();
        $articleFilterData->select(
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
            ->whereBetween('datum', [$startDate, $endDate])
            ->withFilters2($hersteller, $verkaufsplattform, $country)
            ->when($searchTerm, function ($articleFilterData, $searchTerm) {
                return $articleFilterData->where(function ($articleFilterData) use ($searchTerm) {
                    $articleFilterData->where('articles.han', 'like', '%' . $searchTerm . '%')
                        ->orWhere('articles.vaterArtikelHan', 'like', '%' . $searchTerm . '%')
                        ->orWhere('articles.sku', 'like', '%' . $searchTerm . '%')
                        ->orWhere('articles.name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('articles.gtin', 'like', '%' . $searchTerm . '%');
                });
            })
            ->groupBy('articles.han');

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
            ->when($searchTerm, function ($query, $searchTerm) {
                $query->whereIn('articles.kArtikel', function ($query) use ($searchTerm) {
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
                });
            })
            ->whereBetween('datum', [$startDate, $endDate])
            ->withFilters2($hersteller, $verkaufsplattform, $country)
            ->groupBy('articles.kVaterArtikel');


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
            ->when($searchTerm, function ($query) use ($searchTerm) {
                $query->whereIn('articles.kVaterArtikel', function ($subquery) use ($searchTerm) {
                    $subquery->select('kVaterArtikel')
                    ->from('articles')
                    ->where(function ($subquery) use ($searchTerm) {
                        $subquery->where('han', 'like', '%' . $searchTerm . '%')
                            ->orWhere('sku', 'like', '%' . $searchTerm . '%')
                            ->orWhere('name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('gtin', 'like', '%' . $searchTerm . '%');
                    });
                });
            })
            ->whereBetween('datum', [$startDate, $endDate])
            ->withFilters2($hersteller, $verkaufsplattform, $country)
            ->groupBy('articles.kVaterArtikel');

        $parentArticles = $articleFilterData->union($vaterartikel)->union($kinderartikel);

        $user->isClient() ?
            $parentArticles = $parentArticles->whereIn('kLieferant',  explode(",", $user->LieferantID))
            ->get() :
            $parentArticles = $parentArticles->get();

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

        // Merge lifetimeArticleData into $parentArticles based on kVaterArtikel
            $parentArticles = $parentArticles->map(function ($parentArticle) use ($lifetimeArticleData) {
                $matchingLifetimeData = $lifetimeArticleData->where('kVaterArtikel', $parentArticle->kVaterArtikel)->first();

                $parentArticle->lifetime_sales = $matchingLifetimeData->lifetime_sales ?? 0;
                    $parentArticle->lifetime_returns = $matchingLifetimeData->lifetime_returns ?? 0;
                    $parentArticle->lifetime_return_rate = $matchingLifetimeData->lifetime_return_rate ?? 0;

                return $parentArticle;
            });

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
            ->whereIn('articles.kVaterArtikel', $uniqueKVaterArtikel)
            ->orderBy('articles.han')
            ->groupBy('articles.han');

        $childArticles = $childArticles
            ->withFilters2($hersteller, $verkaufsplattform, $country)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                return $query->whereBetween('datum', [$startDate, $endDate]);
            });

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

        //Group child articles by their parent kVaterArtikel
        $childArticlesGrouped = $childArticles->groupBy('kVaterArtikel');

        // Add child articles to the $parentArticles array
        $parentArticles->each(function ($parentArticle) use ($childArticlesGrouped) {
            $parentArticle->child_articles = $childArticlesGrouped->get($parentArticle->kVaterArtikel, collect());
        });

        $articleFilterData = $parentArticles->toArray();
        // Return the processed article filter data
        return $articleFilterData;
    }
}
