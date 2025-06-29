<?php

declare(strict_types = 1);

namespace Langfy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Langfy\Langfy;
use Langfy\Services\AITranslator;

class TranslateStringsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        protected array $strings,
        protected string $fromLanguage,
        protected string $toLanguage,
        protected string $filePath,
        protected ?string $context = null,
        protected ?string $moduleName = null
    ) {
    }

    public function handle(): void
    {
        try {
            if (blank($this->strings)) {
                return;
            }

            $translations = AITranslator::configure()
                ->from($this->fromLanguage)
                ->to($this->toLanguage)
                ->run($this->strings);

            Langfy::utils()->saveStringsToFile($translations, $this->filePath);
        } catch (\Throwable $e) {
            Log::error('Async translation failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'from'  => $this->fromLanguage,
                'to'    => $this->toLanguage,
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Translation job failed permanently', [
            'error'         => $exception->getMessage(),
            'from'          => $this->fromLanguage,
            'to'            => $this->toLanguage,
            'strings_count' => count($this->strings),
        ]);
    }

    public function getQueue(): ?string
    {
        return config('langfy.queue.name', 'default');
    }

    public function getConnection(): ?string
    {
        return config('langfy.queue.connection', 'default');
    }
}
