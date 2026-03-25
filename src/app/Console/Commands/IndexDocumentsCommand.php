<?php

namespace App\Console\Commands;

use App\Ai\Memory\VectorStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

class IndexDocumentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:index-documents {path : Путь к файлу или директории}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Индексация документов и сохранение в VectorStore';

    /**
     * Execute the console command.
     */
    public function handle(VectorStore $vectorStore): int
    {
        $path = $this->argument('path');

        if (!File::exists($path)) {
            $path = base_path($path);
        }

        if (!File::exists($path)) {
            $this->error("Путь не найден: {$path}");
            return Command::FAILURE;
        }

        $files = [];
        if (File::isDirectory($path)) {
            $files = File::allFiles($path);
        } else {
            $files = [new SplFileInfo($path, dirname($path), basename($path))];
        }

        $this->info("Найдено файлов для индексации: " . count($files));

        foreach ($files as $file) {
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, ['txt', 'md', 'pdf', 'docx'])) {
                $this->warn("Пропуск файла (неподдерживаемый формат): " . $file->getRelativePathname());
                continue;
            }

            $this->line("Индексируем: " . $file->getRelativePathname());

            try {
                $content = $this->extractContent($file);

                if (empty(trim($content))) {
                    $this->warn("Файл пуст: " . $file->getRelativePathname());
                    continue;
                }

                $vectorStore->add($content, [
                    'source' => $file->getRelativePathname(),
                    'filename' => $file->getFilename(),
                    'indexed_at' => now()->toIso8601String(),
                ]);

                $this->info("Успешно: " . $file->getRelativePathname());
            } catch (\Exception $e) {
                $this->error("Ошибка при обработке {$file->getRelativePathname()}: " . $e->getMessage());
            }
        }

        $this->info("Индексация завершена.");

        return Command::SUCCESS;
    }

    /**
     * Извлечение содержимого файла в зависимости от расширения.
     */
    protected function extractContent(SplFileInfo $file): string
    {
        $extension = strtolower($file->getExtension());

        if (in_array($extension, ['txt', 'md'])) {
            return File::get($file->getRealPath());
        }

        // Для PDF и DOCX можно было бы добавить библиотеки (smalot/pdfparser, phpoffice/phpword)
        // Но для базовой задачи ограничимся текстовыми файлами или выдадим ошибку, если нет библиотек.
        // Судя по vendor в проекте есть smalot и phpoffice.

        if ($extension === 'pdf') {
            $parser = new \Smalot\PdfParser\Parser();
            return $parser->parseFile($file->getRealPath())->getText();
        }

        if ($extension === 'docx') {
            // Используем HTML-ридер/райтер, чтобы корректно извлечь текст и избежать ошибок преобразования массива в строку
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($file->getRealPath());
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
            ob_start();
            $writer->save('php://output');
            $html = ob_get_clean();
            // Преобразуем HTML в обычный текст
            $text = html_entity_decode(strip_tags($html));
            // Нормализуем пробелы и переводы строк
            $text = preg_replace('/[\r\n\t]+/', "\n", $text);
            $text = preg_replace('/\n{2,}/', "\n\n", $text);
            return trim($text);
        }

        return '';
    }
}
