<?php

declare(strict_types = 1);

namespace Langfy\Concerns;

trait HasProgressCallbacks
{
    use EvaluatesClosures;

    protected ?\Closure $progressCallback = null;

    public function onProgress(\Closure $callback): static
    {
        $this->progressCallback = $callback;

        return $this;
    }

    public function hasProgressCallback(): bool
    {
        return filled($this->progressCallback);
    }

    public function callProgressCallback(int $current, int $total, ?array $extraData = null): void
    {
        if (! $this->hasProgressCallback()) {
            return;
        }

        $this->evaluate($this->progressCallback, [
            'current'             => $current,
            'total'               => $total,
            'percentage'          => $total > 0 ? round(($current / $total) * 100, 2) : 0,
            'progress'            => $total > 0 ? ($current / $total) : 0,
            'completed'           => $current >= $total,
            'remaining'           => $total - $current,
            'remainingPercentage' => $total > 0 ? round(((($total - $current) / $total) * 100), 2) : 0,
            'extraData'           => $extraData,
            ...$extraData,
        ]);
    }
}
