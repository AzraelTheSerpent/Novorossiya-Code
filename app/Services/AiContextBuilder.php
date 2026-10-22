<?php

namespace App\Services;

use App\Enums\ModerationStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Journey;
use App\Models\Place;
use App\Models\Vertical;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Собирает компактный контекст публичного каталога для ИИ-помощника.
 *
 * В контекст попадают только опубликованные данные. Владельцы, телефоны,
 * email, отзывы, черновики и места на модерации не попадают никогда.
 */
class AiContextBuilder
{
    public const CACHE_KEY = 'ai:catalog:v1';
    public const CACHE_TTL = 60;
    public const MAX_LENGTH = 6000;
    public const MAX_PLACES = 25;

    private const DAY_LABELS = [
        'mon' => 'пн', 'tue' => 'вт', 'wed' => 'ср', 'thu' => 'чт',
        'fri' => 'пт', 'sat' => 'сб', 'sun' => 'вс',
    ];

    private const PRICE_LABELS = [
        0 => 'бесплатно', 1 => 'низкая', 2 => 'средняя', 3 => 'высокая', 4 => 'премиум',
    ];

    private const FORMATS = [
        'individual' => 'без записи',
        'booking' => 'по записи',
    ];

    /** Текстовый блок с данными каталога для системного промпта. */
    public function build(): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->render());
    }

    private function render(): string
    {
        $budget = self::MAX_LENGTH - mb_strlen("<CATALOG>\n\n</CATALOG>");
        $lines = [];

        $add = function (string $line) use (&$lines, &$budget): bool {
            $length = mb_strlen($line) + 1;
            if ($length > $budget) {
                return false;
            }
            $lines[] = $line;
            $budget -= $length;

            return true;
        };

        $verticals = Vertical::orderBy('id')->get();
        $verticalNames = $verticals->pluck('name', 'id');

        $add('ВЕРТИКАЛИ: '.($verticals->map(function (Vertical $vertical) {
            $description = $vertical->description ? ' — '.$vertical->description : '';

            return $vertical->name.' ('.$vertical->slug.')'.$description;
        })->implode('; ') ?: 'нет'));

        $categories = Category::orderBy('sort')->get(['name']);
        $add('КАТЕГОРИИ: '.($categories->pluck('name')->implode(', ') ?: 'нет'));

        $this->addJourneys($add);
        $this->addEvents($add);
        $this->addPlaces($add, $verticalNames);

        return "<CATALOG>\n".implode("\n", $lines)."\n</CATALOG>";
    }

    private function addJourneys(callable $add): void
    {
        $add('МАРШРУТЫ:');

        $journeys = Journey::where('published', true)->with('places')->orderBy('id')->get();

        foreach ($journeys as $journey) {
            $stops = $journey->places
                ->filter(fn (Place $place) => $place->status === ModerationStatus::Approved)
                ->pluck('name')
                ->implode(' → ');

            $add('- '.$journey->name.' | '.$journey->duration.' мин | остановки: '.$stops);
        }
    }

    private function addEvents(callable $add): void
    {
        $add('СОБЫТИЯ:');

        $events = Event::where('status', ModerationStatus::Approved)
            ->where('starts_at', '>=', now()->startOfDay())
            ->with('place')
            ->orderBy('starts_at')
            ->take(10)
            ->get();

        foreach ($events as $event) {
            $add(sprintf(
                '- %s | %s | место: %s | цена: %s | тип: %s',
                $event->title,
                $event->starts_at?->format('d.m.Y H:i') ?? 'дата уточняется',
                $event->place?->name ?? 'не указано',
                $event->price ?: 'уточняется',
                $event->type?->value ?? 'не указан',
            ));
        }
    }

    private function addPlaces(callable $add, $verticalNames): void
    {
        $add('МЕСТА:');

        $places = Place::approved()
            ->with('category')
            ->orderByDesc('is_featured')
            ->orderByDesc('rating')
            ->orderBy('id')
            ->take(self::MAX_PLACES)
            ->get();

        foreach ($places as $place) {
            $add('- '.$this->placeLine($place, $verticalNames));
        }
    }

    private function placeLine(Place $place, $verticalNames): string
    {
        $parts = ['id='.$place->id, $place->name];

        if ($place->category?->name) {
            $parts[] = 'категория: '.$place->category->name;
        }

        if ($place->vertical_id && $verticalNames->has($place->vertical_id)) {
            $parts[] = 'вертикаль: '.$verticalNames->get($place->vertical_id);
        }

        if ($place->address) {
            $parts[] = 'адрес: '.$place->address;
        }

        $rating = 'рейтинг: '.(float) $place->rating;
        if ($place->reviews_count) {
            $rating .= ' ('.$place->reviews_count.' отзывов)';
        }
        $parts[] = $rating;

        $parts[] = 'цена: '.(self::PRICE_LABELS[$place->price_level] ?? 'средняя');

        if ($place->visit_format) {
            $parts[] = 'формат: '.(self::FORMATS[$place->visit_format] ?? $place->visit_format);
        }

        if ($place->short_description) {
            $parts[] = 'о: '.Str::limit($place->short_description, 100, '');
        }

        if (! empty($place->tags) && is_array($place->tags)) {
            $parts[] = 'теги: '.implode(', ', array_slice($place->tags, 0, 5));
        }

        $hours = $this->formatHours($place->working_hours);
        if ($hours !== '') {
            $parts[] = 'часы: '.$hours;
        }

        if ($place->verified_at) {
            $parts[] = 'проверено: '.$place->verified_at->format('d.m.Y');
        }

        return implode(' | ', $parts);
    }

    /**
     * Часы работы хранятся в двух форматах: карта «день => интервал»
     * и список объектов ['d' => ..., 'from' => ..., 'to' => ...].
     */
    private function formatHours($hours): string
    {
        if (! is_array($hours) || $hours === []) {
            return '';
        }

        $chunks = [];

        foreach ($hours as $key => $value) {
            if (is_array($value)) {
                $day = $value['d'] ?? '';
                $from = $value['from'] ?? '';
                $to = $value['to'] ?? '';
                $range = $from !== '' ? $from.($to !== '' ? '-'.$to : '') : '';
                $chunks[] = trim($day.' '.$range);

                continue;
            }

            $label = is_string($key) ? (self::DAY_LABELS[$key] ?? $key) : '';
            $chunks[] = trim($label.' '.(is_scalar($value) ? (string) $value : ''));
        }

        return implode(', ', array_filter($chunks));
    }
}
