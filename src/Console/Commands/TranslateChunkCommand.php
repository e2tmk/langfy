<?php

declare(strict_types = 1);

namespace Langfy\Console\Commands;

use Illuminate\Console\Command;
use Langfy\Services\AITranslator;

class TranslateChunkCommand extends Command
{
    protected $signature = 'langfy:translate-chunk {input : Input file path with chunk data} {output : Output file path for results} {--from= : Source language} {--to= : Target language} {--model= : AI model} {--temperature= : Temperature} {--provider= : AI provider}';

    protected $description = 'Translate a chunk of strings using AI';

    public function handle(): int
    {
        $inputFile  = $this->argument('input');
        $outputFile = $this->argument('output');

        if (! file_exists($inputFile)) {
            $this->error("Input file not found: {$inputFile}");

            return 1;
        }

        try {
            $chunkData = json_decode(file_get_contents($inputFile), true);

            if (! is_array($chunkData)) {
                $this->error("Invalid chunk data format");

                return 1;
            }

            $translator = AITranslator::configure()
                ->from($this->option('from'))
                ->to($this->option('to'))
                ->model($this->option('model'))
                ->temperature((float)$this->option('temperature'))
                ->provider($this->option('provider'));

            $translations = $translator->translateChunk(collect($chunkData));

            file_put_contents($outputFile, json_encode($translations, JSON_PRETTY_PRINT));

            return 0;
        } catch (\Exception $e) {
            $this->error("Translation failed: " . $e->getMessage());

            return 1;
        }
    }
}
