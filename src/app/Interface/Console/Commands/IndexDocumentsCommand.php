<?php

declare(strict_types=1);

namespace App\Interface\Console\Commands;

use App\Application\Ai\Knowledge\IndexDocument\IndexDocumentAction;
use App\Application\Ai\Knowledge\IndexDocument\IndexDocumentData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

final class IndexDocumentsCommand extends Command
{
    protected $signature = 'app:index-documents {path : Путь к файлу или директории}';

    protected $description = 'Индексация документов и сохранение в VectorStore';

    public function handle(IndexDocumentAction $action): int
    {
        $path = (string) $this->argument('path');

        if (!File::exists($path)) {
            $path = base_path($path);
        }

        if (!File::exists($path)) {
            $this->error("Путь не найден: {$path}");
            return Command::FAILURE;
        }

        $files = File::isDirectory($path)
            ? File::allFiles($path)
            : [new SplFileInfo($path, dirname($path), basename($path))];

        $this->info('Найдено файлов для индексации: ' . count($files));

        foreach ($files as $file) {
            $extension = strtolower($file->getExtension());

            if (!in_array($extension, ['txt', 'md', 'pdf', 'docx'], true)) {
                $this->warn('Пропуск файла (неподдерживаемый формат): ' . $file->getRelativePathname());
                continue;
            }

            $this->line('Индексируем: ' . $file->getRelativePathname());

            try {
                $content = $this->extractContent($file);

                if (trim($content) === '') {
                    $this->warn('Файл пуст: ' . $file->getRelativePathname());
                    continue;
                }

                $action->handle(new IndexDocumentData(
                    content: $content,
                    metadata: [
                        'source' => $file->getRelativePathname(),
                        'filename' => $file->getFilename(),
                        'indexed_at' => now()->toIso8601String(),
                    ],
                ));

                $this->info('Успешно: ' . $file->getRelativePathname());
            } catch (\Throwable $e) {
                $this->error("Ошибка при обработке {$file->getRelativePathname()}: " . $e->getMessage());
            }
        }

        $this->info('Индексация завершена.');

        return Command::SUCCESS;
    }

    private function extractContent(SplFileInfo $file): string
    {
        $extension = strtolower($file->getExtension());

        if (in_array($extension, ['txt', 'md'], true)) {
            return File::get($file->getRealPath());
        }

        if ($extension === 'pdf') {
            $parser = new \Smalot\PdfParser\Parser();
            return $parser->parseFile($file->getRealPath())->getText();
        }

        if ($extension === 'docx') {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($file->getRealPath());
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
            ob_start();
            $writer->save('php://output');
            $html = ob_get_clean() ?: '';
            $text = html_entity_decode(strip_tags($html));
            $text = preg_replace('/[\r\n\t]+/', "\n", $text) ?? $text;
            $text = preg_replace('/\n{2,}/', "\n\n", $text) ?? $text;

            return trim($text);
        }

        return '';
    }
}
