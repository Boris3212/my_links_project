<?php

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

class LinkManager
{
    private const CACHE_DIR = __DIR__ . '/cache';

    /* ================= INIT ================= */

    public static function init(): void
    {
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0777, true);
        }
    }

    /* ================= PUBLIC ================= */

    public static function getLinks(
        string $xlsxFile,
        int $limit = 10
    ): array {

        self::init();

        $cacheFile = self::getCacheFile();

        // 1. Если есть JSON → читаем
        if (self::cacheExists($cacheFile)) {
            return self::readCache($cacheFile);
        }

        // 2. Иначе читаем Excel
        $links = self::readExcel($xlsxFile);

        // 3. Берём случайные
        $links = self::pickRandom($links, $limit);

        // 4. Пишем JSON
        self::writeCache($cacheFile, $links);

        return $links;
    }

    /* ================= CACHE ================= */

    private static function getCacheFile(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? 'cli';

        $hash = md5($uri);

        return self::CACHE_DIR . "/{$hash}.json";
    }

    private static function cacheExists(string $file): bool
    {
        return file_exists($file);
    }

    private static function readCache(string $file): array
    {
        return json_decode(file_get_contents($file), true) ?? [];
    }

    private static function writeCache(string $file, array $data): void
    {
        file_put_contents(
            $file,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /* ================= EXCEL ================= */

    private static function readExcel(string $file): array
    {
        if (!file_exists($file)) {
            die("Excel не найден: {$file}");
        }

        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();

        $links = [];

        foreach ($sheet->getRowIterator() as $row) {

            $cells = [];

            foreach ($row->getCellIterator() as $cell) {
                $cells[] = self::safeString(
                    $cell->getCalculatedValue()
                );
            }

            if (!empty($cells[0]) && !empty($cells[1])) {

                $links[] = [
                    'url'  => $cells[0],
                    'name' => $cells[1]
                ];
            }
        }

        return $links;
    }

    /* ================= HELPERS ================= */

    private static function safeString(mixed $value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return trim((string)$value);
    }

    private static function pickRandom(array $data, int $count): array
    {
        $count = min($count, count($data));

        $keys = array_rand($data, $count);

        if (!is_array($keys)) {
            $keys = [$keys];
        }

        $result = [];

        foreach ($keys as $k) {
            $result[] = $data[$k];
        }

        // Сортировка
        usort(
            $result,
            fn($a, $b) =>
                mb_strtolower($a['name'])
                <=>
                mb_strtolower($b['name'])
        );

        // Первая буква большая
        foreach ($result as &$link) {

            $link['name'] =
                mb_strtoupper(mb_substr($link['name'], 0, 1)) .
                mb_substr($link['name'], 1);
        }

        return $result;
    }

    /* ================= VIEW ================= */

    public static function render(array $links): void
    {
        ?>
<ul>
<?php foreach ($links as $link): ?>
    <li>
        <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank">
            <?= htmlspecialchars($link['name']) ?>
        </a>
    </li>
<?php endforeach; ?>
</ul>
<?php
    }
    
}





$links = LinkManager::getLinks('links.xlsx', 10);

LinkManager::render($links);
