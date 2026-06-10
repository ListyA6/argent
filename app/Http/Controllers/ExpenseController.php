<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Expense;
use App\Models\KeywordRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with('category')->orderByDesc('spent_at')->orderByDesc('id');

        if ($date = $request->query('date')) {
            $query->whereDate('spent_at', $date);
        } elseif ($month = $request->query('month')) {
            [$y, $m] = explode('-', $month);
            $query->whereYear('spent_at', $y)->whereMonth('spent_at', $m);
        } else {
            $query->limit(100);
        }

        return $query->get()->map(fn ($e) => $this->shape($e));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item' => 'required|string|max:120',
            'amount' => 'required|integer|min:1|max:1000000000000',
            'category_id' => 'required|exists:categories,id',
            'note' => 'nullable|string|max:255',
            'spent_at' => 'nullable|date',
            'learn' => 'sometimes|boolean',
        ]);

        $expense = Expense::create([
            'item' => trim($data['item']),
            'amount' => $data['amount'],
            'category_id' => $data['category_id'],
            'note' => $data['note'] ?? null,
            'spent_at' => $data['spent_at'] ?? now(),
        ]);

        // User overrode the suggestion: learn this item's words for next time.
        if ($request->boolean('learn')) {
            $this->learn($expense->item, $expense->category_id);
        }

        return response()->json($this->shape($expense->load('category')), 201);
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return response()->json(['ok' => true]);
    }

    public function suggest(Request $request)
    {
        $q = mb_strtolower(trim((string) $request->query('q', '')));

        if (mb_strlen($q) < 2) {
            return response()->json(['category_id' => null]);
        }

        $match = KeywordRule::query()
            ->whereRaw('? LIKE CONCAT(\'%\', keyword, \'%\')', [$q])
            ->orderByRaw('CHAR_LENGTH(keyword) DESC')
            ->first();

        if ($match) {
            $match->increment('hits');
        }

        return response()->json(['category_id' => $match?->category_id]);
    }

    public function favorites()
    {
        $rows = Expense::query()
            ->select([
                DB::raw('LOWER(item) as item_key'),
                DB::raw('MAX(item) as item'),
                'category_id',
                DB::raw('COUNT(*) as times'),
                DB::raw('MAX(id) as last_id'),
            ])
            ->where('spent_at', '>=', now()->subDays(90))
            ->groupBy('item_key', 'category_id')
            ->having('times', '>=', 2)
            ->orderByDesc('times')
            ->limit(8)
            ->get();

        $lastAmounts = Expense::whereIn('id', $rows->pluck('last_id'))->pluck('amount', 'id');

        return $rows->map(fn ($r) => [
            'item' => $r->item,
            'category_id' => $r->category_id,
            'times' => (int) $r->times,
            'amount' => (int) ($lastAmounts[$r->last_id] ?? 0),
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'time', 'item', 'category', 'amount', 'note']);

            Expense::with('category')->orderBy('spent_at')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $e) {
                    fputcsv($out, [
                        $e->spent_at->format('Y-m-d'),
                        $e->spent_at->format('H:i'),
                        $e->item,
                        $e->category->name,
                        $e->amount,
                        $e->note,
                    ]);
                }
            });

            fclose($out);
        }, 'argent-expenses.csv', ['Content-Type' => 'text/csv']);
    }

    private function learn(string $item, int $categoryId): void
    {
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($item), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            if (mb_strlen($word) < 3) {
                continue;
            }

            KeywordRule::firstOrCreate(
                ['keyword' => $word],
                ['category_id' => $categoryId, 'is_seed' => false]
            );
        }
    }

    private function shape(Expense $e): array
    {
        return [
            'id' => $e->id,
            'item' => $e->item,
            'amount' => $e->amount,
            'note' => $e->note,
            'spent_at' => $e->spent_at->toIso8601String(),
            'date' => $e->spent_at->format('Y-m-d'),
            'time' => $e->spent_at->format('H:i'),
            'category_id' => $e->category_id,
        ];
    }
}
