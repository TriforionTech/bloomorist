<?php

namespace App\Services;

use App\Models\JournalItem;
use App\Models\ReportLineTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class ReportLineTemplateService
{
    public function generate(string $reportType, Carbon $start, Carbon $end): array
    {
        $templates = ReportLineTemplate::query()
            ->where('report_type', $reportType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $accountBalances = $this->accountBalances($start, $end);
        $results = [];

        foreach ($templates as $template) {
            $results[$template->line_key] = $template->is_subtotal
                ? $this->evaluateFormula($template->subtotal_formula, $results)
                : $this->calculateAccounts($template->account_codes ?? [], $accountBalances) * $template->sign;
        }

        return $results;
    }

    private function accountBalances(Carbon $start, Carbon $end): Collection
    {
        return JournalItem::query()
            ->join('bl_general_journals_t', 'bl_general_journals_t.id', '=', 'bl_journal_items_t.journal_id')
            ->join('bl_coa_t', 'bl_coa_t.id', '=', 'bl_journal_items_t.coa_id')
            ->whereDate('bl_general_journals_t.tanggal', '>=', $start->toDateString())
            ->whereDate('bl_general_journals_t.tanggal', '<=', $end->toDateString())
            ->groupBy('bl_coa_t.kode_akun', 'bl_coa_t.saldo_normal')
            ->selectRaw('bl_coa_t.kode_akun, bl_coa_t.saldo_normal, SUM(debit) AS total_debit, SUM(kredit) AS total_kredit')
            ->get()
            ->mapWithKeys(function ($row): array {
                $balance = $row->saldo_normal === 'Debit'
                    ? $row->total_debit - $row->total_kredit
                    : $row->total_kredit - $row->total_debit;

                return [$row->kode_akun => (float) $balance];
            });
    }

    private function calculateAccounts(array $patterns, Collection $balances): float
    {
        return $balances
            ->filter(fn ($balance, string $code) => collect($patterns)->contains(
                fn (string $pattern) => $this->matches($code, $pattern)
            ))
            ->sum();
    }

    private function evaluateFormula(?string $formula, array $results): float
    {
        if (!$formula) {
            throw new RuntimeException('Subtotal template harus memiliki formula.');
        }

        preg_match_all('/[A-Za-z][A-Za-z0-9_]*/', $formula, $keys);
        $expression = $formula;
        foreach (array_unique($keys[0]) as $key) {
            if (!array_key_exists($key, $results)) {
                throw new RuntimeException("Line key '{$key}' belum tersedia untuk formula subtotal.");
            }
            $expression = preg_replace('/\b' . preg_quote($key, '/') . '\b/', (string) $results[$key], $expression);
        }

        return $this->evaluateArithmetic($expression);
    }

    private function evaluateArithmetic(string $expression): float
    {
        preg_match_all('/\d+(?:\.\d+)?|[()+\-*\/]/', $expression, $matches);
        $tokens = $matches[0];
        if (implode('', $tokens) !== preg_replace('/\s+/', '', $expression)) {
            throw new RuntimeException('Formula subtotal mengandung karakter yang tidak diizinkan.');
        }

        $normalizedTokens = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (($token === '+' || $token === '-')
                && ($index === 0 || in_array($tokens[$index - 1], ['+', '-', '*', '/', '('], true))) {
                $next = $tokens[$index + 1] ?? null;
                if ($next === null || !is_numeric($next)) {
                    throw new RuntimeException('Formula subtotal tidak valid.');
                }
                $normalizedTokens[] = $token === '-' ? '-' . $next : $next;
                $index++;
                continue;
            }
            if ($index > 0 && is_numeric($tokens[$index - 1]) && is_numeric($token)) {
                throw new RuntimeException('Formula subtotal tidak valid.');
            }
            $normalizedTokens[] = $token;
        }
        $tokens = $normalizedTokens;

        $values = [];
        $operators = [];
        $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
        $apply = function () use (&$values, &$operators): void {
            if (count($values) < 2 || !$operators) {
                throw new RuntimeException('Formula subtotal tidak valid.');
            }
            $operator = array_pop($operators);
            $right = array_pop($values);
            $left = array_pop($values);
            if ($operator === '/' && $right == 0) {
                throw new RuntimeException('Formula subtotal tidak boleh membagi dengan nol.');
            }
            $values[] = match ($operator) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $left / $right,
            };
        };

        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $values[] = (float) $token;
            } elseif ($token === '(') {
                $operators[] = $token;
            } elseif ($token === ')') {
                while ($operators && end($operators) !== '(') {
                    $apply();
                }
                if (!$operators || array_pop($operators) !== '(') {
                    throw new RuntimeException('Kurung formula subtotal tidak seimbang.');
                }
            } else {
                while ($operators && end($operators) !== '(' && $precedence[end($operators)] >= $precedence[$token]) {
                    $apply();
                }
                $operators[] = $token;
            }
        }

        while ($operators) {
            if (end($operators) === '(') {
                throw new RuntimeException('Kurung formula subtotal tidak seimbang.');
            }
            $apply();
        }

        if (count($values) !== 1) {
            throw new RuntimeException('Formula subtotal tidak valid.');
        }

        return (float) $values[0];
    }

    private function matches(string $code, string $pattern): bool
    {
        $regex = preg_quote($pattern, '/');
        $regex = str_replace('\*', '.*', $regex);

        return preg_match('/^' . $regex . '$/', $code) === 1;
    }
}
