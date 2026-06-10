<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function month(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $base = Expense::whereBetween('spent_at', [$start, $end]);

        $total = (int) (clone $base)->sum('amount');
        $count = (clone $base)->count();

        $byCategory = (clone $base)
            ->select('category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'category_id' => $r->category_id,
                'total' => (int) $r->total,
                'count' => (int) $r->cnt,
                'pct' => $total > 0 ? round($r->total / $total * 100, 1) : 0,
            ]);

        $daily = (clone $base)
            ->select(DB::raw('DAY(spent_at) as d'), DB::raw('SUM(amount) as total'))
            ->groupBy('d')
            ->pluck('total', 'd')
            ->map(fn ($v) => (int) $v);

        $biggest = (clone $base)->with('category')->orderByDesc('amount')->first();

        $topItems = (clone $base)
            ->select(DB::raw('LOWER(item) as item_key'), DB::raw('MAX(item) as item'), DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('item_key')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['item' => $r->item, 'total' => (int) $r->total, 'count' => (int) $r->cnt]);

        $prevStart = $start->copy()->subMonth();
        $prevTotal = (int) Expense::whereBetween('spent_at', [$prevStart, $prevStart->copy()->endOfMonth()])->sum('amount');

        $daysInMonth = $start->daysInMonth;
        $daysElapsed = $start->isSameMonth(now()) ? max(1, now()->day) : $daysInMonth;
        $avgPerDay = (int) round($total / $daysElapsed);

        return response()->json([
            'month' => $month,
            'total' => $total,
            'count' => $count,
            'by_category' => $byCategory,
            'daily' => $daily,
            'days_in_month' => $daysInMonth,
            'days_elapsed' => $daysElapsed,
            'avg_per_day' => $avgPerDay,
            'projected' => $avgPerDay * $daysInMonth,
            'prev_total' => $prevTotal,
            'biggest' => $biggest ? [
                'item' => $biggest->item,
                'amount' => $biggest->amount,
                'date' => $biggest->spent_at->format('Y-m-d'),
                'category_id' => $biggest->category_id,
            ] : null,
            'top_items' => $topItems,
            'budget' => (int) Setting::get('budget', 0) ?: null,
        ]);
    }
}
