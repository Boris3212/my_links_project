<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$jsonFile = 'links.json';
$xlsxFile = 'links.xlsx';

function safeString($value) {
    if (is_array($value)) {
        $value = reset($value);
    }
    return trim((string)$value);
}

$links = [];

// Чтение Excel
if (file_exists($xlsxFile)) {
    $spreadsheet = IOFactory::load($xlsxFile);
    $sheet = $spreadsheet->getActiveSheet();

    foreach ($sheet->getRowIterator() as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        $cells = [];
        foreach ($cellIterator as $cell) {
            $cells[] = safeString($cell->getCalculatedValue());
        }

        if (!empty($cells[0]) && !empty($cells[1])) {
            $links[] = [
                'url' => $cells[0],
                'name' => $cells[1]
            ];
        }
    }

    file_put_contents($jsonFile, json_encode($links, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
} elseif (file_exists($jsonFile)) {
    $links = json_decode(file_get_contents($jsonFile), true);
}

// Проверка, есть ли данные
if (empty($links)) {
    die("Нет данных для отображения!");
}

// Берем 10 случайных ссылок
$randomLinks = [];
$count = min(10, count($links));
$keys = array_rand($links, $count);
if (!is_array($keys)) $keys = [$keys];
foreach ($keys as $k) {
    $randomLinks[] = $links[$k];
}

// Сортировка по названию
usort($randomLinks, function($a, $b){
    return mb_strtolower($a['name']) <=> mb_strtolower($b['name']);
});

// Первая буква заглавная
foreach ($randomLinks as &$link) {
    $link['name'] = mb_strtoupper(mb_substr($link['name'],0,1)) . mb_substr($link['name'],1);
}
unset($link);

// Формируем HTML
$html = "<ul>\n";
foreach ($randomLinks as $link) {
    $url = htmlspecialchars($link['url'], ENT_QUOTES | ENT_HTML5);
    $name = htmlspecialchars($link['name'], ENT_QUOTES | ENT_HTML5);
    $html .= "    <li><a href=\"$url\" target=\"_blank\">$name</a></li>\n";
}
$html .= "</ul>";

echo $html;
	