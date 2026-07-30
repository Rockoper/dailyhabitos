<?php

namespace App\Livewire\History;

use App\Services\History\HistoryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class HistoryTimeline extends Component
{
    private const WINDOW_DAYS = 30;

    private const MAX_CUSTOM_RANGE_DAYS = 366;

    #[Url(as: 'periodo')]
    public string $period = 'last30';

    #[Url(as: 'desde')]
    public ?string $customFrom = null;

    #[Url(as: 'hasta')]
    public ?string $customTo = null;

    #[Url(as: 'tipo')]
    public string $typeFilter = 'all';

    public string $statusFilter = 'all';

    public ?int $habitId = null;

    public ?int $goalId = null;

    public ?int $categoryId = null;

    public bool $onlyPerfectDays = false;

    public bool $onlyWithReflection = false;

    public bool $onlyManual = false;

    public bool $onlyAutomatic = false;

    #[Url(as: 'buscar')]
    public string $search = '';

    public int $loadedWindows = 1;

    public string $monthAnchor = '';

    public function mount(): void
    {
        $today = $this->today();

        $fecha = request()->query('fecha');
        $desde = request()->query('desde');
        $hasta = request()->query('hasta');

        if (is_string($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $this->period = 'custom';
            $this->customFrom = $fecha;
            $this->customTo = $fecha;
        } elseif (is_string($desde) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $this->period = 'custom';
            $this->customFrom = $desde;
            $this->customTo = is_string($hasta) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) ? $hasta : $today->toDateString();
        }

        $this->monthAnchor = $today->format('Y-m');
    }

    private function timezone(): string
    {
        return Auth::user()->timezone ?: config('app.timezone');
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(): array
    {
        $today = $this->today();

        [$from, $to] = match ($this->period) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'last7' => [$today->subDays(6), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$today->startOfYear(), $today],
            'custom' => $this->resolveCustomRange($today),
            default => [$today->subDays(self::WINDOW_DAYS - 1), $today], // last30
        };

        if ($to->gt($today)) {
            $to = $today;
        }

        $floor = CarbonImmutable::parse(Auth::user()->created_at)->startOfDay();
        $extended = $from->subDays(($this->loadedWindows - 1) * self::WINDOW_DAYS);
        if ($extended->lt($floor)) {
            $extended = $floor;
        }
        if (in_array($this->period, ['last30', 'this_year', 'custom'], true)) {
            $from = $extended;
        }

        return [$from, $to];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveCustomRange(CarbonImmutable $today): array
    {
        $from = $this->customFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->customFrom)
            ? CarbonImmutable::parse($this->customFrom)->startOfDay()
            : $today->subDays(self::WINDOW_DAYS - 1);

        $to = $this->customTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->customTo)
            ? CarbonImmutable::parse($this->customTo)->startOfDay()
            : $today;

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > self::MAX_CUSTOM_RANGE_DAYS) {
            $from = $to->subDays(self::MAX_CUSTOM_RANGE_DAYS);
        }

        return [$from, $to];
    }

    public function loadMore(): void
    {
        $this->loadedWindows++;
    }

    public function updated(string $name): void
    {
        if (! in_array($name, ['loadedWindows', 'search'], true)) {
            $this->loadedWindows = 1;
        }
    }

    public function clearFilters(): void
    {
        $this->typeFilter = 'all';
        $this->statusFilter = 'all';
        $this->habitId = null;
        $this->goalId = null;
        $this->categoryId = null;
        $this->onlyPerfectDays = false;
        $this->onlyWithReflection = false;
        $this->onlyManual = false;
        $this->onlyAutomatic = false;
        $this->search = '';
        $this->loadedWindows = 1;
    }

    public function goToday(): void
    {
        $this->period = 'today';
        $this->customFrom = null;
        $this->customTo = null;
        $this->loadedWindows = 1;
    }

    public function goToPreviousMonth(): void
    {
        $this->shiftMonth(-1);
    }

    public function goToNextMonth(): void
    {
        $this->shiftMonth(1);
    }

    public function goToCurrentMonth(): void
    {
        $this->monthAnchor = $this->today()->format('Y-m');
        $this->applyMonthAnchor();
    }

    private function shiftMonth(int $delta): void
    {
        $anchor = CarbonImmutable::parse($this->monthAnchor.'-01');
        $next = $delta > 0 ? $anchor->addMonthNoOverflow() : $anchor->subMonthNoOverflow();

        if ($next->gt($this->today()->startOfMonth())) {
            return;
        }

        $this->monthAnchor = $next->format('Y-m');
        $this->applyMonthAnchor();
    }

    private function applyMonthAnchor(): void
    {
        $anchor = CarbonImmutable::parse($this->monthAnchor.'-01');
        $this->period = 'custom';
        $this->customFrom = $anchor->startOfMonth()->toDateString();
        $this->customTo = $anchor->endOfMonth()->toDateString();
        $this->loadedWindows = 1;
    }

    #[Computed]
    public function habits()
    {
        return Auth::user()->habits()->orderBy('name')->get(['id', 'name', 'is_private']);
    }

    #[Computed]
    public function goals()
    {
        return Auth::user()->goals()->orderBy('name')->get(['id', 'name', 'is_private']);
    }

    #[Computed]
    public function categories()
    {
        return Auth::user()->categories()->orderBy('position')->get(['id', 'name']);
    }

    public function render(HistoryService $service)
    {
        $user = Auth::user();
        $today = $this->today();
        [$from, $to] = $this->resolveRange();

        $result = $service->build($user, $from, $to, $today, [
            'type' => $this->typeFilter,
            'status' => $this->statusFilter,
            'habitId' => $this->habitId,
            'goalId' => $this->goalId,
            'categoryId' => $this->categoryId,
            'onlyPerfectDays' => $this->onlyPerfectDays,
            'onlyWithReflection' => $this->onlyWithReflection,
            'onlyManual' => $this->onlyManual,
            'onlyAutomatic' => $this->onlyAutomatic,
            'search' => $this->search,
        ]);

        $floor = CarbonImmutable::parse($user->created_at)->startOfDay();
        $canLoadMore = in_array($this->period, ['last30', 'this_year', 'custom'], true) && $from->gt($floor);

        $hasActiveFilters = $this->typeFilter !== 'all'
            || $this->statusFilter !== 'all'
            || $this->habitId || $this->goalId || $this->categoryId
            || $this->onlyPerfectDays || $this->onlyWithReflection || $this->onlyManual || $this->onlyAutomatic
            || trim($this->search) !== '';

        return view('livewire.history.history-timeline', [
            'groups' => $result['groups'],
            'kpis' => $result['kpis'],
            'insights' => $result['insights'],
            'hasAnyActivityEver' => $result['hasAnyActivityEver'],
            'from' => $from,
            'to' => $to,
            'today' => $today,
            'canLoadMore' => $canLoadMore,
            'hasActiveFilters' => $hasActiveFilters,
            'monthLabel' => Str::ucfirst(CarbonImmutable::parse($this->monthAnchor.'-01')->translatedFormat('F Y')),
            'canGoNextMonth' => CarbonImmutable::parse($this->monthAnchor.'-01')->lt($today->startOfMonth()),
        ]);
    }
}
