<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Admin dashboard analytics
    |--------------------------------------------------------------------------
    | Dashboard chart ke liye grouped data. Saare queries grouped/aggregated
    | hain — koi row-by-row loop nahi, isliye data badhne pe bhi slow nahi hoga.
    */

    // Chart July se start hota hai aur current month tak apne aap badhta rehta hai
    private const START_MONTH = 7;

    public function index(Request $request)
    {
        return response()->json([
            'growth' => $this->growthSeries(
                (int) $request->query('from_month', self::START_MONTH),
                $request->query('from_year'),
            ),
        ]);
    }

    // July se current month tak ka trend — students, enrollments, submissions
    private function growthSeries(int $fromMonth, ?string $fromYear): array
    {
        [$start, $end] = $this->resolveRange($fromMonth, $fromYear);

        $periods = [];

        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addMonth()) {
            $periods[] = $cursor->format('Y-m');
        }

        $students = User::where('role', 'student')
            ->where('created_at', '>=', $start->copy()->startOfDay())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period')
            ->all();

        $enrollments = Enrollment::where('created_at', '>=', $start->copy()->startOfDay())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period')
            ->all();

        $submissions = Submission::where('created_at', '>=', $start->copy()->startOfDay())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period')
            ->all();

        $series = [];

        foreach ($periods as $period) {
            $date = Carbon::createFromFormat('Y-m-d', $period . '-01');

            $series[] = [
                'period' => $period,
                'label' => $date->format('M'),
                'label_full' => $date->format('M Y'),
                'students' => (int) ($students[$period] ?? 0),
                'enrollments' => (int) ($enrollments[$period] ?? 0),
                'submissions' => (int) ($submissions[$period] ?? 0),
            ];
        }

        return $series;
    }

    /*
    |--------------------------------------------------------------------------
    | Range resolution
    |--------------------------------------------------------------------------
    | Start = July, End = current month. Jaise-jaise time badhega, month apne
    | aap add hote rahenge. Agar current year ka July abhi aaya hi nahi hai,
    | to range ko aage nahi, current month pe clamp kar dete hain — taaki
    | future month ka khali data na dikhe.
    */

    private function resolveRange(int $fromMonth, ?string $fromYear): array
    {
        $now = now();

        $month = $fromMonth >= 1 && $fromMonth <= 12 ? $fromMonth : self::START_MONTH;
        $year = $fromYear ? (int) $fromYear : $now->year;

        $start = Carbon::create($year, $month, 1)->startOfMonth();

        $end = $now->copy()->startOfMonth();

        if ($end->lessThan($start)) {
            $end = $start->copy();
        }

        // Guard: koi future month kabhi render na ho
        if ($end->diffInMonths($start) > 36) {
            $end = $start->copy()->addMonths(36);
        }

        return [$start, $end];
    }
}
