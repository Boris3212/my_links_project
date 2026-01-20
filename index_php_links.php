<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

class LinkManager {
    private string $xlsxFile;
    private string $cacheDir = __DIR__ . '/cache';
    private string $cacheFile;
    private int $linksAmount;
    private array $links = [];

    public function __construct(string $xlsxFile, int $linksAmount = 10, string $cacheKey = 'default') {
        $this->xlsxFile = $xlsxFile;
        $this->linksAmount = $linksAmount;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        $this->cacheFile = $this->cacheDir . "/links_{$cacheKey}.json";

        $this->loadLinks();
    }

    // =================== Методы ===================
    private function safeString(mixed $value): string {
        if (is_array($value)) $value = reset($value);
        return trim((string)$value);
    }

    private function loadLinks(): void {
        // Если кэш существует — читаем из него
        if ($this->cacheExists()) {
            $this->links = $this->readCache();
            return;
        }

        // Иначе читаем Excel
        $allLinks = $this->readExcel();

        // Берём случайные ссылки
        $this->links = $this->pickRandomLinks($allLinks, $this->linksAmount);

        // Сохраняем в кэш
        $this->writeCache($this->links);
    }

    private function cacheExists(): bool {
        return file_exists($this->cacheFile);
    }

    private function readCache(): array {
        return json_decode(file_get_contents($this->cacheFile), true) ?: [];
    }

    private function writeCache(array $data): void {
        file_put_contents($this->cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function readExcel(): array {
        if (!file_exists($this->xlsxFile)) {
            die("Файл {$this->xlsxFile} не найден!");
        }

        $spreadsheet = IOFactory::load($this->xlsxFile);
        $sheet = $spreadsheet->getActiveSheet();

        $allLinks = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $this->safeString($cell->getCalculatedValue());
            }

            if (!empty($cells[0]) && !empty($cells[1])) {
                $allLinks[] = ['url' => $cells[0], 'name' => $cells[1]];
            }
        }

        return $allLinks;
    }

    private function pickRandomLinks(array $allLinks, int $count): array {
        $count = min($count, count($allLinks));
        $keys = array_rand($allLinks, $count);
        if (!is_array($keys)) $keys = [$keys];

        $random = [];
        foreach ($keys as $k) $random[] = $allLinks[$k];

        // Сортировка по названию
        usort($random, fn($a, $b) => mb_strtolower($a['name']) <=> mb_strtolower($b['name']));

        // Первая буква заглавная
        foreach ($random as &$link) {
            $link['name'] = mb_strtoupper(mb_substr($link['name'], 0, 1)) . mb_substr($link['name'], 1);
        }
        unset($link);

        return $random;
    }

    // =================== Вывод HTML ===================
    public function renderHtml(): void {
        $links = $this->links;
        ?>
<ul>
<?php foreach ($links as $link): ?>
    <li><a href="<?= htmlspecialchars($link['url'], ENT_QUOTES | ENT_HTML5) ?>" target="_blank"><?= htmlspecialchars($link['name'], ENT_QUOTES | ENT_HTML5) ?></a></li>
<?php endforeach; ?>
</ul>
<?php
    }
}

// =================== Использование ===================

// 10 случайных ссылок
$manager = new LinkManager('links.xlsx', 10, 'first_block');
$manager->renderHtml();
